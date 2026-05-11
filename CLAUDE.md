# ProductLens — CLAUDE.md

## What this project does

ProductLens is a multitenant Shopify product audit platform. It periodically fetches
products from Shopify collections (or on webhook events), and audits each product
against the feature flags enabled for the tenant — such as image audit or AI image
audit. Each feature flag maps to a defined audit specification. Reports surface
non-conforming products so merchandising teams can act on them quickly.

Origin use case: Co-Op Superstores needed a way to detect products without images
across their full Shopify catalogue without manual inspection.

---

## Tech stack

- **PHP 8.4** / **Symfony 7.4**
- **Doctrine ORM** (PostgreSQL)
- **Symfony Messenger** — command bus, query bus, and event bus (same component,
  different transports)
- **RabbitMQ** — Messenger async transport for commands and domain events
- **Redis** — caching and session storage
- **Symfony Scheduler** — periodic sync jobs
- **Shopify GraphQL Admin API** — Relay Connection spec, cursor-based pagination
- **Claude API** (Anthropic) — AI image audit step

---

## Architecture: Domain-Driven Design

The project is structured around three **bounded contexts**. Each owns its domain
model, application logic, and infrastructure adapters. Contexts communicate only
via domain events — never by importing each other's domain classes directly.
The only shared primitives live in `Shared/Domain/`.

### Bounded contexts

```
Tenancy        — tenant lifecycle, feature flags, Shopify OAuth
CatalogSync    — product fetching, cursor tracking, webhook ingestion
ImageAudit     — technical audit chain, AI audit, report generation
```

**Tenancy** is a read-dependency for the other two. It exposes `TenantId` (shared)
and `FeatureFlagResolver` (domain service). No other context writes to Tenancy.

**CatalogSync → ImageAudit** is the main flow. When a sync job completes (or a
product webhook arrives), `SyncJobCompleted` is dispatched. ImageAudit listens and
dispatches `RunAuditCommand`. The two contexts never import each other's namespaces.

---

## Directory structure

```
src/
├── Shared/
│   └── Domain/
│       ├── Model/AggregateRoot.php
│       ├── ValueObject/TenantId.php
│       ├── ValueObject/Ulid.php
│       ├── Event/DomainEvent.php
│       └── Clock/ClockInterface.php
│
├── Tenancy/
│   ├── Domain/
│   │   ├── Model/Tenant.php                     ← aggregate root
│   │   ├── ValueObject/FeatureFlag.php           ← backed enum
│   │   ├── Repository/TenantRepositoryInterface.php
│   │   ├── Service/FeatureFlagResolver.php
│   │   ├── Event/{TenantCreated,FeatureEnabled,FeatureDisabled}.php
│   │   └── Exception/{TenantNotFoundException,FeatureAlreadyEnabledException}.php
│   ├── Application/
│   │   ├── Command/CreateTenant/{Command,Handler}.php
│   │   └── Query/GetTenant/{Query,Handler}.php
│   └── Infrastructure/
│       ├── Persistence/DoctrineTenantRepository.php
│       ├── Http/ShopifyOAuthController.php
│       └── Symfony/TenantContextMiddleware.php   ← resolves TenantId per request
│
├── CatalogSync/
│   ├── Domain/
│   │   ├── Model/SyncJob.php                     ← aggregate root
│   │   ├── Model/Product.php                     ← entity (read model)
│   │   ├── ValueObject/SyncCursor.php            ← wraps endCursor + hasNextPage
│   │   ├── ValueObject/SyncStatus.php            ← enum: PENDING|RUNNING|COMPLETED|FAILED
│   │   ├── Repository/{SyncJobRepositoryInterface,ProductRepositoryInterface}.php
│   │   ├── Service/ProductFetcherInterface.php   ← domain service contract
│   │   └── Event/{SyncJobCompleted,SyncJobFailed}.php
│   ├── Application/
│   │   ├── Command/StartSync/{Command,Handler}.php
│   │   ├── Command/HandleWebhook/{Command,Handler}.php
│   │   └── Query/GetSyncStatus/{Query,Handler}.php
│   └── Infrastructure/
│       ├── Shopify/ShopifyProductFetcher.php     ← implements ProductFetcherInterface
│       ├── Shopify/GraphQL/ProductsByCollectionQuery.php
│       ├── Shopify/Webhook/{ShopifyWebhookController,ShopifyWebhookValidator}.php
│       ├── Persistence/{DoctrineSyncJobRepository,DoctrineProductRepository}.php
│       └── Scheduler/SyncSchedule.php            ← Symfony Scheduler
│
└── ImageAudit/
    ├── Domain/
    │   ├── Model/AuditReport.php                 ← aggregate root
    │   ├── Model/AuditFinding.php                ← entity (append-only)
    │   ├── ValueObject/FindingType.php           ← enum: MISSING|WRONG_FORMAT|WRONG_SIZE|AI_FLAGGED
    │   ├── ValueObject/AuditStatus.php           ← enum: PENDING|RUNNING|PASSED|FAILED
    │   ├── Repository/AuditReportRepositoryInterface.php
    │   ├── Service/ImageAuditorInterface.php
    │   ├── Service/AiImageAuditorInterface.php
    │   └── Event/AuditCompleted.php
    ├── Application/
    │   ├── Command/RunAudit/{Command,Handler}.php
    │   ├── Command/RunAiAudit/{Command,Handler}.php   ← feature-flag guarded
    │   └── Query/GetMissingImages/{Query,Handler}.php
    └── Infrastructure/
        ├── Auditor/CompositeAuditor.php          ← tagged_iterator chain
        ├── Auditor/MissingImageAuditor.php
        ├── Auditor/FormatAuditor.php
        ├── Auditor/SizeAuditor.php
        ├── AiAuditor/ClaudeImageAuditor.php      ← implements AiImageAuditorInterface
        └── Persistence/DoctrineAuditReportRepository.php
```

