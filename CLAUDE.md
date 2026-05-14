# ProductLens — CLAUDE.md

## Code Quality

Run static analysis via Make:
```bash
make analyse # alias for php vendor/bin/phpstan analyse --level=8
```

Run code style and formatting via Make:
```bash
make fix # alias for php vendor/bin/php-cs-fixer fix
```

## Tooling Configuration

**PHPStan:** level 8 minimum. No baseline file — all errors must be fixed, not
suppressed. Symfony and Doctrine extensions are active; PHPStan understands
container types, repository return types, and Doctrine nullable mappings.

**PHP CS Fixer:** `@Symfony` + `@Symfony:risky` + `@PHP84Migration` base rulesets,
with:
- `declare(strict_types=1)` enforced on all files
- `ordered_imports` alphabetically (`class` → `function` → `const`)
- No unused imports; no global namespace imports — always use fully qualified types
- `ordered_class_elements`: traits → constants → properties → constructor →
  public static → public → protected → private → magic
- PHPDoc: no superfluous tags, left-aligned, trimmed, ordered
- Trailing commas on multiline arrays, arguments, parameters, and `match`
- Native function/constant invocations use `\` prefix in namespaced files (perf)
- No Yoda conditions; post-increment style; `str_contains` over `strpos`
- Covers `src/`, `tests/`, and `migrations/`

**PHPUnit:** version 13. Configuration flags `failOnDeprecation`, `failOnNotice`,
and `failOnWarning` — deprecation notices from Symfony or Doctrine fail the suite.
Use constructor property promotion in test classes. Avoid `setUp()` where a data
provider or inline construction suffices.

## Testing

- **Test file location:** split by type under `tests/Unit/` and `tests/Integration/`,
  mirroring the `src/` structure beneath. For example:
  `src/Tenancy/Domain/Model/Tenant.php` → `tests/Unit/Tenancy/Domain/Model/TenantTest.php`
- **Namespaces:** `App\Tests\Unit\...` for unit tests,
  `App\Tests\Integration\...` for integration tests
- **Unit tests** cover domain aggregates, value objects, and domain services — no
  framework boot, no database, no HTTP. Construct objects directly; do not use the
  Symfony container.
- **Integration tests** cover repository implementations, Shopify HTTP clients,
  Messenger handlers, and the full OAuth flow. These boot the Symfony kernel and
  require a real database connection.
- **Each bounded context has its own tests.** Do not write cross-context assertions
  inside a context's own test file — cross-context event flow (e.g. `MonitoredCollectionSyncCompleted`
  triggering `RunAuditCommand`) belongs in `tests/Integration/`.
- **Use data providers** for value object validation (e.g. shop domain format rules,
  `FeatureFlag` enum mapping, `UserRole` hierarchy) — there are many input
  combinations to cover exhaustively.
- **Aggregate invariants** must each have a dedicated test: double-enable of a
  `FeatureFlag`, reinstall of an `Uninstalled` tenant, role/scope mismatch on
  `User::create()` vs `User::createForTenant()`, idempotent `grantTenantAccess()`.

Run tests via Make:
```bash
make test              # full test suite
make test-unit         # unit tests only
make test-integration  # integration tests only
```

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

The project is structured around four **bounded contexts**. Each owns its domain
model, application logic, and infrastructure adapters. Contexts communicate only
via domain events — never by importing each other's domain classes directly.
The only shared primitives live in `Shared/Domain/`.

### Bounded contexts

```
Identity       — user lifecycle, roles, tenant membership, authentication
Tenancy        — tenant lifecycle, feature flags, Shopify OAuth
CatalogSync    — product fetching, cursor tracking, webhook ingestion
Audit          — pipeline-and-specification audit engine, per-tenant feature-flag gated
```

**Identity** owns all authentication and authorisation concerns. It references
tenants by UUID only — no import of `Tenancy` domain classes.

**Tenancy** is a read-dependency for `CatalogSync` and `Audit`. It exposes
`TenantId` (shared) and `FeatureFlagResolver` (domain service). No other context
writes to Tenancy.

**CatalogSync → Audit** is the main flow. When a sync job completes (or a
product webhook arrives), `MonitoredCollectionSyncCompleted` is dispatched. Audit listens and
dispatches `RunAuditCommand` (event listener not yet built). The two contexts never import each other's namespaces. See @src/Audit/CLAUDE.md.

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
├── Identity/
│   ├── Domain/
│   │   ├── Model/User.php                       ← aggregate root; implements UserInterface
│   │   ├── Model/UserTenantAccess.php           ← Doctrine join entity for users_tenants (no domain logic)
│   │   ├── ValueObject/UserRole.php             ← backed enum: SuperAdmin, Admin, TenantAdmin, Tenant
│   │   ├── ValueObject/UserStatus.php           ← backed enum: Active, Inactive
│   │   ├── Repository/UserRepositoryInterface.php
│   │   ├── Event/UserCreated.php
│   │   ├── Exception/UserAlreadyExistsException.php
│   │   └── Exception/UserNotFoundException.php
│   └── Infrastructure/
│       ├── Doctrine/Type/
│       │   ├── UserRoleType.php                 ← maps PostgreSQL user_role enum
│       │   └── UserStatusType.php               ← maps PostgreSQL user_status enum
│       ├── Persistence/DoctrineUserRepository.php
│       └── Security/UserProvider.php            ← implements UserProviderInterface + PasswordUpgraderInterface
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
├── Catalog/
│   ├── Domain/
│   │   ├── Model/MonitoredCollection.php         ← aggregate root (table: tenant_monitored_collections)
│   │   ├── Model/MonitoredCollectionSync.php     ← aggregate root (table: tenant_monitored_collections_sync)
│   │   ├── Model/Product.php                     ← entity (read model)
│   │   ├── ValueObject/SyncCursor.php            ← wraps endCursor + hasNextPage
│   │   ├── ValueObject/SyncStatus.php            ← enum: PENDING|RUNNING|COMPLETED|FAILED
│   │   ├── Repository/{MonitoredCollectionSyncRepositoryInterface,ProductRepositoryInterface}.php
│   │   ├── Service/ProductFetcherInterface.php   ← domain service contract
│   │   └── Event/{MonitoredCollectionSyncCompleted,MonitoredCollectionSyncFailed,...}.php
│   ├── Application/
│   │   ├── Command/DispatchCollectionSyncBatch/{Command,Handler,ActiveTenantBatchClaimer,ActiveTenantBatchCriteriaDto,DispatchCollectionSyncBatchCriteriaDto}.php
│   │   ├── Command/ProcessTenantsCollectionsSync/{Command,Handler,TenantScopedMonitoredCollectionSyncClaimer,TenantCollectionSyncClaimCriteriaDto,ClaimedTenantCollectionSyncDto}.php
│   │   ├── Command/StartSync/{Command,Handler}.php
│   │   ├── Command/FetchNextPage/{Command,Handler}.php
│   │   ├── Command/HandleWebhook/{Command,Handler}.php
│   │   ├── Command/RescheduleStuckTenantsCollectionsSync/{Command,Handler,StuckCollectionSyncThresholdDto}.php
│   │   └── Query/GetSyncStatus/{Query,Handler}.php
│   └── Infrastructure/
│       ├── Shopify/ShopifyClient.php             ← GraphQL transport; holds HttpClient + SHOPIFY_API_VERSION
│       ├── Shopify/ShopifyProductFetcher.php     ← implements ProductFetcherInterface; maps DTOs → domain
│       ├── Shopify/GraphQL/Dto/{ImageDto,ProductNodeDto,PageInfoDto,...}.php  ← typed response DTOs
│       ├── Shopify/Webhook/{ShopifyWebhookController,ShopifyWebhookValidator}.php
│       ├── Persistence/{DoctrineMonitoredCollectionSyncRepository,DoctrineProductRepository}.php
│       └── Scheduler/CatalogImportSchedule.php    ← Symfony Scheduler
│
└── Audit/
    ├── Domain/
    │   ├── Specification/{SpecificationInterface,SpecificationResult,Issue}.php
    │   ├── Specification/ValueObject/Severity.php   ← enum: CRITICAL|WARNING|INFO
    │   ├── Pipeline/{AuditPipelineInterface,AuditPipelineResult}.php
    │   ├── Service/{AuditOrchestratorInterface,AuditPipelineResolverInterface}.php
    │   └── ValueObject/{AuditableProduct,ProductImage}.php
    ├── Application/
    │   └── Command/RunAudit/{Command,Handler}.php
    └── Infrastructure/
        ├── Pipeline/{ImageAuditPipeline,AIImageAuditPipeline}.php   ← tagged: app.audit_pipeline
        ├── Specification/Image/{ImageExists,ImageUrlReachable,ImageDimensions}Specification.php
        ├── AuditPipelineResolver.php             ← filters by FeatureFlag via AutowireIterator
        └── AuditOrchestrator.php                 ← stateless; takes pipelines as parameter
```

