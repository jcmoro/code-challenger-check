# Project Replanning — CHECK24 Car Insurance Comparison

> A living document. Every time reality contradicts the plan — a missed
> estimate, a misread spec line, a discovered bug, a scope cut — the
> response goes here. The `constitution.md`, `requirements.md`,
> `specification.md`, `implementation.md`, `validation.md` are then
> updated to match.
>
> Replanning is not a sign of failure; the absence of replanning on a
> non-trivial project is.

---

## 1. How to use this document

When something changes, add a numbered entry under §5 with:

```
### N. <short title>           <YYYY-MM-DD>
**Trigger:**     what surfaced the need to change
**Change:**      the concrete decision
**Impact:**      which other docs / code areas need updating
**Cost / risk:** what we accepted by deciding this
**Author:**      who decided
```

Then update the affected documents and reference this entry in the
relevant commit message: `docs: replan entry #N — <title>`.

---

## 2. Replanning Principles

1. **Decide explicitly.** A drift not written down is technical debt at best,
   broken trust with the reviewer at worst.
2. **Cut scope before quality.** If we are under time pressure, cut features
   from §3 in the listed order. We do **not** cut tests, types, or Docker.
3. **Keep planning docs in sync with code.** A `requirements.md` that no
   longer reflects what the code does is worse than no document at all.
4. **Senior bonuses are deferrable, not free.** They earn their cost only
   if the base requirements are flawless.
5. **Refuse half-finished work.** If a change cannot be completed in the
   remaining timebox, **revert it** and record the decision here — don't
   leave commented-out code or `// TODO`s in the delivery package.

---

## 3. Prioritised Cut Order (if timebox shortens)

The list is ordered: cut #1 first, #2 next, etc. Every item above the cut
line stays; everything below is dropped.

| # | Item | Why droppable |
|---|------|---------------|
| 1 | Frontend wizard transitions & responsiveness | Senior bonus; not required by core spec. |
| 2 | Wizard split into 3 pages | Senior bonus; the single-page form already satisfies §2.1. |
| 3 | Provider C (CSV) | Senior bonus; A & B satisfy the comparison requirement. |
| 4 | OpenAPI docs | Senior bonus; nice but not load-bearing. |
| 5 | Structured logging (Monolog JSON) | Default Symfony logger is acceptable as a fallback. |
| 6 | Coverage thresholds (drop from 85% → 70%) | Maintains discipline but unblocks delivery. |
| 7 | `make fix` target | Convenience; `make lint` is enough. |
| 8 | `make clean` confirmation prompt | Reduce target to a plain `down -v`. |

Items **not** on this list (and therefore not cuttable):
- Pricing correctness and its unit tests.
- Parallel provider fetch with 10 s timeout.
- Form `sessionStorage` persistence.
- "Cheapest highlight" + "No hay ofertas disponibles." UI.
- Docker + Makefile setup.
- PHPStan + ESLint + Prettier green.

---

## 4. Standing Risk Register

Tracked but not yet acted on. Move into §5 with a concrete change if any of these fires.

| ID | Risk | Likelihood | Impact | Mitigation in place |
|----|------|-----------|--------|---------------------|
| R1 | Symfony HttpClient `stream()` per-request timeout semantics differ from expected | Med | High | Integration test stubs the slow provider with a fake clock + asserts timeout exclusion. |
| R2 | Provider B's missing commercial-uplift rule is reviewer-flagged as wrong | Low | Low | Assumption documented in `specification.md §2.3` and surfaced in README. |
| R3 | Random 1% / 10% / 5% rates make tests flaky | High if not isolated | High | `RandomnessProvider` injection. |
| R4 | Docker port conflicts on reviewer machine (8080 / 5173) | Med | Med | README troubleshooting section + `.env` for port overrides. |
| R5 | XML parsing of Provider B trips up on whitespace / encoding | Med | Med | Use Symfony Serializer XmlEncoder; one round-trip test fixture. |
| R6 | CORS misconfigured between Vite dev server and Symfony | High first run | Low | `nelmio/cors-bundle` configured early in Phase 0; smoke-tested at end of Phase 5. |
| R7 | PHPStan max level too noisy and slows development | Med | Low | Start at level 8 (already strict). Raise to `max` only at Phase 8 polish. |
| R8 | Frontend `<input type="date">` UX differs by browser | Med | Low | Acceptable; no library introduced. README notes Chrome/Firefox tested. |

---

## 5. Replanning Log

> Entries are append-only. To revoke a decision, add a **new** entry that
> reverses it — do not edit the prior one.

### 1. Documentation suite established <2026-05-13>
**Trigger:** Project kickoff.
**Change:** Adopt the six-document planning suite (constitution, requirements,
specification, implementation, validation, replanning) as the single source
of truth for the project, with `docs/plan/` as their home.
**Impact:** All future scope discussions reference these docs.
**Cost / risk:** A small upfront time investment vs. the alternative of
"just start coding". Justified by the spec's explicit ask for plan documents
as a delivery artefact.
**Author:** project owner.

### 2. Docker + Makefile as the only runtime entry point <2026-05-13>
**Trigger:** Project owner addendum to the PDF requirements.
**Change:** Every command (run, test, lint, format) must go through
`make` and execute inside containers. Locks `requirements.md §PR-1, PR-2`.
**Impact:** `docker/` and `Makefile` are Phase 0 work; reviewer prereqs are
*Docker + Make only* — explicitly called out in `README.md`.
**Cost / risk:** Slightly slower test feedback inside containers vs. native.
Mitigated by mounting source as volumes during dev.
**Author:** project owner.

