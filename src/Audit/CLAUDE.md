# Audit — Context

Audits Shopify objects fetched by CatalogSync against a configurable set of rules. Each audit run is driven by a per-object command (currently `ProductAuditCommand`, future `OrderAuditCommand`) carrying the subject and the tenant's active audit checks. Results flow through a pipeline-and-specification model: each enabled pipeline runs its specifications in priority order and collects `SpecificationResult` objects. Persistence to an `AuditReport` aggregate is not yet implemented.

---

## Bounded context rules

- Imports from `Shared\Domain\` are allowed (`AuditCheck`, `ShopifyGid`, `UuidV7`).
- Never import from `CatalogSync`, `Tenancy`, `Identity`, or any other bounded context's domain classes.
- `AuditableProduct` is Audit's own view of a product — never inject or reference `CatalogSync\Domain\Model\Product` directly.
- Cross-context communication is inbound only: `MonitoredCollectionSyncCompleted` (async event from CatalogSync) triggers dispatch of `ProductAuditCommand`.

---

## Domain model

### Value objects (`Domain/ValueObject/`)

Audit subjects identify by `ShopifyGid` (from `App\Shared\Domain\ValueObject\`), not by internal `UuidV7`. The subject is fundamentally a Shopify entity; the same GID flows in from upstream webhook payloads and CatalogSync events without any mapping step.

**One class per subject, no separate DTO.** `AuditableProduct` (and siblings) are used end-to-end — as the canonical domain value object consumed by specifications, and as the payload carried directly on `ProductAuditCommand` across the Messenger boundary. Symfony's default `PhpSerializer` transports the nested VO graph (`ShopifyGid`, `UuidV7`, `list<ProductImage>`) transparently. Constructor invariants run once on the producer side; `unserialize()` on the consumer side restores property state without re-running constructors, which is normal.

| Class | Purpose |
|---|---|
| `AuditableObject` | Abstract base: `gid: ShopifyGid`, `tenantId: UuidV7`. Common identity for every audit subject type. |
| `AuditableProduct` | Product subject: extends `AuditableObject` with `title`, `list<ProductImage>` |
| `AuditableProductVariant` | Variant subject: extends `AuditableObject` with `productGid: ShopifyGid`, `title`, `?ProductImage` |
| `AuditableOrder` | Order subject placeholder: extends `AuditableObject` (no additional fields yet) |
| `ProductImage` | Single image: `url`, `altText`, `?width`, `?height` — mirrors the shape stored by CatalogSync |

### Specification (`Domain/Specification/`)

Specifications are typed per audit subject. `ProductSpecificationInterface` is the only one defined today; future subjects get their own (`OrderSpecificationInterface`, etc.) rather than a generic `AuditableObject`-typed interface — this preserves compile-time type guarantees inside specs.

| Class | Purpose |
|---|---|
| `ProductSpecificationInterface` | `isSatisfiedBy(AuditableProduct): SpecificationResult` |
| `SpecificationResult` | Immutable result: `specificationName`, `passed/failed`, `?Issue`, `?Severity`. Constructed via `::pass(name)` / `::fail(name, issue, severity)` |
| `Issue` | Value object: `code` + `message`. Constructed via `Issue::of(code, message)` |
| `Severity` | Enum: `CRITICAL`, `WARNING`, `INFO` |

### Pipeline (`Domain/Pipeline/`)

| Class | Purpose |
|---|---|
| `AuditPipelineInterface` | `name(): string`, `requiredFeatureFlag(): ?AuditCheck`, `run(AuditableObject): AuditPipelineResult`. Implementations narrow the subject type at entry (e.g. `instanceof AuditableProduct`). |
| `AuditPipelineResolverInterface` | `resolve(list<AuditCheck>): list<AuditPipelineInterface>` — filters registered pipelines by `requiredFeatureFlag`. Per-subject implementations autowire a subject-scoped tag (e.g. `app.audit_pipeline.product`). |
| `AuditPipelineResult` | Wraps `pipelineName` + `list<SpecificationResult>`; exposes `passed()` and `failures()` |

### Orchestrator (`Domain/Orchestrator/`)

| Interface | Purpose |
|---|---|
| `AuditOrchestratorInterface` | `orchestrate(AuditableObject, list<AuditPipelineInterface>): list<AuditPipelineResult>` — runs each pipeline and collects results. Stateless; one implementation per subject type. |

---

## Key flow

```
ProductAuditCommand (AuditableProduct $product, list<AuditCheck> $auditChecks)   ← async via RabbitMQ
    │
ProductAuditHandler
    ├── ProductAuditPipelineResolver::resolve(auditChecks) → list<AuditPipelineInterface>
    │       filters all app.audit_pipeline.product-tagged services:
    │       keeps pipelines where requiredFeatureFlag ∈ auditChecks (or null)
    └── ProductAuditOrchestrator::orchestrate(product, pipelines) → list<AuditPipelineResult>
            │ per pipeline:
            └── pipeline->run(product)
                    │ per specification (in priority order):
                    └── spec->isSatisfiedBy(product) → SpecificationResult
