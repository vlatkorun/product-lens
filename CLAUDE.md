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
│   ├── Domain/
│   │   ├── Model/AggregateRoot.php              ← base class: raise(), pullDomainEvents()
│   │   ├── Event/DomainEvent.php                ← marker interface (sync dispatch)
│   │   ├── Event/AsyncDomainEvent.php           ← marker interface (async dispatch via Messenger)
│   │   ├── ValueObject/TenantId.php             ← planned
│   │   └── Clock/ClockInterface.php             ← planned
│   └── Infrastructure/
│       ├── Doctrine/Type/
│       │   ├── EncryptedStringType.php          ← sodium-encrypted TEXT columns
│       │   └── JsonbType.php                    ← JSONB column type
│       └── Event/
│           └── DomainEventPublisher.php         ← routes sync/async events after repository save()
│
├── Tenancy/
│   ├── Domain/
│   │   ├── Model/Tenant.php                     ← aggregate root
│   │   ├── ValueObject/FeatureFlag.php           ← backed enum: ImageAudit, AiImageAudit
│   │   ├── ValueObject/TenantStatus.php          ← backed enum: Active, Suspended, Uninstalled
│   │   ├── ValueObject/ShopifyTokenResult.php    ← access_token + scope from OAuth token exchange
│   │   ├── Repository/TenantRepositoryInterface.php
│   │   ├── Event/TenantCreated.php
│   │   ├── Event/TenantReinstalled.php           ← raised when an Uninstalled tenant re-installs
│   │   ├── Event/{FeatureEnabled,FeatureDisabled}.php  ← planned
│   │   ├── Service/OAuth/OAuthStateStoreInterface.php
│   │   ├── Service/OAuth/ShopifyOAuthClientInterface.php
│   │   ├── Service/OAuth/ShopifyHmacValidatorInterface.php
│   │   ├── Service/FeatureFlagResolver.php       ← planned
│   │   ├── Exception/FeatureAlreadyEnabledException.php
│   │   ├── Exception/InvalidOAuthCallbackException.php
│   │   └── Exception/TenantNotFoundException.php  ← planned
│   ├── Application/
│   │   ├── Command/OAuth/BeginOAuth/{Command,Handler}.php
│   │   ├── Command/OAuth/CompleteOAuth/{Command,Handler}.php
│   │   ├── Command/CreateTenant/{Command,Handler}.php  ← planned
│   │   └── Query/GetTenant/{Query,Handler}.php         ← planned
│   └── Infrastructure/
│       ├── Doctrine/Type/TenantStatusType.php   ← maps PostgreSQL tenant_status enum
│       ├── Http/ShopifyOAuthController.php       ← GET /shopify/install, GET /shopify/callback
│       ├── Persistence/DoctrineTenantRepository.php
│       ├── Shopify/ShopifyOAuthClient.php        ← implements ShopifyOAuthClientInterface
│       ├── Shopify/ShopifyHmacValidator.php      ← implements ShopifyHmacValidatorInterface
│       ├── Symfony/OAuthStateStore.php           ← implements OAuthStateStoreInterface via cache.app
│       └── Symfony/TenantContextMiddleware.php   ← planned
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
- **`FeatureFlag`** is a backed enum (`string`). Current cases: `ImageAudit`,
  `AiImageAudit`. Stored as a JSONB array on the `Tenant` record. Serialized via
  Doctrine lifecycle hooks (`PostLoad` / `PrePersist` / `PreUpdate`) — the mapped
  property `$featureFlagsRaw` holds `string[]`; the transient `$featureFlags` holds
  `FeatureFlag[]` and is the one used in domain logic.

### IDs

All aggregate roots use **UUIDv7** (`Symfony\Component\Uid\UuidV7`) as their
primary key. IDs are **app-assigned** in the factory method (`$entity->id = new UuidV7()`)
using `#[ORM\GeneratedValue(strategy: 'NONE')]` — the aggregate knows its own ID
from birth, which allows domain events to carry the ID before the first `flush()`.

### Doctrine mapping

Each bounded context registers its own mapping block in `config/packages/doctrine.yaml`
pointing to its `Domain/Model/` directory. The default `src/Entity/` Symfony scaffolding
is not used.

```yaml
# config/packages/doctrine.yaml
orm:
  mappings:
    Tenancy:
      type: attribute
      dir: '%kernel.project_dir%/src/Tenancy/Domain/Model'
      prefix: 'App\Tenancy\Domain\Model'
```

**DBAL 4 custom types** — DBAL 4 dropped comment-based type disambiguation. Any
PostgreSQL-specific column type needs a custom Doctrine type class + a `mapping_types`
entry so the schema introspector can round-trip correctly. Current custom types:

| Doctrine type      | Class                                      | SQL type        |
|--------------------|--------------------------------------------|-----------------|
| `encrypted_string` | `Shared/Infrastructure/Doctrine/Type/EncryptedStringType` | `TEXT` |
| `jsonb`            | `Shared/Infrastructure/Doctrine/Type/JsonbType`           | `JSONB` |
| `tenant_status`    | `Tenancy/Infrastructure/Doctrine/Type/TenantStatusType`   | `tenant_status` (PG enum) |

