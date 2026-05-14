# Specs — code-challenger-check24

> Contratos formales del servicio: OpenAPI 3.0 (REST), JSON Schemas (bodies) y
> colección Postman (validación manual).

## Estructura

| Carpeta                                   | Contenido                                                                                  |
| ----------------------------------------- | ------------------------------------------------------------------------------------------ |
| [`openapi/`](openapi/README.md)            | Spec OpenAPI versionada del servicio                                                       |
| [`schemas/`](schemas/README.md)            | JSON Schemas reutilizables (request, response, shared)                                     |
| [`postman/`](postman/README.md)            | Colección Postman + entorno local para validación manual                                  |

## Asíncrono / Kong

`asyncapi/` y `kong/` no aparecen porque el proyecto no produce eventos ni usa
Kong (ver `directives/DIR_api_docs.md` §6 y §8).

## Cómo se mantiene

El spec OpenAPI versionable vive en `openapi/v1/openapi.yaml` y es la fuente
de verdad. En paralelo, el bundle `nelmio/api-doc-bundle` expone el mismo
contrato derivado de las anotaciones `#[OA\*]` en
`http://localhost:8080/api/doc.json`. Ambos deben coincidir; cualquier
desviación es un defecto a corregir.
