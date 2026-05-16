# Changelog

> Historial de cambios significativos en el contrato HTTP, los schemas y la
> documentación. Orden cronológico inverso. El historial completo de commits
> queda en git; este fichero es el resumen legible.

## [2026-05-16] `WizardResult.vue`: `onMounted` async para consistencia con `WizardShell`

**Resumen:** quedaba un `void submit(...)` dentro del `onMounted` de
`WizardResult.vue` que la sweep de SonarCloud anterior no flagó (la regla
`typescript:S3735` no aplicó dentro de un lifecycle hook). Sustituido por
el patrón `onMounted(async () => { await submit(...) })`, idéntico al
fix que ya aplicamos en `WizardShell.vue`. El operador `void` queda
ausente en todo el frontend.

**Endpoints afectados:** ninguno.
**Cambios en schemas:** ninguno.
**Cobertura adicional:** ninguna (fix byte-equivalente en runtime).

**Impacto operacional:** ninguno; mejor higiene de tipos. Si en el futuro
se activa `no-floating-promises` (typescript-eslint, type-checked rule),
este sitio ya no triggerea.

---

## [2026-05-16] Frontend captura `X-Request-Id` y lo muestra en errores

**Resumen:** cierre simétrico de la entrada anterior. Backend exponía el
header pero el frontend lo ignoraba — el `request_id` solo era útil
abriendo devtools. Ahora el SPA lo captura en `ApiError` y lo muestra
en `<ErrorMessage>` para que el usuario pueda compartirlo cuando
reporta un fallo.

**Endpoints afectados:** ninguno (solo se consume el header existente
del backend).

**Cambios visibles para el cliente:**

- En cualquier error (4xx o 5xx) el componente `ErrorMessage` muestra
  ahora una línea `ID de referencia: <16-hex>` debajo del mensaje y de
  las violations, antes del botón "Reintentar".

**Cobertura adicional:** 3 tests nuevos (1 en `client.test.ts` para el
capture del header; 2 en `ErrorMessage.test.ts` para los modos con/sin
id).

**Cambios cuantitativos:**

| Sitio | Cambio |
|---|---|
| `ApiError` | nuevo campo `requestId?: string` (5º param del constructor) |
| `ApiClient.postJson()` | lee `response.headers.get('X-Request-Id')`; lo propaga a las dos ramas de error (validation/server) |
| `ErrorMessage.vue` | nuevo `<p class="error__request-id">` que solo se renderiza si el id está presente |
| `i18n/es.ts` | nueva clave `errors.requestIdLabel = 'ID de referencia'` |

**Impacto operacional:** el correlation id que el backend genera viaja
ahora ininterrumpidamente: log JSON estructurado → header HTTP → UI →
support. El usuario lee el id en su pantalla, lo comparte; support
hace `grep "request_id":"<id>"` en los logs y va directo al evento.

---

## [2026-05-16] `X-Request-Id` response header + `ValidationErrorResponse` factory

**Resumen:** dos mejoras de capa HTTP:

1. **`X-Request-Id` en headers de respuesta.** El `request_id` que el
   handler ya generaba para correlación de logs ahora también se expone
   como header HTTP. Un cliente que reporta un fallo puede compartir el id
   desde su navegador; el log line existente lo emite con el mismo valor.
2. **DRY del envelope `validation_failed`.** El shape
   `{ error: 'validation_failed', violations: [...] }` se construía en dos
   sitios independientes (`ValidationFailedListener` y
   `CalculateController::validationError()`). Extraído a una factory única
   `App\UI\Http\Response\ValidationErrorResponse` con `fromField()` y
   `fromViolations()`.

**Endpoints afectados:**

- **Modificados:** `POST /calculate` — emite ahora `X-Request-Id: <16-hex>`
  en cada respuesta 200. El body sigue idéntico bit a bit.
- **Sin cambios en envelope 400** — mismo shape; única fuente de verdad
  ahora.

**Cambios en schemas:** ninguno (los headers de respuesta no están
contemplados en el OpenAPI runtime; el spec versionable se podrá actualizar
si se decide formalizar el header en el contrato).

**Cobertura adicional:** 1 test nuevo (`testItExposesTheRequestIdAsAResponseHeader`).

**Cambios cuantitativos:**