### Encrypted columns

Shopify API credentials (`shopify_access_token`, `shopify_webhook_secret`) are
encrypted at rest using `sodium_crypto_secretbox` (XSalsa20-Poly1305). A fresh
random nonce is generated per write; the stored value is `base64url(nonce ‖ ciphertext)`.

Key is loaded from `APP_ENCRYPTION_KEY` env var — a 64-character hex string (32 bytes).
Generate with: `php -r "echo sodium_bin2hex(random_bytes(32)), PHP_EOL;"`

`APP_ENCRYPTION_KEY` is kept separate from `APP_SECRET` so the DB encryption key
can be rotated independently of Symfony's framework secret (CSRF, cookies, sessions).

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

### Shopify OAuth flow

Merchant installation runs the standard OAuth 2.0 authorization code grant.
Two routes live in `ShopifyOAuthController`:

| Route | Handler | Responsibility |
|---|---|---|
| `GET /shopify/install?shop=` | `BeginOAuthCommand` | Validate domain, generate nonce, store in cache, redirect to Shopify |
| `GET /shopify/callback` | `CompleteOAuthCommand` | Verify HMAC, consume nonce, exchange code, upsert Tenant |

**BeginOAuth** (`Application/Command/OAuth/BeginOAuth/`):
- Generates a 16-byte random `state` nonce via `OAuthStateStoreInterface::store()`.
- Returns the Shopify authorization URL (handler returns `string`; controller gets it
  via Messenger's `HandleTrait`).

**CompleteOAuth** (`Application/Command/OAuth/CompleteOAuth/`):
1. `ShopifyHmacValidatorInterface::validate()` — HMAC-SHA256 over sorted query params
   with `%` → `%25` and `&` → `%26` escaping per Shopify spec.
2. `OAuthStateStoreInterface::consume()` — retrieves and deletes the nonce; throws
   `InvalidOAuthCallbackException` if expired or unknown.
3. `ShopifyOAuthClientInterface::exchangeCodeForToken()` — POSTs to
   `https://{shop}/admin/oauth/access_token`; returns `ShopifyTokenResult`.
4. Upserts `Tenant`: creates new via `Tenant::create()`, or calls `Tenant::reinstall()`
   if previously `Uninstalled` (which raises `TenantReinstalled`).
5. `Tenant::storeCredentials(accessToken, webhookSecret, scope)` — encrypted at rest.

**State storage** (`OAuthStateStore`): PSR-6 `cache.app` (Redis in production),
10-minute TTL. Atomic get-and-delete on `consume()`.

**Invariants**: `InvalidOAuthCallbackException` is thrown (→ HTTP 400) on HMAC
mismatch, expired/unknown state, or shop domain mismatch between the callback and
the stored nonce.

**Required env vars**: `SHOPIFY_API_KEY`, `SHOPIFY_API_SECRET`, `SHOPIFY_OAUTH_SCOPES`,
`SHOPIFY_OAUTH_REDIRECT_URI`, `SHOPIFY_WEBHOOK_SECRET`.

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
autowired via `#[AsMessageHandler]`.

### Domain event dispatch: sync vs async

After every `repository->save()`, domain events collected by `pullDomainEvents()`
are handed to `DomainEventPublisher` (`Shared/Infrastructure/Event/`), which routes
them based on the event's type:

- Implements only `DomainEvent` → dispatched via **Symfony EventDispatcher** (in-process,
  synchronous, handled immediately in the same request/worker turn).
- Implements `AsyncDomainEvent extends DomainEvent` → dispatched via **Symfony Messenger**
  and routed to the `async` RabbitMQ transport via `messenger.yaml`.

```php
// sync — handled inline by an EventDispatcher listener
final readonly class TenantCreated implements DomainEvent { ... }

// async — pushed to RabbitMQ, consumed by a Messenger worker
final readonly class SyncJobCompleted implements AsyncDomainEvent { ... }
```

```yaml
# config/packages/messenger.yaml
routing:
    'App\Shared\Domain\Event\AsyncDomainEvent': async
```

This keeps the choice of sync vs async at the event definition level and out of
every repository and handler.

---

## Domain rules / invariants

- A `Tenant`'s `shopDomain` must end with `.myshopify.com`. Enforced in
  `Tenant::create()`.
- Enabling an already-enabled `FeatureFlag` throws `FeatureAlreadyEnabledException`.
  Disabling an absent flag is a no-op (idempotent).
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

- Tenancy application layer: `CreateTenant` command/handler, `GetTenant` query/handler
- Tenancy infrastructure: `TenantContextMiddleware`, `FeatureFlagResolver`
- Post-OAuth shop metadata fetch (populate `name`, `email`, `currencyCode`, etc. via
  Shopify Admin API after `CompleteOAuth` succeeds)
- Tenant runtime isolation: Doctrine SQL filter + PostgreSQL Row Level Security
- CatalogSync bounded context (all of it)
- ImageAudit bounded context (all of it)
- Shopify App UI (React / App Bridge) for toggling feature flags per tenant
- Report export (CSV / PDF)
- Notification layer (email / Slack when audit finds issues)
- Retry / dead-letter handling for failed sync jobs