# Changelog

> Historial de cambios significativos en el contrato HTTP, los schemas y la
> documentación. Orden cronológico inverso. El historial completo de commits
> queda en git; este fichero es el resumen legible.

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
