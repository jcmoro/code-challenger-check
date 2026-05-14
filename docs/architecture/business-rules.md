# Reglas de negocio — code-challenger-check24

> Valores y reglas observables en el comportamiento del servicio. Para la
> documentación funcional (qué hace) ver [`functional/README.md`](../functional/README.md).

## Timeouts y latencias simuladas

| Concepto                                  | Valor   | Capa                          | Significado                                                                       |
| ----------------------------------------- | ------- | ----------------------------- | --------------------------------------------------------------------------------- |
| Timeout por proveedor en `/calculate`     | 10 s    | `ParallelQuoteFetcher`        | Si un proveedor no emite chunk en 10 s, se cancela y se marca `failed`            |
| Latencia simulada provider-a              | 2 s     | `ProviderAController::sleep`   | Imita un proveedor "rápido"                                                       |
| Latencia simulada provider-b (normal)     | 5 s     | `ProviderBController::sleep`   | Imita un proveedor "lento" pero estable                                           |
| Latencia simulada provider-b (1 % spike)  | +55 s   | `ProviderBController`         | Excede el timeout de 10 s — el orquestador debe marcarlo como failed              |
| Latencia simulada provider-c              | 1 s     | `ProviderCController::sleep`   | Imita un proveedor muy rápido                                                     |

> En tests las latencias se reemplazan por `FakeClock::sleep()` que sólo
> registra el sleep pedido sin esperar — esto mantiene el suite por debajo
> de 1 segundo.

## Constantes configurables

| Variable de entorno          | Valor por defecto                     | Descripción                                              |
| ---------------------------- | ------------------------------------- | -------------------------------------------------------- |
| `CAMPAIGN_ACTIVE`            | `true`                                | Encendido/apagado del descuento de campaña               |
| `CAMPAIGN_PERCENTAGE`        | `5.0`                                 | Porcentaje del descuento (0–100)                         |
| `PROVIDER_TIMEOUT_SECONDS`   | `10`                                  | Timeout del fan-out paralelo                             |
| `PROVIDER_A_BASE_URL`        | `http://nginx/provider-a`             | URL del proveedor A                                      |
| `PROVIDER_B_BASE_URL`        | `http://nginx/provider-b`             | URL del proveedor B                                      |
| `PROVIDER_C_BASE_URL`        | `http://nginx/provider-c`             | URL del proveedor C                                      |
| `CORS_ALLOW_ORIGIN`          | `^https?://(localhost…)`              | Regex de origins permitidos para CORS                    |
| `VITE_API_BASE`              | `http://localhost:8080`               | URL desde la que el SPA hace `fetch`                     |
| `NGINX_HOST_PORT`            | `8080`                                | Puerto del host mapeado a nginx                          |
| `VITE_HOST_PORT`             | `5173`                                | Puerto del host mapeado al dev server de Vite            |

## Tasas de fallo simuladas (randomness)

Los tres controllers reciben un `RandomnessProvider::intInRange(1, 100)` y
deciden si fallar basándose en el rango devuelto. En producción es real
(`random_int`); en tests es `FixedRandomnessProvider` con valores guionizados.

| Proveedor   | Probabilidad de fallo            | Tipo de fallo                                       |
| ----------- | --------------------------------- | --------------------------------------------------- |
| provider-a  | 10 % (`intInRange(1,100) ≤ 10`)   | HTTP 500 inmediato (después del sleep)              |
| provider-b  | 1 % (`intInRange(1,100) ≤ 1`)     | Sleep extra de 55 s — fuerza timeout en orquestador |
| provider-c  | 5 % (`intInRange(1,100) ≤ 5`)     | HTTP 503 inmediato (después del sleep)              |

## Tablas de pricing

Las tres tablas viven como pure functions en `Infrastructure/Provider/*/Provider*PricingService.php`.
Los tests cubren todas las combinaciones (`tests/Infrastructure/Provider/*/*PricingServiceTest.php`).

