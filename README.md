# CHECK24 Car Insurance Comparison

A small fullstack application that compares car-insurance quotes from multiple
simulated providers. Built as a code challenge for the CHECK24 fullstack role.

- **Backend** — Symfony 7.3 on PHP 8.4. Three mock provider endpoints
  (JSON / XML / CSV) plus a `/calculate` orchestrator that fans out in parallel
  with a hard 10 s timeout, applies a configurable campaign discount, and sorts
  by final price. OpenAPI documentation, JSON structured logging, and a
  per-request `request_id` for log correlation.
- **Frontend** — Vue 3 + TypeScript + Vite SPA with two entry points: a
  single-page form on `/` and an iOS-style 3-step wizard on `/wizard`. Form
  state survives reload via `sessionStorage`. Results table is hand-coded
  (no UI library), with cheapest-row highlight and asc/desc sort toggle.
- **Runtime** — everything runs in Docker; a single `Makefile` is the
  entry point for every developer task.

The full plan, requirements, specification, implementation order, validation
strategy and decision log live in [`docs/plan/`](./docs/plan/).

---

## Prerequisites

Only **Docker** (with Compose v2) and **GNU Make**. No local PHP, Composer
or Node installation is needed — every command runs inside a container.

| Default port | Service                 | Override via      |
| -----------: | ----------------------- | ----------------- |
|       `8080` | nginx → Symfony API     | `NGINX_HOST_PORT` |
|       `5173` | Vite dev server (Vue 3) | `VITE_HOST_PORT`  |

---

## Quickstart

```bash
cp .env.example .env

make build         # build all images
make install       # composer install + npm install (inside containers)
make up-d          # start the stack in the background
make test          # run all 215 tests (113 backend + 102 frontend)
make lint          # PHPStan level 10 + PHP-CS-Fixer + ESLint + Prettier + vue-tsc
make coverage      # PHPUnit Clover + Vitest LCOV reports
make sonar         # upload analysis to SonarCloud (needs SONAR_TOKEN)
```

Then open in a browser:

| URL                                  | What                                                  |
| ------------------------------------ | ----------------------------------------------------- |
| http://localhost:5173                | Single-page form (default)                            |
| http://localhost:5173/wizard         | 3-step iOS-style wizard with slide transitions        |
| http://localhost:8080/calculate      | `POST` JSON endpoint (the API the SPA calls)          |
| http://localhost:8080/api/doc        | Swagger UI                                            |
| http://localhost:8080/api/doc.json   | Raw OpenAPI 3.0 spec                                  |

---

## Screenshot

Filled form, active campaign banner, hand-coded results table with the cheapest
quote highlighted. `provider-a` is missing from this run — it hit the simulated
10 % failure (the backend dropped it from the response and emitted a JSON log
line; the UI shows the surviving providers only). Wizard and responsive
viewports follow the same layout primitives.

![Single-page form and results](docs/_assets/screenshot-home.png)

---

## Make targets

The seven workflows a reviewer needs:

| Target          | Purpose                                                                |
| --------------- | ---------------------------------------------------------------------- |
| `make build`    | Build the three Docker images (backend, frontend, nginx)               |
| `make install`  | `composer install` + `npm ci`                                          |
| `make up-d`     | Start the full stack (detached)                                        |
| `make test`     | Run all tests (113 PHPUnit + 102 Vitest)                               |
| `make lint`     | PHPStan L10 + PHP-CS-Fixer + ESLint + Prettier + vue-tsc               |
| `make coverage` | Generate Clover + LCOV reports for `make sonar` (needs `SONAR_TOKEN`)  |
| `make help`     | Live, colour-coded menu of every target (auto-generated, source of truth) |

Run `make help` for the full list (bootstrap, shells, fix-mode, clean, etc.).

---

## Project layout

```
.
├── Makefile
├── docker-compose.yml
├── docker-compose.override.yml
├── .env.example
├── sonar-project.properties # SonarCloud monorepo config + rule overrides
├── docker/                  # Dockerfiles + service configs
│   ├── php/                 # PHP 8.4-fpm-alpine with required extensions + pcov
│   ├── nginx/               # 1.27-alpine fronting PHP-FPM
│   └── node/                # Node 20-alpine running Vite dev
├── docs/
│   ├── Technical_case_semotor[18].pdf
│   └── plan/                # Six planning documents — see below
├── backend/                 # Symfony 7.3 / PHP 8.4
│   ├── src/
│   │   ├── Domain/          # Pure value objects (DriverAge, Money, Quote, …)
│   │   ├── Application/     # Use cases (CalculateQuoteHandler, …)
│   │   ├── Infrastructure/  # Adapters: Provider{A,B,C}{Client,PricingService,Simulator}, System services
│   │   └── UI/Http/         # Controller + DTO + Response/CalculateQuoteResponseFactory + listeners
│   ├── config/
│   └── tests/
└── frontend/                # Vue 3 + TypeScript + Vite
    ├── src/
    │   ├── api/             # fetch wrapper + typed /calculate call
    │   ├── domain/          # TS mirrors of the backend's JSON contract
    │   ├── composables/     # useFormState (sessionStorage), useCalculate, useSort
    │   ├── components/      # form/, results/, feedback/, wizard/
    │   ├── pages/           # HomePage + wizard/{Step1…Step3, WizardResult}
    │   ├── router/          # vue-router with iOS slide-direction tracking
    │   └── i18n/            # Spanish strings
    └── tests/
```

