# ADR-002 — Fan-out paralelo via `HttpClient::stream()`

| Campo        | Valor                                                                              |
| ------------ | ---------------------------------------------------------------------------------- |
| **Estado**   | Aceptado                                                                            |
| **Fecha**    | 2026-05-13                                                                          |
| **Autores**  | Project owner                                                                       |
| **Contexto** | El endpoint `/calculate` debe llamar a tres proveedores en paralelo con timeout 10 s |

---

## Contexto

El PDF del code challenge especifica:

- Llamar a los proveedores **en paralelo** (bonus senior).
- Aplicar un **timeout de 10 segundos** por proveedor.
- **Excluir** proveedores que fallen / superen el timeout, sin propagar 5xx.

PHP es un lenguaje single-threaded por request. Para paralelizar I/O hay tres
patrones: extensiones nativas (ext-curl_multi), bibliotecas async
(ReactPHP, AMPHP) o el cliente HTTP de Symfony con `stream()`.

## Decisión

Usar **`Symfony\Contracts\HttpClient\HttpClientInterface::stream()`** para
multiplexar los tres requests sobre el mismo cliente. Patrón:

```php
foreach ($providers as $provider) {
    $response = $provider->startRequest(...);   // request(): non-blocking
    $providersByResponse->attach($response, $provider);
    $responses[] = $response;
}
foreach ($httpClient->stream($responses, 10.0) as $response => $chunk) {
    if ($chunk->isTimeout()) { ... marcar timeout ... }
    if ($chunk->isFirst())   { ... leer status, drop si non-2xx ... }
    if ($chunk->isLast())    { ... parsear y devolver Quote ... }
}
```

El interface `QuoteProvider` se diseña en **dos fases** para soportar esto:
`startRequest(): ResponseInterface` (no bloquea) + `parseResponse(ResponseInterface): ?Quote`
(lo invoca el fetcher cuando el chunk completo llega).

## Alternativas consideradas

| Opción                                          | Descripción                                                                     | Decisión                                                            |
| ----------------------------------------------- | ------------------------------------------------------------------------------- | ------------------------------------------------------------------- |
| A — Llamadas secuenciales                       | Bucle `foreach`, cada `Provider*Client->quote()` bloquea                          | ❌ Descartada: violaría el bonus senior y duraría 8 s mínimo         |
| B — `ext-curl_multi` directo                    | `curl_multi_init` + `curl_multi_exec`                                            | ❌ Descartada: más código, peor integración con DI                    |
| C — ReactPHP / AMPHP                            | Frameworks async basados en promises                                             | ❌ Descartada: añaden complejidad para un beneficio marginal aquí    |
| D — `HttpClient::stream()`                      | Multiplex nativo del cliente HTTP de Symfony                                     | ✅ Elegida                                                           |

## Consecuencias

**Positivas:**

- Una sola dependencia (`symfony/http-client`) ya en el stack.
- El timeout de 10 s es nativo (`$client->stream($responses, 10.0)`).
- Funciona sin extensiones extra (CurlHttpClient cae a NativeHttpClient).
- Testeable con `MockHttpClient` (mismo cliente con mocks).

**Negativas:**

- El timeout es "per-chunk", no "per-request total". Para nuestro caso (los
  proveedores responden en un único chunk completo) el efecto es equivalente.
- El estado per-response se gestiona con `SplObjectStorage` para mapear cada
  `ResponseInterface` con su `QuoteProvider`. Funcional pero verboso.

## Riesgos operativos

| Dependencia / Riesgo                                | Impacto si falla                              | Comportamiento de degradación                                                            |
| --------------------------------------------------- | --------------------------------------------- | ---------------------------------------------------------------------------------------- |
| `HttpClient::stream()` post-yield emite excepciones | Loop muere antes de procesar siguiente proveedor | Mitigado: en `isFirst()` leemos `getStatusCode()` para limpiar el flag de initializer (ver `docs/plan/replanning.md` #17) |
| Provider muy lento bloquea fan-out completo         | `/calculate` puede tardar hasta 10 s          | El timeout es la cota dura; resto de proveedores ya completados se conservan             |
| MockResponse destructor lanza para 5xx              | Suite de tests inestable                      | `$response->cancel()` cuando filtramos un proveedor; documentado en `troubleshooting.md` PROB-003 |

## Referencias

- `backend/src/Application/Provider/ParallelQuoteFetcher.php`
- `docs/plan/replanning.md` — entrada #17.
- `docs/plan/specification.md` §2.1 — contrato de `/calculate`.
- `docs/architecture/business-rules.md` — tabla de timeouts.
