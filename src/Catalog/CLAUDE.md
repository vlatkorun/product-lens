# CatalogSync — Context

## Purpose

Fetches Shopify products into the platform so ImageAudit can act on them. Tenants configure which collections to monitor and which audit features to run. A scheduler reconciles enabled collections across all tenants and drives paginated product fetching via the Shopify GraphQL Admin API.

---

## Bounded context rules

- Imports from `Shared\Domain\` are allowed.
- Imports from `Tenancy\Domain\Repository\TenantRepositoryInterface` are allowed (credential lookup only).
- Never import from `Identity`, `ImageAudit`, or any other bounded context's domain classes.
- Cross-context communication happens only via domain events (`MonitoredCollectionSyncCompleted` → ImageAudit).

---

## Domain model

### Value objects (`Domain/ValueObject/`)

| Class | Purpose |
|---|---|
| `ShopifyGid` | Wraps `gid://shopify/{Type}/{Id}`; factory methods `::product()`, `::collection()`, `::fromString()` |
| `SyncCursor` | `endCursor: ?string` + `hasNextPage: bool`; stored as JSONB on `tenant_monitored_collections_sync` |
| `SyncStatus` | Enum: `Pending`, `Running`, `Completed`, `Failed` |
| `ProductStatus` | Enum: `Active`, `Archived`, `Draft` |
| `ProductFilter` | `collectionGid: ShopifyGid`, `status: ProductStatus = Active` |
| `ProductPage` | `products: Product[]`, `cursor: SyncCursor` — return type from `ProductFetcherInterface::fetchPage()` |

### Aggregates (`Domain/Model/`)

**`MonitoredCollection`** — table `tenant_monitored_collections`

Configuration aggregate. Owns what to sync and which audit features to run on a collection's products. Unique per `(tenantId, collectionGid)`.

```
create(tenantId, collectionGid, name, FeatureFlag[], enabled, now) → raises CollectionMonitoringEnabled if enabled
enable()       → raises CollectionMonitoringEnabled (idempotent)
disable()      → raises CollectionMonitoringDisabled (idempotent)
updateFeatureFlags(FeatureFlag[])
rename(string)
```

**`MonitoredCollectionSync`** — table `tenant_monitored_collections_sync`

One full sync execution for a monitored collection. Created in `Pending` state by the scheduler (not the handler) to close the race window. Carries `createdAt` (set at scheduling time) and `updatedAt` (refreshed on every save).

```
schedule(tenantId, monitoredCollectionId, collectionGid, now) → status=Pending, no events
start()                                                       → Pending→Running, raises MonitoredCollectionSyncStarted
recordPage(endCursor, hasNextPage, count, featureFlags[])     → raises MonitoredCollectionSyncProcessed; if !hasNextPage: Completed + MonitoredCollectionSyncCompleted
fail(reason, at)                                              → raises MonitoredCollectionSyncFailed
```

**`Product`** — table `products`

Read model snapshot of a Shopify product. Not an aggregate — no domain events. Upserted on every sync, keyed on `(tenantId, shopifyGid)`. Latest sync always wins.

### Domain events

| Event | Type | Raised by |
|---|---|---|
| `CollectionMonitoringEnabled` | sync | `MonitoredCollection::enable()` / `create()` |
| `CollectionMonitoringDisabled` | sync | `MonitoredCollection::disable()` |
| `MonitoredCollectionSyncStarted` | async | `MonitoredCollectionSync::start()` |
| `MonitoredCollectionSyncProcessed` | async | `MonitoredCollectionSync::recordPage()` on each page |
| `MonitoredCollectionSyncCompleted` | async | `MonitoredCollectionSync::recordPage()` on last page — carries `tenantId`, `collectionGid`, `featureFlags[]` |
| `MonitoredCollectionSyncFailed` | async | `MonitoredCollectionSync::fail()` |

`MonitoredCollectionSyncCompleted` gives ImageAudit everything it needs without querying CatalogSync tables.

---

## Application layer

### Commands

| Command | Handler responsibility |
|---|---|
| `ConfigureMonitoredCollectionCommand` | Upsert `MonitoredCollection` by `(tenantId, collectionGid)`; idempotent |
| `DispatchTenantsCollectionsSyncCommand(criteria)` | **Targeted path** (`criteria.tenantIds` non-empty): dispatch `AcquireTenantsCollectionsForSyncCommand` immediately. **Paginated path** (`criteria.tenantIds` empty): call `ActiveTenantBatchClaimer` to claim up to 100 active tenants via keyset pagination, dispatch `AcquireTenantsCollectionsForSyncCommand` per batch, self-dispatch with advanced `lastTenantId` cursor until batch < 100 |
| `AcquireTenantsCollectionsForSyncCommand(tenantIds, lastCollectionId)` | Call `TenantScopedMonitoredCollectionSyncClaimer` to claim up to 100 eligible collections for the given tenants, dispatch `ProcessTenantCollectionSyncCommand` + `TenantStamp` per claimed job, self-dispatch with advanced `lastCollectionId` cursor if batch was full |
| `ProcessTenantCollectionSyncCommand(syncJobId)` | If job is `Pending`: call `start()` + save. Fetch one product page from Shopify (configurable `$productPageSize`, default 250); upsert products; call `recordPage()`; re-dispatch if `hasNextPage` |
| `HandleWebhookCommand` | Single-product create/update: fetch + upsert; delete: remove from table |
| `RescheduleStuckTenantsCollectionsSyncCommand` | Find Pending jobs older than injected `$stuckThresholdMinutes` (default 5); re-dispatch `ProcessTenantCollectionSyncCommand` + `TenantStamp` for each |

