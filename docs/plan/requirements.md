# Requirements — CHECK24 Car Insurance Comparison

> Source of truth for what the system must do. Derived from the official PDF
> (`docs/Technical_case_semotor[18].pdf`) plus explicit additions noted under
> *Project-level additions*.

---

## 1. Glossary

| Term | Meaning |
|------|---------|
| **Provider** | An external simulated insurer (A, B, optionally C). |
| **Quote** | A normalized price offer for a customer + car combination. |
| **Campaign** | A discount programme covering 5% of the price (customer pays 95%). |
| **Calculation** | The `/calculate` orchestration that fans out to providers and aggregates results. |
| **Driver** | The person whose age/birthday determines pricing. |

---

## 2. Functional Requirements — Backend

### FR-B-1 — Provider A (JSON)
- **Endpoint:** `POST /provider-a/quote`
- **Request body (JSON):** `{ "driver_age": int, "car_form": "suv"|"compact", "car_use": "private"|"commercial" }`
- **Response (JSON):** `{ "price": "<int> EUR" }`
- **Latency:** ~2 s (must `sleep(2)`).
- **Reliability:** 10% of calls return HTTP 500.
- **Pricing:**
  - Base: 217 €.
  - Age: 18–24 → +70 €; 25–55 → +0 €; 56+ → +90 €.
  - Vehicle: SUV → +100 €; Compact → +10 €.
  - Commercial use → +15% of the running total.
- **Mapping note:** "Turismo" and "Compacto" from the user → `compact`. "SUV" → `suv`.

### FR-B-2 — Provider B (XML)
- **Endpoint:** `POST /provider-b/quote`
- **Request body (XML):** `<SolicitudCotizacion><EdadConductor/><TipoCoche/><UsoCoche/></SolicitudCotizacion>`. `TipoCoche` ∈ {`turismo`, `suv`, `compacto`}. `UsoCoche` ∈ {`privado`, `comercial`}.
- **Response (XML):** `<RespuestaCotizacion><Precio>310.0</Precio><Moneda>EUR</Moneda></RespuestaCotizacion>`.
- **Latency:** ~5 s (must `sleep(5)`); 1% of calls take 60 s.
- **Pricing:**
  - Base: 250 €.
  - Age: 18–29 → +50 €; 30–59 → +20 €; 60+ → +100 €.
  - Vehicle: Turismo → +30 €; SUV → +200 €; Compacto → +0 €.
  - *(The PDF does not specify commercial uplift for B. We treat commercial use as no additional adjustment for B and document this assumption in `specification.md`.)*

### FR-B-3 — Calculate Endpoint
- **Endpoint:** `POST /calculate`
- **Request (JSON):** `{ "driver_birthday": "YYYY-MM-DD", "car_type": "Turismo"|"SUV"|"Compacto", "car_use": "Privado"|"Commercial" }`
- **Behaviour:**
  1. Compute `driver_age` from `driver_birthday`.
  2. Fan out to all configured providers **in parallel**.
  3. Enforce a **10 s per-provider timeout**; drop late responses.
  4. Drop providers that error (HTTP ≠ 2xx, network error, parse error).
  5. Apply 5% campaign discount (when active) to every surviving quote.
  6. Sort surviving quotes by **final price ascending**.
  7. Return aggregated, normalized response. *Even if zero providers survived,
     return 200 with an empty quotes list and a `meta` block — the frontend
     handles the user-facing "No hay ofertas disponibles." message.*

- **Response shape (proposed; finalised in `specification.md`):**
  ```json
  {
    "campaign_active": true,
    "quotes": [
      {"provider": "provider-a", "price": 295.0, "discounted_price": 280.25, "currency": "EUR"},
      {"provider": "provider-b", "price": 310.0, "discounted_price": 294.50, "currency": "EUR"}
    ],
    "meta": {
      "failed_providers": [],
      "duration_ms": 5123
    }
  }
  ```

### FR-B-4 — Campaign Toggle
The campaign on/off state must be configurable without redeploying code.
Decision (locked in `specification.md`): **environment variable `CAMPAIGN_ACTIVE=true|false`** with optional override via a config file. Rationale: simplest reliable mechanism within the timebox; the abstraction (a `CampaignProvider` interface) keeps the door open for a DB-backed feature flag later.

### FR-B-5 — Senior Bonus: Third Provider (Provider C, CSV)
- **Endpoint:** `POST /provider-c/quote`
- **Request body:** CSV line `driver_age,car_form,car_use`.
- **Response body:** CSV line `price,currency`.
- **Latency:** ~1 s; 5% return HTTP 503.
- **Pricing:** documented in `specification.md`.

### FR-B-6 — OpenAPI documentation
A machine-readable OpenAPI 3.1 document (and a Swagger UI page) must
describe every public endpoint of the calculate API.

### FR-B-7 — Logging
Each request to `/calculate` produces one structured log line at INFO with:
request id, durations per provider, outcome per provider (ok/failed/timeout),
campaign state. Errors from providers log at WARNING with stack/cause.

---

## 3. Functional Requirements — Frontend

### FR-F-1 — Input form fields
- `driver_birthday` — HTML date input, required, must be a real date,
  not in the future, and not before 1900-01-01. Implied driver age must
  be ≥ 18 and ≤ 120.
- `car_type` — select; options `Turismo`, `SUV`, `Compacto`. Required.
- `car_use` — radio; options `Privado`, `Comercial`. Required.

### FR-F-2 — Submission
- POST to `/calculate` with the JSON payload (per spec).
- During the request: submit button disabled, loading indicator shown.
- On network or HTTP 4xx/5xx error: a user-friendly Spanish error message
  appears; the form remains editable.