| Sitio | Antes | Ahora |
|---|---|---|
| `CalculateController::validationError()` | método privado de 6 líneas | inyecta factory `ValidationErrorResponse` |
| `ValidationFailedListener` | construye envelope inline | inyecta factory `ValidationErrorResponse` |
| `CalculateQuoteResult` | 4 campos públicos | 5 campos públicos (`+ requestId`) |
| `CalculateQuoteHandler` | generaba `requestId` solo para log | sigue generándolo; lo expone en el result |
| `CalculateQuoteResponseFactory::fromResult()` | solo body | body + `X-Request-Id` header |

**Impacto operacional:**

- Cualquier cambio futuro del envelope 400 (ej. añadir `code`, `request_id`,
  i18n del message) toca **un único fichero** en lugar de dos.
- Support / debugging gana correlation id directo desde el browser/curl
  sin tener que cazar por timestamp+IP en los logs.

---

## [2026-05-15] `ParallelQuoteFetcher`: sentinel `Quote|true` → `?Quote`

**Resumen:** la sesión interna del fan-out usaba `Quote|true` como tipo de
los slots resueltos donde `true` significaba "rechazado". Senior smell —
mezclar primitivo (bool) como sentinel con el tipo dominio (Quote).
Sustituido por `?Quote` (`null` = rechazado), distinguiendo "aún no
resuelto" mediante `array_key_exists()` en lugar de `?? true`.

**Endpoints afectados:** ninguno (refactor interno del scratchpad
`FetchSession`).
**Cambios en schemas:** ninguno.
**Cobertura adicional:** ninguna — los tests existentes
(`ParallelQuoteFetcherTest`) ya cubrían los 3 escenarios (success,
failed, never-resolved); el cambio es semánticamente equivalente.

**Cambios:**

| Sitio | Antes | Ahora |
|---|---|---|
| `finalize()` return type | `Quote\|bool` | `?Quote` |
| `finalize()` failure return | `return true` (×2) | `return null` |
| `markResolvedFailure()` | `$resolved[$id] = true` | `$resolved[$id] = null` |
| `handleChunk()` "ya resuelto" check | `!isset(...)` | `!array_key_exists(...)` |
| `handleChunk()` outcome decision | `true === $entry ? FAILED : OK` | `null === $entry ? FAILED : OK` |
| `buildFetchResult()` | rama única con `?? true` | tres ramas explícitas |
| `FetchSession::$resolved` PHPDoc | `array<string, Quote\|true>` | `array<string, Quote\|null>` con doc de los 3 estados |

**Impacto operacional:** ninguno; la representación interna es más idiomática.

---

## [2026-05-15] Revisión senior: revierto `ProviderBXmlCodec`, narrow exception catches

**Resumen:** segunda pasada crítica sobre la extracción de codecs (entrada
anterior). Tres correcciones aplicadas:

1. **`ProviderBXmlCodec` revertido / eliminado.** Era un wrapper de 1-líneas
   sobre `XmlEncoder` (que ya es la abstracción real). El "valor" del codec —
   unificar en una librería — se logra igual inyectando `XmlEncoder`
   directamente en `ProviderBClient` y `ProviderBController`, sin clase
   intermedia, sin `@phpstan-ignore`, sin tests para casos imposibles.
2. **`catch (\Throwable)` narrowed a la exception concreta** donde la capa
   tiene contrato:
   - `ProviderAClient::parseResponse()` → `HttpClientExceptionInterface | \JsonException`
   - `ProviderBClient::parseResponse()` y `ProviderBController::__invoke()` → `NotEncodableValueException`
   - `ParallelQuoteFetcher` deja `\Throwable` (port boundary — implementaciones
     de `QuoteProvider` pueden tirar cualquier cosa por contrato).
