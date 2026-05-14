# Troubleshooting — code-challenger-check24

> Base de conocimiento de problemas resueltos durante el desarrollo del
> servicio. Cada entrada documenta causa raíz, solución aplicada y
> prevención. Orden cronológico inverso.

## Tabla de contenidos

- [PROB-001: `Could not find service "test.service_container"`](#prob-001-could-not-find-service-testservice_container)
- [PROB-002: `XmlEncoder` no autowireable](#prob-002-xmlencoder-no-autowireable)
- [PROB-003: `MockResponse::__destruct` lanza para 5xx](#prob-003-mockresponse__destruct-lanza-para-5xx)
- [PROB-004: `5.0` se serializa como `5` en la respuesta JSON](#prob-004-50-se-serializa-como-5-en-la-respuesta-json)
- [PROB-005: Vue Test Utils — `tests/wizard/Wizard.test.ts` con `vi.mock` hoisteado](#prob-005-vitest-vimock-hoisteado-rompe-tests-del-wizard)
- [PROB-006: Lint del frontend: imports de TS no resolubles desde tests](#prob-006-lint-del-frontend-imports-de-ts-no-resolubles-desde-tests)

---

## PROB-001: `Could not find service "test.service_container"`

**Fecha:** 2026-05-13
**Severidad:** Alta — bloqueaba toda la suite de WebTestCases.
**Componente afectado:** Tests backend (Fase 2).

**Síntomas:**

```
Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException:
  You have requested a non-existent service "test.service_container".
```

Ocurre en `static::getContainer()` al intentar reemplazar un servicio en un
WebTestCase.

**Causa raíz:**

`KernelTestCase::createKernel()` lee la variable de entorno **`$_ENV['APP_ENV']`
antes de `$_SERVER['APP_ENV']`**. Mi `phpunit.dist.xml` sólo declaraba
`<server name="APP_ENV" value="test">`, que sólo modifica `$_SERVER`.
`Dotenv::bootEnv()` carga `.env` (donde `APP_ENV=dev`) y popula `$_ENV` con
`dev`, así que el kernel arrancaba en `dev` y no tenía el servicio
`test.service_container`.

**Solución aplicada:**

Añadir `<env name="APP_ENV" value="test" force="true"/>` además del `<server>`
existente en `backend/phpunit.dist.xml`:

```xml
<env name="APP_ENV" value="test" force="true"/>
<env name="SHELL_VERBOSITY" value="-1" force="true"/>
<server name="APP_ENV" value="test" force="true"/>
<server name="SHELL_VERBOSITY" value="-1" force="true"/>
```

**Prevención:**

- Documentado en `docs/plan/replanning.md` entrada #11.
- Cualquier futuro `.env.test` debe declarar explícitamente `APP_ENV=test` (aunque
  redundante con la línea de phpunit), para defensa en profundidad.

**Referencias:**

- `vendor/symfony/framework-bundle/Test/KernelTestCase.php` línea ~120.

---

## PROB-002: `XmlEncoder` no autowireable

**Fecha:** 2026-05-13
**Severidad:** Media — bloqueaba el arranque del backend tras Fase 2.
**Componente afectado:** `ProviderBController` y el container DI.

**Síntomas:**

```
Cannot autowire service "App\UI\Http\Controller\ProviderBController":
  argument "$xml" of method "__construct()" references class
  "Symfony\Component\Serializer\Encoder\XmlEncoder" but no such service
  exists.
```

**Causa raíz:**

El componente `symfony/serializer` autoregistra el `Serializer` agregador
pero **no las clases concretas de encoders** (`XmlEncoder`, `JsonEncoder`,
`CsvEncoder`). El controller pedía la concreta y el container no la conocía.

**Solución aplicada:**

Registrar el encoder explícitamente en `config/services.yaml`:

```yaml
services:
    # XML encoder/decoder used by Provider B's controller.
    Symfony\Component\Serializer\Encoder\XmlEncoder: ~
```

**Prevención:**

- Antes de inyectar una clase concreta del namespace `Symfony\Component\…`,
  verificar con `php bin/console debug:container <FQCN>` que el container la
  conoce.
- Si no la conoce, registrarla en `services.yaml` con `: ~` (autowire) o
  documentar la razón.

**Referencias:**

- `docs/plan/replanning.md` entrada #14.

---

## PROB-003: `MockResponse::__destruct` lanza para 5xx

**Fecha:** 2026-05-13
**Severidad:** Alta — el suite del fetcher era inestable.
**Componente afectado:** Tests de `ParallelQuoteFetcher`.

**Síntomas:**

Tras un test que usaba `new MockResponse('', ['http_code' => 503])`, el
runner mostraba:

```
HTTP 503 returned for "http://nginx/provider-c/quote"
  /vendor/symfony/http-client/Response/CommonResponseTrait.php:166
  Symfony\Component\HttpClient\Response\MockResponse->doDestruct()
  PHPUnit\Framework\TestCase->run()
```

El error ocurría **después** del último assert, durante la destrucción del
objeto de test.

**Causa raíz:**

`MockResponse::doDestruct()` llama a `checkStatusCode()` cuando el response
no ha sido "consumido" (sin lectura de body, sin `cancel()`). Para códigos
≥ 3xx eso lanza `ServerException` / `ClientException` por diseño — la idea
es proteger al usuario de olvidar comprobar el código.

Mi fetcher leía sólo el `getStatusCode()` en `isFirst()` y decidía descartar
el proveedor; el body nunca se leía y `cancel()` nunca se llamaba.

**Solución aplicada:**

En `ParallelQuoteFetcher::fetchAll()`, cuando un response es non-2xx, llamar
`$response->cancel()` además de registrar el outcome `failed`. Lo mismo
cuando el body no se puede parsear:

```php
if ($statusCode < 200 || $statusCode >= 300) {
    $resolved[$providerId] = true;
    $outcomes[$providerId] = $this->outcome($providerId, ProviderOutcome::FAILED, …);
    $response->cancel();         // ← clave
    continue;
}
```

**Prevención:**

- Cualquier código futuro que use `HttpClient::stream()` y filtre responses
  por status debe llamar `cancel()` para que el destructor no proteste.
- Añadir un assert explícito en los tests del fetcher para detectar este
  caso (`expect($outcome->providerId)->toBe(...)` ya no es suficiente).

**Referencias:**

- `docs/plan/replanning.md` entrada #17.
- `backend/src/Application/Provider/ParallelQuoteFetcher.php`.

---

## PROB-004: `5.0` se serializa como `5` en la respuesta JSON

**Fecha:** 2026-05-13
**Severidad:** Baja — la spec mostraba `5.0` y mi test lo verificaba.
**Componente afectado:** `CalculateController` (Fase 3).

**Síntomas:**

```
Failed asserting that 5 is identical to 5.0.
```

El test del controller compara el campo `campaign.percentage` con `5.0`
(float) y recibe `5` (int).

**Causa raíz:**

`json_encode()` de PHP descarta el `.0` de los floats con valor entero. Es
una optimización por defecto. La spec del code challenge muestra
`"percentage": 5.0`, así que necesitamos preservar la fracción.

**Solución aplicada:**

Construir el `JsonResponse` vacío, fijar `encodingOptions` y luego llamar
`setData()` para que el primer encode use el flag:

```php
$response = new JsonResponse();
$response->setEncodingOptions(
    $response->getEncodingOptions() | \JSON_PRESERVE_ZERO_FRACTION
);
$response->setData($this->serializeResult($result));
return $response;
```

**Prevención:**

- Cualquier endpoint que devuelva floats con valores enteros (`0.0`, `5.0`,
  `100.0`) y la spec quiera preservarlos, repetir el mismo patrón.
- Tests pueden comparar `assertEquals(5.0, $value, …, $delta=0.001)` si el
  type-strict no es importante.

**Referencias:**

- `docs/plan/replanning.md` entrada #18.

---

## PROB-005: Vitest `vi.mock` hoisteado rompe tests del wizard

**Fecha:** 2026-05-14
**Severidad:** Baja — sólo afectaba al primer intento de los tests del wizard.
**Componente afectado:** `frontend/tests/wizard/Wizard.test.ts`.

**Síntomas:**

```
ReferenceError: Cannot access 'slideDirection' before initialization
```

**Causa raíz:**

Tenía:

```ts
const slideDirection = ref<...>('forward');
vi.mock('@/router', () => ({ slideDirection }));
```

Vitest **hoistea `vi.mock(...)` al inicio del fichero**, antes de los
imports y las declaraciones. La factory intenta usar `slideDirection`
antes de que esté inicializada.

**Solución aplicada:**

Eliminar el `vi.mock` y dejar que el test importe el módulo real `@/router`.
El test crea su propio router en memoria (`createMemoryHistory()`) y los
guards del router real no afectan al router del test.

**Prevención:**

- Evitar `vi.mock` con factory que cierre sobre variables del módulo.
- Si el mock es imprescindible, usar `vi.hoisted()` para declarar las
  variables explícitamente arriba.

**Referencias:**

- `frontend/tests/wizard/Wizard.test.ts`.

---

## PROB-006: Lint del frontend — imports de TS no resolubles desde tests

**Fecha:** 2026-05-14
**Severidad:** Baja — sólo afectaba al primer arranque del lint.
**Componente afectado:** `frontend/tsconfig.app.json`.

**Síntomas:**

ESLint y `vue-tsc` rechazaban los imports en `frontend/tests/**/*.ts`
porque la ruta `@/...` no se resolvía.

**Causa raíz:**

El `tsconfig.app.json` original sólo incluía `src/**/*` en su `include`.
Los ficheros de `tests/` quedaban fuera del scope del path alias `@/`.

**Solución aplicada:**

En `tsconfig.app.json`:

```json
{
  "compilerOptions": {
    "baseUrl": ".",
    "paths": { "@/*": ["./src/*"] }
  },
  "include": ["src/**/*.ts", "src/**/*.tsx", "src/**/*.vue", "tests/**/*.ts"]
}
```

Mismo alias replicado en `vite.config.ts` y `vitest.config.ts`.

**Prevención:**

- Cuando se añada una nueva carpeta de código a typecheckear (e.g. `e2e/`),
  incluirla explícitamente en `tsconfig.app.json` y en `vitest.config.ts`.

**Referencias:**

- `frontend/tsconfig.app.json`.
- `frontend/vitest.config.ts`.