```

Results are currently not persisted — `AuditReport` aggregate and repository are pending.

---

## Application layer

Per Shopify object type, each audit run has its own command + handler pair under `Application/Command/Audit/{Subject}/`. Today only `Product` exists; `Order` will follow the same shape.

- `Command/Audit/Product/ProductAuditCommand` — readonly, carries `AuditableProduct $product` and `list<AuditCheck> $auditChecks`. Routed to the `async` transport in `messenger.yaml`.
- `Command/Audit/Product/ProductAuditHandler` — `#[AsMessageHandler]`. Two-line `__invoke`: resolves pipelines via `AuditPipelineResolverInterface`, runs `AuditOrchestratorInterface`. Discards results until `AuditReport` persistence is added.

The interface typehints in the handler resolve via plain Symfony autowiring (one impl per interface today). When `OrderAuditHandler` lands, switch each handler's params to `#[Autowire(service: ...)]` to disambiguate.

---

## Infrastructure

### Pipelines (`Infrastructure/Pipeline/{Subject}/`)

Per-subject pipelines tag themselves with `app.audit_pipeline.{subject}`. The matching `{Subject}AuditPipelineResolver` autowires that tag.

| Class | Tag | Required flag |
|---|---|---|
| `ImageAuditPipeline` | `app.audit_pipeline.product` | `AuditCheck::ImageAudit` |
| `AIImageAuditPipeline` | `app.audit_pipeline.product` | `AuditCheck::AiImageAudit` |

Pipelines receive their specifications via `#[AutowireIterator('<tag>')]`. `AIImageAuditPipeline` has no specifications wired yet — add them under tag `app.audit_specification.ai_image`.

### Specifications (`Infrastructure/Specification/Image/`)

All tagged `app.audit_specification.image`. Priority controls execution order within the pipeline — higher runs first.

| Class | Priority | Issue code | Severity |
|---|---|---|---|
| `ImageExistsSpecification` | 100 | `IMAGES_MISSING` | CRITICAL |
| `ImageUrlReachableSpecification` | 50 | `IMAGE_URL_UNREACHABLE` | WARNING |
| `ImageDimensionsSpecification` | 25 | `IMAGE_DIMENSIONS_TOO_SMALL` | WARNING |

`ImageDimensionsSpecification` defaults to 800×800px minimum, configured via `services.yaml` `$minWidth`/`$minHeight` arguments.

### `ProductAuditPipelineResolver` (`Infrastructure/Pipeline/Product/`)

Receives all `app.audit_pipeline.product`-tagged services via `#[AutowireIterator('app.audit_pipeline.product')]`. Filters by comparing each pipeline's `requiredFeatureFlag()` against the provided `AuditCheck[]` list using strict `in_array`.

### `ProductAuditOrchestrator` (`Infrastructure/Orchestrator/Product/`)

Stateless — takes resolved pipelines as a method parameter, never holds them as state. Each call is independent. Logically identical to what a generic orchestrator would be; the separate class exists so DI can bind one orchestrator per subject when more arrive.

---

## Adding a new subject type (e.g. Order)

1. Add `AuditableOrder` (already exists as a placeholder) and any subject-specific fields.
2. Add `OrderSpecificationInterface` under `Domain/Specification/`.
3. Add `OrderAuditCommand` + `OrderAuditHandler` under `Application/Command/Audit/Order/`. Route the command to `async` in `messenger.yaml`.
4. Add `OrderAuditPipelineResolver` (autowire `app.audit_pipeline.order`) and `OrderAuditOrchestrator` under `Infrastructure/Pipeline/Order/` and `Infrastructure/Orchestrator/Order/`.
5. In both handlers, replace plain interface typehints with `#[Autowire(service: …)]` to pick the correct resolver/orchestrator per subject.

## Adding a new pipeline

1. Implement `AuditPipelineInterface`.
2. Add `#[AutoconfigureTag('app.audit_pipeline.{subject}')]` to the class — match the subject type the pipeline runs against.
3. Choose a new specification tag, e.g. `app.audit_specification.price`.
4. Inject via `#[AutowireIterator('app.audit_specification.price')]`.

## Adding a new specification

1. Implement the subject's specification interface (e.g. `ProductSpecificationInterface`).
2. Add `#[AutoconfigureTag('app.audit_specification.<pipeline>', attributes: ['priority' => N])]`.
3. Return `SpecificationResult::pass(self::class)` or `SpecificationResult::fail(self::class, Issue::of(...), Severity::X)`.
4. Pure specs (no I/O) may live in `Domain/Specification/`. I/O-bound specs belong in `Infrastructure/Specification/`.

---

## Constraints

- `AuditCheck` filtering happens in `ProductAuditPipelineResolver` (per-subject resolver), not inside the orchestrator or individual pipelines. The orchestrator is ignorant of tenancy.
- Specifications return a single `SpecificationResult` per call — one pass or the first failure encountered. They do not return per-image results.
- `SpecificationResult::specificationName` is set to `self::class` by convention in all specification implementations.
- `ImageUrlReachableSpecification` makes HTTP HEAD requests — mock `HttpClientInterface` in unit tests.

---

## What is not yet built

- Event listener on `MonitoredCollectionSyncCompleted` that fetches products from CatalogSync and dispatches one `ProductAuditCommand` per product.
- `AuditReport` aggregate and `AuditReportRepositoryInterface` — results are not yet persisted.
- AI image specifications under `app.audit_specification.ai_image` (Claude API integration).
- `AuditCompleted` domain event.
- Query layer: `GetAuditReport`.
- `OrderAuditCommand` / `OrderAuditHandler` + matching `Order`-scoped resolver, orchestrator, and pipelines.
