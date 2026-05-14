# code-challenger-check24

> Comparación de seguros de coche con tres proveedores simulados (JSON / XML / CSV)
> y descuento de campaña configurable. Backend Symfony 7.3 + frontend Vue 3.

## Descripción

El servicio recibe los datos del conductor y del coche, llama en paralelo a tres
proveedores con timeout de 10 segundos por proveedor, aplica un 5% de descuento
si la campaña está activa, ordena los presupuestos por precio ascendente y
marca el más barato. Los proveedores fallidos (5xx, timeout, body
no-parseable) se omiten del resultado sin propagar el error.

## Stack tecnológico

| Capa                 | Tecnología                          | Versión |
| -------------------- | ----------------------------------- | ------- |
| Lenguaje (backend)   | PHP                                 | 8.4     |
| Framework            | Symfony                             | 7.3     |
| HTTP client          | `symfony/http-client` (multiplex)   | 7.3     |
| API docs             | `nelmio/api-doc-bundle`             | ^5.10   |
| Logging              | `symfony/monolog-bundle`            | ^4.0    |
| Tests backend        | PHPUnit                             | 13.x    |
| Static analysis      | PHPStan max + PHP-CS-Fixer          | —       |
| Lenguaje (frontend)  | TypeScript                          | ~6.0    |
| Framework            | Vue.js                              | 3.5     |
| Router               | vue-router                          | ^4.6    |
| Build                | Vite                                | ^8.0    |
| Tests frontend       | Vitest + `@vue/test-utils` + jsdom  | —       |
| Lint frontend        | ESLint 10 + Prettier + vue-tsc      | —       |
| Reverse proxy        | nginx alpine                        | 1.27    |
| Runtime              | Docker + Docker Compose             | —       |

## Índice de documentación

| Sección                                          | Descripción                                                                      |
| ------------------------------------------------ | -------------------------------------------------------------------------------- |
| [Specs](specs/README.md)                          | Contrato OpenAPI 3.0, JSON Schemas, colección Postman                            |
| [Arquitectura](architecture/README.md)            | Visión general, dependencias, reglas de negocio, ADRs                            |
| [Base de datos](database/README.md)               | N/A — el proyecto no tiene persistencia; sólo el mapa de dependencias            |
| [Funcional](functional/README.md)                 | Flujo `/calculate`, códigos de error del dominio                                  |
| [Operaciones](operations/README.md)               | Runbook, troubleshooting, guía de desarrollo local                                |
| [Plan](plan/)                                     | Constitución, requisitos, spec, validación, replanning — vivos desde Fase 0      |
| [Directivas](directives/)                         | `DIR_api_docs.md` adaptada a este proyecto                                       |
| [Glosario](glossary.md)                           | Términos del dominio                                                              |
| [Changelog](changelog.md)                         | Historial de cambios en el contrato y en la documentación                         |

## Mantenedor

| Campo          | Valor                                       |
| -------------- | ------------------------------------------- |
| **Autor**      | jcmorodiaz@gmail.com                         |
| **Repositorio** | `code-challenger-check24`                    |
| **Stack**      | Symfony 7.3 + Vue 3 dentro de Docker        |
| **Spec runtime** | http://localhost:8080/api/doc.json         |
| **Spec versionable** | [`specs/openapi/v1/openapi.yaml`](specs/openapi/v1/openapi.yaml) |