---

## Key patterns

### Aggregates and value objects

- **Private constructors** on all aggregates. Creation only through named static
  factory methods (`Tenant::create()`, `MonitoredCollectionSync::schedule()`).
- **`AggregateRoot::raise()`** collects domain events internally.
  `pullDomainEvents()` is called by the repository after `save()` so events are
  dispatched transactionally.
- **Value objects are immutable.** Mutation returns a new instance. `SyncCursor`
  is replaced wholesale on each page (`MonitoredCollectionSync::recordPage()`), never mutated.
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
| `user_role`        | `Identity/Infrastructure/Doctrine/Type/UserRoleType`      | `user_role` (PG enum) |
| `user_status`      | `Identity/Infrastructure/Doctrine/Type/UserStatusType`    | `user_status` (PG enum) |

### Encrypted columns

Shopify API credentials (`shopify_access_token`, `shopify_webhook_secret`) are
encrypted at rest using `sodium_crypto_secretbox` (XSalsa20-Poly1305). A fresh
random nonce is generated per write; the stored value is `base64url(nonce ‖ ciphertext)`.

Key is loaded from `APP_ENCRYPTION_KEY` env var — a 64-character hex string (32 bytes).
Generate with: `php -r "echo sodium_bin2hex(random_bytes(32)), PHP_EOL;"`

