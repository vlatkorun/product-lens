# CatalogSync — Design Document

## Purpose

CatalogSync fetches Shopify products into the platform so that ImageAudit (and future
audit contexts) can act on them. Each tenant configures which collections to monitor and
which audit features to run per collection. A scheduler reconciles enabled collections
across all active tenants and drives paginated product fetching via the Shopify GraphQL
Admin API.

---

## Changes to the Shared context

### `FeatureFlag` moved to `Shared/Domain/ValueObject/`

`FeatureFlag` was previously in `Tenancy\Domain\ValueObject`. It is a cross-cutting
concept — no single bounded context owns it. Tenancy *manages* flags on the `Tenant`
aggregate; CatalogSync *reads* them per collection; ImageAudit *checks* them before
running audits; future contexts (e.g. PriceAudit) will do the same.

Moving it to `Shared` makes it the single source of truth. Adding a new feature (e.g.
`PriceAudit`) is one line in one file, and the type system enforces exhaustive handling
everywhere.

```
Tenancy\Domain\ValueObject\FeatureFlag   ← deleted
Shared\Domain\ValueObject\FeatureFlag    ← canonical location
```

All references in `Tenancy`, and all new references in `CatalogSync` and `ImageAudit`,
import from `Shared`.

### `TenantScopedInterface` added to `Shared/Domain/Model/`

A marker interface that identifies entities subject to tenant isolation. Used by the
Doctrine SQL filter and as documentation of which aggregates carry a `tenant_id`.

```php
// Shared/Domain/Model/TenantScopedInterface.php
interface TenantScopedInterface
{
    public function tenantId(): UuidV7;
}
```

---

## CatalogSync bounded context

### Value Objects

| Class | Location | Purpose |
|---|---|---|
| `ShopifyGid` | `Domain/ValueObject/` | Wraps `gid://shopify/{Type}/{Id}`; validates format; factory methods `::product()`, `::collection()` |
| `SyncCursor` | `Domain/ValueObject/` | `endCursor: ?string` + `hasNextPage: bool`; stored as JSONB on `sync_jobs` |
| `SyncStatus` | `Domain/ValueObject/` | Enum: `Pending`, `Running`, `Completed`, `Failed` |
| `ProductStatus` | `Domain/ValueObject/` | Enum: `Active`, `Archived`, `Draft` — mirrors Shopify |
| `ProductFilter` | `Domain/ValueObject/` | `collectionGid: ShopifyGid`, `status: ProductStatus = Active` |
| `ProductPage` | `Domain/ValueObject/` | `products: Product[]`, `cursor: SyncCursor` — return type from `ProductFetcherInterface::fetchPage()` |

---

### Aggregates

#### `MonitoredCollection` — `collection_sync_configs` table

Represents a Shopify collection that a tenant has configured for syncing and auditing.
This is the configuration aggregate — it owns *what* to sync and *which* audit features
to run on that collection's products.

```
id              UuidV7
tenantId        UuidV7
collectionGid   ShopifyGid
collectionName  string              denormalized from Shopify for display
featureFlags    FeatureFlag[]       from Shared\Domain\ValueObject\FeatureFlag
enabled         bool
createdAt       DateTimeImmutable
updatedAt       DateTimeImmutable
```

**Factory:** `MonitoredCollection::create(tenantId, collectionGid, name, FeatureFlag[], now)`

**Domain methods:**

```
enable()                          raises CollectionMonitoringEnabled
disable()                         raises CollectionMonitoringDisabled
updateFeatureFlags(FeatureFlag[])
rename(string $name)
```

**Invariant:** `tenantId + collectionGid` is unique — one config per collection per tenant.

---

#### `SyncJob` — `sync_jobs` table

Represents one full sync execution for a monitored collection. Created fresh each
scheduled run. Tracks cursor state so a run can be resumed if interrupted.

```
id                     UuidV7
tenantId               UuidV7
monitoredCollectionId  UuidV7
collectionGid          ShopifyGid          denormalized — saves a join in the fetcher
status                 SyncStatus
cursor                 ?SyncCursor         null = not yet started
totalProcessed         int
startedAt              DateTimeImmutable
completedAt            ?DateTimeImmutable
failedAt               ?DateTimeImmutable
failureReason          ?string
```

