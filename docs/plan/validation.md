# Feature Validation — CHECK24 Car Insurance Comparison

> The proof that what we built matches `requirements.md` and `specification.md`.
> Read as: "to consider this project done, we must pass every gate below."

---

## 1. Validation Strategy

Validation is layered, with each layer's gates more user-facing than the last:

```
   ┌─────────────────────────────────────────────┐
   │  Layer 4 — Manual reviewer walk-through     │
   │  Layer 3 — End-to-end happy & sad paths      │
   │  Layer 2 — Integration tests                 │
   │  Layer 1 — Unit tests                        │
   │  Layer 0 — Static analysis + lint + format   │
   └─────────────────────────────────────────────┘
```

A change is *only* considered complete when every layer beneath it is green.

---

## 2. Layer 0 — Tooling Gates

| Gate | Tool | Pass criterion |
|------|------|----------------|
| PHP types | `phpstan` | Exit 0 at level **10** (the strictest level) over `backend/src` and `backend/tests` |
| PHP style | `php-cs-fixer` | `--dry-run --diff` exits 0 |
| TS types | `vue-tsc --noEmit` | Exit 0 |
| TS lint | `eslint` | Exit 0 with `--max-warnings 0` |
| TS format | `prettier --check` | Exit 0 |

Run via `make lint`. Pipeline-equivalent: a single `make lint && make test` invocation must end with exit 0.

---

## 3. Layer 1 — Unit Tests

### 3.1 Provider A pricing — `ProviderAPricingServiceTest`

Table-driven cases (all final integers in EUR):

| age | car_form | car_use | expected |
|-----|----------|---------|----------|
| 18  | suv      | private | 217 + 70 + 100 = **387** |
| 24  | suv      | private | **387** |
| 25  | suv      | private | 217 + 0 + 100 = **317** |
| 55  | suv      | private | **317** |
| 56  | suv      | private | 217 + 90 + 100 = **407** |
| 30  | compact  | private | 217 + 0 + 10  = **227** |
| 30  | suv      | commercial | round(317 * 1.15) = **365** |
| 30  | compact  | commercial | round(227 * 1.15) = **261** |

A `boundaryAgeCases()` provider tests each transition.

### 3.2 Provider B pricing — `ProviderBPricingServiceTest`

| age | TipoCoche | UsoCoche | expected |
|-----|-----------|----------|----------|
| 18  | turismo   | privado  | 250 + 50 + 30  = **330** |
| 29  | turismo   | privado  | **330** |
| 30  | turismo   | privado  | 250 + 20 + 30  = **300** |
| 59  | turismo   | privado  | **300** |
| 60  | turismo   | privado  | 250 + 100 + 30 = **380** |
| 30  | suv       | privado  | 250 + 20 + 200 = **470** |
| 30  | compacto  | privado  | 250 + 20 + 0   = **270** |
| 30  | turismo   | comercial| **300** (assumption: no uplift for B) |

A second test asserts the assumption *"B has no commercial uplift"* with a comment linking to `specification.md §2.3`.

### 3.3 Provider C pricing — `ProviderCPricingServiceTest`

Mirror of A/B with C's table. Must include at least one case where C beats both A & B to exercise the "highlight cheapest" UI later.

### 3.4 Sorting & tie-breaker — `QuoteSorterTest`

- Three quotes, different prices → strictly ascending.
- Two quotes with identical price → sorted by `provider` id alphabetical.

### 3.5 Campaign discount — `CampaignDiscountTest`

| Input price | Campaign active | Percentage | Expected discounted |
|-------------|----------------|------------|---------------------|
| 295.00      | true           | 5.0        | 280.25              |
| 310.00      | true           | 5.0        | 294.50              |
| 295.00      | false          | 5.0        | null (no discount)  |
| 100.00      | true           | 0.0        | 100.00              |

### 3.6 Age computation — `AgeFromBirthdayTest`

- `1992-02-24` on clock `2026-05-13` → 34.
- Birthday today → exact-year age.
- Birthday tomorrow → one year less than naïve diff.

### 3.7 Frontend composables — Vitest

- `useFormState`: writes to `sessionStorage`; reads on mount; clears when sessionStorage cleared mid-test.
- `useCalculate`: state transitions `idle → loading → success` and `idle → loading → error` with a stubbed `fetch`.
- `useSort`: toggles between asc/desc; sort respects discounted price when present.

---

## 4. Layer 2 — Integration Tests

### 4.1 Backend — `WebTestCase`

`/provider-a/quote`
- `POST` valid body, randomness stubbed to "not error" → 200 with `{ "price": "<n> EUR" }`.
- Randomness stubbed to "error" → 500 with `{ "error": "provider_a_unavailable" }`.
- Sleep is stubbed via the `Clock` fake; test asserts the sleep was *requested* (`assertSlept(2)`), without actually waiting.

`/provider-b/quote`
- Valid XML body → 200 with `<RespuestaCotizacion>`.
- Malformed XML → 400.
- 1% branch → asserts a `sleep(60)` was requested.

`/provider-c/quote` (mirror)

`/calculate`
- All providers respond successfully → 200, three quotes, ascending order.
- Provider A errors → 200, quotes for B & C only, `failed_providers: ["provider-a"]`.
- Provider B times out (stub returns after >10 s of fake clock) → excluded.
- All providers fail → 200 with `quotes: []`.
- `CAMPAIGN_ACTIVE=false` → `discounted_price` is null on every quote.
- Validation failure (e.g. age < 18) → 400 with `violations`.

