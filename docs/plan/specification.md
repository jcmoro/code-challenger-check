# Feature Specification — CHECK24 Car Insurance Comparison

> The detailed contract for *what* gets built. Every field, every status code,
> every UI state. If `requirements.md` is the "what we must do",
> this document is the "exactly how the system behaves".

---

## 1. Domain Model

### 1.1 Value objects (backend)

```text
DriverAge        : int (≥ 0, ≤ 120)
CarType          : enum { TURISMO, SUV, COMPACTO }     (user-facing values)
CarForm          : enum { SUV, COMPACT }               (Provider A's vocabulary)
TipoCoche        : enum { TURISMO, SUV, COMPACTO }     (Provider B's vocabulary)
CarUse           : enum { PRIVATE, COMMERCIAL }        (canonical)
Money            : { amount: float (2dp), currency: ISO-4217 ("EUR") }
Quote            : { providerId: string, price: Money, discountedPrice: Money|null }
CampaignState    : { active: bool, percentage: float }
```

### 1.2 Vocabulary mapping

| User (frontend) | Canonical | Provider A `car_form` | Provider B `TipoCoche` | Provider C `car_form` |
|-----------------|-----------|----------------------|------------------------|-----------------------|
| `Turismo`       | `TURISMO` | `compact`            | `turismo`              | `compact`             |
| `SUV`           | `SUV`     | `suv`                | `suv`                  | `suv`                 |
| `Compacto`      | `COMPACT` | `compact`            | `compacto`             | `compact`             |
| `Privado`       | `PRIVATE` | `private`            | `privado`              | `private`             |
| `Commercial`/`Comercial` | `COMMERCIAL` | `commercial` | `comercial`            | `commercial`          |

The frontend label `Commercial` (English, from the PDF) is treated as
equivalent to `Comercial` for tolerance; the backend canonicalises to
`COMMERCIAL` either way.

---

## 2. HTTP API — Backend

All endpoints are versionless (the spec implies prototype). Internally,
controllers live under `App\Controller\Api\V1\` so a `/v1/` prefix can be
introduced later without code churn.

### 2.1 `POST /calculate`

**Headers**
- `Content-Type: application/json`
- `Accept: application/json`

**Request body**

```json
{
  "driver_birthday": "1992-02-24",
  "car_type": "Turismo",
  "car_use": "Privado"
}
```

| Field | Type | Constraint |
|-------|------|------------|
| `driver_birthday` | string | ISO-8601 date, `1900-01-01` ≤ value ≤ today, age 18–120 |
| `car_type` | string | one of `Turismo`, `SUV`, `Compacto` |
| `car_use` | string | one of `Privado`, `Comercial`, `Commercial` |

**Success response — 200**

```json
{
  "campaign": { "active": true, "percentage": 5.0 },
  "quotes": [
    {
      "provider": "provider-a",
      "price":  { "amount": 295.00, "currency": "EUR" },
      "discounted_price": { "amount": 280.25, "currency": "EUR" },
      "is_cheapest": true
    },
    {
      "provider": "provider-b",
      "price":  { "amount": 310.00, "currency": "EUR" },
      "discounted_price": { "amount": 294.50, "currency": "EUR" },
      "is_cheapest": false
    }
  ],
  "meta": {
    "duration_ms": 5132,
    "failed_providers": []
  }
}
```

Notes
- `quotes` is sorted by `discounted_price.amount` (or `price.amount` if no
  campaign) ascending.
- `is_cheapest` is true for **exactly one** quote when ≥ 1 quote survives.
- `failed_providers` lists provider ids that errored or timed out
  (e.g. `["provider-c"]`), so the frontend may show diagnostics.
- When **no** quotes survived, the response is still `200` with
  `quotes: []` and the failed list populated. This makes failure modes
  observable client-side without ambiguous error handling.

**Validation error — 400**
```json
{
  "error": "validation_failed",
  "violations": [
    { "field": "driver_birthday", "message": "must be a date in the past" }
  ]
}
```

**Unexpected server error — 500**
Used only for bugs in our own code, never for provider failures.

### 2.2 `POST /provider-a/quote`

**Request (JSON)**
```json
{ "driver_age": 30, "car_form": "suv", "car_use": "private" }
```

**Response (JSON, 200)**
```json
{ "price": "295 EUR" }
```

**Failure (10% chance):** `HTTP 500` with body `{ "error": "provider_a_unavailable" }`.

**Latency:** `sleep(2)` before responding.

**Pricing:**
```
price = 217
       + ageAdjust(driver_age)
       + vehicleAdjust(car_form)