**Factory:** `SyncJob::schedule(tenantId, monitoredCollectionId, collectionGid, now)`
→ status = `Pending`; raises no events — created by the scheduler inside the
claiming transaction, before `StartSyncCommand` is dispatched.

**Domain methods:**

```
start()
    status Pending → Running, raises SyncJobStarted

recordPage(endCursor, hasNextPage, count)
    replaces cursor, adds count to totalProcessed
    always raises SyncJobProcessed
    when !hasNextPage: status → Completed, also raises SyncJobCompleted

fail(reason, at)
    status → Failed, raises SyncJobFailed
```

**Invariant:** Exclusivity is enforced at the database level. The scheduler queries
with `NOT EXISTS (... status IN ('pending', 'running'))` and `FOR UPDATE SKIP LOCKED`,
then inserts the `Pending` job in the same transaction. The lock is released and the
row becomes visible atomically on commit — there is no window in which a concurrent
scheduler can claim the same collection.

---

#### `Product` — `products` table

A read model snapshot of a Shopify product. Not an aggregate — no domain events. Upserted
on every sync run, keyed on `tenantId + shopifyGid`. The latest sync always wins.

```
id                UuidV7
shopifyGid        ShopifyGid
tenantId          UuidV7
collectionGid     ShopifyGid
title             string
handle            string
vendor            string
productType       string
status            ProductStatus
images            array (JSONB)     [{url, altText, width, height}]
featuredImageUrl  ?string
syncedAt          DateTimeImmutable
```

`Product` does **not** extend `AggregateRoot` and raises no domain events.

---

### Domain Events

| Event | Interface | Raised by | Purpose |
|---|---|---|---|
| `CollectionMonitoringEnabled` | `DomainEvent` (sync) | `MonitoredCollection::enable()` | Notifies listeners that a collection is now active |
| `CollectionMonitoringDisabled` | `DomainEvent` (sync) | `MonitoredCollection::disable()` | Notifies listeners that a collection was deactivated |
| `SyncJobStarted` | `AsyncDomainEvent` | `SyncJob::start()` | Observability; marks when a run begins |
| `SyncJobProcessed` | `AsyncDomainEvent` | `SyncJob::recordPage()` on each page | Progress tracking; carries page count and cursor |
| `SyncJobCompleted` | `AsyncDomainEvent` | `SyncJob::recordPage()` on last page | Triggers ImageAudit; carries `featureFlags[]` |
| `SyncJobFailed` | `AsyncDomainEvent` | `SyncJob::fail()` | Observability; dead-letter handling later |

`SyncJobCompleted` carries `tenantId`, `collectionGid`, and `featureFlags[]`. ImageAudit
receives everything it needs to act without querying CatalogSync's tables.

---

### Domain Service Interface

```php
// CatalogSync/Domain/Service/ProductFetcherInterface.php

interface ProductFetcherInterface
{
    public function fetchByGid(ShopifyGid $gid, UuidV7 $tenantId): Product;

    public function fetchPage(
        ProductFilter $filter,
        UuidV7 $tenantId,
        ?SyncCursor $after = null,
        int $pageSize = 250,
    ): ProductPage;
}
```

`fetchByGid` — used by the webhook handler for single-product create/update events.
`fetchPage` — used by `FetchNextPageHandler` for scheduled paginated syncs.

The interface is domain-pure. `ShopifyProductFetcher` in Infrastructure resolves tenant
credentials from `TenantRepositoryInterface` internally — credentials never appear in the
domain contract.

---

### Application layer

#### Commands

| Command | Handler responsibility |
|---|---|
| `ConfigureMonitoredCollection` | Create or update a `MonitoredCollection` for a tenant; idempotent on `tenantId + collectionGid` |
| `StartSync` | Load the `Pending` `SyncJob` created by the scheduler; call `syncJob->start()` (→ `Running`); dispatch first `FetchNextPage` async |
| `FetchNextPage` | Call `ProductFetcherInterface::fetchPage()`; upsert products; call `syncJob->recordPage()`; dispatch next `FetchNextPage` if `hasNextPage`, otherwise done |
| `HandleWebhook` | Call `ProductFetcherInterface::fetchByGid()` for a single product; upsert into `products` table |
| `RescheduleStuckJobs` | Find `SyncJob` rows stuck in `Pending` beyond a threshold; re-dispatch `StartSyncCommand` for each |

