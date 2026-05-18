# Audit — Context

Audits products fetched by CatalogSync against a configurable set of rules. Each audit run is driven by a `RunAuditCommand` carrying the product data and the tenant's active audit checks. Results flow through a pipeline-and-specification model: each enabled pipeline runs its specifications in priority order and collects `SpecificationResult` objects. Persistence to an `AuditReport` aggregate is not yet implemented.

---

## Bounded context rules

- Imports from `Shared\Domain\` are allowed (`AuditCheck`, `UuidV7`).
- Never import from `CatalogSync`, `Tenancy`, `Identity`, or any other bounded context's domain classes.
- `AuditableProduct` is Audit's own view of a product — never inject or reference `CatalogSync\Domain\Model\Product` directly.
- Cross-context communication is inbound only: `MonitoredCollectionSyncCompleted` (async event from CatalogSync) triggers dispatch of `RunAuditCommand`.

---

## Domain model

### Value objects (`Domain/ValueObject/`)

| Class | Purpose |
|---|---|
| `AuditableProduct` | The subject under audit: `productId`, `tenantId`, `title`, `list<ProductImage>` |
| `ProductImage` | Single image: `url`, `altText`, `?width`, `?height` — mirrors the shape stored by CatalogSync |

### Specification (`Domain/Specification/`)

| Class | Purpose |
|---|---|
| `SpecificationInterface` | `isSatisfiedBy(AuditableProduct): SpecificationResult` |
| `SpecificationResult` | Immutable result: `specificationName`, `passed/failed`, `?Issue`, `?Severity`. Constructed via `::pass(name)` / `::fail(name, issue, severity)` |
| `Issue` | Value object: `code` + `message`. Constructed via `Issue::of(code, message)` |
| `Severity` | Enum: `CRITICAL`, `WARNING`, `INFO` |

### Pipeline (`Domain/Pipeline/`)

| Class | Purpose |
|---|---|
| `AuditPipelineInterface` | `name(): string`, `requiredFeatureFlag(): ?AuditCheck`, `run(AuditableProduct): AuditPipelineResult` |
| `AuditPipelineResult` | Wraps `pipelineName` + `list<SpecificationResult>`; exposes `passed()` and `failures()` |

### Domain services (`Domain/Service/`)

| Interface | Purpose |
|---|---|
| `AuditPipelineResolverInterface` | `resolve(list<AuditCheck>): list<AuditPipelineInterface>` — filters all registered pipelines to those whose `requiredFeatureFlag` is in the provided list (or `null`) |
| `AuditOrchestratorInterface` | `orchestrate(AuditableProduct, list<AuditPipelineInterface>): list<AuditPipelineResult>` — runs each pipeline and collects results |

---

## Key flow

```
RunAuditCommand (productId, tenantId, productTitle, images[], auditChecks[])
    │
RunAuditHandler
    ├── builds AuditableProduct from command data
    ├── AuditPipelineResolver::resolve(auditChecks) → list<AuditPipelineInterface>
    │       filters all app.audit_pipeline-tagged services:
    │       keeps pipelines where requiredFeatureFlag ∈ auditChecks (or null)
    └── AuditOrchestrator::orchestrate(product, pipelines) → list<AuditPipelineResult>
            │ per pipeline:
            └── pipeline->run(product)
                    │ per specification (in priority order):
                    └── spec->isSatisfiedBy(product) → SpecificationResult
```

Results are currently not persisted — `AuditReport` aggregate and repository are pending.

---

## Application layer

- `Command/RunAudit/RunAuditCommand` — carries all product data inline (`productId`, `tenantId`, `productTitle`, raw `images[]` array, `auditChecks[]`). The handler hydrates `AuditableProduct` from this data so the command serialises cleanly for async Messenger dispatch.
- `Command/RunAudit/RunAuditHandler` — resolves pipelines, orchestrates, discards results until `AuditReport` persistence is added.

---

## Infrastructure

### Pipelines (`Infrastructure/Pipeline/`)

| Class | Tag | Required flag |
|---|---|---|
| `ImageAuditPipeline` | `app.audit_pipeline` | `AuditCheck::ImageAudit` |
| `AIImageAuditPipeline` | `app.audit_pipeline` | `AuditCheck::AiImageAudit` |

Pipelines receive their specifications via `#[AutowireIterator('<tag>')]`. `AIImageAuditPipeline` has no specifications wired yet — add them under tag `app.audit_specification.ai_image`.

### Specifications (`Infrastructure/Specification/Image/`)

All tagged `app.audit_specification.image`. Priority controls execution order within the pipeline — higher runs first.

| Class | Priority | Issue code | Severity |
|---|---|---|---|
| `ImageExistsSpecification` | 100 | `IMAGES_MISSING` | CRITICAL |
| `ImageUrlReachableSpecification` | 50 | `IMAGE_URL_UNREACHABLE` | WARNING |
| `ImageDimensionsSpecification` | 25 | `IMAGE_DIMENSIONS_TOO_SMALL` | WARNING |

`ImageDimensionsSpecification` defaults to 800×800px minimum, configured via `services.yaml` `$minWidth`/`$minHeight` arguments.

### `AuditPipelineResolver`

Receives all `app.audit_pipeline`-tagged services via `#[AutowireIterator('app.audit_pipeline')]`. Filters by comparing each pipeline's `requiredFeatureFlag()` against the provided `AuditCheck[]` list using strict `in_array`.

### `AuditOrchestrator`

Stateless — takes resolved pipelines as a method parameter, never holds them as state. Each call is independent.

---

## Adding a new pipeline

1. Implement `AuditPipelineInterface`.
2. Add `#[AutoconfigureTag('app.audit_pipeline')]` to the class.
3. Choose a new specification tag, e.g. `app.audit_specification.price`.
4. Inject via `#[AutowireIterator('app.audit_specification.price')]`.

## Adding a new specification

1. Implement `SpecificationInterface`.
2. Add `#[AutoconfigureTag('app.audit_specification.<pipeline>', attributes: ['priority' => N])]`.
3. Return `SpecificationResult::pass(self::class)` or `SpecificationResult::fail(self::class, Issue::of(...), Severity::X)`.
4. Pure specs (no I/O) may live in `Domain/Specification/`. I/O-bound specs belong in `Infrastructure/Specification/`.

---

## Constraints

- `AuditCheck` filtering happens in `AuditPipelineResolver`, not inside the orchestrator or individual pipelines. The orchestrator is ignorant of tenancy.
- Specifications return a single `SpecificationResult` per call — one pass or the first failure encountered. They do not return per-image results.
- `SpecificationResult::specificationName` is set to `self::class` by convention in all specification implementations.
- `ImageUrlReachableSpecification` makes HTTP HEAD requests — mock `HttpClientInterface` in unit tests.

---

## What is not yet built

- Event listener on `MonitoredCollectionSyncCompleted` that fetches products from CatalogSync and dispatches one `RunAuditCommand` per product.
- `AuditReport` aggregate and `AuditReportRepositoryInterface` — results are not yet persisted.
- AI image specifications under `app.audit_specification.ai_image` (Claude API integration).
- `AuditCompleted` domain event.
- Query layer: `GetAuditReport`.