price = round(price * (car_use == "commercial" ? 1.15 : 1.0), 0)
```
| Age | Adjust |
|-----|--------|
| 18–24 | +70 |
| 25–55 | +0  |
| 56+  | +90 |
| <18  | invalid (return 400) |

| `car_form` | Adjust |
|------------|--------|
| `suv`      | +100   |
| `compact`  | +10    |

### 2.3 `POST /provider-b/quote`

**Request (XML)**
```xml
<SolicitudCotizacion>
  <EdadConductor>30</EdadConductor>
  <TipoCoche>turismo</TipoCoche>
  <UsoCoche>privado</UsoCoche>
</SolicitudCotizacion>
```

**Response (XML, 200)**
```xml
<RespuestaCotizacion>
  <Precio>310.0</Precio>
  <Moneda>EUR</Moneda>
</RespuestaCotizacion>
```

**Latency:** `sleep(5)`. 1% of calls sleep an additional `55` seconds
(so the total exceeds the 10 s timeout enforced by `/calculate`).

**Pricing:**
```
price = 250
      + ageAdjust(driver_age)
      + vehicleAdjust(TipoCoche)
```
| Age | Adjust |
|-----|--------|
| 18–29 | +50 |
| 30–59 | +20 |
| 60+  | +100 |

| `TipoCoche` | Adjust |
|-------------|--------|
| `turismo`   | +30    |
| `suv`       | +200   |
| `compacto`  | +0     |

**Commercial-use uplift:** the PDF leaves this unspecified for B.
**Decision:** B has no commercial uplift. Documented as an assumption.

### 2.4 `POST /provider-c/quote` *(senior bonus, third provider, CSV)*

**Request (CSV body)**
```
driver_age,car_form,car_use
30,suv,private
```

**Response (CSV body, 200)**
```
price,currency
275,EUR
```

**Latency:** `sleep(1)`; 5% chance of HTTP 503.

**Pricing:** Base 200 €; age 18–25 +60; 26–60 +10; 61+ +80; vehicle SUV +120,
compact +0; commercial +10%. (Values picked to make C frequently — but not
always — the cheapest, so the "highlight cheapest" UI is exercised.)

### 2.5 OpenAPI

Generated via `nelmio/api-doc-bundle`, served at `GET /api/doc` (Swagger UI)
and `GET /api/doc.json` (raw spec). Includes all four endpoints, all DTOs,
and example payloads.

---

## 3. Backend Architecture

```
backend/
├── bin/console
├── composer.json
├── phpunit.xml.dist
├── phpstan.neon
├── .php-cs-fixer.php
├── config/
│   ├── packages/                   (framework, http_client, monolog, nelmio_api_doc)
│   ├── routes/                     (controllers, api_platform-free)
│   └── services.yaml
├── public/index.php
└── src/
    ├── Kernel.php
    ├── Domain/
    │   ├── Driver/Age.php
    │   ├── Car/CarType.php
    │   ├── Car/CarUse.php
    │   ├── Quote/Quote.php
    │   ├── Quote/Money.php
    │   └── Pricing/AgeBracket.php
    ├── Application/
    │   ├── Calculate/
    │   │   ├── CalculateQuoteHandler.php       # the use case
    │   │   ├── CalculateQuoteRequest.php
    │   │   └── CalculateQuoteResponse.php
    │   ├── Campaign/
    │   │   ├── CampaignProvider.php             # interface
    │   │   └── EnvCampaignProvider.php
    │   └── Provider/
    │       ├── QuoteProvider.php                # interface
    │       ├── QuoteProviderRegistry.php
    │       └── ParallelQuoteFetcher.php
    ├── Infrastructure/
    │   ├── Provider/
    │   │   ├── A/ProviderAClient.php
    │   │   ├── A/ProviderAPricingService.php    # used by /provider-a/quote controller
    │   │   ├── B/ProviderBClient.php
    │   │   ├── B/ProviderBPricingService.php
    │   │   └── C/ProviderCClient.php
    │   └── Http/HttpClientFactory.php
    └── UI/
        └── Http/
            └── Controller/
                ├── CalculateController.php
                ├── ProviderAController.php
                ├── ProviderBController.php
                └── ProviderCController.php
