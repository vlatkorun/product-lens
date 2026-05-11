# ProductLens

ProductLens is a multitenant Shopify product audit platform. It periodically fetches
products from Shopify collections (or on webhook events), and audits each product
against the feature flags enabled for the tenant — such as image audit or AI image
audit. Each feature flag maps to a defined audit specification. Reports surface
non-conforming products so merchandising teams can act on them quickly.

## Technologies

- **PHP 8.4** / **Symfony 7.4**
- **Doctrine ORM** (PostgreSQL)
- **Symfony Messenger** — command bus, query bus, and event bus
- **RabbitMQ** — async transport for commands and domain events
- **Redis** — caching and session storage
- **Symfony Scheduler** — periodic sync jobs
- **Shopify GraphQL Admin API** — Relay Connection spec, cursor-based pagination
- **Claude API** (Anthropic) — AI image audit step