3. **`sonar-project.properties` reformateado** — `multicriteria` con line
   continuation `\`, comentario justificativo encima de cada bloque de
   regla, cabecera explicando el contrato ("narrow, file-scoped, with
   documented rationale; not silencing real warnings").

**Endpoints afectados:** ninguno (contrato HTTP de B y C byte-for-byte
intacto; `ProviderCCsvCodec` se mantiene — ahí la duplicación CSV era real).

**Cambios en schemas:** ninguno.

**Cobertura adicional:** ninguna (el revert eliminó 7 tests del codec B
sin perder cobertura efectiva — los tests del controller y client cubren
los mismos paths a través de `XmlEncoder`).

**Cambios cuantitativos:**

| Fichero | Acción |
|---|---|
| `ProviderBXmlCodec.php` | borrado |
| `ProviderBXmlCodecTest.php` | borrado |
| `ProviderBClient` | inyecta `XmlEncoder` directo, catch específico |
| `ProviderBController` | inyecta `XmlEncoder` directo, catch específico, helper `encodeResponse()` |
| `ProviderAClient` | catch `HttpClientExceptionInterface\|\JsonException` |

**Backend tests:** 127 → 120.

**Impacto operacional:** ninguno; mejor higiene de tipos. Excepciones que
no son las esperadas dejarán de ser atrapadas silenciosamente — un
`\TypeError` o `\Error` por bug propagará en lugar de manifestarse como
"provider failed", facilitando diagnóstico.

---

## [2026-05-15] Codecs unificados para Provider B (XML) y Provider C (CSV)

**Resumen:** dos formatos de wire estaban implementados en dos sitios cada
uno (cliente + controller-side), y para B con dos librerías distintas
(`simplexml_load_string` en el cliente vs. Symfony `XmlEncoder` en el
controller). Extracción a 2 codecs reusables — un único punto de verdad
por formato.

**Ficheros nuevos:**

- `App\Infrastructure\Provider\B\ProviderBXmlCodec` — `decode(string): ?array`
  + `encode(string $rootNode, array): string`. Wrapper sobre `XmlEncoder` con
  manejo de errores idéntico en ambas direcciones.
- `App\Infrastructure\Provider\C\ProviderCCsvCodec` — `decodeRow(string): ?array`
  + `encodeRow(array): string`. Format-only: 2 filas (header + datos).

**Endpoints afectados:** ninguno (contratos B y C byte-for-byte idénticos).
**Cambios en schemas:** ninguno.

**Cobertura adicional:** 14 unit tests (7+7) cubren happy path, body
malformado, líneas en blanco, columnas desbalanceadas, roundtrip
encode↔decode. Sin arrancar kernel.

**Cambios cuantitativos:**

| Fichero | Líneas (antes → ahora) |
|---|---|
| `ProviderBClient` | 70 → 60 |
| `ProviderCClient` | 81 → 64 |
| `ProviderBController` | 95 → 91 |
| `ProviderCController` | 90 → 75 |

**Impacto operacional:** ninguno; futura evolución de cualquiera de los
dos formatos toca **un único fichero**. El cliente y el controller no
pueden diverger.

---

## [2026-05-15] `CalculateQuoteResponseFactory` extraído del controller

**Resumen:** la serialización JSON del `/calculate` 200 (`serializeResult` +
`serializeQuote` + `serializeMoney` + flag `JSON_PRESERVE_ZERO_FRACTION`)
sale del `CalculateController` a un nuevo servicio `App\UI\Http\Response\CalculateQuoteResponseFactory`.
El controller queda en 84 líneas (139 antes) y se limita a parsear el DTO,
invocar el handler, traducir `\DomainException` a 400, y delegar el 200 al factory.

**Endpoints afectados:** ninguno (contrato JSON idéntico bit a bit).
**Cambios en schemas:** ninguno.
**Cobertura adicional:** 5 unit tests dedicados al factory (`is_cheapest`
solo para el primero, `discounted_price` null vs presente, preservación
de `5.0`, `percentage` redondeado a 2 decimales, `failed_providers` y
`durationMs` propagados a `meta`, `quotes:[]` no degenera a `null`).

**Impacto operacional:** ninguno; refactor estructural. Tests del factory
locks the wire format independientemente del controller, así que un
futuro cambio del contrato falla rápido y donde corresponde.

---

## [2026-05-15] Provider simulators — regla de negocio fuera del controller

**Resumen:** los 3 controllers de provider (A/B/C) violaban la
constitución §88 ("no business rule lives in a controller") al ejecutar
el sleep de latencia + roll de error rate inline. Extracción a 3 servicios
nuevos `App\Infrastructure\Provider\{A,B,C}\Provider{A,B,C}Simulator` que
encapsulan el comportamiento simulado (PDF §1.2). Cada controller queda
como format-adapter puro: parse input → call simulator → format output.

**Endpoints afectados:** ninguno (contrato HTTP de cada provider intacto).
**Cambios en schemas:** ninguno.

**Cambios cuantitativos:**

| Controller | Líneas (antes → ahora) |
|---|---|
| `ProviderAController` | 59 → 42 |
| `ProviderBController` | 109 → 97 |
| `ProviderCController` | 100 → 89 |

**Cobertura adicional:** 9 unit tests nuevos (3 por simulator) ejercitan
las latencias exactas y los rolls de boundary sin arrancar el kernel
(antes había que pasar por `WebTestCase` para verificarlo). Los
`Provider{A,B,C}ControllerTest` (HTTP integration) se mantienen sin
cambios y siguen pasando.

**Impacto operacional:** ninguno; mejora la testabilidad y la cohesión.

---

## [2026-05-15] Sweep de code smells alineado con SonarCloud

**Resumen:** primera pasada con SonarCloud (vía SonarQube for IDE +
scanner CLI) dejó 25 code smells. Sweep dirigido a 0 issues:

| Categoría | Acción |
|---|---|
| `php:S1192` | `'application/xml'` (×4) en `ProviderBController` → `const CONTENT_TYPE_XML` |
| `Web:S6819` (a11y) | `<aside role="status">` y `<p role="status">` en `CampaignBanner`, `LoadingIndicator`, `EmptyResults` → `<output>` (rol implícito + semántica nativa) |
| `typescript:S3735` | `void router.push(...)` en `WizardShell` → `async / await router.push(...)` |
| `typescript:S3358` | Ternario anidado en `useCalculate.ts` extraído a `if/else` con variable intermedia |
| `typescript:S7735` | `cmp !== 0 ? cmp : tieBreak` en `useSort` → `cmp === 0 ? tieBreak : cmp` |
| `typescript:S7764` + `S7741` | `typeof window !== 'undefined' ? window.sessionStorage : undefined` en `useFormState` → helper `defaultStorage()` con `globalThis.sessionStorage ?? undefined` |
| `typescript:S4325` | 2 `as` redundantes en `useFormState.hydrate()` eliminados |
| `typescript:S5914` | `frontend/tests/smoke.test.ts` (`expect(1+1).toBe(2)`) borrado — 100+ tests reales prueban que el runner funciona |
| `php:S116` | Snake_case en DTOs (`$driver_birthday`, `$car_type`, `$car_form`...) suprimido vía multicriteria — son contract con la API |
| `php:S1142` | "demasiados returns" en provider controllers/clients suprimido vía multicriteria — son guard clauses idiomáticas para input no confiable |

**Endpoints afectados:** ninguno.
**Cambios visibles para el cliente:** los 3 componentes Vue (`CampaignBanner`,
`LoadingIndicator`, `EmptyResults`) renderizan ahora `<output>` en lugar
de `<aside>` / `<p>` con `role="status"`. Cambio puramente DOM/a11y.

**Impacto operacional:** ninguno. Tests `CampaignBanner` y `LoadingIndicator`
adaptados para asertar `tagName === 'OUTPUT'` en vez de `attributes('role')`.

---

## [2026-05-15] Integración de SonarCloud (CI-on-demand vía Docker)

**Resumen:** scanner local en Docker con coverage real (PHP Clover via
pcov + JS LCOV via vitest/v8) subiendo a SonarCloud bajo demanda. Sin
GitHub Actions todavía — el flujo es `make coverage && make sonar` con
un `SONAR_TOKEN` en env.

**Ficheros nuevos:**

- `sonar-project.properties` — config monorepo (`sources=backend/src,frontend/src`,
  `tests=backend/tests,frontend/tests`, exclusions estándar). Multicriteria
  ignore para `php:S116` en DTOs y `php:S1142` en provider adapters
  (decisiones documentadas en replanning #26).

**Cambios en infra:**

- `docker/php/Dockerfile`: `pecl install pcov` + `docker-php-ext-enable pcov`.
- `docker/php/php.ini`: `pcov.enabled=1`, `pcov.directory=/app/src`.
- `frontend/vitest.config.ts`: añadido reporter `lcov` a `coverage.reporter`.
- `Makefile`: nuevos targets `coverage`, `coverage-backend`, `coverage-frontend`,
  `sonar`. `coverage-backend` post-procesa el `clover.xml` con `sed` para
  remapear paths del container (`/app/...`) a relativos (`backend/...`)
  para que SonarCloud los resuelva.
- `docker-compose.override.yml`: removido el named volume
  `frontend_node_modules` para que el host vea los `node_modules` y el
  IDE pueda resolver `vite/client`, `@vue/tsconfig`, etc. Coste: I/O un
  poco más lento en macOS (irrelevante con Docker Desktop + VirtioFS).

**Estado al cierre:** 0 bugs · 0 vulnerabilities · 0 code smells · 0
hotspots · coverage 88.5% (line 90.7%, branch 79.7%) · duplication 0.0% ·
quality gate **PASSED** (New Code definition = "Number of days = 1"
para que la fase de bootstrap no contamine el threshold del 80%).

---

## [2026-05-15] Pago de deuda técnica: regla de negocio al dominio

**Resumen:** la regla "el conductor debe tener al menos 18 años" sube de la
capa UI al dominio. Nueva excepción tipada `App\Domain\Driver\UnderageDriverException`
y nuevo método `DriverAge::assertInsurable()`. El `CalculateController` se
simplifica de 32 → 16 líneas en el `__invoke` (cero lógica de negocio,
cero acoplamiento a `Clock`).

**Endpoints afectados:**

- **Modificados:** `POST /calculate` — el mensaje del 400 ahora viene del
  dominio. La forma del response sigue siendo idéntica
  (`{error: "validation_failed", violations: [{field, message}]}`).

**Cambios en schemas:** ninguno (mismo `responses/problem-details.json`).

**Cambios visibles para el cliente:**

| Caso | Mensaje antes | Mensaje ahora |
|---|---|---|
| Conductor < 18 años | `"driver must be at least 18 years old"` (literal) | `"Driver must be at least 18 years old, got N."` (incluye edad real) |
| Cumpleaños futuro | `"must be a date in the past"` (literal) | `"Birthday cannot be in the future."` (mensaje del dominio) |

**Impacto operacional:** ninguno; la lógica está mejor localizada y los
mensajes son más informativos. Cualquier futuro caller del handler
(CLI command, otro use case) hereda la validación gratis sin re-implementarla.

---

## [2026-05-15] Refactor `ParallelQuoteFetcher::fetchAll()` (114 → 12 líneas)

**Resumen:** el método único que fanout-eaba los 3 proveedores se parte en
3 fases nombradas — `startAllRequests`, `processStreamedResponses`,
`buildFetchResult` — más helpers (`handleChunk`, `isSuccessfulStatus`,
`markResolvedFailure`). Estado mutable compartido entre fases en una nueva
clase interna `FetchSession` (`@internal`, mismo fichero). El fetcher sigue
siendo `final readonly` y stateless.

**Endpoints afectados:** ninguno.
**Cambios en schemas:** ninguno.
**Cobertura adicional:** ninguno (los tests existentes siguen pasando sin
cambios — la refactorización es comportamiento-equivalente).

**Impacto operacional:** la complejidad cognitiva del método baja de 32 a ~3
(SonarLint deja de marcarlo). Mantenibilidad mejorada.

---

## [2026-05-15] Composable `validateBirthday` (frontend, DRY)

**Resumen:** lógica duplicada de validación de fecha de nacimiento entre
`QuoteForm.vue` y `Step1Birthday.vue` extraída a `frontend/src/domain/birthdayValidation.ts`
(función pura). Constantes `MIN_AGE = 18` y `MAX_AGE = 120` exportadas.
Suite Vitest dedicada de 14 casos cubre bordes exactos (hoy − 18 años, hoy − 18 + 1 día, etc.).

**Endpoints afectados:** ninguno (frontend).
**Cambios en schemas:** ninguno.

**Impacto operacional:** un cambio futuro en la regla "≥ 18" toca **un único
fichero** del frontend en lugar de dos.

---

## [2026-05-15] DI cleanup: env vars y tagged services al `services.yaml`

**Resumen:** se elimina `#[Autowire('%env(...)%')]` de los 6 sitios donde
aparecía y se sustituye por `bind:` per-class scoped en `services.yaml`.
También se mueve `#[AutoconfigureTag]` de la interfaz `QuoteProvider` al
bloque `_instanceof` en YAML, junto con el `#[AutowireIterator]` del
fetcher (`!tagged_iterator`). Resultado: los constructors quedan PHP puro
sin imports `Symfony\Component\DependencyInjection\Attribute\*` en clases
de aplicación. Atributos PHP retenidos sólo para `#[WithMonologChannel]`
(pattern explícito en CLAUDE.md).

