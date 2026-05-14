# Implementation Plan — CHECK24 Car Insurance Comparison

> *How* we will build what `specification.md` describes. Ordered, opinionated,
> dependency-aware. A reviewer should be able to follow these phases top-to-bottom.

---

## 0. Ground Rules

1. Work in **vertical slices**: each phase ends with something that runs and is
   tested, not with a half-wired layer.
2. Tests are written alongside (or before) the code they cover. No "we'll add
   tests at the end" phase.
3. The Makefile target for whatever phase produced must be green before moving on.
4. Commits are small, scoped, and titled `<area>: <what>` (e.g.
   `backend(pricing): add Provider A pricing service`).

---

## 1. Technology Choices (locked)

| Concern | Choice | Reason |
|---------|--------|--------|
| Backend framework | Symfony 7.3 | Spec mandates Symfony; 7.3 resolves cleanly (7.1 transitives flagged by composer-audit). |
| PHP runtime | 8.4 | Required by PHPUnit 13 (latest); Symfony 7.3 fully supports it. |
| HTTP client | `symfony/http-client` | Native multiplexed parallel `stream()` — perfect for provider fan-out. |
| API docs | `nelmio/api-doc-bundle` | Industry standard, auto-derives schemas. |
| Testing (BE) | PHPUnit 11 | Symfony-default; mature. |
| Static analysis | PHPStan + `phpstan-symfony` | Level 8/max. |
| Code style | PHP-CS-Fixer (`@Symfony`, `@PER-CS`) | Idiomatic Symfony. |
| Logging | Monolog (JSON formatter to stderr) | Container-friendly. |
| Frontend framework | Vue 3 (`<script setup>`) | Spec mandates Vue. |
| Build tool | Vite 5 | Fast dev server + bundler. |
| Language | TypeScript strict | Catch type bugs cheaply. |
| Router (senior wizard) | `vue-router` 4 | Standard. |
| HTTP | Native `fetch` wrapped in `api/client.ts` | Avoid axios bloat for a small SPA. |
| State | Composables + `sessionStorage` | No Pinia — overkill here. |
| Testing (FE) | Vitest + `@vue/test-utils` + jsdom | Vite-native, fast. |
| Lint/format (FE) | ESLint + Prettier + `vue-tsc --noEmit` | Stack standard. |
| Container runtime | Docker + Docker Compose v2 | Required by `requirements.md`. |
| Process manager | PHP-FPM behind nginx | Closest to production-like. |

Deliberately **avoided**: Doctrine ORM (no DB needed), Pinia (no shared state),
Axios (`fetch` is enough), Tailwind/MUI (spec discourages heavy styling),
ApiPlatform (too much magic for a code-review project).

---

## 2. Repository Layout

```
code-challenger-check24/
├── Makefile
├── docker-compose.yml
├── docker-compose.override.yml         (dev-only mounts; ignored in prod-like runs)
├── .env.example
├── .gitignore
├── .editorconfig
├── README.md
├── docs/
│   ├── Technical_case_semotor[18].pdf
│   └── plan/                            (these planning docs)
├── docker/
│   ├── php/Dockerfile
│   ├── php/php.ini
│   ├── nginx/Dockerfile
│   ├── nginx/default.conf
│   └── node/Dockerfile
├── backend/                             (Symfony app — see specification §3)
└── frontend/                            (Vue app — see specification §4)
```

---

## 3. Phased Plan

### Phase 0 — Bootstrap (½ day)

**Goal:** `make up` starts an empty backend + frontend + nginx; `make test` runs and is green (no tests yet, but commands exist).

Tasks
1. Initialise root files: `Makefile`, `.gitignore`, `.editorconfig`, `README.md`.
2. Write `docker-compose.yml` with services: `backend`, `frontend`, `nginx`.
3. Author the three Dockerfiles (PHP 8.4-fpm, Node 20, nginx alpine).
4. Bootstrap Symfony skeleton: `composer create-project symfony/skeleton backend`.
   - Add `symfony/runtime`, `symfony/http-client`, `nelmio/api-doc-bundle`,
     `monolog/monolog`, `symfony/validator`, `symfony/serializer`.