```

### 3.1 Key seams

- **`QuoteProvider` interface** with a single method
  `quote(CalculationRequest): ?Quote` (returns `null` on failure/timeout).
  Implementations: `ProviderAClient`, `ProviderBClient`, `ProviderCClient`.
- **`ParallelQuoteFetcher`** uses Symfony HttpClient's multiplexed `stream()`
  to fan out, applying a per-provider 10 s timeout.
- **`CampaignProvider` interface** with `isActive(): bool` and
  `percentage(): float`. Default impl reads `CAMPAIGN_ACTIVE` from env.
- **`CalculateQuoteHandler`** orchestrates fetcher + campaign + sorting.
  Has zero knowledge of HTTP frameworks.

### 3.2 Configuration

- `.env.example`
  ```
  APP_ENV=dev
  APP_SECRET=changeme
  CAMPAIGN_ACTIVE=true
  CAMPAIGN_PERCENTAGE=5.0
  PROVIDER_A_BASE_URL=http://nginx/provider-a
  PROVIDER_B_BASE_URL=http://nginx/provider-b
  PROVIDER_C_BASE_URL=http://nginx/provider-c
  PROVIDER_TIMEOUT_SECONDS=10
  CORS_ALLOW_ORIGIN=http://localhost:5173
  ```

### 3.3 Logging

- Monolog channel `calculate` → stderr JSON in containers.
- Each `/calculate` request emits one INFO line with structured fields:
  `request_id`, `duration_ms`, `providers.<id>.outcome` ∈ {ok,failed,timeout},
  `providers.<id>.duration_ms`, `campaign_active`.

---

## 4. Frontend Specification

### 4.1 Structure

```
frontend/
├── index.html
├── package.json
├── vite.config.ts
├── tsconfig.json
├── .eslintrc.cjs
├── .prettierrc
├── vitest.config.ts
└── src/
    ├── main.ts
    ├── App.vue
    ├── router/index.ts             (only used for the senior wizard)
    ├── api/
    │   ├── client.ts                (fetch wrapper, base URL, error mapping)
    │   └── calculate.ts             (typed POST /calculate)
    ├── domain/
    │   ├── types.ts                 (CalculateRequest, CalculateResponse, Quote)
    │   └── carOptions.ts
    ├── composables/
    │   ├── useFormState.ts          (sessionStorage-backed reactive form)
    │   ├── useCalculate.ts          (mutation: loading, error, data)
    │   └── useSort.ts
    ├── components/
    │   ├── form/
    │   │   ├── QuoteForm.vue
    │   │   ├── BirthdayField.vue
    │   │   ├── CarTypeField.vue
    │   │   └── CarUseField.vue
    │   ├── results/
    │   │   ├── QuoteTable.vue       (hand-coded <table>)
    │   │   ├── QuoteRow.vue
    │   │   ├── SortToggle.vue
    │   │   └── CampaignBanner.vue
    │   ├── feedback/
    │   │   ├── LoadingIndicator.vue
    │   │   ├── ErrorMessage.vue
    │   │   └── EmptyResults.vue
    │   └── wizard/                  (senior bonus)
    │       ├── StepPage.vue
    │       ├── WizardShell.vue
    │       └── transitions.css
    └── pages/
        ├── HomePage.vue             (form + results)
        └── wizard/
            ├── Step1Birthday.vue
            ├── Step2CarType.vue
            └── Step3CarUse.vue