---

## Key patterns

### Aggregates and value objects

- **Private constructors** on all aggregates. Creation only through named static
  factory methods (`Tenant::create()`, `SyncJob::start()`).
- **`AggregateRoot::raise()`** collects domain events internally.
  `pullDomainEvents()` is called by the repository after `save()` so events are
  dispatched transactionally.
- **Value objects are immutable.** Mutation returns a new instance. `SyncCursor`
  is replaced wholesale on each page (`SyncJob::recordPage()`), never mutated.
- **`FeatureFlag`** is a backed enum (`string`). Stored as a JSON array on the
  `Tenant` record. Serialized via Doctrine lifecycle hooks (`@PostLoad` /
  `@PrePersist`).

### CatalogSync: cursor tracking

`SyncJob` owns the cursor state. `SyncJob::recordPage(endCursor, hasNextPage)`
replaces `SyncCursor` each page and transitions to `COMPLETED` + raises
`SyncJobCompleted` when `hasNextPage` is false. The job can be resumed at any
point by reading `$syncJob->cursor()`.

`ProductFetcherInterface` is a domain service contract. `ShopifyProductFetcher`
implements it in Infrastructure via Shopify GraphQL Admin API (Relay Connection
spec, cursor-based pagination). The domain never touches HTTP.

Periodic sync is driven by `SyncSchedule` (Symfony Scheduler component).
Webhook-triggered syncs go through `HandleWebhookCommand`, which creates or
resumes a `SyncJob` for the affected collection.

### ImageAudit: composite auditor chain

`ImageAuditorInterface` is the domain contract. All technical auditors
(`MissingImageAuditor`, `FormatAuditor`, `SizeAuditor`) are tagged with
`app.image_auditor` and injected into `CompositeAuditor` via `!tagged_iterator`.
`CompositeAuditor` itself implements the interface — handlers only ever depend on
`ImageAuditorInterface`.

```yaml
# config/services.yaml
App\ImageAudit\Infrastructure\Auditor\CompositeAuditor:
    arguments:
        $auditors: !tagged_iterator { tag: app.image_auditor, default_priority_method: getPriority }
```

The AI auditor (`ClaudeImageAuditor`) is **not** in the composite chain. It is
invoked by a separate `RunAiAuditCommand`/`RunAiAuditHandler`, dispatched by
`RunAuditHandler` after the technical audit completes. `RunAiAuditHandler` checks
the `FeatureFlag::AI_IMAGE_AUDIT` flag before proceeding — if disabled for the
tenant, it returns early without error.

### Multitenancy

`TenantContextMiddleware` (Symfony Messenger middleware) resolves `TenantId` from
each incoming request (via `X-Shopify-Shop-Domain` header or JWT claim) and stores
it in a request-scoped service. Command and query handlers that need multi-tenant
scoping declare this service as a constructor dependency. No static globals.

`FeatureFlagResolver` accepts an optional `$systemWideFlags` array injected from
config — flags set here are enabled for all tenants without touching the database.

### CQRS via Symfony Messenger

Commands, queries, and domain events all flow through Symfony Messenger on
separate buses (or separate transports on the same bus). Handlers are
autowired. Repository `save()` flushes the entity manager and dispatches any
domain events collected via `pullDomainEvents()`.

---

## Domain rules / invariants

- A `Tenant`'s `shopDomain` must end with `.myshopify.com`. Enforced in
  `Tenant::create()`.
- Enabling an already-enabled `FeatureFlag` throws
  `FeatureAlreadyEnabledException`. Disabling an absent flag is a no-op
  (idempotent).
- `AuditFinding` is append-only. Once recorded on an `AuditReport`, findings are
  never mutated or deleted — only new findings can be appended.
- A `SyncJob` in `RUNNING` status cannot be started again. Starting a new sync for
  a collection while one is running should resume the existing job via its cursor.
- The AI audit step only runs if `FeatureFlag::AI_IMAGE_AUDIT` is enabled for the
  tenant. This is enforced at the application layer, not the domain layer.

---

## Cross-context event flow

```
[Shopify webhook]  ──►  HandleWebhookCommand
[Symfony Scheduler] ──►  StartSyncCommand
                              │
                        SyncJob::recordPage()
                              │ (on last page)
                        SyncJobCompleted raised
                              │
                        [Messenger event bus]
                              │
                        RunAuditCommand dispatched
                              │
                        CompositeAuditor chain runs
                        (Missing → Format → Size)
                              │
                        RunAiAuditCommand dispatched
                              │ (if FeatureFlag enabled)
                        ClaudeImageAuditor runs
                              │
                        AuditCompleted raised
```

---

## What is not yet built

- Shopify App UI (React / App Bridge) for toggling feature flags per tenant
- Report export (CSV / PDF)
- Notification layer (email / Slack when audit finds issues)
- Retry / dead-letter handling for failed sync jobs
- Webhook HMAC validation hardening (placeholder exists in `ShopifyWebhookValidator`)