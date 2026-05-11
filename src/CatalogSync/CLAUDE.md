# CatalogSync — Context

## Purpose

Fetches Shopify products into the platform so ImageAudit can act on them. Tenants configure which collections to monitor and which audit features to run. A scheduler reconciles enabled collections across all tenants and drives paginated product fetching via the Shopify GraphQL Admin API.

---

## Bounded context rules

- Imports from `Shared\Domain\` are allowed.
- Imports from `Tenancy\Domain\Repository\TenantRepositoryInterface` are allowed (credential lookup only).
- Never import from `Identity`, `ImageAudit`, or any other bounded context's domain classes.
- Cross-context communication happens only via domain events (`SyncJobCompleted` → ImageAudit).

---

## Domain model

### Value objects (`Domain/ValueObject/`)

| Class | Purpose |
|---|---|
| `ShopifyGid` | Wraps `gid://shopify/{Type}/{Id}`; factory methods `::product()`, `::collection()`, `::fromString()` |
| `SyncCursor` | `endCursor: ?string` + `hasNextPage: bool`; stored as JSONB on `sync_jobs` |
| `SyncStatus` | Enum: `Pending`, `Running`, `Completed`, `Failed` |
| `ProductStatus` | Enum: `Active`, `Archived`, `Draft` |
| `ProductFilter` | `collectionGid: ShopifyGid`, `status: ProductStatus = Active` |
| `ProductPage` | `products: Product[]`, `cursor: SyncCursor` — return type from `ProductFetcherInterface::fetchPage()` |

### Aggregates (`Domain/Model/`)

**`MonitoredCollection`** — table `collection_sync_configs`

Configuration aggregate. Owns what to sync and which audit features to run on a collection's products. Unique per `(tenantId, collectionGid)`.

```
create(tenantId, collectionGid, name, FeatureFlag[], enabled, now) → raises CollectionMonitoringEnabled if enabled
enable()       → raises CollectionMonitoringEnabled (idempotent)
disable()      → raises CollectionMonitoringDisabled (idempotent)
updateFeatureFlags(FeatureFlag[])
rename(string)
```

**`SyncJob`** — table `sync_jobs`

One full sync execution for a monitored collection. Created in `Pending` state by the scheduler (not the handler) to close the race window.

```
schedule(tenantId, monitoredCollectionId, collectionGid, now) → status=Pending, no events
start()                                                       → Pending→Running, raises SyncJobStarted
recordPage(endCursor, hasNextPage, count, featureFlags[])     → raises SyncJobProcessed; if !hasNextPage: Completed + SyncJobCompleted
fail(reason, at)                                              → raises SyncJobFailed
```

**`Product`** — table `products`

Read model snapshot of a Shopify product. Not an aggregate — no domain events. Upserted on every sync, keyed on `(tenantId, shopifyGid)`. Latest sync always wins.

### Domain events

| Event | Type | Raised by |
|---|---|---|
| `CollectionMonitoringEnabled` | sync | `MonitoredCollection::enable()` / `create()` |
| `CollectionMonitoringDisabled` | sync | `MonitoredCollection::disable()` |
| `SyncJobStarted` | async | `SyncJob::start()` |
| `SyncJobProcessed` | async | `SyncJob::recordPage()` on each page |
| `SyncJobCompleted` | async | `SyncJob::recordPage()` on last page — carries `tenantId`, `collectionGid`, `featureFlags[]` |
| `SyncJobFailed` | async | `SyncJob::fail()` |

`SyncJobCompleted` gives ImageAudit everything it needs without querying CatalogSync tables.

---

## Application layer

### Commands

| Command | Handler responsibility |
|---|---|
| `ConfigureMonitoredCollectionCommand` | Upsert `MonitoredCollection` by `(tenantId, collectionGid)`; idempotent |
| `ProcessSyncScheduleCommand` | SELECT FOR UPDATE SKIP LOCKED eligible collections; INSERT pending `SyncJob` per collection in same transaction; dispatch `StartSyncCommand` per job |
| `StartSyncCommand(syncJobId)` | Load existing Pending job; call `start()`; dispatch `FetchNextPageCommand` |
| `FetchNextPageCommand(syncJobId)` | Fetch page from Shopify; upsert products; call `recordPage()`; re-dispatch if `hasNextPage` |
| `HandleWebhookCommand` | Single-product create/update: fetch + upsert; delete: remove from table |
| `RescheduleStuckJobsCommand` | Find Pending jobs older than 5 min; re-dispatch `StartSyncCommand` for each |