`FetchNextPage` is dispatched as an async Messenger message — each page is an independent
worker turn, which naturally provides retry and backpressure boundaries later.

---

### Stuck-job recovery (reaper)

There is a failure seam between the scheduler's `COMMIT` and successful message delivery
to RabbitMQ. If the scheduler process dies in that window, the `SyncJob` row exists in
`Pending` state but no `StartSyncCommand` is in the queue. The `NOT EXISTS` guard treats
the collection as active on every subsequent scheduler run — the job is stuck forever.

`SyncSchedule` registers a second `RecurringMessage` (every 2 minutes) that dispatches
`RescheduleStuckJobsCommand`. Its handler re-dispatches `StartSyncCommand` for any job
that has been `Pending` beyond a threshold:

```sql
SELECT * FROM sync_jobs
WHERE  status = 'pending'
AND    started_at < NOW() - INTERVAL '5 minutes'
FOR UPDATE SKIP LOCKED
```

For each result it dispatches `StartSyncCommand(syncJobId) + TenantStamp`.
`StartSyncHandler` finds the existing `Pending` job by ID and transitions it normally —
there is no special reaper path in the handler.

`SyncSchedule` wires both tasks together:

```php
#[AsSchedule('catalog_sync')]
class SyncSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return $this->schedule ??= (new Schedule())
            ->with(
                RecurringMessage::every('5 minutes', new ProcessSyncScheduleCommand()),
                RecurringMessage::every('2 minutes', new RescheduleStuckJobsCommand()),
            );
    }
}
```

The 5-minute threshold must exceed the maximum time a healthy scheduler could take
between `COMMIT` and a successful publish, which in practice is milliseconds. Five
minutes provides ample margin without leaving collections blocked for a meaningful
period. `FOR UPDATE SKIP LOCKED` ensures that if two worker instances consume the
reaper message concurrently they do not double-dispatch the same job.

#### Queries

| Query | Returns |
|---|---|
| `GetSyncStatus` | Current `SyncStatus`, cursor, `totalProcessed`, timestamps for a given `SyncJob` |

---

### Infrastructure

#### `ShopifyProductFetcher`

Implements `ProductFetcherInterface`. Depends on `TenantRepositoryInterface` to resolve
the Shopify access token from `tenantId`.

**`fetchByGid` — single product query:**
```graphql
query GetProduct($id: ID!) {
  product(id: $id) {
    id title handle vendor productType status
    featuredImage { url }
    images(first: 10) { edges { node { url altText width height } } }
  }
}
```

**`fetchPage` — paginated collection query:**
```graphql
query ProductsByCollection($collectionId: ID!, $first: Int!, $after: String) {
  collection(id: $collectionId) {
    products(first: $first, after: $after) {
      pageInfo { hasNextPage endCursor }
      edges {
        node {
          id title handle vendor productType status
          featuredImage { url }
          images(first: 10) { edges { node { url altText width height } } }
        }
      }
    }
  }
}
```

The nested `collection { products }` connection is preferred over
`products(query: "collection_id:X")` because the cursor is scoped to that collection's
product list — mid-sync additions to other collections do not cause drift.

---

## Tenant isolation

### Design

Two enforcement layers — defence in depth:

| Layer | Enforces | Protects against |
|---|---|---|
| Doctrine SQL filter | Application-level isolation | Accidental cross-tenant queries in application code |
| PostgreSQL RLS | Database-level isolation | Compromised app code, raw SQL, direct DB access |

The scheduler is a legitimately privileged cross-tenant process and must bypass both.

---

### `TenantFilter` (Doctrine SQL Filter)

Added to `Shared/Infrastructure/Doctrine/Filter/TenantFilter.php`. Appends a
`WHERE tenant_id = :tenant_id` clause to every query for entities that implement
`TenantScopedInterface`.

Registered in `doctrine.yaml` with `enabled: false` — it is activated explicitly by
`TenantContext::activate()`, never globally.

---

### `TenantContext` service

`Shared/Infrastructure/Tenancy/TenantContext.php` — called from both HTTP and Messenger
entry points.

