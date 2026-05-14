# Runbook — code-challenger-check24

> Guía operacional para diagnosticar y resolver incidencias del servicio.

## Diagnóstico rápido

```bash
# Estado de los tres contenedores
make ps

# Logs en vivo (todos los servicios)
make logs

# Logs sólo del backend
docker compose logs -f backend

# Healthcheck rápido
curl -i http://localhost:8080/api/doc.json | head -5     # debería ser 200
curl -i http://localhost:5173/                         | head -5     # debería ser 200
```

Si los tres servicios están `Up`, hay 4 frentes a inspeccionar:

1. Puertos disponibles en el host (8080, 5173).
2. Variables de entorno (`backend/.env`, root `.env` para compose).
3. Cache de Symfony (`backend/var/cache/`).
4. `node_modules` (volumen nombrado `frontend_node_modules`).

---

## INC-001: `/calculate` devuelve `quotes: []` constantemente

**Síntomas:**

- `POST /calculate` siempre devuelve 200 con `quotes: []` y los tres ids en
  `meta.failed_providers`.

**Diagnóstico:**

```bash
# 1. ¿Los proveedores responden por sí mismos?
curl -X POST http://localhost:8080/provider-a/quote \
  -H "Content-Type: application/json" \
  -d '{"driver_age":30,"car_form":"suv","car_use":"private"}'

# 2. ¿El backend resuelve el host de los proveedores?
docker compose exec backend sh -c "curl -i http://nginx/provider-a/quote -X POST -H 'Content-Type: application/json' -d '{\"driver_age\":30,\"car_form\":\"suv\",\"car_use\":\"private\"}' | head -5"

# 3. ¿La línea de log JSON muestra qué falló?
docker compose logs --tail=10 backend | grep calculate_completed | tail -1
```

**Causas comunes:**

| Causa                                                | Diagnóstico                                                  | Acción                                                     |
| ---------------------------------------------------- | ------------------------------------------------------------ | ---------------------------------------------------------- |
| Mala URL de proveedores en `.env`                    | `PROVIDER_*_BASE_URL` no apunta a `http://nginx/provider-*`  | Restaurar `.env` desde `.env.example`                       |
| nginx caído                                          | `make ps` muestra `c24-nginx` en estado distinto a `Up`      | `docker compose restart nginx`                              |
| Llamadas saliendo a internet (DNS roto)              | `docker compose exec backend curl http://nginx/...` falla    | Verificar red `c24` (`docker network ls`)                   |

**Resolución:**

| Paso | Acción                                                            |
| ---- | ----------------------------------------------------------------- |
| 1    | `make down && make up-d`                                          |
| 2    | Verificar `.env` (especialmente `PROVIDER_*_BASE_URL`)            |
| 3    | Si persiste, `make clean` y volver a `make build && make install && make up-d` |

---

## INC-002: `/calculate` tarda más de 10 s

**Síntomas:**

- Las requests al `/calculate` superan los ~7 s habituales (esperados por la
  latencia simulada de provider-b).
- El frontend muestra el spinner durante > 10 s y eventualmente recibe el
  resultado con `provider-b` en `failed_providers`.

**Diagnóstico:**

```bash
docker compose logs --tail=5 backend | grep calculate_completed | tail -1
```

Inspeccionar `providers.provider-b.outcome`. Si es `timeout`, el flujo se
está comportando como diseñado (1 % de spikes de 55 s).

**Causas comunes:**

| Causa                                                | Diagnóstico                                              | Acción                                                            |
| ---------------------------------------------------- | -------------------------------------------------------- | ----------------------------------------------------------------- |
| Spike aleatorio de provider-b (1 % por diseño)       | `outcome=timeout`, `duration_ms=10000`                   | Ninguna — es el comportamiento esperado                            |
| `PROVIDER_TIMEOUT_SECONDS` mal configurado           | `outcome=timeout` en todos                               | Restaurar a `10` en `.env`                                         |
| Contenedor backend bajo carga (CPU saturada)         | `docker stats c24-backend` muestra > 90 % CPU            | Investigar otros procesos en el host                                |

---

## INC-003: `make up-d` falla con "port is already allocated"

**Síntomas:**

```
Error response from daemon: failed to set up container networking:
Bind for 0.0.0.0:8080 failed: port is already allocated
```

