# Desarrollo local — code-challenger-check24

> Guía para configurar y ejecutar el servicio en local sin fricción.
> Complementa el [`README.md`](../../README.md) del repositorio, que mantiene
> la versión más reciente del quickstart.

## Requisitos previos

| Herramienta       | Versión mínima | Notas                                              |
| ----------------- | -------------- | -------------------------------------------------- |
| Docker            | 24.x           | macOS / Linux / WSL2                               |
| Docker Compose v2 | 2.x            | Incluido con Docker Desktop                        |
| GNU Make          | 3.x            | Preinstalado en macOS / Linux                      |

**No** se necesita PHP, Composer, Node ni npm instalados en el host: todos
los comandos corren dentro de contenedores.

## Variables de entorno

Copiar `.env.example` a `.env`:

```bash
cp .env.example .env
```

| Variable                      | Descripción                                              | Valor por defecto                      | Obligatoria |
| ----------------------------- | -------------------------------------------------------- | -------------------------------------- | ----------- |
| `APP_ENV`                     | Entorno Symfony (`dev` / `prod` / `test`)                | `dev`                                  | Sí          |
| `APP_DEBUG`                   | Habilita el modo debug de Symfony                        | `1`                                    | Sí          |
| `CAMPAIGN_ACTIVE`             | Enciende/apaga el descuento de campaña                   | `true`                                 | Sí          |
| `CAMPAIGN_PERCENTAGE`         | Porcentaje del descuento (0–100)                         | `5.0`                                  | Sí          |
| `PROVIDER_TIMEOUT_SECONDS`    | Timeout del fan-out paralelo                             | `10`                                   | Sí          |
| `PROVIDER_A_BASE_URL`         | URL base del proveedor A                                  | `http://nginx/provider-a`              | Sí          |
| `PROVIDER_B_BASE_URL`         | URL base del proveedor B                                  | `http://nginx/provider-b`              | Sí          |
| `PROVIDER_C_BASE_URL`         | URL base del proveedor C                                  | `http://nginx/provider-c`              | Sí          |
| `CORS_ALLOW_ORIGIN`           | Regex de origins CORS permitidos                          | `http://localhost:5173`                | Sí          |
| `VITE_API_BASE`               | URL desde la que el SPA hace `fetch`                      | `http://localhost:8080`                | Sí          |
| `NGINX_HOST_PORT`             | Puerto del host mapeado a nginx                           | `8080`                                 | No          |
| `VITE_HOST_PORT`              | Puerto del host mapeado al dev server de Vite             | `5173`                                 | No          |

> Cualquier cambio en estas variables se refleja sin rebuild de imagen
> (`docker-compose.yml` las pasa al runtime via `environment:`).

## Levantar el servicio

```bash
make build         # Construye las 3 imágenes
make install       # composer install + npm ci
make up-d          # Arranca el stack en background
```

URLs disponibles tras arrancar:

| URL                                  | Qué                                      |
| ------------------------------------ | ---------------------------------------- |
| http://localhost:5173                | Frontend single-page                     |
| http://localhost:5173/wizard         | Frontend wizard de 3 pasos                |
| http://localhost:8080/calculate      | API REST                                  |
| http://localhost:8080/api/doc        | Swagger UI                                |
| http://localhost:8080/api/doc.json   | OpenAPI 3.0 JSON                          |

## Ejecutar los tests

```bash
make test            # PHPUnit + Vitest (132 tests)
make test-backend    # Sólo PHPUnit (91 cases)
make test-frontend   # Sólo Vitest (41 cases)
```

## Lint + análisis estático

```bash
make lint            # PHPStan + PHP-CS-Fixer + ESLint + Prettier + vue-tsc
make stan            # Sólo PHPStan
make cs              # Sólo PHP-CS-Fixer (dry-run)
make eslint          # Sólo ESLint
make prettier        # Sólo Prettier --check
make typecheck       # Sólo vue-tsc --noEmit
```

Para auto-fix:

```bash
make fix             # Aplica todos los auto-fixers
make fix-backend     # Sólo PHP-CS-Fixer en modo write
make fix-frontend    # ESLint --fix + Prettier --write
```

## Tareas habituales

| Tarea                                          | Comando                                                                       |
| ---------------------------------------------- | ----------------------------------------------------------------------------- |
| Ver routes registradas en Symfony              | `docker compose exec backend php bin/console debug:router`                    |
| Ver servicios autowireados                     | `docker compose exec backend php bin/console debug:container --tag=app.quote_provider` |
| Limpiar caché de Symfony                       | `docker compose exec backend php bin/console cache:clear`                     |
| Calentar caché test                            | `docker compose exec backend php bin/console cache:warmup --env=test`         |
| Curl al `/calculate`                            | `curl -X POST http://localhost:8080/calculate -H "Content-Type: application/json" -d @docs/specs/postman/...` |
| Curl con un cuerpo inline                       | Ver `docs/specs/postman/` para ejemplos                                       |
| Tail logs en vivo                              | `make logs`                                                                    |
| Parar y limpiar contenedores + volumes         | `make clean`                                                                   |

## Workflow recomendado para un cambio

1. `make up-d` — asegurarse de que el stack está arriba.
2. Editar código en `backend/src/` o `frontend/src/`. Vite recarga al
   instante; los cambios en PHP se reflejan tras siguiente request (FastCGI
   recarga el bootstrap, opcache valida timestamps).
3. `make test` — antes de commit.
4. `make lint` — antes de commit.
5. Si algo falla en CI/CD, replicar localmente con los comandos de arriba.

## Cuando algo no funciona

1. `make logs` — busca errores en los tres servicios.
2. `docker compose ps` — los tres contenedores deben estar `Up`.
3. Consultar [`runbook.md`](runbook.md) para incidencias previsibles.
4. Consultar [`troubleshooting.md`](troubleshooting.md) para problemas
   resueltos previamente.

Si el problema es nuevo: investigar, resolver, añadir entrada `PROB-NNN` en
`troubleshooting.md` con causa raíz y prevención.