### Provider A (JSON)

Base price: **217 €**. Vocabulario: `car_form ∈ {suv, compact}`,
`car_use ∈ {private, commercial}`.

| Edad     | Ajuste edad | Vehículo | Ajuste vehículo | Uso `commercial` |
| -------- | ----------- | -------- | --------------- | ---------------- |
| 18–24    | +70 €       | `suv`    | +100 €          | × 1.15           |
| 25–55    | +0 €        | `compact`| +10 €           | × 1.0            |
| 56+      | +90 €       |          |                 |                  |

Fórmula:
`round((BASE + ageAdjust + vehicleAdjust) * commercialMultiplier)`.

### Provider B (XML, vocabulario español)

Base price: **250 €**. Vocabulario: `TipoCoche ∈ {turismo, suv, compacto}`,
`UsoCoche ∈ {privado, comercial}`.

| Edad     | Ajuste edad | Vehículo  | Ajuste vehículo |
| -------- | ----------- | --------- | --------------- |
| 18–29    | +50 €       | `turismo` | +30 €           |
| 30–59    | +20 €       | `suv`     | +200 €          |
| 60+      | +100 €      | `compacto`| +0 €            |

**No hay multiplier commercial** para provider-b — decisión documentada en
`docs/plan/replanning.md` (entrada #4). El uso `comercial` aplica el mismo
precio que `privado`.

### Provider C (CSV)

Base price: **200 €**. Vocabulario: `car_form ∈ {suv, compact}`,
`car_use ∈ {private, commercial}`.

| Edad     | Ajuste edad | Vehículo | Ajuste vehículo | Uso `commercial` |
| -------- | ----------- | -------- | --------------- | ---------------- |
| 18–25    | +60 €       | `suv`    | +120 €          | × 1.10           |
| 26–60    | +10 €       | `compact`| +0 €            | × 1.0            |
| 61+      | +80 €       |          |                 |                  |

## Reglas de ordenación

1. **Orden ascendente** por `final price` (`discounted_price` si la campaña
   está activa; `price` en otro caso).
2. **Desempate alfabético** por `provider_id` (`provider-a < provider-b <
   provider-c`).
3. **`is_cheapest`** lo lleva exactamente el primer quote del array cuando
   `len(quotes) ≥ 1`. Cuando `len(quotes) == 0`, ningún quote lo lleva.

## Comportamiento ante errores y degradación

| Escenario                                                   | Comportamiento del orquestador                                                  | Visible para el cliente                                          |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| Proveedor devuelve 5xx                                      | Cancelar response, marcar provider `failed`, no propagar 5xx                    | `meta.failed_providers` incluye el id; 200 OK                    |
| Proveedor excede 10 s (timeout)                             | Cancelar response, marcar `timeout` en el outcome                               | Idem                                                              |
| Proveedor devuelve body no-parseable                        | Marcar `failed`, no propagar                                                    | Idem                                                              |
| **Todos** los proveedores fallan                            | Devolver 200 con `quotes: []` y `failed_providers` con los tres ids             | UI muestra "No hay ofertas disponibles."                        |
| Cliente envía body inválido                                 | Devolver 400 con `error: validation_failed` y `violations[]`                    | UI muestra los errores inline en el formulario                   |
| Cliente envía edad < 18 (calculada desde `driver_birthday`) | Idem                                                                            | Idem                                                              |
| Cliente envía `driver_birthday` futuro                      | Idem                                                                            | Idem                                                              |

## Códigos de error del dominio

| Código              | HTTP | Cuándo se produce                                                                   |
| ------------------- | ---- | ----------------------------------------------------------------------------------- |
| `validation_failed` | 400  | El DTO de `/calculate` no pasa los `Assert\*` o la edad calculada queda fuera de [18, 120] |

> No hay códigos propios para los errores 500 / 503 de proveedores — porque
> los proveedores nunca propagan al cliente: se filtran y aparecen en
> `meta.failed_providers`.