### 3. Senior-track bonuses targeted, with explicit cut order <2026-05-13>
**Trigger:** Reading the PDF's "Senior Profiles only" section.
**Change:** Plan includes all senior bonuses (parallel fetching, OpenAPI,
robust errors, logging, third provider, Docker, wizard, transitions,
responsive). §3 of this doc lists the order they get dropped if needed.
**Impact:** `implementation.md` phases 7 + parts of 3/4 are senior-only.
**Cost / risk:** Extra ~1 day of work; reversible by §3 priority list.
**Author:** project owner.

### 4. Provider B commercial-use ambiguity resolved by assumption <2026-05-13>
**Trigger:** PDF §1.2 lists a 15% commercial uplift for Provider A but
omits any equivalent rule for Provider B.
**Change:** Treat Provider B as having no commercial uplift. Document the
assumption in `specification.md §2.3`, in a unit test, and in the README.
**Impact:** Pricing tests for B assume `commercial == private`.
**Cost / risk:** If the reviewer expected a B uplift, the assumption is at
worst a discussion point — and the test explicitly flags it.
**Author:** project owner.

### 5. Failed-providers returns 200, not 5xx <2026-05-13>
**Trigger:** PDF §1.3 says *"In case one or both of the connections have
an issue, do not return prices for the provider"* — leaving the response
contract underspecified.
**Change:** `/calculate` always returns `200` (provided the input is valid),
with an empty `quotes` array when all providers fail and a `meta.failed_providers`
list in every case.
**Impact:** Frontend renders "No hay ofertas disponibles." directly from a
200 response. Backend never produces a 5xx for downstream provider issues.
**Cost / risk:** Reviewer might prefer a non-200 status for full failure;
we accept this trade for clearer client-side handling. README documents the choice.
**Author:** project owner.

### 6. Campaign toggle via env var (with interface for future DB swap) <2026-05-13>
**Trigger:** PDF §1.4 offers three options (env var / config file / DB flag).
**Change:** Implement `CAMPAIGN_ACTIVE` env var, behind a `CampaignProvider`
interface, so a DB-backed implementation is a one-class drop-in later.
**Impact:** Simplest delivery; full extensibility kept open.
**Cost / risk:** None within the timebox.
**Author:** project owner.