**Logger del canal `calculate`** ahora se inyecta vía
`#[WithMonologChannel('calculate')]` sobre la clase (Monolog upstream,
no Symfony Bridge). El parámetro `LoggerInterface $logger` ya no tiene
default `NullLogger` — Symfony siempre resuelve.

**Endpoints afectados:** ninguno.
**Cambios en schemas:** ninguno.

**Impacto operacional:** ninguno en runtime (el log JSON sigue saliendo en
el canal `calculate`). Refactor interno de mantenibilidad.

---

## [2026-05-15] PHPStan nivel 8 → 10 (el más estricto)

**Resumen:** se sube PHPStan al máximo nivel disponible. Cambios necesarios:
`SplObjectStorage` tipado con generics (`<ResponseInterface, QuoteProvider>`),
`@phpstan-type` aliases en `CalculateControllerTest` para tipar
`responseJson()`, anotaciones `@var` en 3 sites de `json_decode`. Documentado
en `docs/plan/replanning.md` #25.

**Endpoints afectados:** ninguno.
**Cambios en schemas:** ninguno.

**Impacto operacional:** la red de seguridad estática captura ahora
violaciones de `mixed`. Coste futuro: cualquier `json_decode` o boundary
sin tipar romperá `make stan`.

---

## [2026-05-14] Adopción de la directriz DIR_api_docs

