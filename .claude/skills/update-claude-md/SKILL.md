# Skill: Update CLAUDE.md (Modular Monolith / DDD)

## Purpose

This skill instructs you on how to surgically update documentation context files after codebase changes, planning sessions, PR reviews, or newly discovered conventions.

The project is a **Symfony 7.4 modular monolith with DDD**. Each module is a bounded context and owns its own `CLAUDE.md`. There are therefore two tiers of context files:

| File                      | Scope                                                                                |
| ------------------------- | ------------------------------------------------------------------------------------ |
| `/CLAUDE.md`              | Project-wide: architecture, global conventions, cross-cutting concerns, module index |
| `/src/<Module>/CLAUDE.md` | Module-specific: domain model, flows, use cases, internal conventions                |

**The cardinal rule:** detail lives in the module. The root file holds summaries and cross-cutting concerns only.

---

## When to Apply This Skill

Trigger this skill when:

- A task, feature, or refactor has completed and introduced new domain behaviour, constraints, or architectural decisions
- A planning session produced decisions that should persist across sessions (tech choices, module boundaries, naming patterns, flow changes)
- A PR or code review revealed an undocumented convention or anti-pattern
- A new module (bounded context) has been created or an existing one has been renamed, merged, or split
- A cross-cutting concern has been introduced (shared kernel, new infrastructure service, new global convention)
- An instruction in either CLAUDE.md file is no longer accurate or has drifted from the codebase

---

## Step 1 — Classify the Change

Before reading any file, determine the nature of the change:

### Module-Scoped Change

The change lives inside a single bounded context. Examples:

- New domain event, aggregate, value object, or repository added to a module
- A use case or application service flow has changed within one module
- A new external integration is owned exclusively by one module (e.g. a PSP adapter inside `Payment`)
- Internal conventions specific to one module (naming, validation rules, state machine transitions)

→ **Detail goes into `/src/<Module>/CLAUDE.md`. A one-line summary goes into `/CLAUDE.md` under the module's entry.**

### Cross-Cutting Change

The change affects multiple modules, the shared kernel, global infrastructure, or project-wide conventions. Examples:

- A new shared interface, base class, or trait added to the shared kernel
- A new global Symfony convention (event subscriber pattern, middleware, serializer config)
- A change to how modules communicate (domain events, message bus, shared DTOs)
- Global code style, testing strategy, or workflow change
- A new environment variable, infrastructure service, or deployment concern
- A new module being added (creates a root entry + a new module CLAUDE.md)

→ **Goes directly into `/CLAUDE.md` in the appropriate section.**

---

## Step 2 — Read Before Writing

### For a module-scoped change, read both files:

```bash
cat CLAUDE.md
cat src/<Module>/CLAUDE.md
```

### For a cross-cutting change, read only the root:

```bash
cat CLAUDE.md
```

If a new module is being documented for the first time, check whether a `CLAUDE.md` already exists inside it:

```bash
ls src/<Module>/CLAUDE.md 2>/dev/null || echo "Does not exist yet"
```

You must understand what is already documented before adding or changing anything. Never write blind.

---

## Step 3 — Routing Decision

After classifying and reading, follow the correct path:

### Path A — Module-Scoped: Update `/src/<Module>/CLAUDE.md`

1. Find the section in the module's CLAUDE.md that covers this area.
2. If a relevant section exists → update it in-place. Do not create a duplicate section.
3. If no relevant section exists → create a new `## Section` with a domain-specific heading.
4. Then go to the root `/CLAUDE.md` and find the module's summary entry (see format below). Update the one-line summary only if the module's responsibility or key behaviour has materially changed.

**Module entry format in root CLAUDE.md:**

```markdown
## Modules

### Payment

Handles payment initiation, PSP routing, 3DS flows, and webhook reconciliation. See @src/Payment/CLAUDE.md.

### Order

Manages order lifecycle from creation to fulfilment. See @src/Order/CLAUDE.md.
```

The summary must be one to two sentences maximum. It describes _what_ the module owns, not _how_ it works. The how lives in the module's own file.

---

### Path B — Cross-Cutting: Update `/CLAUDE.md`

1. Find the section in the root CLAUDE.md that covers this area.
2. If a relevant section exists → update it in-place.
3. If no relevant section exists → create a new `## Section` and insert it in a logical position. Do not append blindly at the bottom.

**New section heading guidelines:**

- Be specific: `## Domain Event Bus` not `## Events`
- Use the project's own domain language
- Avoid headings generic enough to swallow unrelated content over time

---

### Path C — New Module

1. Create `/src/<NewModule>/CLAUDE.md` using the Module CLAUDE.md Template below.
2. Add a new entry for the module under `## Modules` in the root `/CLAUDE.md` with a one-to-two sentence summary and a `@src/<NewModule>/CLAUDE.md` reference.

---

## Step 4 — Writing Rules

Apply these rules regardless of which file you are editing:

### Actionable and decision-level only

Only include things that would cause Claude to make a **wrong decision** if omitted. Do not document what Claude can infer from the code.

Good (module CLAUDE.md):

```
- PaymentIntent is always created before redirecting to the PSP — never redirect with a raw amount
- Refunds are processed asynchronously via RefundRequested domain event, not inline
```

Bad (root CLAUDE.md):

```
- The Payment module uses Stripe
```

### Rules as bullets, context as prose

Non-obvious architectural rationale → one or two sentences of prose.
Constraints, conventions, and rules → bullet points.

### Emphasis sparingly

Reserve `IMPORTANT:` or `YOU MUST` for rules that are critical and genuinely likely to be violated. If everything is emphasised, nothing is.

### Reference, don't duplicate

If the detail already lives somewhere, link to it:

```
See @src/Payment/CLAUDE.md for PSP routing logic.
See @docs/adr/0012-domain-events.md for the event bus decision.
```

---

## Step 5 — Prune as You Go

Every update is an opportunity to clean. While editing, also:

- Remove instructions that no longer apply (deleted aggregates, replaced services, resolved workarounds)
- Consolidate two bullets that express the same rule
- Clarify phrasing that caused Claude to ask unnecessary questions
- If something in the root CLAUDE.md has grown too specific to one module, move it into that module's CLAUDE.md and replace it with a reference

**Size check (root CLAUDE.md):** Every line should be either a cross-cutting concern or a module summary. If it is neither, it does not belong here.

**Size check (module CLAUDE.md):** Every line should be something that would cause a wrong decision about this bounded context if missing. If not, cut it.

---

## Step 6 — Validate

After making the update:

1. Re-read the edited file top to bottom for coherence
2. Confirm no section contradicts another section in the same file
3. Confirm the root CLAUDE.md has not accumulated module-specific detail that belongs in a module file
4. Confirm the module CLAUDE.md has not accumulated cross-cutting rules that belong in the root

---

## Module CLAUDE.md Template

Use this structure when creating a new module's CLAUDE.md. Only include sections that are relevant — do not add empty sections.

```markdown
# <Module Name>

<One paragraph: what this bounded context owns, its core responsibility, and where it sits in the domain.>

## Domain Model

- **<Aggregate>** — <what it represents and its invariants>
- **<Value Object>** — <what it encapsulates>
- **<Domain Event>** — <when it is raised and who listens>

## Key Flows

### <Flow Name (e.g. Payment Initiation)>

<Short prose describing the happy path and any non-obvious branching.>

1. Step one
2. Step two
3. Step three

## Application Layer

- Commands and queries live in `Application/`
- <Any non-obvious conventions: handler naming, DTO structure, validation approach>

## Infrastructure

- <Repository implementations and their persistence strategy>
- <External service adapters owned by this module>
- <Any Symfony-specific wiring: tagged services, compiler passes, event subscribers>

## Constraints

- <Hard rules that must never be violated within this module>
- <Anti-patterns that have been explicitly rejected and why>

## Cross-Module Dependencies

- Receives: `<EventName>` from `<OtherModule>`
- Emits: `<EventName>` consumed by `<OtherModule>`
- IMPORTANT: never import from another module's domain layer directly — use shared kernel types or domain events only
```

---

## Root CLAUDE.md Sections Reference

| Section                         | What belongs here                                                                |
| ------------------------------- | -------------------------------------------------------------------------------- |
| `## Project Overview`           | Stack, architecture style, entry point                                           |
| `## Modules`                    | One-to-two sentence summary per bounded context + `@src/<Module>/CLAUDE.md` link |
| `## Shared Kernel`              | Shared interfaces, base classes, value objects used across modules               |
| `## Cross-Module Communication` | How modules talk: domain events, message bus, shared DTOs                        |
| `## Commands`                   | Build, test, lint, console commands                                              |
| `## Code Style`                 | Project-specific conventions not enforced by linters                             |
| `## Workflow`                   | Git conventions, PR process, branch naming                                       |
| `## Infrastructure`             | Global Symfony config, DI conventions, environment variables                     |
| `## Testing`                    | Test strategy, what to test per layer, framework used                            |
| `## Constraints`                | Project-wide hard rules — things Claude must never do anywhere                   |

---

## Gotchas

- **Never put flow or domain detail in the root CLAUDE.md** — one-to-two sentence summaries only. The detail belongs in the module.
- **Never duplicate between root and module** — if it is in the module's file, the root references it, not repeats it.
- **Module boundaries are hard** — if a change touches two modules, document it as a cross-cutting concern in the root and reference both module files.
- **Do not auto-regenerate** — always edit surgically. Regenerating from scratch destroys accumulated knowledge.
- **Stale rules actively mislead** — an outdated instruction is worse than no instruction. Remove it immediately when the underlying reality changes.
- **Instruction count matters** — the more instructions exist, the less reliably any single one is followed. Keep both files as short as possible.