`APP_ENCRYPTION_KEY` is kept separate from `APP_SECRET` so the DB encryption key
can be rotated independently of Symfony's framework secret (CSRF, cookies, sessions).

### CatalogSync: cursor tracking

`MonitoredCollectionSync` owns the cursor state. `MonitoredCollectionSync::recordPage(endCursor, hasNextPage)`
replaces `SyncCursor` each page and transitions to `COMPLETED` + raises
`MonitoredCollectionSyncCompleted` when `hasNextPage` is false. The job can be resumed at any
point by reading `$sync->cursor()`.

`ProductFetcherInterface` is a domain service contract. `ShopifyProductFetcher`
implements it in Infrastructure via `ShopifyClient` (which wraps `HttpClientInterface`
and the versioned Shopify GraphQL Admin API). Responses are parsed through typed DTOs
before being mapped to domain objects. The domain never touches HTTP or raw arrays.

Periodic sync is driven by `CatalogImportSchedule` (Symfony Scheduler component).
Webhook-triggered syncs go through `HandleWebhookCommand`, which creates or
resumes a `MonitoredCollectionSync` for the affected collection.

### Audit: pipeline-and-specification engine

Each audit run passes an `AuditableProduct` through a resolved set of `AuditPipelineInterface` implementations. Pipelines declare a `requiredFeatureFlag()` — `AuditPipelineResolver` filters them against the tenant's active flags before the orchestrator runs them. Each pipeline holds a prioritised set of `SpecificationInterface` implementations injected via `#[AutowireIterator]`. See @src/Audit/CLAUDE.md for the full model and extension points.

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
`SHOPIFY_OAUTH_REDIRECT_URI`, `SHOPIFY_WEBHOOK_SECRET`, `SHOPIFY_API_VERSION` (used by
`ShopifyClient` in CatalogSync; current value `2024-10`).

