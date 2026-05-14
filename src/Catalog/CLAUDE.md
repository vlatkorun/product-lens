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
| `ProcessSyncScheduleCommand` | Delegates the transactional DB work to `MonitoredCollectionSyncClaimer::claim()` (Transaction Script); receives `ClaimedMonitoredCollectionSyncsDto` back; dispatches `StartSyncCommand` + `TenantStamp` per `ClaimedMonitoredCollectionSyncDto` |
| `StartSyncCommand(syncJobId)` | Load existing Pending job; call `start()`; dispatch `FetchNextPageCommand` |
| `FetchNextPageCommand(syncJobId)` | Fetch page from Shopify; upsert products; call `recordPage()`; re-dispatch if `hasNextPage` |
| `HandleWebhookCommand` | Single-product create/update: fetch + upsert; delete: remove from table |
| `RescheduleStuckJobsCommand` | Find Pending jobs older than 5 min; re-dispatch `StartSyncCommand` for each |

### Transaction Scripts

`MonitoredCollectionSyncClaimer` lives in the `ProcessSyncSchedule` command namespace. It is not a Messenger handler — it is a focused service injected into `ProcessSyncScheduleHandler`. It owns the raw `Doctrine\DBAL\Connection` and is the only place allowed to run the `FOR UPDATE SKIP LOCKED` SELECT + INSERT transaction. Returns `ClaimedMonitoredCollectionSyncsDto` containing `list<ClaimedMonitoredCollectionSyncDto>`.

Do not add further DB logic to `ProcessSyncScheduleHandler` directly — extend `MonitoredCollectionSyncClaimer` or create a new Transaction Script alongside it.

### Query

`GetSyncStatusQuery(syncJobId)` → returns `?MonitoredCollectionSync`

---

## Full sync flow

```
Symfony Scheduler (every 5 min)
    → ProcessSyncScheduleCommand

ProcessSyncScheduleHandler
    → MonitoredCollectionSyncClaimer::claim(now)  ← Transaction Script; owns DBAL directly
        BEGIN TRANSACTION
        SELECT mc.resource_id, mc.tenant_id, mc.collection_gid
        FROM   tenant_monitored_collections mc
        WHERE  mc.enabled = true
        AND    NOT EXISTS (active tenant_monitored_collections_sync for this collection)
        FOR UPDATE SKIP LOCKED
        → INSERT tenant_monitored_collections_sync (status='pending') for each row
        COMMIT
        → return ClaimedMonitoredCollectionSyncsDto(list<ClaimedMonitoredCollectionSyncDto(syncJobId, tenantId)>)
    → dispatch StartSyncCommand(syncJobId) + TenantStamp per ClaimedMonitoredCollectionSyncDto

StartSyncHandler
    → findById(syncJobId)             ← job already in Pending state
    → sync->start()                   ← Pending → Running, raises MonitoredCollectionSyncStarted
    → save(sync)
    → dispatch FetchNextPageCommand(syncJobId) + TenantStamp

FetchNextPageHandler  [repeated until hasNextPage = false]
    → ProductFetcherInterface::fetchPage(ProductFilter, tenantId, cursor)
    → ProductRepository::upsertAll(products)
    → MonitoredCollectionRepository::findById(monitoredCollectionId)
    → sync->recordPage(endCursor, hasNextPage, count, featureFlags[])
    → save(sync)
    → if hasNextPage: dispatch FetchNextPageCommand again
    → if !hasNextPage: MonitoredCollectionSyncCompleted dispatched → ImageAudit listens
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
RescheduleStuckJobsCommand
    → findStuckPending(now - 5 min)
    → dispatch StartSyncCommand(syncJobId) + TenantStamp per stuck job
```

Protects against the failure window between scheduler COMMIT and RabbitMQ delivery.

---

## Tenant isolation

Two enforcement layers:

| Layer | Mechanism |
|---|---|
| Application | `TenantContextMiddleware` activates `TenantContext` (Doctrine SQL filter + `SET LOCAL app.current_tenant_id`) for every tenant-stamped Messenger message |
| Database | PostgreSQL RLS `tenant_isolation` policy on all three tables |

`ProcessSyncScheduleHandler` and `RescheduleStuckJobsHandler` carry no `TenantStamp` — they run cross-tenant as privileged processes. In production these workers connect as the `app_scheduler` PostgreSQL role (`BYPASSRLS`).

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
RecurringMessage::every('5 minutes', new DispatchCollectionSyncBatchCommand())
RecurringMessage::every('2 minutes', new RescheduleStuckJobsCommand())
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
- A `MonitoredCollectionSync` is created in `Pending` before `StartSyncCommand` is dispatched — the DB row is the source of truth, not the message queue.
- `Product` carries no domain events and does not extend `AggregateRoot`.
- `FeatureFlag` lives in `Shared\Domain\ValueObject` — imported by both Tenancy and CatalogSync.