### 4.2 Frontend — Vitest + component tests

- `<QuoteForm>` happy path: fill, submit, see loading, see table.
- `<QuoteForm>` validation: submit with missing fields → all three errors shown, button stays disabled.
- `<QuoteTable>`: cheapest row carries the `row--cheapest` class.
- `<SortToggle>`: clicking flips order; clicking again restores.
- `<EmptyResults>`: renders the spec string verbatim.
- `<CampaignBanner>`: visible iff `campaign.active`.

---

## 5. Layer 3 — End-to-End (manual scripted)

A markdown checklist run before submission. Each step has an explicit expected outcome.

### 5.1 Happy path
1. `make down -v && make build && make up-d`.
2. Open `http://localhost:5173` (or whichever port the dev server uses).
3. Fill: birthday `1992-02-24`, car_type `SUV`, car_use `Privado`. Submit.
4. **Expected within ~7 s:** results table with 2–3 quotes, cheapest highlighted, sorted ascending, campaign banner visible.
5. Toggle sort → order reverses.
6. Reload (F5) → form remains filled.
7. Close tab, reopen → form is empty.

### 5.2 Partial failure path
1. Set env `PROVIDER_A_FORCE_ERROR=true` (a debug flag exposed only in dev) and restart backend.
2. Submit form → table shows B (and C) only, no error banner. Cheapest highlight still works.

### 5.3 Total failure path
1. Force all providers to error.
2. Submit → `No hay ofertas disponibles.` message.

### 5.4 Campaign-off path
1. Set `CAMPAIGN_ACTIVE=false` and restart backend.
2. Submit → no campaign banner, discounted-price column shows `—`.

### 5.5 Senior wizard path
1. Visit `/wizard`.
2. Walk steps 1 → 2 → 3 with valid input. Slide-forward transitions visible.
3. Back from step 3 → slide-back transition.
4. On step 3, submit → land on results page with quotes.
5. Test viewport widths 375 / 768 / 1280 — layout adapts without horizontal scroll.

### 5.6 Documentation path
1. Visit `/api/doc` — Swagger UI renders, all four endpoints listed with examples.
2. Read `README.md` from a fresh clone; follow only its instructions to reach a green build.

---

## 6. Layer 4 — Reviewer Walk-through

A senior-engineer mental checklist, executed manually before submission:

- [ ] **First five minutes:** clone → `make help` → `make up` → form renders → quotes shown. No README-reading needed beyond `make help`.
- [ ] **Source navigation:** opening `backend/src/` reveals an obvious entry point (`UI/Http/Controller/CalculateController.php`), and tracing it forward leads cleanly into Application → Domain. No "where does X actually happen?" moments.
- [ ] **Domain isolation:** `grep -r "Symfony\\|Request" backend/src/Domain` returns **nothing**.
- [ ] **No leaked secrets:** `.env` is gitignored; `.env.example` exists.
- [ ] **Test names read like a spec:** `it_charges_an_additional_15_percent_for_commercial_use` over `testPrice1`.
- [ ] **Failure modes documented:** the README clearly states what happens if a provider fails (and a one-line example log line).
- [ ] **The PDF's senior bonus list:** parallel ✅, OpenAPI ✅, robust errors ✅, logging ✅, third provider ✅, Docker ✅; wizard ✅, transitions ✅, responsive ✅.

---

## 7. Acceptance Criteria (binary)

The project is **done** when *all* of the following are simultaneously true.

| # | Criterion |
|---|-----------|
| A1 | `make lint && make test` exits 0 on a fresh clone. |
| A2 | Coverage report shows ≥ 85% on `backend/src/{Application,Domain,Infrastructure/Provider}`; ≥ 75% on `frontend/src/{components,composables}`. |
| A3 | All PDF functional requirements (FR-B-1..4, FR-F-1..4) are demonstrably implemented. |
| A4 | All project-added requirements (PR-1..4) are present. |
| A5 | All senior-bonus items in `requirements.md §FR-B-5..7` and `FR-F-5` are present. |
| A6 | `docs/plan/` contains constitution, requirements, specification, implementation, validation, replanning — all up-to-date with the code. |
| A7 | `README.md` lets a fresh reviewer run the project with only Docker + Make installed. |
| A8 | The manual walk-through (§6) is completed by the author with a clean trace. |

---

## 8. Risks & How Validation Catches Them

| Risk | Detection layer | Detection mechanism |
|------|-----------------|--------------------|
| Pricing off-by-one at age boundary | Layer 1 | Boundary-aged unit cases |
| Provider timeout doesn't trigger | Layer 2 | Stubbed slow provider in `/calculate` test |
| Frontend leaks form state across tabs | Layer 3 | Manual E2E §5.1 step 7 |
| Cheapest highlight wrong with one quote | Layer 2 | Component test asserts highlight on single-row case |
| OpenAPI drifts from real handlers | Layer 4 | Reviewer manually compares `/api/doc` to behaviour |
| Docker setup works on author's machine only | Layer 4 | Walk-through on a fresh container/VM |

---

*Adopted: 2026-05-13. Changes go through `replanning.md`.*