```

### 4.2 Form behaviour

| Field | Control | Validation |
|-------|---------|------------|
| Birthday | `<input type="date">` | required; ≤ today; age 18–120; `max` attr = today, `min` attr = today-120y |
| Car type | `<select>` | required; values `Turismo`/`SUV`/`Compacto` |
| Car use | `<input type="radio" name="car_use">` | required; values `Privado`/`Comercial` |

- Errors render inline (`<small class="error">`) and are cleared on next valid blur.
- Submit button is `disabled` whenever validation fails or a request is in flight.

### 4.3 State persistence

- A single composable `useFormState` reads/writes
  `sessionStorage["quote-form-v1"]` (debounced 200 ms).
- On `mounted()` of `QuoteForm`, hydrate from sessionStorage if present.
- No `localStorage` is used anywhere, satisfying the "empty after tab close"
  requirement.

### 4.4 Result table

- Hand-coded `<table>` with `<thead>` and `<tbody>`; **no** PrimeVue / Vuetify / etc.
- Header row contains the SortToggle (a clickable button with an arrow icon)
  on the price column.
- A row is rendered with `class="row--cheapest"` (bold + star icon) when
  the API marks it `is_cheapest`.
- When `quotes.length === 0`:
  - render `<EmptyResults />` with the text `"No hay ofertas disponibles."`.

### 4.5 Campaign banner

If `campaign.active === true`:
- Show `<CampaignBanner />` above the table: a coloured strip reading
  *"¡Campaña activa! CHECK24 cubre el 5% de tu seguro."*.
- The discounted-price column is rendered with a strike-through original
  price and the discounted price emphasised.

### 4.6 Loading & errors

- `<LoadingIndicator />` overlays the result area while `useCalculate.loading`.
- Network errors → `<ErrorMessage variant="network" />` with retry button.
- HTTP 4xx → show server message if available.
- HTTP 5xx → `<ErrorMessage variant="server" />`.

### 4.7 Senior bonus wizard

- Router (`vue-router`) with three named routes `wizard.step1..3` and a final `wizard.result`.
- Each `StepX*.vue` is a single-field page; Back/Continue buttons.
- Transitions: `<RouterView v-slot="{ Component }"><Transition name="slide">…</Transition>`
  with CSS keyframes for left/right slide based on direction (forward vs back).
- Responsive: container max-widths at 640 / 1024 / 1440; touch-friendly
  controls; no fixed pixel widths on inputs.

---

## 5. Cross-Cutting Specification

### 5.1 Error semantics

| Layer | Condition | Outcome |
|-------|-----------|---------|
| Provider responds with non-2xx | classified as `failed`, excluded |
| Provider exceeds 10 s | classified as `timeout`, excluded |
| Provider returns un-parseable body | classified as `failed`, excluded |
| All providers fail | 200 OK with empty `quotes` |
| Validation of `/calculate` input | 400 with `violations` |
| Bug in our code | 500 with `error: "internal_server_error"` |

### 5.2 Sorting

Tie-breaker order if two providers report the same final price:
1. Lower `provider` id (alphabetical) — deterministic & cheap.

### 5.3 Currency

Only EUR is supported. Any non-EUR response from a provider is treated
as a `failed` quote and logged.

### 5.4 CORS

Backend allows the origin from `CORS_ALLOW_ORIGIN` only. Frontend dev
server hits `http://localhost:8080` (nginx) in dev.

### 5.5 Internationalisation

Frontend strings are Spanish, matching the PDF mocks. No `i18n` library
is added — strings live in a single `src/i18n/es.ts` map so they can be
relocated later.

---

*Adopted: 2026-05-13. Changes go through `replanning.md`.*