**Resumen:** se adopta el modelo de documentación técnica de la directriz
`docs/directives/DIR_api_docs.md`. Se generan los ficheros obligatorios bajo
`docs/specs/`, `docs/architecture/`, `docs/database/`, `docs/functional/` y
`docs/operations/`. Las capturas de pantalla se trasladan a `docs/_assets/`.

**Endpoints afectados:** ninguno.

**Cambios en schemas:** primera publicación de los JSON Schemas que documentan
el contrato.

**Impacto operacional:** ninguno — sólo documentación.

---

## [2026-05-14] Fase 8 — Pulido y entrega

**Resumen:** sweep de código muerto, README rewrite con tabla agrupada de make
targets, registro de 14 nuevas entradas en `docs/plan/replanning.md` que
documentan las desviaciones de las Fases 1-7.

**Endpoints afectados:** ninguno.

---

## [2026-05-14] Fase 7 — Wizard + responsive (senior bonus)

**Resumen:** se añade el wizard de 3 pasos con transiciones iOS-style en
`/wizard`. El backend no cambia; el frontend reutiliza el handler vía
`provide/inject` de `useFormState` + `useCalculate`. Layout responsive en tres
breakpoints (480 / 640 / 960).

**Endpoints afectados:** ninguno.

**Cambios en schemas:** ninguno.