```
activate(UuidV7 $tenantId)
    SET LOCAL app.current_tenant_id = '{uuid}'   ← transaction-scoped; resets on commit
    enable Doctrine TenantFilter with tenant_id

deactivate()
    disable Doctrine TenantFilter
```

`SET LOCAL` is used (not `SET`) — the PostgreSQL session variable resets automatically on
commit or rollback, making it safe with Doctrine's transaction management and connection
pooling.

---

### `TenantContextMiddleware` (Symfony Messenger)

`Shared/Infrastructure/Symfony/TenantContextMiddleware.php` — wraps every message handler.
Tenant-scoped commands carry a `TenantStamp` with the `tenantId`. The middleware calls
`TenantContext::activate()` before the handler and `deactivate()` in a `finally` block.

An equivalent Symfony `KernelEvents::REQUEST` listener handles HTTP requests.

---

### PostgreSQL RLS

Row Level Security is enabled on all three CatalogSync tables.

```sql
ALTER TABLE collection_sync_configs ENABLE ROW LEVEL SECURITY;
ALTER TABLE collection_sync_configs FORCE ROW LEVEL SECURITY;

ALTER TABLE sync_jobs ENABLE ROW LEVEL SECURITY;
ALTER TABLE sync_jobs FORCE ROW LEVEL SECURITY;

ALTER TABLE products ENABLE ROW LEVEL SECURITY;
ALTER TABLE products FORCE ROW LEVEL SECURITY;
```

`FORCE ROW LEVEL SECURITY` ensures the policy applies to the table owner (the app DB
user), not just other roles.

**Isolation policies:**

```sql
CREATE POLICY tenant_isolation ON collection_sync_configs
    USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid);

CREATE POLICY tenant_isolation ON sync_jobs
    USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid);

CREATE POLICY tenant_isolation ON products
    USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid);
```

`current_setting('app.current_tenant_id', true)` — the `true` flag (missing_ok) returns
`NULL` when the setting is absent. `tenant_id = NULL` evaluates to `NULL` (never true),
so an unset context sees no rows — a safe default.

---

### Scheduler: privileged cross-tenant access

The scheduler reads across all tenants to decide what to dispatch. It uses a dedicated
`SchedulerEntityManager` bound to the `app_scheduler` PostgreSQL role, which has
`BYPASSRLS`. `TenantContext` is never activated for the scheduler process.

```sql
CREATE ROLE app_scheduler WITH NOLOGIN BYPASSRLS;
```

> `BYPASSRLS` requires superuser privilege to grant. If the migration DB user is not a
> superuser, this statement must be run separately by a DBA or via infrastructure
> provisioning (Terraform, Ansible).

`StartSyncCommand` dispatches carry a `TenantStamp` — once the message is consumed by a
worker, `TenantContextMiddleware` activates tenant context for that handler's scope.

---

## Database schema and migrations

### Migration 003 — CatalogSync tables

**New PostgreSQL enum types:**
```sql
CREATE TYPE sync_status    AS ENUM ('pending', 'running', 'completed', 'failed')
CREATE TYPE product_status AS ENUM ('active', 'archived', 'draft')
```

These require corresponding Doctrine custom types (`SyncStatusType`, `ProductStatusType`)
following the same pattern as the existing `TenantStatusType`.

---

**`collection_sync_configs`**

```sql
CREATE TABLE collection_sync_configs (
    id              UUID         NOT NULL,
    tenant_id       UUID         NOT NULL,   -- FK tenants(id) ON DELETE CASCADE
    collection_gid  VARCHAR(255) NOT NULL,
    collection_name VARCHAR(255) NOT NULL,
    feature_flags   JSONB        NOT NULL DEFAULT '[]',
    enabled         BOOLEAN      NOT NULL DEFAULT true,
    created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
```

| Index | Definition | Reason |
|---|---|---|
| `collection_sync_configs_tenant_collection_uq` | `UNIQUE (tenant_id, collection_gid)` | Enforces the one-config-per-collection invariant |
| `collection_sync_configs_tenant_id_idx` | `(tenant_id)` | General tenant-scoped lookups |
| `collection_sync_configs_enabled_idx` | `(tenant_id) WHERE enabled = true` | Scheduler outer scan; only touches enabled rows |

