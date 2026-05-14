# ADR-003 — Interface `QuoteFetcher` introducida para testabilidad

| Campo        | Valor                                                                                |
| ------------ | ------------------------------------------------------------------------------------ |
| **Estado**   | Aceptado                                                                              |
| **Fecha**    | 2026-05-13                                                                            |
| **Autores**  | Project owner                                                                         |
| **Contexto** | Cómo testear `CalculateQuoteHandler` sin tocar HTTP ni `HttpClient`                   |

---

## Contexto

`CalculateQuoteHandler` orquesta: `QuoteFetcher` → discount → sort → mark
cheapest. La implementación real (`ParallelQuoteFetcher`) hace fan-out HTTP
con `HttpClient::stream()`.

Si el handler dependiera directamente de `ParallelQuoteFetcher`:

- Tests unitarios del handler tendrían que cablear `MockHttpClient`
  con respuestas para los tres proveedores, replicando todo el flujo de
  parseo. El test cubriría dos cosas a la vez (orquestación + fetch), con la
  consiguiente fragilidad.
- `ParallelQuoteFetcher` es `final readonly class`. PHPUnit no puede crear
  un mock por herencia (ni siquiera con `getMockBuilder()->disableOriginalConstructor()`).

## Decisión

Extraer una **interface** `App\Application\Provider\QuoteFetcher` con un
único método:

```php
interface QuoteFetcher
{
    public function fetchAll(DriverAge $age, CarType $type, CarUse $use): FetchResult;
}
```

`ParallelQuoteFetcher implements QuoteFetcher`. El handler depende de la
interface. En `config/services.yaml`:

```yaml
App\Application\Provider\QuoteFetcher:
    alias: App\Application\Provider\ParallelQuoteFetcher
    public: true   # para que tests puedan reemplazarlo
```

Tests inyectan `App\Tests\Support\InMemoryQuoteFetcher` (que vive en
`tests/Support/`) con un `FetchResult` pre-armado.

## Alternativas consideradas

| Opción                                                    | Descripción                                                                | Decisión                                                                  |
| --------------------------------------------------------- | -------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| A — Handler depende de `ParallelQuoteFetcher` concreto    | Tests del handler usan `MockHttpClient` con 3 respuestas                   | ❌ Descartada: acopla dos capas en cada test                              |
| B — Hacer `ParallelQuoteFetcher` non-final                | Permitir herencia para crear `StubParallelQuoteFetcher`                    | ❌ Descartada: viola la convención de `final readonly` del resto del código |
| C — Introducir interface `QuoteFetcher`                   | Handler depende de abstracción; impl real y fake conviven                  | ✅ Elegida                                                                |

## Consecuencias

**Positivas:**

- Tests del handler quedan en 7 casos puros (~40 ms), sin ni un solo mock HTTP.
- `ParallelQuoteFetcher` tiene su propio suite con `MockHttpClient` que cubre
  fan-out, timeout, non-2xx y errores de transporte.
- Si en el futuro queremos un `CachedQuoteFetcher` o un
  `CircuitBreakerQuoteFetcher`, basta con otra implementación de la misma
  interface — el handler no cambia.

**Negativas:**

- Una indirección más en el container DI.
- Hay que recordar mantener la interface alineada con la implementación
  cuando se añadan métodos (raro, pero posible).

## Riesgos operativos

| Dependencia / Riesgo                                    | Impacto si falla                          | Comportamiento de degradación                              |
| ------------------------------------------------------- | ----------------------------------------- | ---------------------------------------------------------- |
| Alias `QuoteFetcher` no `public: true` en services.yaml | Tests no pueden reemplazar el servicio    | Detectado inmediatamente al ejecutar `make test-backend`   |
| Interfaz y impl divergen                                | Bug latente en producción                 | `php bin/console debug:container` lista métodos del alias  |

## Referencias

- `backend/src/Application/Provider/QuoteFetcher.php` (interface)
- `backend/src/Application/Provider/ParallelQuoteFetcher.php` (impl real)
- `backend/tests/Support/InMemoryQuoteFetcher.php` (fake)
- `docs/plan/replanning.md` — entrada #16.
