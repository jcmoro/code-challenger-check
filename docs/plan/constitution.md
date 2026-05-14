# Project Constitution — CHECK24 Car Insurance Comparison

> Foundational principles that govern every decision in this project.
> When in doubt, return to this document. If a rule no longer fits, change it explicitly via `replanning.md` — never silently.

---

## 1. Purpose

Deliver a working, production-grade reference implementation of a car insurance
quote comparison flow that demonstrates:

- Sound software architecture (separation of concerns, testability, scalability).
- Honest engineering practices (tests, static analysis, reproducible builds).
- Pragmatic judgment about scope, trade-offs, and complexity.

The reviewer should be able to *clone, run, and read* the project in under
fifteen minutes and reach a confident opinion about the author's seniority.

---

## 2. Core Principles

### 2.1 Correctness before cleverness
Pricing logic, sorting, discounts, and timeouts must be **provably correct**
before any optimisation, abstraction, or stylistic flourish. Every pricing
rule from the spec must be covered by a deterministic unit test.

### 2.2 Architecture for the imagined large system
The spec asks us to "imagine this will be a large project" with "requests of
>60 fields". We design accordingly:

- Domain logic lives in services, not controllers.
- Provider integrations are isolated behind a uniform port (interface) so a
  third provider can be added without touching `/calculate`.
- DTOs, value objects, and enums encode the domain — no stringly-typed data
  crossing module boundaries.
- Frontend mirrors this: components are dumb, composables/services own logic.

### 2.3 Boundaries are sacred
- The **backend** never assumes anything about the frontend layout.
- The **frontend** never replicates business rules from the backend (pricing,
  discount %, age brackets). It renders what the API returns.
- **Providers** are accessed through the same interface; the orchestrator
  knows nothing about JSON vs XML vs CSV.

### 2.4 Failure is a first-class case
External providers are flaky by design (10% errors, 1% extreme latency).
The system treats partial failure as expected behaviour:

- A failed/slow provider is excluded — never crashes the whole response.
- Errors are logged with context, never swallowed silently.
- The frontend surfaces partial and total failure clearly to the user.

### 2.5 Reproducibility via containers
Every component (backend, frontend, providers, tests, linters) runs inside
Docker. A reviewer with only Docker installed must be able to do
`make up && make test` and see a green build.

### 2.6 Tooling enforces quality
Manual discipline does not scale. Tests, static analysis, linting, and
formatting are wired into the Makefile and (ideally) a pre-commit step.
A red pipeline blocks merge; we do not bypass with `--no-verify`.

### 2.7 Documentation lives next to code
- `README.md` explains how to run.
- `docs/plan/*` captures the *thinking* (this constitution, the spec, plan).
- OpenAPI describes the HTTP surface.
- Inline comments only when the *why* is non-obvious — code should be the
  primary documentation.

### 2.8 No premature abstraction, no premature optimisation
We will resist:
- Building plugin systems for problems we do not have.
- Adding queues, caches, or DBs the spec does not require.
- Generic frameworks for two providers.

But we *will* keep seams (interfaces, DTOs, config) so the next person can
extend the system without rewriting it.

---

## 3. Non-Negotiables

| # | Rule | Rationale |
|---|------|-----------|
| 1 | All commands run inside Docker via `make`. | Reproducibility for the reviewer. |
| 2 | No business rule lives in a controller. | Testability, reuse. |
| 3 | Provider calls execute in parallel with a hard 10 s timeout each. | Spec requirement; UX. |
| 4 | A failing/slow provider is logged and dropped, never propagated as a 5xx. | Spec requirement; resilience. |
| 5 | Tests for: provider pricing, sort order, campaign discount, timeout handling. | Spec requirement. |
| 6 | Strict types end-to-end (PHP `declare(strict_types=1)`, TS `strict: true`). | Catch bugs at compile time. |
| 7 | Static analysis at the strictest level (PHPStan **level 10**, ESLint, tsc strict). | Catch bugs before runtime. |
| 8 | Frontend never hard-codes pricing/discount values. | Server is the single source of truth. |
| 9 | Form state survives reload, not tab close (`sessionStorage`). | Spec requirement. |
| 10 | The cheapest quote is visually highlighted; "No hay ofertas disponibles." is shown when both providers fail. | Spec requirement. |

---

## 4. Decision-Making Heuristics

When a choice arises that is not covered above:

1. **Ask: what would a senior reviewer flag?** Build for that audience.
2. **Smaller diff beats bigger diff** if both solve the problem.
3. **Boring tech beats novel tech** unless the novelty earns its keep.
4. **Delete the comment that explains *what*.** Keep the one that explains *why*.
5. **If a rule from this constitution is in the way, change the constitution explicitly** — do not silently violate it.

---

## 5. Out of Scope (by Constitutional Decision)

To keep the project focused, we **will not** include:

- User authentication / accounts.
- A persistent database for quotes (campaign flag may use config or a simple cache).
- Real payment or insurance issuance.
- Email/SMS notifications.
- i18n beyond the Spanish UI strings already implied by the design.
- A CI/CD pipeline beyond a local Makefile (CI config may be added if trivial).

If the reviewer requests any of these, they belong in `replanning.md`.

---

## 6. Amendments

This document is version-controlled. Any change requires:

1. A note in `replanning.md` explaining the trigger and the trade-off.
2. An update to dependent docs (`requirements.md`, `specification.md`, etc.).
3. A commit titled `constitution: <short reason>`.

---

*Adopted: 2026-05-13. Author: project owner.*