---

**`sync_jobs`**

```sql
CREATE TABLE sync_jobs (
    id                      UUID        NOT NULL,
    tenant_id               UUID        NOT NULL,   -- FK tenants(id) ON DELETE CASCADE
    monitored_collection_id UUID        NOT NULL,   -- FK collection_sync_configs(id) ON DELETE CASCADE
    collection_gid          VARCHAR(255) NOT NULL,
    status                  sync_status NOT NULL DEFAULT 'pending',
    cursor                  JSONB       DEFAULT NULL,
    total_processed         INTEGER     NOT NULL DEFAULT 0,
    started_at              TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    completed_at            TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    failed_at               TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    failure_reason          TEXT        DEFAULT NULL,
    PRIMARY KEY (id)
)
```

| Index | Definition | Reason |
|---|---|---|
| `sync_jobs_tenant_id_idx` | `(tenant_id)` | Tenant-scoped job lookups |
| `sync_jobs_monitored_collection_id_idx` | `(monitored_collection_id)` | FK navigation and job history per collection |
| `sync_jobs_status_idx` | `(status)` | Status-based filtering |
| `sync_jobs_started_at_idx` | `(started_at)` | History queries ordered by time |
| `sync_jobs_running_idx` | `(monitored_collection_id) WHERE status IN ('pending', 'running')` | Scheduler `NOT EXISTS` subquery; partial index covers only the tiny active subset |

---

**`products`**

```sql
CREATE TABLE products (
    id                 UUID           NOT NULL,
    shopify_gid        VARCHAR(255)   NOT NULL,
    tenant_id          UUID           NOT NULL,   -- FK tenants(id) ON DELETE CASCADE
    collection_gid     VARCHAR(255)   NOT NULL,
    title              VARCHAR(255)   NOT NULL,
    handle             VARCHAR(255)   NOT NULL,
    vendor             VARCHAR(255)   NOT NULL DEFAULT '',
    product_type       VARCHAR(255)   NOT NULL DEFAULT '',
    status             product_status NOT NULL,
    images             JSONB          NOT NULL DEFAULT '[]',
    featured_image_url TEXT           DEFAULT NULL,
    synced_at          TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
)
```

| Index | Definition | Reason |
|---|---|---|
| `products_tenant_shopify_gid_uq` | `UNIQUE (tenant_id, shopify_gid)` | Upsert key — one record per tenant per Shopify GID |
| `products_tenant_id_idx` | `(tenant_id)` | General tenant-scoped lookups |
| `products_collection_gid_idx` | `(tenant_id, collection_gid)` | Fetch products for a specific collection under a tenant |
| `products_status_idx` | `(tenant_id, status)` | Filter active/archived/draft per tenant |
| `products_synced_at_idx` | `(synced_at)` | Identify stale products across the fleet |

---

### Migration 004 — Row Level Security

Enables RLS and `FORCE ROW LEVEL SECURITY` on the three CatalogSync tables, creates
the `tenant_isolation` policy on each, and creates the `app_scheduler` role with
`BYPASSRLS`.

**`down()` reversal order:**

1. `DROP ROLE IF EXISTS app_scheduler`
2. `DROP POLICY IF EXISTS tenant_isolation` on each table
3. `NO FORCE ROW LEVEL SECURITY` on each table
4. `DISABLE ROW LEVEL SECURITY` on each table

---

## Full directory tree

