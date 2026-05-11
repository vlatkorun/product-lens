# Skill: Update CLAUDE.md

## Purpose

This skill instructs you on how to surgically update `CLAUDE.md` (or any scoped rules file under `.claude/rules/`) after codebase changes, planning sessions, PR reviews, or newly discovered conventions — without bloating or destabilising the existing file.

---

## When to Apply This Skill

Trigger this skill when:

- A task, feature, or refactor has just completed and introduced new conventions, constraints, or architectural decisions
- A planning session produced decisions that should persist across sessions (tech choices, module boundaries, naming patterns)
- A PR review or code review revealed an undocumented pattern or rule
- You notice yourself repeating the same instruction to Claude across sessions
- An instruction already in `CLAUDE.md` is no longer accurate or has drifted from the codebase reality
- A new integration, dependency, or workflow has been added that Claude needs to be aware of

---

## Step 1 — Read the Current File First

Before writing anything, always read the full current state of the file:

```bash
cat CLAUDE.md
```

If scoped rules files exist, check those too:

```bash
ls .claude/rules/
cat .claude/rules/<relevant-file>.md
```

You must understand what is already documented before adding or changing anything. Never write blind.

---

## Step 2 — Identify the Right Section

After reading, determine where the new information belongs:

### Case A — A Relevant Section Already Exists

Update that section in-place. Do **not** create a duplicate or a new section with a slightly different heading. Merge the new information into the existing block, keeping the section concise.

Examples of existing sections you might update:

- `# Architecture` or `# Project Structure` → new module, new service, refactored boundaries
- `# Code Style` → new linting rule, naming convention, pattern preference
- `# Commands` or `# Workflow` → new script, changed test command, new CI step
- `# Integrations` or `# External Services` → new API, new PSP, new third-party dependency
- `# Constraints` or `# Do Not` → newly discovered anti-pattern or hard rule
- `# Database` → schema change, new migration pattern, new ORM convention

### Case B — No Relevant Section Exists

Create a new `## Section` with a clear, specific heading. Insert it in a logical position — group related concepts together. Do not append everything at the bottom blindly.

**New section heading guidelines:**

- Be specific: `## Checkout Flow` not `## Features`
- Use domain language from the codebase: `## Webhook Processing`, `## Tenant Isolation`, `## 3DS Authentication`
- Avoid generic headings that could swallow unrelated content over time

---

## Step 3 — Write the Update

Follow these rules when writing content:

### Keep it actionable and decision-level

Only include things that would cause Claude to make a **wrong decision** if omitted. Do not document obvious things or things Claude can infer from the code itself.

Good:

```
- All PSP adapters implement PaymentGatewayInterface — never call PSP SDKs directly from controllers
```

Bad:

```
- We use Stripe for payments
```

### Use bullets for rules, prose for context

Rules and constraints → bullet points.  
Architectural rationale or non-obvious context → one or two sentences of prose before the bullets.

### Use emphasis sparingly

Reserve `IMPORTANT:` or `YOU MUST` for rules that are genuinely critical and frequently violated. If everything is marked important, nothing is.

### Reference, don't duplicate

If the detail lives in another file, link to it rather than copying it:

```
See @docs/deployment.md for environment variable requirements.
```

---

## Step 4 — Prune as You Go

Every update is an opportunity to clean. While editing the relevant section, also:

- Remove instructions that no longer apply (deleted features, replaced dependencies, resolved workarounds)
- Consolidate two similar bullets into one if they express the same rule
- Clarify ambiguous phrasing that previously caused Claude to ask redundant questions
- Move highly specific or rarely-needed content to a scoped `.claude/rules/<domain>.md` file instead

**Size check:** After updating, ask yourself — is every remaining line one whose removal would cause Claude to make a mistake? If not, cut it.

---

## Step 5 — Validate the Result

After making the update:

1. Re-read the full file to confirm it reads coherently from top to bottom
2. Check that the updated section does not contradict another section
3. If the file has grown significantly, consider whether some content should move to a scoped rules file (`.claude/rules/`) or a dedicated skill (`SKILL.md`)
4. Optionally, ask Claude to review: _"Review this CLAUDE.md and flag anything obsolete, redundant, or ambiguous"_

---

## Common Section Reference

The following are common sections found in project `CLAUDE.md` files. Use them as a guide when creating new sections, but only include what is genuinely necessary:

| Section                | What belongs here                                          |
| ---------------------- | ---------------------------------------------------------- |
| `## Project Overview`  | One-liner description, tech stack, entry points            |
| `## Commands`          | Build, test, lint, dev server commands                     |
| `## Architecture`      | Module structure, layer boundaries, key patterns           |
| `## Code Style`        | Language-specific conventions not covered by linters       |
| `## Workflow`          | Git conventions, PR process, branch naming                 |
| `## Database`          | ORM patterns, migration conventions, naming                |
| `## External Services` | APIs, PSPs, third-party dependencies and their constraints |
| `## Testing`           | Test framework, coverage expectations, what to test        |
| `## Constraints`       | Hard rules — things Claude must never do                   |
| `## Environment`       | Env vars, secrets handling, local setup notes              |

---

## Gotchas

- **Do not auto-generate the full file** — always edit surgically. Regenerating from scratch loses accumulated knowledge.
- **Avoid instruction inflation** — each new rule competes with all existing rules. Research suggests LLMs begin ignoring all instructions uniformly as count increases, not just the newer ones.
- **Scoped rules beat global rules** — if a rule only applies to one part of the codebase (e.g. API layer, test files), put it in `.claude/rules/<domain>.md` with a `paths:` frontmatter filter rather than the root `CLAUDE.md`.
- **Do not document the obvious** — Claude knows what Symfony is. Do not explain the framework; document your project's specific decisions.
- **Stale rules are worse than no rules** — an incorrect instruction actively misleads. Remove outdated content immediately when it no longer applies.
