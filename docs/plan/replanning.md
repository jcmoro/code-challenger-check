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