**Diagnóstico:**

```bash
docker ps --filter "publish=8080" --format "{{.Names}}\t{{.Image}}\t{{.Ports}}"
lsof -i :8080 | head -5
```

**Resolución:**

| Paso | Acción                                                                      |
| ---- | --------------------------------------------------------------------------- |
| 1    | Identificar qué proceso ocupa el puerto                                     |
| 2a   | Si es otro contenedor Docker: pararlo o cambiar nuestro puerto              |
| 2b   | Reasignar nuestro puerto: `NGINX_HOST_PORT=8081 make up-d`                  |
| 3    | Si cambiamos el puerto, ajustar `VITE_API_BASE` para que el SPA lo conozca  |

---

## INC-004: cache de Symfony corrupta tras cambio de config

**Síntomas:**

- Tests fallan con errores tipo `Could not find service "X"` aunque el
  servicio claramente exista en `config/services.yaml`.
- `php bin/console debug:container X` no lo encuentra.
- Cambios recientes en `config/` (bundles.php, services.yaml, monolog.yaml).

**Diagnóstico:**

```bash
docker compose exec backend sh -c "ls var/cache/"
```

**Resolución:**

| Paso | Acción                                                                         |
| ---- | ------------------------------------------------------------------------------ |
| 1    | `docker compose exec backend sh -c "rm -rf var/cache/*"`                       |
| 2    | `docker compose exec backend php bin/console cache:warmup`                     |
| 3    | `docker compose exec backend php bin/console cache:warmup --env=test`          |
| 4    | Reintentar la operación                                                        |

---

## Operaciones rutinarias

```bash
# Limpiar cache de Symfony sin parar el stack
docker compose exec backend php bin/console cache:clear

# Reiniciar sólo el backend
docker compose restart backend

# Ver routes registradas
docker compose exec backend php bin/console debug:router

# Ver servicios autowireados
docker compose exec backend php bin/console debug:container --tag=app.quote_provider

# Forzar regeneración del spec OpenAPI runtime (no hay generación versionable automática)
curl http://localhost:8080/api/doc.json > /tmp/runtime-spec.json
diff /tmp/runtime-spec.json docs/specs/openapi/v1/openapi.yaml  # comparación manual
```

## SLOs de referencia

El proyecto **no tiene SLOs formales** — es un code challenge. Para una
versión productiva:

| Métrica                                          | Objetivo razonable                          |
| ------------------------------------------------ | ------------------------------------------- |
| Latencia p95 de `/calculate`                     | ≤ 7 s (gateado por provider-b a 5 s + overhead) |
| Disponibilidad (`/calculate` devuelve 200)       | ≥ 99 % (incluso si todos los proveedores fallan, devuelve 200 con `quotes: []`) |
| Quotes ≥ 1 por llamada                           | ≥ 95 % (depende de las tasas de fallo simuladas) |

## Acceso a logs

El backend emite una línea JSON por `/calculate` en el canal `calculate`,
escrita a `php://stderr` (capturada por `docker compose logs backend`).

Campos del log:

| Campo                                  | Tipo     | Descripción                                                            |
| -------------------------------------- | -------- | ---------------------------------------------------------------------- |
| `context.request_id`                   | string   | Hex 16 chars; único por request                                         |
| `context.duration_ms`                  | int      | Duración total del handler                                              |
| `context.campaign_active`              | bool     | Estado de la campaña                                                    |
| `context.campaign_percentage`          | float    | Porcentaje aplicado                                                     |
| `context.quotes_count`                 | int      | Número de quotes supervivientes                                         |
| `context.failed_providers`             | string[] | Ids de los proveedores que no produjeron quote                         |
| `context.providers.<id>.outcome`       | string   | `ok` / `failed` / `timeout`                                             |
| `context.providers.<id>.duration_ms`   | int      | Duración hasta resolución/cancelación del proveedor                     |

Búsquedas habituales:

```bash
# Todas las requests del último minuto
docker compose logs --since=1m backend | grep calculate_completed

# Sólo las requests con algún proveedor fallido
docker compose logs --tail=200 backend | grep calculate_completed | grep -v '"failed_providers":\[\]'

# Por request_id
docker compose logs backend | grep "request_id\":\"<HEX>"
```