---

## [2026-05-14] Fase 4 — OpenAPI runtime + logging estructurado

**Resumen:** se registra `NelmioApiDocBundle` en `bundles.php`; añadidas
anotaciones `#[OA\*]` en controllers. Swagger UI en `/api/doc`, OpenAPI JSON en
`/api/doc.json`. Nueva canal Monolog `calculate` que emite una línea JSON por
request a stderr con `request_id`, `duration_ms`, `campaign_active` y
`providers[].outcome` (`ok` / `failed` / `timeout`).

**Endpoints afectados:**
- **Añadidos:** `GET /api/doc`, `GET /api/doc.json`.

**Cambios en schemas:** ninguno (el contrato de `/calculate` no cambió).

**Impacto operacional:** los logs de `/calculate` cambian de formato a JSON
estructurado; los consumidores de log deben adaptarse.

---

## [2026-05-13] Fase 3 — `/calculate` operativo

**Resumen:** primera versión del endpoint orquestador. Fan-out paralelo a los
tres proveedores via `HttpClient::stream()`, timeout de 10 s por proveedor,
descuento del 5% si `CAMPAIGN_ACTIVE=true`, ordenación ascendente por precio
final, marcado del más barato.

**Endpoints afectados:**
- **Añadidos:** `POST /calculate`.

**Cambios en schemas:** añadidos `CalculateRequest` y `CalculateResponse`
(definidos como DTO Symfony, reflejados ahora en `docs/specs/schemas/`).

**Impacto operacional:** sin impacto previo (primera entrega).

---

## [2026-05-13] Fase 2 — endpoints de los proveedores simulados

**Resumen:** se añaden los tres endpoints simulados con latencias y tasas de
fallo: provider-a (JSON, 2 s, 10% 500), provider-b (XML, 5 s, 1% +55 s),
provider-c (CSV, 1 s, 5% 503). Inyección de `Clock` y `RandomnessProvider`
para tests deterministas.

**Endpoints afectados:**
- **Añadidos:** `POST /provider-a/quote`, `POST /provider-b/quote`,
  `POST /provider-c/quote`.

---

## [2026-05-13] Fase 0 — bootstrap

**Resumen:** estructura del repositorio creada. Docker + Makefile, Symfony 7.3
+ PHP 8.4, Vue 3 + TS + Vite. `make test` y `make lint` operativos con un
smoke test en cada lado.

**Endpoints afectados:** ninguno (entrega vacía).