### Query

`GetSyncStatusQuery(syncJobId)` → returns `?SyncJob`

---

## Full sync flow

```
Symfony Scheduler (every 5 min)
    → ProcessSyncScheduleCommand

ProcessSyncScheduleHandler
    BEGIN TRANSACTION
    SELECT mc.id, mc.tenant_id, mc.collection_gid
    FROM   collection_sync_configs mc
    WHERE  mc.enabled = true
    AND    NOT EXISTS (
               SELECT 1 FROM sync_jobs sj
               WHERE  sj.monitored_collection_id = mc.id
               AND    sj.status IN ('pending', 'running')
           )
    FOR UPDATE SKIP LOCKED
    → INSERT sync_jobs (status='pending') for each row
    COMMIT
    → dispatch StartSyncCommand(syncJobId) + TenantStamp per job

StartSyncHandler
    → findById(syncJobId)             ← job already in Pending state
    → syncJob->start()                ← Pending → Running, raises SyncJobStarted
    → save(syncJob)
    → dispatch FetchNextPageCommand(syncJobId) + TenantStamp

FetchNextPageHandler  [repeated until hasNextPage = false]
    → ProductFetcherInterface::fetchPage(ProductFilter, tenantId, cursor)
    → ProductRepository::upsertAll(products)
    → MonitoredCollectionRepository::findById(monitoredCollectionId)
    → syncJob->recordPage(endCursor, hasNextPage, count, featureFlags[])
    → save(syncJob)
    → if hasNextPage: dispatch FetchNextPageCommand again
    → if !hasNextPage: SyncJobCompleted dispatched → ImageAudit listens
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

### `ShopifyProductFetcher`

Implements `ProductFetcherInterface`. Resolves Shopify credentials from `TenantRepositoryInterface` internally — credentials never appear in domain contracts. Uses Symfony `HttpClientInterface` to call Shopify GraphQL Admin API (2024-10).

### `ShopifyWebhookValidator`

HMAC-SHA256 validation against `SHOPIFY_WEBHOOK_SECRET` env var (`#[Autowire(env: 'SHOPIFY_WEBHOOK_SECRET')]`).

### `SyncSchedule`

```php
#[AsSchedule('catalog_sync')]
// worker: messenger:consume scheduler_catalog_sync
RecurringMessage::every('5 minutes', new ProcessSyncScheduleCommand())
RecurringMessage::every('2 minutes', new RescheduleStuckJobsCommand())
```

### Persistence

- `DoctrineMonitoredCollectionRepository` — standard ORM save + event publish
- `DoctrineSyncJobRepository` — standard ORM save + event publish; `findStuckPending()` via DQL
- `DoctrineProductRepository` — raw DBAL upsert (`INSERT ... ON CONFLICT DO UPDATE`)

---

## Database

Tables: `collection_sync_configs`, `sync_jobs`, `products`

Migration 003 — tables, enums (`sync_status`, `product_status`), indexes including partial indexes:
- `collection_sync_configs_enabled_idx` — `(tenant_id) WHERE enabled = true`
- `sync_jobs_running_idx` — `(monitored_collection_id) WHERE status IN ('pending', 'running')`

Migration 004 — RLS policies, `app_scheduler` role with `BYPASSRLS`

---

## Key invariants

- `(tenantId, collectionGid)` is unique in `collection_sync_configs`.
- No two schedulers can claim the same collection concurrently: `FOR UPDATE SKIP LOCKED` + in-transaction insert.
- A `SyncJob` is created in `Pending` before `StartSyncCommand` is dispatched — the DB row is the source of truth, not the message queue.
- `Product` carries no domain events and does not extend `AggregateRoot`.
- `FeatureFlag` lives in `Shared\Domain\ValueObject` — imported by both Tenancy and CatalogSync.