5. Bootstrap Vue: `npm create vite@latest frontend -- --template vue-ts`.
   - Add `vue-router`, `vitest`, `@vue/test-utils`, `jsdom`, `eslint`, `prettier`.
6. Wire `make install`, `make build`, `make up`, `make down`, `make logs`,
   `make shell-*` targets.
7. Wire `make test` to run empty PHPUnit + Vitest suites.
8. Wire `make lint`: PHPStan (level 0 initially → raise as code lands),
   PHP-CS-Fixer (dry run), ESLint, Prettier, `vue-tsc`.

Exit criteria: `make up`, `make test`, `make lint` all return 0.

---

### Phase 1 — Backend Domain Core (½ day)

**Goal:** Pure, framework-free pricing logic for Provider A & B, fully unit-tested.

Tasks
1. Implement value objects: `DriverAge`, `CarType`, `CarUse`, `Money`, `Quote`.
2. Implement pricing services as **pure** classes:
   - `Infrastructure\Provider\A\ProviderAPricingService::priceFor(int $age, string $carForm, string $carUse): int`
   - `Infrastructure\Provider\B\ProviderBPricingService::priceFor(int $age, string $tipoCoche, string $usoCoche): float`
   - `Infrastructure\Provider\C\ProviderCPricingService::priceFor(int $age, string $carForm, string $carUse): float`
3. Table-driven PHPUnit tests covering every bracket and edge case:
   - 18, 24, 25, 55, 56 boundary ages.
   - All `car_form` / `tipo` combinations.
   - Commercial vs private uplift.
4. Raise PHPStan to level 8 for `src/Domain` and `src/Infrastructure/Provider/*Pricing*`.

Exit criteria: pricing tests green, PHPStan level 8 green on the touched code.

---

### Phase 2 — Provider Endpoints (½ day)

**Goal:** `/provider-a/quote`, `/provider-b/quote`, `/provider-c/quote` work end-to-end inside containers.