### 7. Symfony skeleton bumped from 7.1 → 7.3 <2026-05-13>
**Trigger:** `composer create-project symfony/skeleton:"7.1.*"` aborted with
security advisories on `symfony/http-foundation` v6.4.0..v7.4.8 (the 7.1
line's transitive range is entirely blocked by composer-audit).
**Change:** Bootstrap the skeleton at `^7.3` instead. Makefile target
`bootstrap-backend` updated accordingly; `implementation.md §1` reflects
the new pin.
**Impact:** No code impact — Symfony 7.3 is API-compatible with the spec.
**Cost / risk:** None observed; resolves cleanly, no security warnings.
**Author:** project owner (forced by composer-audit).

### 8. PHP runtime bumped from 8.3 → 8.4 <2026-05-13>
**Trigger:** PHPUnit 13 (latest installed via `symfony/test-pack`) requires
PHP ≥ 8.4.1; `make test-backend` errored on the 8.3-fpm-alpine image.
**Change:** Dockerfile pinned to `php:8.4-fpm-alpine`. `implementation.md §1`
updated. `composer.json` keeps `php: >=8.2` (8.4 satisfies it; we don't
need to force-bump the floor for users who later run outside containers).
**Impact:** No application-code impact at this stage. Future code may
use PHP 8.4-only features freely.
**Cost / risk:** None — 8.4 is stable and supported.
**Author:** project owner (forced by PHPUnit 13's PHP requirement).

### 9. PHPStan `tests/bootstrap.php` excluded <2026-05-13>
**Trigger:** Symfony's auto-generated `tests/bootstrap.php` triggers
`function.alreadyNarrowedType` at PHPStan level 8 (the `method_exists`
check is statically known to succeed).
**Change:** Added `tests/bootstrap.php` to `phpstan.dist.neon`
`excludePaths`. The file is framework-managed, not our domain code.
**Impact:** None on our code coverage; the file remains untouched.
**Cost / risk:** None.
**Author:** project owner.

### 10. ESLint v10 (latest) <2026-05-13>
**Trigger:** Initial install pinned `eslint@^9` but `@eslint/js@10.0.1`
required ESLint 10 (peer conflict).
**Change:** Frontend uses ESLint 10 with `@eslint/js`,
`typescript-eslint`, `eslint-plugin-vue` flat config, plus Prettier
integration. Flat config lives in `frontend/eslint.config.js`.
**Impact:** Modern, supported toolchain; no functional impact.
**Cost / risk:** None.
**Author:** project owner.

### 11. `phpunit.dist.xml` exports APP_ENV via both `<env>` and `<server>` <2026-05-13>
**Trigger:** Phase 2 WebTestCase suites failed with *"Could not find service
test.service_container"*. Symfony's `KernelTestCase::createKernel()` reads
`$_ENV['APP_ENV']` before `$_SERVER['APP_ENV']`, and `Dotenv::bootEnv()`
populates `$_ENV` from `backend/.env` (which pins `APP_ENV=dev`). The
existing `<server name="APP_ENV" value="test">` only mutated `$_SERVER`,
so the kernel booted in `dev` and the test container service didn't exist.
**Change:** Added a matching `<env name="APP_ENV" value="test" force="true"/>`
entry in `phpunit.dist.xml`.
**Impact:** All WebTestCase suites boot the test container correctly.
**Cost / risk:** None.
**Author:** project owner.

### 12. Pricing services accept enums, not strings <2026-05-13>
**Trigger:** `implementation.md §Phase 1.2` literally specifies
`priceFor(int $age, string $carForm, string $carUse)`. Implementing it that
way conflicts with the constitution's "no stringly-typed data crossing
module boundaries" rule and weakens PHPStan coverage.
**Change:** All three pricing services accept `DriverAge`, the appropriate
provider-specific car enum (`CarForm` / `TipoCoche`), and `CarUse`. The HTTP
controllers convert strings to enums at the boundary.
**Impact:** Pricing tests use enum arguments; PHPStan reaches max coverage
on the service signatures.
**Cost / risk:** Minor deviation from the literal plan; documented here.
**Author:** project owner.

### 13. CarType ↔ provider vocab mappings live on `CarType` itself <2026-05-13>
**Trigger:** Three providers, three "car category" vocabularies (CarForm,
TipoCoche, CarType). Spec mapping table in `specification.md §1.2`.
**Change:** `App\Domain\Car\CarType::toCarForm()` and `::toTipoCoche()` host
the mappings rather than a separate `Mapper` class per provider. Domain
knows about provider-specific enums — accepted trade-off for cohesion.
**Impact:** Adding a fourth provider with its own vocabulary requires
extending `CarType`. Easy to refactor into external mappers later.
**Cost / risk:** Coupling from Domain to provider vocabularies.
**Author:** project owner.

### 14. `XmlEncoder` explicitly registered as a service <2026-05-13>
**Trigger:** `ProviderBController` autowiring failed because Symfony's
Serializer component doesn't auto-register concrete encoders.
**Change:** Added `Symfony\Component\Serializer\Encoder\XmlEncoder: ~`
to `config/services.yaml`.
**Impact:** Provider B controller wires cleanly.
**Cost / risk:** None.
**Author:** project owner.

### 15. Provider HTTP failures shaped at the controller, not via a listener <2026-05-13>
**Trigger:** `implementation.md §Phase 2.1` proposed a `kernel.exception`
listener to map a domain `\RuntimeException` to HTTP 500. Returning the
JSON response directly from the controller is shorter and avoids a global
exception listener that other tests would need to be aware of.
**Change:** Provider controllers return their `JsonResponse(500)` /
`Response(503)` directly when the randomness bucket triggers failure.
**Impact:** Simpler, more local; no global listener needed for this path.
**Cost / risk:** None.
**Author:** project owner.

### 16. `QuoteFetcher` interface introduced for testability <2026-05-13>
**Trigger:** Phase 3 unit tests for `CalculateQuoteHandler` would otherwise
need to mock `ParallelQuoteFetcher` (a `final readonly` class) — which
PHPUnit can't subclass — or the Symfony HttpClient layer beneath it.
**Change:** Extracted `App\Application\Provider\QuoteFetcher` interface.
`ParallelQuoteFetcher` implements it; tests use `InMemoryQuoteFetcher`.
Bound in `services.yaml` as an alias.
**Impact:** Handler tests are pure unit tests; the fetcher has its own
dedicated suite with `MockHttpClient`.
**Cost / risk:** None.
**Author:** project owner.

### 17. ParallelQuoteFetcher reads status on the first chunk <2026-05-13>
**Trigger:** Symfony's `HttpClient::stream()` calls `getHeaders(true)` after
each yield while the response is still in its initializer phase. That throws
`ServerException` / `ClientException` for non-2xx responses, killing the
stream loop before our error-handling code could mark the provider failed.
**Change:** On `$chunk->isFirst()` we eagerly call `getStatusCode()` (which
clears the initializer flag) and short-circuit to "failed" if non-2xx,
calling `$response->cancel()` to silence the destructor's status check too.
**Impact:** Non-2xx responses cleanly mark the provider failed, no exceptions
escape, MockResponses destruct without throwing in tests.
**Cost / risk:** None.
**Author:** project owner.

### 18. `JSON_PRESERVE_ZERO_FRACTION` on the `/calculate` response <2026-05-13>
**Trigger:** PHP's default `json_encode` drops trailing `.0` from
whole-number floats. A campaign percentage of `5.0` arrives on the wire as
`5` (int), violating the spec's `{"percentage": 5.0}` example.
**Change:** Controller constructs an empty `JsonResponse`, sets
encoding options to include `JSON_PRESERVE_ZERO_FRACTION`, then calls
`setData()` so the encode pass uses the new flag.
**Impact:** Wire-level types stay correct; frontend tests can `assertSame(5.0, …)`.
**Cost / risk:** Slightly more verbose response construction.
**Author:** project owner.

### 19. `nelmio/api-doc-bundle` is the *only* recipe-skipped bundle; manual registration <2026-05-13>
**Trigger:** During Phase 0 the Flex recipe for Nelmio was marked IGNORED.
Phase 4 had to register it manually in `bundles.php` plus install
`symfony/twig-bundle` and `symfony/asset` for the Swagger UI to render.
**Change:** `config/bundles.php` includes `NelmioApiDocBundle::class`.
`twig-bundle` and `asset` are now first-class runtime deps. UI at
`/api/doc`, JSON at `/api/doc.json`.
**Impact:** Two extra dependencies but the bonus is fully delivered.
**Cost / risk:** None.
**Author:** project owner.

### 20. Structured logging via a dedicated `calculate` Monolog channel <2026-05-13>
**Trigger:** Spec §3.3 requires one INFO line per `/calculate` with nested
per-provider outcomes. The default Monolog config logs to a file and uses
the line formatter — wrong format and wrong destination for a containerised
service.
**Change:** Declared `calculate` channel in `monolog.yaml`; dedicated handler
emits JSON to `php://stderr` at INFO in dev and prod, `null`-routed in test.
`CalculateQuoteHandler` and `ParallelQuoteFetcher` inject
`monolog.logger.calculate` via `#[Autowire(service: …)]`.
**Impact:** `make logs` shows one machine-parseable line per request with
`request_id`, `duration_ms`, campaign state, and per-provider outcomes.
**Cost / risk:** None.
**Author:** project owner.

### 21. Frontend wizard shares state via provide/inject, not Pinia <2026-05-14>
**Trigger:** Wizard steps need to share form state + a single
`useCalculate` mutation across 4 routes.
**Change:** `WizardPage` calls `useFormState()` + `useCalculate()` once and
`provide`s both via `InjectionKey`s exported from each composable. Steps
`inject` what they need. No state-management library introduced.
**Impact:** No new dependency; back-button navigation preserves form state.
The single-page form on `/` keeps using its own local `useFormState()`.
**Cost / risk:** Steps now throw if mounted outside `WizardPage`, but
type errors catch this at compile time.
**Author:** project owner.

### 22. `useFormState` flushes on `onBeforeUnmount` <2026-05-14>
**Trigger:** The 200 ms debounce was losing the latest input when the user
clicked Continue immediately after typing — the unmounting step ran before
the timer fired.
**Change:** When called inside a Vue component (`getCurrentInstance()`),
the composable registers an `onBeforeUnmount` hook that flushes any pending
write synchronously.
**Impact:** Wizard step transitions are now lossless; single-page form is
unaffected.
**Cost / risk:** None.
**Author:** project owner.

### 23. ApiError taxonomy for frontend error handling <2026-05-14>
**Trigger:** The frontend needs to render different Spanish messages for
network failure vs. validation 400 vs. server 5xx, and surface the
backend's `violations` array when present.
**Change:** `frontend/src/api/client.ts` exports an `ApiError` class with
a `kind: 'network' | 'validation' | 'server' | 'unknown'` discriminator and
an optional `violations` payload. `ErrorMessage.vue` switches on `kind`;
`useCalculate` wraps non-ApiError throws in `kind: 'unknown'`.
**Impact:** UI error states map cleanly to the backend's response taxonomy.
**Cost / risk:** None.
**Author:** project owner.

### 24. `WizardResult` is a 4th wizard route, not an in-place step transition <2026-05-14>
**Trigger:** `implementation.md §Phase 7` mentions only three step pages,
but the spec mock requires showing results after the wizard completes.
**Change:** Added `wizard.result` route (`meta.order = 4`). The result
page mounts, reads form state, calls `submit()`, and renders the shared
`QuoteResults` component. The slide-transition direction logic naturally
extends.
**Impact:** Wizard now ends with quotes; same slide animation between
step 3 → result as between any two steps.
**Cost / risk:** None.
**Author:** project owner.

### 25. PHPStan raised from level 8 → 10 (the strictest) <2026-05-15>
**Trigger:** Post-Phase-8 polish. The original requirements set the bar at
"≥ level 8" with `max` as a stretch goal (R7 in §4 of this doc). With the
codebase well-typed at level 8, climbing further surfaced 41 errors at
level 9, all in three files; level 10 then passed with no additional
changes (level 10 only adds checks on top of level 9 for `mixed`).
**Change:**
- `phpstan.dist.neon` → `level: 10`.
- `ParallelQuoteFetcher`: typed `\SplObjectStorage<ResponseInterface, QuoteProvider>`
  (and four sibling locals) so `$providersByResponse[$response]` returns
  `QuoteProvider` instead of `mixed`.
- `CalculateControllerTest`: declared `@phpstan-type` aliases on the class
  (`MoneyShape`, `QuoteShape`, `CampaignShape`, `MetaShape`,
  `CalculateResponseShape`, `ProblemDetailsShape`); replaced `responseJson()`
  with two typed helpers (`calculateResponse()` and `problemDetailsResponse()`).
  Added one `assertNotNull(...)` before dereferencing `discounted_price`.
- `ProviderAControllerTest`: added `@var array{price: string}` /
  `array{error: string}` annotations on two `json_decode()` results that
  were missing them.
**Impact:**
- `make stan` returns `[OK] No errors` at level 10.
- 91/91 backend tests still green (+1 assertion from the explicit
  `assertNotNull` guard).
- Documentation references to "PHPStan max" / "level 8" updated across
  `README.md`, `docs/README.md`, `docs/architecture/README.md`,
  `docs/directives/DIR_api_docs.md`, `docs/plan/{constitution,
  requirements, implementation, validation}.md`. Historical narrative
  (Phase 0/1 exit criteria, replanning #9) intentionally left alone.
**Cost / risk:** None observed. The type annotations make the test code
slightly more verbose but also self-documenting (the `@phpstan-type` block
serves as a one-stop reference for the response shape).
**Author:** project owner.

---

### 26. SonarCloud added as a quality gate (scanner-on-demand, no CI yet) <2026-05-15>
**Trigger:** validation.md asked for "PHPStan + ESLint + Prettier" as the
quality bar. Reviewer-facing static analysis (Sonar-style) was not in
the original plan. Adding it surfaced 25 latent code smells across the
existing codebase plus several documentation/tooling decisions worth
recording.
**Change:**
- `sonar-project.properties` (new) — monorepo config (`sources=backend/src,frontend/src`,
  `tests=backend/tests,frontend/tests`). PHP coverage via Clover, JS via LCOV.
- Multicriteria suppression for two rules with documented false-positive
  patterns in this codebase:
  - `php:S116` (snake_case fields) in `**/Http/Dto/**` and
    `**/Provider*Request.php` — these DTOs intentionally mirror the wire
    format; renaming would need `#[SerializedName(...)]` per field or
    break the contract.
  - `php:S1142` ("more than 3 returns") in `**/Infrastructure/Provider/**/*Client.php`
    and `**/UI/Http/Controller/Provider*Controller.php` — these are guard
    clauses for untrusted external input (JSON / XML / CSV); collapsing
    to a single return nests deeper and worsens cognitive complexity.
- `docker/php/Dockerfile`: `pecl install pcov` + `docker-php-ext-enable pcov`.
- `docker/php/php.ini`: `pcov.enabled=1`, `pcov.directory=/app/src`.
- `frontend/vitest.config.ts`: `'lcov'` added to `coverage.reporter`.
- `Makefile`: targets `coverage`, `coverage-backend`, `coverage-frontend`,
  `sonar`. `coverage-backend` post-processes `clover.xml` with `sed` to
  rewrite container-side paths (`/app/...`) to repo-relative (`backend/...`)
  so the scanner can resolve them.
- `docker-compose.override.yml`: removed the `frontend_node_modules`
  named volume. It was there for pre-VirtioFS macOS perf; today it
  prevented the host IDE from resolving `vite/client`, `@vue/tsconfig`,
  etc. — TS errors that didn't reproduce in CLI.
- SonarCloud project: New Code definition set to "Number of days = 1"
  (the SonarCloud free plan doesn't expose "Specific analysis"). Result:
  the bootstrapping phase does not poison the `new_coverage` threshold.
**Impact:**
- New `make sonar` flow is reproducible inside Docker (no Node / SonarScanner
  on host).
- Reviewers / CI can run `SONAR_TOKEN=... make coverage && make sonar`
  for an authoritative Sonar pass.
- 0 issues / quality gate PASSED at the end of the same-day sweep
  (see replanning #27 and #28 for the matching code-side cleanups).
- Setting "Number of days = 1" means `new_coverage` becomes meaningful
  only from 2026-05-16 onwards. Acceptable trade-off for a code challenge.
- GitHub Actions workflow (`.github/workflows/sonar.yml`) deferred —
  recorded in §6 as out-of-scope-for-now.
**Cost / risk:** Two extra Docker layers (pcov), ~250 MB of node_modules
duplicated on host, and ~70 lines of `sonar-project.properties` to maintain.
The `sed` post-process on `clover.xml` is a known smell — long-term it would
move to PHPUnit's source path config or a custom processor.
**Author:** project owner.

---

### 27. Provider business rule moved out of controllers (constitution §88 enforcement) <2026-05-15>
**Trigger:** Manual audit of the 4 controllers against `constitution.md`
§88 ("No business rule lives in a controller"). The 3 provider
controllers each repeated `clock->sleep(N); if (random ≤ M) return error;`
inline — that simulated latency + error-rate is the **business behaviour
of the provider** (PDF §1.2), not HTTP plumbing.
**Change:**
- New `App\Infrastructure\Provider\A\ProviderASimulator` (2 s baseline,
  10 % failure rate, returns `?int`).
- New `App\Infrastructure\Provider\B\ProviderBSimulator` (5 s baseline,
  1 % chance of +55 s spike, never fails — returns `int`).
- New `App\Infrastructure\Provider\C\ProviderCSimulator` (1 s baseline,
  5 % failure rate, returns `?int`).
- Each controller becomes a pure format-adapter: parse input → call simulator
  → format output. Latency / random constants live next to the simulator,
  not the controller.
- Existing `Provider{A,B,C}ControllerTest` (WebTestCase) untouched and
  still pass — proof of behavioural equivalence.
- 9 new unit tests (`Provider{A,B,C}SimulatorTest`) for the simulators,
  using `FakeClock` + `FixedRandomnessProvider`. Run without booting the
  kernel; pin both happy and failure paths plus boundary rolls.
**Impact:**
- HTTP contract: unchanged for all 3 providers (same JSON / XML / CSV).
- Total controller LoC: 268 → 228 (−15 %).
- `php:S1142` ("too many returns") still ignored on these controllers
  via Sonar multicriteria — the simulators don't have that smell, but
  the format-parsing in B and C still uses guard clauses.
**Cost / risk:** Three extra source files + three extra test files. The
simulators live in `Infrastructure/Provider/` (cohesion with their
matching `*PricingService` and `*Client`), even though they technically
orchestrate Application-layer concerns (`Clock`, `RandomnessProvider`).
Acceptable — the constitution forbids leakage *out* of layers, not strict
co-location.
**Author:** project owner.

---

### 28. `CalculateQuoteResponseFactory` extracted from `CalculateController` <2026-05-15>
**Trigger:** Same audit as #27. `CalculateController` was at 139 lines
with three private serialization helpers (`serializeResult`,
`serializeQuote`, `serializeMoney`) and the `JSON_PRESERVE_ZERO_FRACTION`
flag setup. Not a constitution violation (presentation logic is HTTP's
business) but a clear cohesion smell.
**Change:**
- New `App\UI\Http\Response\CalculateQuoteResponseFactory::fromResult(CalculateQuoteResult): JsonResponse`
  owns the wire format and the `JSON_PRESERVE_ZERO_FRACTION` flag.
- `CalculateController` shrinks to 84 lines; it now does only:
  parse DTO → invoke handler → translate `\DomainException` to 400 →
  delegate 200 to the factory.
- 5 unit tests for the factory cover the corners that previously needed
  `WebTestCase`: `is_cheapest` flag, `discounted_price` null vs present,
  `5.0` not degrading to `5`, `percentage` rounded to 2 decimals,
  `failed_providers` and `durationMs` propagated to `meta`, and
  `quotes:[]` not degenerating to `null`.
- An earlier attempt to also extract reusable `OA\Schema` components
  (`ValidationError`, `CalculateQuoteResponse`) was applied and then
  reverted — for a 4-endpoint code challenge, the inline `example: [...]`
  blocks in the controller are ergonomic enough; the indirection cost
  outweighed the cleanup. Decision recorded so this isn't relitigated.
**Impact:**
- HTTP contract: byte-for-byte unchanged.
- Tests: 113 backend (was 108 → +5 factory tests).
- Coverage dipped from 90.9 % → 88.5 % because the factory adds 67 LoC,
  not all of which are exercised by the 5 unit tests (the rest is
  covered indirectly via `CalculateControllerTest`). Still well above
  the 80 % gate.
**Cost / risk:** One new class + one new test file. The factory takes
no constructor args (no DI complexity). `validationError()` stays in the
controller — 6 lines, single use site, not worth extracting.
**Author:** project owner.

---

### 29. `Provider{B,C}` codecs extracted — one wire format, one source of truth <2026-05-15>
**Trigger:** Continuation of the §88 audit (#27, #28). Two real smells
surfaced once the controllers were thin enough to read end-to-end:
1. Provider B's XML envelope was being parsed with **two different
   libraries** — `Symfony\…\XmlEncoder` in `ProviderBController`, but
   `simplexml_load_string` (with `libxml_use_internal_errors` toggling)
   in `ProviderBClient`. Same wire format, two parsing surfaces, drift
   risk on every spec change.
2. Provider C's CSV (one header + one data row) was being parsed by
   **two near-identical implementations** — `ProviderCController::parseCsv()`
   and `ProviderCClient::parseResponse()`. Encoding was inline `\sprintf`
   in three places.
**Change:**
- New `App\Infrastructure\Provider\B\ProviderBXmlCodec` — thin wrapper
  over `XmlEncoder` exposing `decode(string): ?array` and
  `encode(string $rootNode, array): string`. Both directions now go through
  the same Symfony component.
- New `App\Infrastructure\Provider\C\ProviderCCsvCodec` — `decodeRow(string): ?array`
  + `encodeRow(array): string`. Two-line CSV with header + data.
- `ProviderBController`, `ProviderBClient`, `ProviderCController`,
  `ProviderCClient` re-wired to depend on the codec. `simplexml`,
  `libxml_use_internal_errors`, `str_getcsv`, `array_combine` — all
  removed from controllers and clients.
- 14 new unit tests (7 per codec) cover happy path, malformed input,
  blank lines, mismatched columns, encode↔decode roundtrip. No kernel.
- `ParallelQuoteFetcherTest` updated where it constructs the clients
  directly — added the codec arg + relevant imports.
**Impact:**
- HTTP contracts (B XML and C CSV) byte-for-byte unchanged.
- LoC trimmed: `ProviderBClient` 70→60, `ProviderCClient` 81→64,
  `ProviderBController` 95→91, `ProviderCController` 90→75.
- Backend tests: 113 → 127.
- Future spec changes for either format touch a single class.
**Cost / risk:** Two new source files + two new test files. Both clients
gained a constructor arg (the codec); auto-resolved by autowire so
`services.yaml` did not need changes.
**Author:** project owner.

---

### 30. Senior pass on #29: revert `ProviderBXmlCodec`, narrow exception catches <2026-05-15>
**Trigger:** Critical second look at #29 caught two real debt items the
first pass introduced:
1. The first scan after extracting `ProviderBXmlCodec` flagged `php:S1488`
   ("immediately return this expression") on the codec's `decode()`. The
   instinctive fix — adding multicriteria suppression — was rejected as
   "no se debe excluir de Sonar un error localizado, se debe corregir".
   The next attempt — runtime `foreach` validating every leaf — was
   correctly called out as overengineered defence (CLAUDE.md: "don't add
   validation for scenarios that can't happen"). The deeper question
   surfaced: does the codec abstraction even pay for itself for B?
2. `catch (\Throwable)` in three sites (`ProviderAClient::parseResponse`,
   `ProviderBClient::parseResponse`, `ProviderBController::__invoke`) was
   broader than warranted — masks `\TypeError` / `\Error` (programming
   bugs) as "provider failed".
**Change:**
- `ProviderBXmlCodec.php` and its 7 tests deleted. `ProviderBClient` and
  `ProviderBController` now inject `XmlEncoder` directly. Both sides use
  the same Symfony component (the original goal of #29 is preserved
  without the wrapper).
- Catches narrowed where the layer knows the concrete library:
  - `ProviderAClient::parseResponse` → `HttpClientExceptionInterface | \JsonException`
  - `ProviderBClient::parseResponse` and `ProviderBController::__invoke` → `NotEncodableValueException`
- `ParallelQuoteFetcher` keeps `catch (\Throwable)` on `startRequest` and
  `cancel()` deliberately: those are **port-boundary** catches
  (`QuoteProvider` is an interface; any implementation may throw
  anything) and the cleanup catch is documented belt-and-suspenders.
- `sonar-project.properties` reformatted — `multicriteria` listed with
  line continuation, each rule's rationale comment immediately above its
  block. Header explicitly states the contract: "narrow, file-scoped,
  with documented rationale; exists because the rule's premise doesn't
  apply, not to silence a real warning".
**Impact:**
- HTTP contracts unchanged (B and C byte-for-byte identical).
- Backend tests: 127 → 120 (−7 codec tests removed; coverage of the
  removed paths is reabsorbed by the existing controller/client tests
  that exercise the same code through `XmlEncoder` directly).
- SonarCloud after the change: 0 issues, gate green, coverage 93.2 %.
- The remaining `ProviderCCsvCodec` is justified — CSV had real
  implementation duplication (4 sites, ~25 lines of `array_filter +
  str_getcsv + array_combine + sprintf`); XML didn't.
**Cost / risk:** Documented partial revert of #29. The senior lesson
captured here: "abstraction must pay for its own tax" — a thin wrapper
over a service that's already an abstraction is overhead, not value.
Same logic applies if anyone later proposes a `ProviderAJsonCodec` or
similar.
**Author:** project owner.

---

### 31. `ParallelQuoteFetcher`: `Quote|true` sentinel replaced with `?Quote` <2026-05-15>
**Trigger:** Senior audit pass after the codec calibration. The internal
`FetchSession::$resolved` map used `Quote|true` where `true` meant "this
provider was rejected". Mixing a primitive sentinel into a domain-type
union is a recognised code smell — `null` is the idiomatic PHP signal
for absence.
**Change:**
- `finalize()` return type → `?Quote`. Both failure paths (non-2xx,
  unparseable body) now `return null` instead of `return true`.
- `markResolvedFailure()` writes `null` instead of `true`.
- `handleChunk()`: the "already resolved?" guard switched from `!isset`
  to `!array_key_exists` (necessary because `isset` returns false for
  null values, which would re-finalize an already-failed entry).
- `handleChunk()` outcome branch: `true === $entry ? FAILED : OK`
  becomes `null === $entry ? FAILED : OK`.
- `buildFetchResult()` rewritten with three explicit branches: key
  absent → defensive timeout, value null → resolved as failed, value
  Quote → success.
- `FetchSession::$resolved` PHPDoc upgraded to enumerate the three
  states (key absent / null / Quote).
**Impact:**
- HTTP contract: unchanged (the three semantic outcomes are preserved
  byte-for-byte).
- Tests: 120/120 still green; no new tests needed (existing
  `ParallelQuoteFetcherTest` already covers the three paths).
- PHPStan level 10 happy with the cleaner union.
**Cost / risk:** None observed. The `array_key_exists` substitution is
a one-character semantic difference from `isset` that is easy to miss
in review — captured here so a future "use isset, it's faster" PR has
this entry to land against.
**Author:** project owner.

---

### 32. Dockerfile `USER` directives deferred (containers are dev/CI, not production) <2026-05-15>
**Trigger:** SonarQube rule S6471 / generic security review flags
"container should not run as root". The three Dockerfiles
(`docker/{php,nginx,node}/Dockerfile`) have no `USER` directive — all
processes run as root inside the container.
**Change:** None. Decision documented here.
**Rationale:**
- These containers exist only for local dev (`make up`, `make test`,
  `make sonar`) and CI. There is no production deploy in scope (per
  `constitution.md` §5: no persistence, no auth, no infra beyond Docker
  on the developer's machine).
- Adding `USER` non-trivially affects the bind-mount workflow:
  - `docker/php/Dockerfile`: `composer install` and PHPUnit + pcov write
    to `/app/vendor`, `/app/var/cache`, `/app/var/coverage` — bind-mounted.
    Running as `www-data` (UID 82) requires either chowning host paths
    or matching UIDs via `--build-arg HOST_UID=$(id -u)`.
  - `docker/node/Dockerfile`: `npm install` writes to `/app/node_modules`
    (named volume since #...; was bind-mounted earlier). Same UID issue.
  - `docker/nginx/Dockerfile`: bind to port 80 requires root. Switching
    to `nginxinc/nginx-unprivileged` (binds 8080) needs the compose
    port mapping to change to `8080:8080`.
- The proper hardening for production would be a multi-stage build
  separating the dev image from a slim non-root prod image, plus a
  CI-time vulnerability scan. That is out of scope for a code-challenge
  submission.
**Impact:**
- SonarCloud's PHP/JS analyzers do not enable Docker rules by default
  on the free tier, so this isn't surfaced as an open issue today.
- A future maintainer reading the Dockerfiles will find the rationale
  here instead of re-litigating "why no USER".
**Cost / risk:** Documented dev-only stance. If the project ever gets a
production deploy, this entry is the first thing to revisit (along with
adding a `prod` build target, multi-stage hardening, vulnerability
scanning, and a separate compose file).
**Author:** project owner.

---

### 33. `X-Request-Id` response header + `ValidationErrorResponse` factory <2026-05-16>
**Trigger:** Senior audit pass on the HTTP layer surfaced two findings:
1. `request_id` was generated inside `CalculateQuoteHandler` (for log
   correlation) but never reached the client. A user reporting "my call
   failed" had no id to share — support had to hunt by timestamp + IP.
2. The `{ error: 'validation_failed', violations: [...] }` envelope was
   built in two places: `ValidationFailedListener` (Symfony validator
   failures) and `CalculateController::validationError()` (domain
   exceptions). Format change → two-place edit.
**Change:**
- New `App\UI\Http\Response\ValidationErrorResponse` factory with
  `fromField(string, string): JsonResponse` and
  `fromViolations(ConstraintViolationListInterface): JsonResponse`.
  Both producers (controller and listener) now route through this single
  source of truth for the 400 envelope.
- `CalculateQuoteResult` gained a `requestId` field (the handler already
  generated it; previously only logged).
- `CalculateQuoteResponseFactory::fromResult()` sets `X-Request-Id`
  header on the response from `result->requestId`.
- `ValidationFailedListener` is now `final readonly` with the factory
  injected via constructor.
**Impact:**
- HTTP body of `/calculate` 200 unchanged byte-for-byte; new header
  `X-Request-Id` exposed.
- HTTP body of `/calculate` 400 unchanged (same envelope, single
  builder).
- Backend tests: 120 → 121 (+1 for the X-Request-Id assertion).
- Operational: log line and response header now share the same
  correlation id — support can search logs by the value the client
  shows.
- Future formal addition of `request_id` field to the JSON envelope
  itself (or i18n on `message`) is a one-file change in
  `ValidationErrorResponse`.
**Cost / risk:** `CalculateQuoteResult` now carries a tracing field
(`requestId`) — small leak of HTTP / observability concern into the
application result. Acceptable: the result is already a
presentation-shaped DTO (it has `durationMs`); `requestId` fits the
pattern. The alternative (request listener storing in request
attributes + response listener reading them) is more layers for the
same outcome.
**Author:** project owner.

---

### 34. Frontend captures `X-Request-Id` and surfaces it in `ErrorMessage` <2026-05-16>
**Trigger:** Audit pass after #33. The backend exposes `X-Request-Id`
on every `/calculate` response — but the SPA was throwing the header
away. Net effect: the correlation id was useful only to anyone willing
to open devtools. The natural completion of #33 is "the user reads it
in the UI and shares it with support".
**Change:**
- `ApiError` gains an optional `requestId?: string` (5th constructor
  argument).
- `ApiClient.postJson()` reads `response.headers.get('X-Request-Id')`
  once and propagates it to both error branches (4xx validation, 5xx
  server). Captured in a single place; not surfaced on success because
  the client doesn't need it for happy paths today.
- `ErrorMessage.vue` renders `<p class="error__request-id">` with the
  id when present (kind-agnostic — applies to all four `ApiErrorKind`s).
  When the id is absent, the block is omitted (network-only failures
  before any HTTP response, for example).
- New i18n string `errors.requestIdLabel = 'ID de referencia'`.
- 3 new tests: 1 in `client.test.ts` (capture of header on a 5xx),
  2 in `ErrorMessage.test.ts` (renders when present, omits when absent).
**Impact:**
- HTTP contract: unchanged (consumer-side change only).
- Frontend tests: 102 → 105.
- Operational: the id now flows backend log → HTTP header → UI → user
  → support input → log grep. Closed loop.
**Cost / risk:** None observed. The `requestId` field on `ApiError` is
optional and additive — older code constructing `ApiError` without it
keeps compiling. The component renders the block conditionally so
errors without an id (the rejected-fetch network case before any HTTP
exchange) degrade gracefully.
**Author:** project owner.

---

### 35. `WizardResult.vue` `onMounted` switched to `async/await` for `void`-operator consistency <2026-05-16>
**Trigger:** Final-pass audit caught a leftover `void submit(...)` inside
`WizardResult.vue`'s `onMounted` callback. The same pattern was
addressed in `WizardShell.vue` during the SonarCloud sweep
(`typescript:S3735`, replanning entry around #25-30) but Sonar's rule
didn't fire here this round — likely because the call sits inside
`onMounted` rather than a top-level handler. Inconsistency surfaced
on a manual re-read.
**Change:**
- `onMounted(() => { void submit({...}) })` becomes
  `onMounted(async () => { await submit({...}) })`.
- Vue 3's lifecycle hooks accept `Promise<void>` callbacks; no behaviour
  change.
**Impact:**
- `void` operator now absent from the frontend codebase.
- Frontend tests: 105/105 unchanged.
- If `no-floating-promises` (typescript-eslint, type-checked rule) is
  ever enabled, this site no longer trips it.
**Cost / risk:** None. The change is byte-equivalent at runtime.
**Author:** project owner.

---

## 6. Out-of-Scope, Re-Opened If Asked

These were ruled out by the constitution but may be re-opened on review feedback:

- Persistent storage of quote history (would justify Doctrine + Postgres).
- Authenticated users + saved quotes (Symfony Security + JWT or sessions).
- i18n across EN/ES (vue-i18n).
- CI pipeline (GitHub Actions running `make lint && make test` on PR).
- Production-grade Docker images (multi-stage, distroless, healthchecks).
- Rate limiting on `/calculate`.

If the reviewer raises any of these, the response is:
1. Append an entry to §5 with the trigger + decision.
2. Estimate impact on `implementation.md` timeline.
3. Update `requirements.md` to reflect the new scope.

---

## 7. Definition of "Replanning Done"

A replanning entry is complete when:

1. The entry is in §5 with all five fields filled.
2. Every doc whose contents changed has been edited.
3. The commit that implements the change references the entry by number.
4. `make lint && make test` is green after the change.

---

*Adopted: 2026-05-13. This document grows over the project's life.*