### Transaction Scripts

Two Transaction Scripts own all `FOR UPDATE SKIP LOCKED` DB work — neither is a Messenger handler.

`ActiveTenantBatchClaimer` lives in `Application/Claim/`. Accepts `ActiveTenantBatchCriteriaDto` (`lastTenantId`, `batchSize`). Runs a keyset-paginated select on `tenants` and returns `list<string>` tenant IDs.

`TenantScopedMonitoredCollectionSyncClaimer` lives in `Application/Claim/`. Accepts `TenantCollectionSyncClaimCriteriaDto` (`tenantIds`, `lastCollectionId`, `batchSize`, `now`). Runs a keyset-paginated select on `tenant_monitored_collections` filtered to the given tenant IDs, inserts a `pending` sync row for each claimed collection in the same transaction, and returns `list<ClaimedTenantCollectionSyncDto>`.

Do not add further DB logic to handlers directly — create a new Transaction Script in the relevant command namespace.

### Query

`GetSyncStatusQuery(syncJobId)` → returns `?MonitoredCollectionSync`

---

## Full sync flow

```
Symfony Scheduler (every 5 min)
    → DispatchTenantsCollectionsSyncCommand(criteria=empty)   ← routed to async transport

DispatchTenantsCollectionsSyncHandler  [paginated path — criteria.tenantIds is empty]
    → ActiveTenantBatchClaimer::claim(ActiveTenantBatchCriteriaDto(lastTenantId, batchSize=100))
        BEGIN TRANSACTION
        SELECT resource_id FROM tenants
        WHERE status = 'active'
          [AND resource_id > :lastTenantId]   ← keyset cursor when set
        ORDER BY resource_id ASC LIMIT 100
        FOR UPDATE SKIP LOCKED
        COMMIT
        → return list<string> tenantIds
    → dispatch AcquireTenantsCollectionsForSyncCommand(tenantIds)
    → if count = 100: self-dispatch DispatchTenantsCollectionsSyncCommand(lastTenantId=last)
      ← repeats until batch < 100

DispatchTenantsCollectionsSyncHandler  [targeted path — criteria.tenantIds is non-empty]
    → dispatch AcquireTenantsCollectionsForSyncCommand(criteria.tenantIds) immediately, no DB query

AcquireTenantsCollectionsForSyncHandler  [repeats until batch < batchSize]
    → TenantScopedMonitoredCollectionSyncClaimer::claim(TenantCollectionSyncClaimCriteriaDto(
          tenantIds, lastCollectionId, batchSize=100, now))
        BEGIN TRANSACTION
        SELECT mc.resource_id, mc.tenant_id, mc.collection_gid
        FROM tenant_monitored_collections mc
        WHERE mc.enabled = true
          AND mc.tenant_id IN (:tenantIds)
          [AND mc.resource_id > :lastCollectionId]  ← keyset cursor when set
          AND NOT EXISTS (active sync for this collection)
        ORDER BY mc.resource_id ASC LIMIT 100
        FOR UPDATE SKIP LOCKED
        → INSERT tenant_monitored_collections_sync (status='pending') for each row
        COMMIT
        → return list<ClaimedTenantCollectionSyncDto(syncJobId, tenantId, monitoredCollectionId)>
    → dispatch ProcessTenantCollectionSyncCommand(syncJobId) + TenantStamp per job
    → if count = 100: self-dispatch AcquireTenantsCollectionsForSyncCommand(
          tenantIds=same, lastCollectionId=last monitoredCollectionId)

ProcessTenantCollectionSyncHandler  [repeated until hasNextPage = false]
    → findById(syncJobId)
    → if Pending: sync->start() + save   ← Pending → Running, raises MonitoredCollectionSyncStarted
    → wrapInTransaction:
        → findByIdForProcessing(syncJobId)  ← pessimistic write lock
        → MonitoredCollectionRepository::findById(monitoredCollectionId)
        → ProductCatalogInterface::getPage(ProductFilter, tenantId, cursor, productPageSize=250)
        → ProductRepository::upsertAll(products)
        → sync->recordPage(endCursor, hasNextPage, count, featureFlags[])
        → save(sync)
        → if hasNextPage: dispatch ProcessTenantCollectionSyncCommand again + TenantStamp
        → if !hasNextPage: MonitoredCollectionSyncCompleted raised → Audit context listens
```

### Webhook path

```
POST /webhooks/shopify/{tenantId}/products
    → ShopifyWebhookValidator::validate() (HMAC-SHA256)
    → HandleWebhookCommand(tenantId, gid, topic)
        products/create | products/update → fetchByGid → upsert
        products/delete                  → remove(gid, tenantId)
```