### FR-F-3 — Form persistence
- Values survive a **page reload** (F5).
- Values are **cleared** when the browser tab/window is closed
  (implementation: `sessionStorage`).

### FR-F-4 — Result rendering
- Quotes table, hand-coded (no UI table library), with columns:
  Provider | Precio (EUR) | Precio con descuento (EUR) | Nota.
- Default sort: price ascending. A toggle switches asc/desc.
- The cheapest quote is visually highlighted (badge, bold, or icon — final
  choice in `specification.md`).
- If zero quotes survive: show `"No hay ofertas disponibles."`.
- When `campaign_active` is true: a banner / badge informs the user that
  CHECK24 is covering 5%.

### FR-F-5 — Senior Bonus: Three-page wizard
The input form is split across three pages, one question per page, with
Back/Continue navigation, lateral iOS-style transitions, and responsive
layout (mobile / tablet / desktop).

---

## 4. Non-Functional Requirements

### NFR-1 — Performance / UX
- The whole `/calculate` round trip completes in ≤ ~7 s under normal
  conditions (B's 5 s sleep is the floor). Frontend handles up to 10 s.
- The UI must remain responsive (no blocking of the main thread) during
  the wait.

### NFR-2 — Reliability
- Provider failures never produce a 5xx from `/calculate`.
- Provider timeouts (> 10 s) never block the response.

### NFR-3 — Security
- Strict input validation on the backend (DTO + validator).
- No secrets in the repo; `.env.example` is committed, `.env` is not.
- CORS is restricted to the configured frontend origin.

### NFR-4 — Testability
- Pricing rules are pure functions, fully unit-tested with table-driven cases.
- HTTP-layer tests use Symfony's `WebTestCase` (or equivalent) with a
  mocked HTTP client.
- Frontend components are tested with Vitest + Vue Test Utils.

### NFR-5 — Maintainability
- PHPStan level 8 (or `max`) green.
- ESLint + Prettier green; TypeScript strict.
- No dead code, no `// TODO` left without an issue.

---

## 5. Project-Level Additions (beyond the PDF)

These are **explicit project requirements added by the project owner**.
They are mandatory and have the same weight as PDF requirements.

### PR-1 — Docker everywhere
Every runtime and dev-time component runs in a container.
- `docker/php.Dockerfile` — Symfony app + PHP-FPM.
- `docker/nginx.Dockerfile` — reverse proxy / static asset host.
- `docker/node.Dockerfile` — Vue build & dev server.
- `docker-compose.yml` — orchestrates `backend`, `frontend`, `nginx`,
  and (optionally) `mailcatcher`/`redis` if and only if needed.

The reviewer's host needs **only Docker + Make** to run the project.

### PR-2 — Makefile is the entry point
A single `Makefile` exposes targets that fully abstract the docker-compose
invocations. Required targets (minimum):

| Target | Purpose |
|--------|---------|
| `make help` | Print available targets with one-line descriptions. |
| `make build` | Build all images. |
| `make up` | Start the full stack in the foreground. |
| `make up-d` | Start the full stack detached. |
| `make down` | Stop the stack. |
| `make logs` | Tail logs from all services. |
| `make shell-backend` | Open a shell into the backend container. |
| `make shell-frontend` | Open a shell into the frontend container. |
| `make install` | Install backend (composer) + frontend (npm) deps. |
| `make test` | Run **all** tests (backend + frontend). |
| `make test-backend` | Run only backend tests. |
| `make test-frontend` | Run only frontend tests. |
| `make lint` | Run all static analyzers / linters / formatters in check mode. |
| `make fix` | Run formatters in write mode (php-cs-fixer, eslint --fix, prettier). |
| `make stan` | PHPStan only. |
| `make typecheck` | TypeScript `tsc --noEmit` only. |
| `make clean` | Remove caches, build artefacts, volumes (with a confirmation prompt). |

No target may require the user to `cd` into a sub-directory first.

### PR-3 — Static code analysis
- **Backend:** PHPStan at the highest practical level (target: 8 / max),
  PHP-CS-Fixer with `@Symfony` + `@PER-CS` rulesets.
- **Frontend:** ESLint (Vue 3 + TypeScript recommended configs),
  Prettier, `tsc --noEmit` in strict mode.
- All run via `make lint` and must be green for the build to be considered done.

### PR-4 — Automated tests
- **Backend:** PHPUnit 11+, configured to run inside the container with
  coverage output (text + html), targeted minimum coverage on `src/Domain`
  and `src/Application` of ≥ 85%.
- **Frontend:** Vitest, Vue Test Utils, jsdom; coverage via `c8` /
  Vitest built-in, target ≥ 75% on `src/components` and `src/composables`.
- Tests run via `make test` and produce a single consolidated pass/fail.

---

## 6. Requirement Traceability Matrix (high-level)

| Req ID | PDF section | Verified in |
|--------|-------------|-------------|
| FR-B-1 | 1.2 (Provider A) | `validation.md` §Backend / pricing tests |
| FR-B-2 | 1.2 (Provider B) | `validation.md` §Backend / pricing tests |
| FR-B-3 | 1.3 (Calculation) | `validation.md` §Integration tests |
| FR-B-4 | 1.4 (Campaign rule) | `validation.md` §Backend / discount tests |
| FR-B-5 | Senior bonus | `validation.md` §Backend / third provider |
| FR-B-6 | Senior bonus | manual review of `/api/doc` |
| FR-B-7 | Senior bonus | `validation.md` §Observability |
| FR-F-1..5 | 2.x | `validation.md` §Frontend |
| PR-1..4 | Project-added | `validation.md` §Tooling |

---

*Adopted: 2026-05-13. Changes go through `replanning.md`.*