**Access control**: `/shopify/install` and `/shopify/callback` are guarded with
`ROLE_TENANT_ADMIN` in `security.yaml`. Only a Tenant Admin user may initiate or
complete the Shopify OAuth flow for their tenant.

### Identity: users and roles

`User` is the aggregate root of the `Identity` context. It implements Symfony's
`UserInterface` and `PasswordAuthenticatedUserInterface` — the framework's security
layer integrates directly with the domain model (same pragmatic approach as Doctrine
attributes on aggregates).

**Role hierarchy** (configured in `security.yaml`):

```
ROLE_SUPER_ADMIN  ⊃  ROLE_ADMIN  ⊃  ROLE_TENANT_ADMIN  ⊃  ROLE_TENANT
```

Each role maps to a `UserRole` backed enum value:

| Enum case    | DB value      | Symfony role        | Scope                          |
|--------------|---------------|---------------------|--------------------------------|
| `SuperAdmin` | `super_admin` | `ROLE_SUPER_ADMIN`  | Full access to everything      |
| `Admin`      | `admin`       | `ROLE_ADMIN`        | Restricted global access       |
| `TenantAdmin`| `tenant_admin`| `ROLE_TENANT_ADMIN` | Full access within their tenant|
| `Tenant`     | `tenant`      | `ROLE_TENANT`       | Restricted access within tenant|

**Factory methods** enforce the global / tenant-scoped split at construction time:

- `User::create(email, password, role, now)` — for `SuperAdmin` / `Admin`. Throws
  if a tenant-scoped role is passed.
- `User::createForTenant(email, password, role, tenantId, now)` — for `TenantAdmin`
  / `Tenant`. Throws if a global role is passed. Calls `grantTenantAccess()` internally.

**Tenant membership** (`users_tenants` table) is a pure join table with two columns:
`user_id` and `tenant_id` (composite PK). It carries no role and no timestamps —
the role lives on the `User` aggregate, not on the membership row. `UserTenantAccess`
is a thin Doctrine entity that maps this table; it has no domain logic and should
not be used outside of `User`'s own methods.

Domain methods for managing membership:

```php
$user->grantTenantAccess(UuidV7 $tenantId): void   // idempotent
$user->revokeTenantAccess(UuidV7 $tenantId): void
$user->hasTenantAccess(UuidV7 $tenantId): bool
$user->tenantIds(): UuidV7[]
```

Cross-context reference: `tenant_id` in `users_tenants` is a plain UUID with a
DB-level FK to `tenants.id ON DELETE CASCADE`. No Doctrine ORM association to
`Tenant` — Identity never imports Tenancy domain classes.

**`UserProvider`** (`Infrastructure/Security/UserProvider.php`) implements
`UserProviderInterface` and `PasswordUpgraderInterface`. It loads users by email
and is registered as `app_user_provider` in `security.yaml`.

### Multitenancy

`TenantContextMiddleware` (Symfony Messenger middleware) resolves `TenantId` from
each incoming request (via `X-Shopify-Shop-Domain` header or JWT claim) and stores
it in a request-scoped service. Command and query handlers that need multi-tenant
scoping declare this service as a constructor dependency. No static globals.

`FeatureFlagResolver` accepts an optional `$systemWideFlags` array injected from
config — flags set here are enabled for all tenants without touching the database.

### DTOs