```
src/
├── Shared/
│   ├── Domain/
│   │   ├── Model/
│   │   │   ├── AggregateRoot.php
│   │   │   └── TenantScopedInterface.php            ← new
│   │   ├── ValueObject/
│   │   │   └── FeatureFlag.php                      ← moved from Tenancy
│   │   └── Event/
│   │       ├── DomainEvent.php
│   │       └── AsyncDomainEvent.php
│   └── Infrastructure/
│       ├── Doctrine/
│       │   ├── Filter/
│       │   │   └── TenantFilter.php                 ← new
│       │   └── Type/
│       │       ├── EncryptedStringType.php
│       │       └── JsonbType.php
│       ├── Event/
│       │   └── DomainEventPublisher.php
│       ├── Symfony/
│       │   ├── EncryptionKeyConfigurator.php
│       │   └── TenantContextMiddleware.php          ← new
│       └── Tenancy/
│           └── TenantContext.php                    ← new
│
└── CatalogSync/
    ├── Domain/
    │   ├── Model/
    │   │   ├── MonitoredCollection.php
    │   │   ├── SyncJob.php
    │   │   └── Product.php
    │   ├── ValueObject/
    │   │   ├── ShopifyGid.php
    │   │   ├── ProductStatus.php
    │   │   ├── ProductFilter.php
    │   │   ├── ProductPage.php
    │   │   ├── SyncCursor.php
    │   │   └── SyncStatus.php
    │   ├── Repository/
    │   │   ├── MonitoredCollectionRepositoryInterface.php
    │   │   ├── SyncJobRepositoryInterface.php
    │   │   └── ProductRepositoryInterface.php
    │   ├── Service/
    │   │   └── ProductFetcherInterface.php
    │   └── Event/
    │       ├── CollectionMonitoringEnabled.php
    │       ├── CollectionMonitoringDisabled.php
    │       ├── SyncJobStarted.php
    │       ├── SyncJobProcessed.php
    │       ├── SyncJobCompleted.php
    │       └── SyncJobFailed.php
    ├── Application/
    │   ├── Command/
    │   │   ├── ConfigureMonitoredCollection/
    │   │   │   ├── ConfigureMonitoredCollectionCommand.php
    │   │   │   └── ConfigureMonitoredCollectionHandler.php
    │   │   ├── StartSync/
    │   │   │   ├── StartSyncCommand.php
    │   │   │   └── StartSyncHandler.php
    │   │   ├── FetchNextPage/
    │   │   │   ├── FetchNextPageCommand.php
    │   │   │   └── FetchNextPageHandler.php
    │   │   ├── HandleWebhook/
    │   │   │   ├── HandleWebhookCommand.php
    │   │   │   └── HandleWebhookHandler.php
    │   │   └── RescheduleStuckJobs/
    │   │       ├── RescheduleStuckJobsCommand.php
    │   │       └── RescheduleStuckJobsHandler.php
    │   └── Query/
    │       └── GetSyncStatus/
    │           ├── GetSyncStatusQuery.php
    │           └── GetSyncStatusHandler.php
    └── Infrastructure/
        ├── Shopify/
        │   ├── ShopifyProductFetcher.php
        │   └── GraphQL/
        │       ├── GetProductQuery.php
        │       └── ProductsByCollectionQuery.php
        ├── Doctrine/
        │   └── Type/
        │       ├── SyncStatusType.php
        │       └── ProductStatusType.php
        ├── Webhook/
        │   ├── ShopifyWebhookController.php
        │   └── ShopifyWebhookValidator.php
        ├── Persistence/
        │   ├── DoctrineMonitoredCollectionRepository.php
        │   ├── DoctrineSyncJobRepository.php
        │   └── DoctrineProductRepository.php
        └── Scheduler/
            └── SyncSchedule.php

migrations/
    Version20260511000003.php    ← CatalogSync tables + indexes
    Version20260511000004.php    ← RLS policies + app_scheduler role
```

---

## Full sync flow

