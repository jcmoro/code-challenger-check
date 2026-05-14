# Glosario — code-challenger-check24

> Términos y conceptos específicos de este proyecto y de su dominio
> (comparación de seguros de coche). Complementa, no reemplaza, el glosario
> general de la directriz [`directives/DIR_api_docs.md`](directives/DIR_api_docs.md) §17.

| Término                       | Definición                                                                                                                                  |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| **Calculate / `/calculate`**  | Endpoint principal del servicio. Recibe la información del conductor y del coche y devuelve los presupuestos de los proveedores supervivientes. |
| **Quote**                     | Cotización individual de un proveedor: `{ provider, price, discounted_price, is_cheapest }`.                                                  |
| **Provider**                  | Proveedor de seguro simulado. Tres en este proyecto: `provider-a` (JSON), `provider-b` (XML), `provider-c` (CSV).                            |
| **Provider A / B / C**        | Nombres estables de los proveedores. Cada uno tiene latencia y tasa de fallo distintas (ver `architecture/business-rules.md`).               |
| **Campaign**                  | Descuento del 5% aplicado al precio de cada quote cuando `CAMPAIGN_ACTIVE=true`. Se documenta en la respuesta como `campaign.active = true`. |
| **`discounted_price`**        | Precio que paga el cliente con el descuento de campaña aplicado. `null` si la campaña no está activa.                                        |
| **`is_cheapest`**             | Marca booleana — exactamente un quote la lleva en `true` cuando hay al menos un proveedor superviviente.                                     |
| **Final price**               | Precio efectivo para ordenación: `discounted_price` si existe, `price` en caso contrario.                                                    |
| **Failed provider**           | Proveedor cuya respuesta no produjo un quote válido. Causa: 5xx, timeout, error de parseo. Sólo se omite del resultado, no propaga error.    |
| **DriverAge**                 | Value object con la edad del conductor (0–120). Se calcula desde el `driver_birthday` y la fecha actual inyectada por `Clock`.               |
| **CarType**                   | Categoría de coche que ve el usuario: `Turismo`, `SUV`, `Compacto`.                                                                          |
| **CarForm**                   | Categoría que aceptan los proveedores A y C: `suv` o `compact`. `Turismo` → `compact`.                                                       |
| **TipoCoche**                 | Categoría del proveedor B (XML, vocabulario español): `turismo`, `suv`, `compacto`.                                                          |
| **CarUse / `car_use`**        | Tipo de uso del coche: `Privado` o `Comercial`. El backend acepta también el sinónimo en inglés `Commercial`.                                |
| **UsoCoche**                  | Vocabulario español de uso usado por el proveedor B (XML): `privado` o `comercial`.                                                          |
| **CalculateQuoteHandler**     | Caso de uso (capa application) que orquesta `QuoteFetcher` → discount → sort → mark cheapest.                                                |
| **CalculateQuoteCommand**     | DTO de entrada al handler: birthday + carType + carUse ya parseados a tipos del dominio.                                                     |
| **CalculateQuoteResult**      | DTO de salida del handler: campaign + quotes ordenados + meta (`duration_ms`, `failed_providers`).                                           |
| **QuoteFetcher**              | Interface aplicación con un único método `fetchAll(...)`. Implementación real: `ParallelQuoteFetcher`. Fake en tests: `InMemoryQuoteFetcher`. |
| **ParallelQuoteFetcher**      | Fan-out paralelo via `HttpClient::stream()` con timeout de 10 s. Marca cada respuesta como `ok` / `failed` / `timeout`.                       |
| **QuoteProvider**             | Interface (auto-tag `app.quote_provider`) con `id() + startRequest() + parseResponse()`. Implementaciones: `ProviderAClient`, `B`, `C`.       |
| **ProviderOutcome**           | Value object por proveedor: `{ providerId, outcome, durationMs }` con `outcome` ∈ `{ok, failed, timeout}`. Aparece en la línea de log estructurado. |
| **FetchResult**               | Salida de `QuoteFetcher`: `quotes[]`, `failedProviderIds[]`, `outcomes` (map por provider id).                                                |
| **CampaignProvider**          | Interface aplicación con un único método `state()`. Implementación real: `EnvCampaignProvider` (lee `CAMPAIGN_ACTIVE` y `CAMPAIGN_PERCENTAGE`). |
| **CampaignState**             | Value object con `{ active: bool, percentage: float }` y el helper `customerPaysMultiplier()`.                                               |
| **Clock**                     | Interface infraestructura para `now()` y `sleep()`. Implementación real: `SystemClock`. Fake: `FakeClock` (registra los sleeps sin esperar).  |
| **RandomnessProvider**        | Interface infraestructura para `intInRange(min, max)`. Real: `MtRandomnessProvider`. Fake: `FixedRandomnessProvider` (script de valores).    |
| **`request_id`**              | Hexadecimal de 16 caracteres generado por el handler para cada `/calculate`. Aparece en la línea JSON de log; pensado para correlar logs.    |
| **Wizard / `/wizard/...`**    | Bonus senior — flujo de 3 pasos (birthday / car_type / car_use) más una página de resultados. Usa `provide/inject` para compartir estado.    |
| **`useFormState`**            | Composable Vue 3 que mantiene los campos del formulario en un `reactive` y persiste a `sessionStorage` con debounce 200 ms.                  |
| **`useCalculate`**            | Composable Vue 3 que orquesta `submit()` → `loading` / `error` / `data` reactivos; expone `retry()` y `reset()`.                             |
| **`useSort`**                 | Composable Vue 3 que ordena `quotes[]` ascendente o descendente por `final price`, con desempate por `provider_id`.                          |
| **`QuoteResults`**            | Componente compartido por `HomePage` y `WizardResult` que renderiza banner de campaña, loading, error, tabla o empty-state.                  |
| **`SlideDirection`**          | Ref exportada por el router (`forward` / `back`) — determina la animación del `<Transition>` en el wizard según el `meta.order` de la ruta.   |
| **Make targets**              | Punto de entrada único de tareas (build, install, up, test, lint, fix, clean). Agrupados por workflow en `README.md` y en `make help`.        |
