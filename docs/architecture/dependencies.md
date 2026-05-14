# Dependencias externas — code-challenger-check24

> Paquetes y servicios externos que un desarrollador nuevo necesita conocer
> para entender la arquitectura del repositorio. Las dependencias transitivas
> de menor entidad se omiten — las cubre `composer.lock` / `package-lock.json`.

## Monorepo

**N/A** — el proyecto vive en un repositorio aislado y no consume paquetes
de un monorepo interno.

## Backend (`backend/composer.json`)

### Estructurales

| Paquete                            | Versión       | Rol                                                                  |
| ---------------------------------- | ------------- | -------------------------------------------------------------------- |
| `symfony/framework-bundle`         | 7.3.*         | Framework base                                                       |
| `symfony/runtime`                  | 7.3.*         | Runtime de Symfony para CLI y FPM                                    |
| `symfony/http-client`              | 7.3.*         | Cliente HTTP. `stream()` permite multiplexar las 3 requests          |
| `symfony/validator`                | 7.3.*         | Validación del DTO `CalculateQuoteHttpRequest`                       |
| `symfony/serializer-pack`          | 7.3.*         | `XmlEncoder` para Provider B                                         |
| `symfony/property-info`            | 7.3.*         | Soporte para `MapRequestPayload`                                     |
| `symfony/dotenv`                   | 7.3.*         | Carga de `.env`                                                      |
| `symfony/console`                  | 7.3.*         | `bin/console`                                                        |
| `symfony/yaml`                     | 7.3.*         | Configuración YAML                                                   |
| `nelmio/cors-bundle`               | ^2.6          | CORS para el dev server de Vite                                      |
| `symfony/monolog-bundle`           | ^4.0          | Logging estructurado JSON en stderr                                  |
| `nelmio/api-doc-bundle`            | ^5.10         | OpenAPI runtime en `/api/doc.json` + Swagger UI en `/api/doc`        |
| `symfony/twig-bundle`              | ^7.3          | Render de la UI Swagger                                              |
| `symfony/asset`                    | ^7.3          | Assets estáticos para Twig (Swagger UI)                              |

### Desarrollo

| Paquete                            | Versión       | Rol                                                                  |
| ---------------------------------- | ------------- | -------------------------------------------------------------------- |
| `symfony/test-pack`                | ^1.x          | Suite WebTestCase + browser-kit + css-selector                       |
| `phpunit/phpunit`                  | 13.x          | Test runner                                                          |
| `phpstan/phpstan` + plugins        | ^2.x          | Análisis estático nivel 8                                            |
| `friendsofphp/php-cs-fixer`        | ^3.95         | Estilo (`@Symfony` + `@PER-CS` + `declare_strict_types`)              |

## Frontend (`frontend/package.json`)

### Estructurales

| Paquete                            | Versión       | Rol                                                                  |
| ---------------------------------- | ------------- | -------------------------------------------------------------------- |
| `vue`                              | ^3.5          | Framework UI                                                         |
| `vue-router`                       | ^4.6          | Routing (single page form + wizard de 3 pasos)                       |

### Build y test

| Paquete                            | Versión       | Rol                                                                  |
| ---------------------------------- | ------------- | -------------------------------------------------------------------- |
| `vite`                             | ^8.0          | Dev server + build                                                   |
| `@vitejs/plugin-vue`               | ^6.0          | Soporte SFC `.vue` en Vite                                           |
| `typescript`                       | ~6.0          | Compilador TS (modo strict)                                          |
| `vue-tsc`                          | ^3.2          | Type-check para `.vue`                                               |
| `vitest`                           | ^4.1          | Test runner                                                          |
| `@vue/test-utils`                  | ^2.4          | Utilidades de montaje                                                |
| `jsdom`                            | ^29.1         | Entorno DOM para Vitest                                              |
| `@vitest/coverage-v8`              | ^4.1          | Cobertura                                                            |

### Lint y formato

| Paquete                            | Versión       | Rol                                                                  |
| ---------------------------------- | ------------- | -------------------------------------------------------------------- |
| `eslint` + `@eslint/js`            | ^10           | Linter                                                               |
| `typescript-eslint`                | ^8.59         | Plugin TypeScript para ESLint                                        |
| `eslint-plugin-vue`                | ^10.9         | Plugin Vue                                                           |
| `vue-eslint-parser`                | ^10.4         | Parser SFC                                                           |
| `prettier`                         | ^3.8          | Formatter                                                            |
| `eslint-config-prettier`           | ^10.1         | Resolución de conflictos prettier/eslint                             |
| `eslint-plugin-prettier`           | ^5.5          | Prettier vía ESLint                                                  |
| `globals`                          | ^17.6         | Listas estándar de globals (`window`, `document`, …)                 |

## Infraestructura

| Componente              | Tecnología                              | Notas                                                       |
| ----------------------- | --------------------------------------- | ----------------------------------------------------------- |
| Reverse proxy           | nginx 1.27-alpine                       | Routes `/` → backend FastCGI                                |
| PHP runtime             | PHP 8.4-fpm-alpine                      | Extensions: `intl`, `opcache`, `zip`                        |
| Node runtime            | Node 20-alpine                          | Vite dev server bind a `0.0.0.0:5173`                       |
| Orquestador             | Docker Compose v2                       | Una sola red `c24`; volume nombrado para `node_modules`     |

## APIs externas / servicios consumidos

**Ninguno real.** Los tres `/provider-*/quote` son simulados y viven en el
propio backend. En producción se sustituirían por proveedores reales cambiando
`PROVIDER_A_BASE_URL`, `PROVIDER_B_BASE_URL`, `PROVIDER_C_BASE_URL` en `.env`.

## Bases de datos / brokers

**Ninguno.** El proyecto es stateless. Si se introdujera persistencia, ver
`docs/database/README.md`.