```
┌─────────────────────────────────────────────────────────────────┐
│  Tenant admin                                                   │
│    ConfigureMonitoredCollectionCommand                          │
│      → MonitoredCollection::create(tenantId, collectionGid,    │
│                                    name, [ImageAudit], now)     │
│      → raises CollectionMonitoringEnabled                       │
└────────────────────────────┬────────────────────────────────────┘
                             │ (stored in collection_sync_configs)
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  SyncSchedule (runs on interval, as app_scheduler / BYPASSRLS) │
│                                                                 │
│    SELECT mc.* FROM collection_sync_configs mc                  │
│    WHERE mc.enabled = true          ← outer scan hits enabled_idx│
│    AND NOT EXISTS (                                             │
│        SELECT 1 FROM sync_jobs sj                               │
│        WHERE sj.monitored_collection_id = mc.id                 │
│          AND sj.status IN ('pending', 'running')  ← hits running_idx│
│    )                                                            │
│    FOR UPDATE SKIP LOCKED  ← concurrent schedulers skip claimed │
│                               rows; no two processes claim the  │
│                               same collection                   │
│                                                                 │
│    → INSERT sync_jobs (status='pending') for each claimed row   │
│      (same transaction — lock released and row live atomically) │
│    → COMMIT                                                     │
│    → dispatch StartSyncCommand(syncJobId) + TenantStamp         │
│              for each inserted job                              │
└────────────────────────────┬────────────────────────────────────┘
                             │ [async Messenger — RabbitMQ]
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  TenantContextMiddleware                                        │
│    SET LOCAL app.current_tenant_id = '{tenantId}'               │
│    enable Doctrine TenantFilter(tenant_id)                      │
└────────────────────────────┬────────────────────────────────────┘
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  StartSyncHandler                                               │
│    1. SyncJobRepository::findById(syncJobId)                    │
│       → job already exists in Pending state; created by the     │
│         scheduler before dispatch — no creation needed here     │
│    2. syncJob->start()                                          │
│       → status Pending → Running, raises SyncJobStarted        │
│    3. SyncJobRepository::save(syncJob)                          │
│       → DomainEventPublisher dispatches SyncJobStarted async    │
│    4. dispatch FetchNextPageCommand(syncJobId) + TenantStamp    │
└────────────────────────────┬────────────────────────────────────┘
                             │ [async Messenger — one message per page]
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  FetchNextPageHandler  (repeated until hasNextPage = false)     │
│                                                                 │
│    1. ProductFetcherInterface::fetchPage(                       │
│           ProductFilter(collectionGid),                         │
│           tenantId,                                             │
│           syncJob->cursor(),                                    │
│           pageSize: 250,                                        │
│       ): ProductPage                                            │
│       → ShopifyProductFetcher resolves access token from        │
│         TenantRepository, calls Shopify GraphQL                 │
│                                                                 │
│    2. ProductRepository::upsertAll(products)                    │
│       → INSERT ... ON CONFLICT (tenant_id, shopify_gid)         │
│          DO UPDATE SET ...                                      │
│                                                                 │
│    3. syncJob->recordPage(endCursor, hasNextPage, count)        │
│       → always raises SyncJobProcessed                          │
│       → if hasNextPage = false: also raises SyncJobCompleted    │
│                                                                 │
│    4. SyncJobRepository::save(syncJob)                          │
│       → DomainEventPublisher dispatches events async            │
│                                                                 │
│    5. if hasNextPage = true:                                    │
│         dispatch FetchNextPageCommand(syncJobId) + TenantStamp  │
│       if hasNextPage = false:                                   │
│         done — SyncJobCompleted carries tenantId,               │
│                collectionGid, featureFlags[]                    │
└────────────────────────────┬────────────────────────────────────┘
                             │ SyncJobCompleted [async Messenger]
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  ImageAudit context                                             │
│    RunAuditCommand dispatched                                   │
│    CompositeAuditor chain runs (Missing → Format → Size)        │
│    if FeatureFlag::AiImageAudit in featureFlags[]:              │
│      RunAiAuditCommand dispatched → ClaudeImageAuditor          │
│    AuditCompleted raised                                        │
└─────────────────────────────────────────────────────────────────┘
```

### Webhook path (single product)

```
Shopify webhook → ShopifyWebhookController
    → ShopifyWebhookValidator (HMAC check)
    → HandleWebhookCommand(tenantId, shopifyProductGid, eventType)
        → ProductFetcherInterface::fetchByGid(gid, tenantId)
        → ProductRepository::upsert(product)
        → (if event = delete) ProductRepository::remove(gid, tenantId)
```

---

## Isolation flow summary

```
HTTP / Messenger message (tenant-scoped)
    TenantContextMiddleware / RequestListener
        SET LOCAL app.current_tenant_id = '{uuid}'     ← PG session var, tx-scoped
        Doctrine TenantFilter enabled(tenant_id)
        All queries: WHERE tenant_id = '{uuid}'        ← application layer
        RLS policy: tenant_id = current_setting(...)   ← database layer backstop

SyncSchedule (cross-tenant, privileged)
    Connects as app_scheduler (BYPASSRLS)
    TenantContext never activated, TenantFilter never enabled
    Reads all enabled MonitoredCollections freely
    Dispatches per-tenant commands with TenantStamp
        → TenantContextMiddleware activates context per handler
```