---

## Planning documents

Six markdown documents in [`docs/plan/`](./docs/plan/) capture the
"thinking" the spec asks for. Each is self-contained:

1. [`constitution.md`](docs/plan/constitution.md) — principles, non-negotiables, decision heuristics, explicit out-of-scope items.
2. [`requirements.md`](docs/plan/requirements.md) — functional + non-functional requirements derived from the PDF, plus project-level additions (Docker, Makefile, static analysis, automated tests). Includes a traceability matrix.
3. [`specification.md`](docs/plan/specification.md) — detailed contracts: API request/response shapes, vocabulary mapping table (user-facing ↔ each provider), error semantics, frontend component structure.
4. [`implementation.md`](docs/plan/implementation.md) — eight phased build plan with exit criteria, technology choices (locked), repo layout, Makefile sketch, and effort estimate.
5. [`validation.md`](docs/plan/validation.md) — five-layer validation strategy (tooling → unit → integration → end-to-end → reviewer walk-through), exhaustive pricing test tables, binary acceptance criteria.
6. [`replanning.md`](docs/plan/replanning.md) — the change log: 35 entries documenting every decision that drifted from the original plan, with trigger / change / impact / cost / risk for each.

---

## How the system behaves

### `POST /calculate` — the customer's call

```json
{
  "driver_birthday": "1992-02-24",
  "car_type":        "Turismo",
  "car_use":         "Privado"
}
```

The handler:

1. Computes age from birthday using an injected `Clock` (deterministic in tests).
2. Reads the campaign state from `CampaignProvider` (env-backed by default).
3. Fans out to **all** registered `QuoteProvider`s in parallel via
   `HttpClient::stream()` with a hard **10 s** per-request timeout.
4. Failed providers (5xx, transport error, parse error, timeout) are
   **dropped** — their ids land in `meta.failed_providers`; the response
   stays at 200.
5. Applies the 5% campaign discount (when active) to each surviving quote.
6. Sorts ascending by final price, ties broken by provider id.
7. Marks the single cheapest quote with `is_cheapest: true`.

Response (campaign active):

```json
{
  "campaign": { "active": true, "percentage": 5.0 },
  "quotes": [
    {
      "provider": "provider-c",
      "price":            { "amount": 210.0,  "currency": "EUR" },
      "discounted_price": { "amount": 199.5,  "currency": "EUR" },
      "is_cheapest": true
    },
    {
      "provider": "provider-a",
      "price":            { "amount": 317.0,  "currency": "EUR" },
      "discounted_price": { "amount": 301.15, "currency": "EUR" },
      "is_cheapest": false
    }
  ],
  "meta": { "duration_ms": 5132, "failed_providers": ["provider-b"] }
}
```

Validation errors come back as `400` with a uniform envelope, regardless of
whether the violation was caught by Symfony's `MapRequestPayload` or thrown
from the domain (`UnderageDriverException` for age < 18, future birthday, …):

```json
{
  "error": "validation_failed",
  "violations": [
    { "field": "driver_birthday", "message": "Driver must be at least 18 years old, got 17." }
  ]
}
```

### The three providers (simulated)

| Provider     | Format | Latency | Failure mode                   |
| ------------ | ------ | ------- | ------------------------------ |
| `provider-a` | JSON   | 2 s     | HTTP 500 on 10 % of calls      |
| `provider-b` | XML    | 5 s     | 1 % of calls add a 55 s spike  |
| `provider-c` | CSV    | 1 s     | HTTP 503 on 5 % of calls       |

Latency and randomness are injected via `Clock` and `RandomnessProvider`
interfaces so tests run instantly and deterministically.

### Structured logs

`make logs` shows one JSON line per `/calculate` on the `calculate` channel:

```json
{
  "message": "calculate_completed",
  "context": {
    "request_id": "3006038b733f7ab0",
    "duration_ms": 5028,
    "campaign_active": true,
    "campaign_percentage": 5.0,
    "quotes_count": 2,
    "failed_providers": ["provider-a"],
    "providers": {
      "provider-c": { "outcome": "ok",     "duration_ms": 1824 },
      "provider-a": { "outcome": "failed", "duration_ms": 2021 },
      "provider-b": { "outcome": "ok",     "duration_ms": 5026 }
    }
  },
  "level_name": "INFO",
  "channel": "calculate"
}
```

---

## Troubleshooting

Resolved problems with root cause + prevention live in
[`docs/operations/troubleshooting.md`](docs/operations/troubleshooting.md)
as numbered `PROB-NNN` entries.

---

## Submission deliverables checklist

Per the challenge's submission requirements:

- [x] Full source code of backend and frontend.
- [x] Automated tests for backend pricing, comparison/sort, campaign discount.
- [x] Automated tests for frontend components and composables.
- [x] Planning documents in `docs/plan/`.
- [x] Senior bonuses: parallel fetch, OpenAPI, robust error handling,
      structured logging, third provider (CSV), Docker setup, 3-page
      wizard with iOS-style transitions, responsive layout.