Tasks
1. `ProviderAController` — accepts JSON, calls pricing service, returns `{ "price": "<int> EUR" }`. Adds `sleep(2)`. Random 10%: throws `\RuntimeException` mapped to 500 by an exception listener.
2. `ProviderBController` — accepts XML (parse with Symfony Serializer's XML encoder), pricing → XML response. `sleep(5)`. Random 1% adds `sleep(55)`.
3. `ProviderCController` — accepts CSV body, returns CSV. `sleep(1)`. Random 5%: 503.
4. Route them via `config/routes/providers.yaml`.
5. WebTestCase smoke tests: with the random factors **stubbed** to deterministic values via a `RandomnessProvider` interface (real → `mt_rand`, test → `FixedRandomnessProvider`).

Exit criteria: curl from host through nginx hits each endpoint and returns the spec-shaped response.

---

### Phase 3 — Calculate Orchestration (1 day)

**Goal:** `/calculate` fans out in parallel, enforces 10 s timeout, applies discount, sorts.

Tasks
1. Define `QuoteProvider` interface and concrete `ProviderAClient`,
   `ProviderBClient`, `ProviderCClient` — each wraps a Symfony `HttpClientInterface` bound to that provider's base URL.
2. Implement `ParallelQuoteFetcher`:
   - Kicks off `request()` for every registered provider (non-blocking).
   - Iterates via `$client->stream($responses, timeout: 10.0)` and collects
     successful responses, ignoring/marking errors.
   - Returns `array{quotes: Quote[], failed: string[]}`.
3. Implement `EnvCampaignProvider` reading `CAMPAIGN_ACTIVE` + `CAMPAIGN_PERCENTAGE`.
4. Implement `CalculateQuoteHandler::handle(CalculateQuoteRequest): CalculateQuoteResponse`:
   - Computes age from birthday.
   - Calls fetcher.
   - Applies discount if campaign active.
   - Sorts ascending by final price, marks `is_cheapest`.
5. `CalculateController` wires HTTP → DTO → handler → JSON response.
   - Request validation via `symfony/validator` constraints on the DTO.
6. Exception listener returns 400 on validation errors with the
   `{ error, violations }` shape.
7. Tests:
   - Unit test the handler with **fake** `QuoteProvider`s (one that returns,
     one that throws, one that sleeps past the timeout via async test stub).
   - Integration test the controller with a mocked HTTP client returning
     fixed fixtures for A/B/C.

Exit criteria: `POST /calculate` returns the spec response shape; tests cover
campaign on/off, all-fail, partial-fail, sorting tiebreaker.

---

### Phase 4 — OpenAPI + Logging (½ day)

Tasks
1. Configure `nelmio_api_doc.yaml` to scan the controllers; serve UI at `/api/doc`.
2. Add attributes on DTOs (`#[OA\Property(...)]`) and on controllers
   (`#[OA\Response(...)]`) — only where Nelmio's auto-detection is insufficient.
3. Wire Monolog: JSON formatter to `php://stderr`. Channel `calculate`.
4. Inject `LoggerInterface` into `CalculateQuoteHandler` and emit the
   structured INFO line described in `specification.md`.

Exit criteria: `/api/doc` renders; logs show as JSON in `make logs`.

---

### Phase 5 — Frontend Skeleton (½ day)

Tasks
1. `App.vue` with a router and a default route → `HomePage.vue`.
2. `api/client.ts` — minimal `fetch` wrapper: base URL from `import.meta.env.VITE_API_BASE`, JSON serialisation, classifies errors.
3. `api/calculate.ts` — typed `postCalculate(req): Promise<Response>`.
4. `domain/types.ts` — TypeScript mirrors of the API DTOs.
5. `composables/useFormState.ts` — `sessionStorage` hydration + persistence.
6. ESLint, Prettier, `vue-tsc --noEmit` clean.

Exit criteria: app boots, form renders, `useFormState` round-trips through sessionStorage.

---

### Phase 6 — Form, Submission, Result Table (1 day)

Tasks
1. `QuoteForm.vue` composes `BirthdayField`, `CarTypeField`, `CarUseField`.
2. Client-side validation (HTML5 + composable-level `validateForm`).
3. `useCalculate` composable manages `loading`, `error`, `data`.
4. `CampaignBanner.vue` — visible when `data.campaign.active`.
5. `QuoteTable.vue` — hand-coded `<table>`. Receives `quotes`, renders rows.
6. `SortToggle.vue` — toggles `'asc' | 'desc'`; `useSort` composable applies it client-side (independent of backend sort order).
7. `EmptyResults.vue` — Spanish message.
8. `ErrorMessage.vue` — variants for network / 4xx / 5xx with a retry CTA.
9. Vitest tests:
   - Form: required-field validation, disabled submit while loading.
   - Table: cheapest-row highlight, sort toggle, empty state.
   - Composables: `useFormState` persists on input + clears after sessionStorage wipe; `useCalculate` transitions through states.

Exit criteria: full happy-path works against the live containerised backend; error & empty paths verified.

---

### Phase 7 — Senior Bonus: Wizard + Responsive (½ day)

Tasks
1. Wrap the existing form fields into per-step pages
   (`Step1Birthday`, `Step2CarType`, `Step3CarUse`).
2. `WizardShell.vue` provides Back/Continue buttons that route forward or back.
3. `<Transition name="slide">` with two CSS keyframes — `slide-forward` and
   `slide-back` — toggled by a `direction` reactive ref set in router guards.
4. Responsive: a minimal CSS reset + container queries:
   - `max-width: 480px` on mobile, `640px` on tablet, `960px` on desktop.
5. Smoke test with Vitest that all three steps render and Continue advances.

Exit criteria: wizard works on the host browser at multiple viewport widths.

---

### Phase 8 — Polish & Delivery (½ day)

Tasks
1. Re-run `make lint` and `make test`; fix everything that drifted.
2. Sweep for dead code, stray TODOs, debug `dump()`s, `console.log`s.
3. Fill out `README.md` with: prereqs, quickstart, ports, test commands,
   troubleshooting (e.g. "if port 8080 is in use…"), and links to the docs in `docs/plan/`.
4. Hand-test the full flow in a fresh clone on a clean VM-style environment
   (or `docker compose down -v && make up`).
5. Tag the repo `v1.0.0` and write release notes pointing at the planning docs.

Exit criteria: A reviewer who only knows `make` can clone, run, click through the wizard, and see realistic quotes within ~10 s of submitting.

---

## 4. Cross-Cutting Implementation Notes

### 4.1 Random behaviour is injected, never hard-coded
Each provider controller depends on a `RandomnessProvider` (interface). The
production binding uses `random_int`; tests bind a `FixedRandomnessProvider`
that returns scripted sequences. This makes the 10%/1%/5% rules deterministic
under test without hacks like `srand()`.

### 4.2 Sleep is also injected
Same shape: `Clock` interface with `sleep(int $seconds): void`. Production
uses `real(time)`; tests use a fake that records sleeps without waiting.
Without this, the test suite would take ≥ 7 s per integration test.

### 4.3 Time and age
`Clock::today()` returns a `DateTimeImmutable`. Used by both `CalculateController`
(to compute age) and tests. Tests bind a fixed `2026-05-13` clock so age
calculations are reproducible.

### 4.4 CORS
`nelmio/cors-bundle` configured to allow `CORS_ALLOW_ORIGIN`.

### 4.5 Environment in containers
Inside the compose network the frontend hits `http://localhost:8080/calculate`
(nginx routes to the right service). From the Vue dev server, the same URL
is configured via `VITE_API_BASE`.

---

## 5. Makefile Sketch

Key targets in pseudo-form (full version lives in the repo root):

```
help:                       awk parses '##' comments and prints targets

build:                      docker compose build
up:                         docker compose up
up-d:                       docker compose up -d
down:                       docker compose down
logs:                       docker compose logs -f --tail=200

shell-backend:              docker compose exec backend bash
shell-frontend:             docker compose exec frontend sh

install:                    docker compose run --rm backend composer install \
                            && docker compose run --rm frontend npm ci

test:                       $(MAKE) test-backend && $(MAKE) test-frontend
test-backend:               docker compose run --rm backend php bin/phpunit
test-frontend:              docker compose run --rm frontend npm run test -- --run

lint:                       $(MAKE) stan && $(MAKE) cs && $(MAKE) eslint && $(MAKE) typecheck
stan:                       docker compose run --rm backend vendor/bin/phpstan
cs:                         docker compose run --rm backend vendor/bin/php-cs-fixer fix --dry-run --diff
eslint:                     docker compose run --rm frontend npm run lint
typecheck:                  docker compose run --rm frontend npm run typecheck

fix:                        docker compose run --rm backend vendor/bin/php-cs-fixer fix \
                            && docker compose run --rm frontend npm run lint:fix

clean:                      @read -p "Remove caches + volumes? [y/N] " ans; \
                            [ "$$ans" = "y" ] && docker compose down -v && rm -rf backend/var frontend/node_modules frontend/dist
```

(All targets are `.PHONY`. Real Makefile uses tabs and proper quoting.)

---

## 6. Estimated Timeline

| Phase | Effort | Cumulative |
|-------|--------|------------|
| 0 Bootstrap | 0.5 d | 0.5 d |
| 1 Domain core | 0.5 d | 1.0 d |
| 2 Provider endpoints | 0.5 d | 1.5 d |
| 3 Calculate orchestration | 1.0 d | 2.5 d |
| 4 OpenAPI + Logging | 0.5 d | 3.0 d |
| 5 Frontend skeleton | 0.5 d | 3.5 d |
| 6 Form + table | 1.0 d | 4.5 d |
| 7 Senior wizard + responsive | 0.5 d | 5.0 d |
| 8 Polish | 0.5 d | 5.5 d |

Total ≈ 5–6 focused engineering days, including testing and polish. If the
timebox is tighter, see `replanning.md` for the prioritised cut order.

---

*Adopted: 2026-05-13. Changes go through `replanning.md`.*