### Reaper (every 2 min)

```
RescheduleStuckTenantsCollectionsSyncCommand
    → StuckCollectionSyncThresholdDto($stuckThresholdMinutes=5 injected)
    → findStuckPending(now - thresholdMinutes)
    → dispatch ProcessTenantCollectionSyncCommand(syncJobId) + TenantStamp per stuck job
```

Protects against the failure window between the collection claimer COMMIT and RabbitMQ delivery.

---

## Tenant isolation

Two enforcement layers:

| Layer | Mechanism |
|---|---|
| Application | `TenantContextMiddleware` activates `TenantContext` (Doctrine SQL filter + `SET LOCAL app.current_tenant_id`) for every tenant-stamped Messenger message |
| Database | PostgreSQL RLS `tenant_isolation` policy on all three tables |

`DispatchTenantsCollectionsSyncHandler` and `RescheduleStuckTenantsCollectionsSyncHandler` carry no `TenantStamp` — they run cross-tenant as privileged processes. In production these workers connect as the `app_scheduler` PostgreSQL role (`BYPASSRLS`).

---

## Infrastructure

### `ShopifyClient`

Low-level GraphQL transport. Holds `HttpClientInterface` and the `SHOPIFY_API_VERSION` env var. Exposes a single `query(shopDomain, accessToken, query, variables): array` method. The API version is injected via `#[Autowire('%env(SHOPIFY_API_VERSION)%')]` — do not hard-code the version inside the fetcher.

### `ShopifyProductFetcher`

Implements `ProductFetcherInterface`. Resolves Shopify credentials from `TenantRepositoryInterface` internally — credentials never appear in domain contracts. Delegates HTTP to `ShopifyClient`; parses responses through typed DTOs in `GraphQL/Dto/` before mapping to domain objects. No raw `array` shapes leak past this class.

### GraphQL DTOs (`Infrastructure/Shopify/GraphQL/Dto/`)

Typed value objects for Shopify GraphQL responses — not domain objects. Used only inside `ShopifyProductFetcher` to parse raw API arrays before mapping.

| Class | Purpose |
|---|---|
| `ImageDto` | Single image node: `url`, `altText`, `width`, `height`. `toArray()` returns the `list<array{...}>` shape that `Product::create()` accepts. |
| `ProductNodeDto` | Full product node: id, title, handle, vendor, productType, status, featuredImageUrl, `list<ImageDto>`. |
| `PageInfoDto` | `hasNextPage: bool`, `endCursor: ?string`. |
| `ProductsByCollectionResponseDto` | Parses `data.collection.products` — `list<ProductNodeDto>` + `PageInfoDto`. |
| `GetProductResponseDto` | Parses `data.product` — single `ProductNodeDto`. |

Each DTO has a `static fromResponse(array $data)` or `fromNode(array $node)` factory. Never pass raw Shopify response arrays to `mapProduct()`.

### `ShopifyWebhookValidator`

HMAC-SHA256 validation against `SHOPIFY_WEBHOOK_SECRET` env var (`#[Autowire(env: 'SHOPIFY_WEBHOOK_SECRET')]`).

### `CatalogImportSchedule`

```php
#[AsSchedule('catalog_import')]
// worker: messenger:consume scheduler_catalog_import
RecurringMessage::every('5 minutes', new DispatchTenantsCollectionsSyncCommand(new DispatchTenantsCollectionsSyncCriteriaDto()))
RecurringMessage::every('2 minutes', new RescheduleStuckTenantsCollectionsSyncCommand())
```

### Persistence

- `DoctrineMonitoredCollectionRepository` — standard ORM save + event publish
- `DoctrineMonitoredCollectionSyncRepository` — standard ORM save + event publish; `findStuckPending()` via DQL
- `DoctrineProductRepository` — raw DBAL upsert (`INSERT ... ON CONFLICT DO UPDATE`)

---

## Database

Tables: `tenant_monitored_collections`, `tenant_monitored_collections_sync`, `products`

Migration 003 — tables, enums (`sync_status`, `product_status`), indexes including partial indexes:
- `tenant_monitored_collections_enabled_idx` — `(tenant_id) WHERE enabled = true`
- `tenant_monitored_collections_sync_running_idx` — `(monitored_collection_id) WHERE status IN ('pending', 'running')`

Migration 004 — RLS policies, `app_scheduler` role with `BYPASSRLS`

---

## Key invariants

- `(tenantId, collectionGid)` is unique in `tenant_monitored_collections`.
- No two schedulers can claim the same collection concurrently: `FOR UPDATE SKIP LOCKED` + in-transaction insert.
- A `MonitoredCollectionSync` is created in `Pending` before `ProcessTenantCollectionSyncCommand` is dispatched — the DB row is the source of truth, not the message queue.
- `Product` carries no domain events and does not extend `AggregateRoot`.
- `FeatureFlag` lives in `Shared\Domain\ValueObject` — imported by both Tenancy and CatalogSync.