Data Transfer Objects used in the application layer (command namespaces, query results) must have class names ending with the `Dto` suffix — e.g. `ClaimedMonitoredCollectionSyncsDto`, not `ClaimedMonitoredCollectionSyncs`. Infrastructure DTOs (Shopify GraphQL response objects) follow the same rule. This applies across all bounded contexts.

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
final readonly class MonitoredCollectionSyncCompleted implements AsyncDomainEvent { ... }
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
- A `MonitoredCollectionSync` in `RUNNING` status cannot be started again. Starting a new sync for
  a collection while one is running should resume the existing job via its cursor.
- The AI audit step only runs if `FeatureFlag::AI_IMAGE_AUDIT` is enabled for the
  tenant. This is enforced at the application layer, not the domain layer.
- `User::create()` rejects tenant-scoped roles (`TenantAdmin`, `Tenant`); use
  `User::createForTenant()` instead. The reverse guard applies symmetrically.
- `User::grantTenantAccess()` is idempotent — calling it with an already-held
  `tenantId` is a no-op.
- A user has a single `UserRole` that applies across all their tenant memberships.
  Per-tenant role differentiation is not supported.

---

## Cross-context event flow

```
[Shopify webhook]   ──►  HandleWebhookCommand
[Symfony Scheduler] ──►  DispatchCollectionSyncBatchCommand
                              │
                        ActiveTenantBatchClaimer::claim()
                              │ (up to 100 active tenants, keyset-paginated, self-dispatching)
                        ProcessTenantsCollectionsSyncCommand dispatched per tenant batch
                              │
                        TenantScopedMonitoredCollectionSyncClaimer::claim()
                              │ (up to 100 collections per tenant batch, keyset-paginated, self-dispatching)
                        StartSyncCommand dispatched per claimed collection
                              │
                        MonitoredCollectionSync::recordPage()
                              │ (on last page)
                        MonitoredCollectionSyncCompleted raised
                              │
                        [Messenger event bus]
                              │
                        RunAuditCommand dispatched (listener not yet built)
                              │
                        AuditPipelineResolver::resolve(featureFlags)
                              │ (filters to enabled pipelines)
                        AuditOrchestrator::orchestrate(product, pipelines)
                              │ (per enabled pipeline)
                        Pipeline runs tagged specifications in priority order
                              │
                        list<AuditPipelineResult> returned
                              │ (AuditReport persistence not yet built)
                        AuditCompleted raised (not yet built)
```

---

## What is not yet built

**Identity**
- Application layer: `CreateUser`, `AssignToTenant`, `DeactivateUser` commands;
  `GetUser` query
- `TenantVoter` — Symfony Voter checking `$user->hasTenantAccess($tenantId)` for
  object-level tenant access control
- Authentication mechanism not yet chosen (form_login + session vs JSON login + JWT)

**Tenancy**
- Application layer: `CreateTenant` command/handler, `GetTenant` query/handler
- Infrastructure: `TenantContextMiddleware`, `FeatureFlagResolver`
- Post-OAuth shop metadata fetch (populate `name`, `email`, `currencyCode`, etc. via
  Shopify Admin API after `CompleteOAuth` succeeds)
- Tenant runtime isolation: Doctrine SQL filter + PostgreSQL Row Level Security

**CatalogSync**
- Unit and integration tests for all commands and handlers
- `MonitoredCollectionSyncClaimer` integration test (requires real DB + `FOR UPDATE SKIP LOCKED` verification)

**Audit** — `AuditReport` aggregate + persistence; event listener on `MonitoredCollectionSyncCompleted`; AI image specifications; `AuditCompleted` event; query layer. See @src/Audit/CLAUDE.md.

**General**
- Shopify App UI (React / App Bridge) for toggling feature flags per tenant
- Report export (CSV / PDF)
- Notification layer (email / Slack when audit finds issues)
- Retry / dead-letter handling for failed sync jobs