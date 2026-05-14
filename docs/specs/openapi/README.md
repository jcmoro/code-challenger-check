# OpenAPI — code-challenger-check24

> Spec OpenAPI 3.0 del servicio. Documenta los 4 endpoints HTTP del backend:
> `/calculate` (orquestador) y los tres `/provider-{a,b,c}/quote` simulados.

## Versión

| Campo | Valor |
| --- | --- |
| Versión OpenAPI | 3.0.3 |
| Versión del spec | 1.0.0 |
| Servidor | `http://localhost:8080` (nginx local) |

## Estructura

```
openapi/
├── README.md            # Este fichero
└── v1/
    └── openapi.yaml     # Spec del servicio
```

El nombre `v1` permite añadir un `v2/` cuando el contrato sufra cambios
breaking. Mientras no haya `v2`, todas las modificaciones del contrato pasan
por el `v1/` con su correspondiente entrada en `docs/changelog.md`.

## Convenciones

- **Formato YAML** (más legible que JSON, permite comentarios).
- **operationId en camelCase** (`postCalculate`, `postProviderAQuote`, etc.).
- **Tags** agrupan operaciones por audiencia: `calculate` (cliente final) vs
  `providers` (proveedores internos simulados).
- **Errores**: todos los responses 4xx referencian `ProblemDetails` (RFC 9457).
- **Schemas**: ningún schema se define inline; siempre `$ref` a
  `docs/specs/schemas/`.

## Sincronización con runtime

El bundle `nelmio/api-doc-bundle` deriva un spec equivalente desde las
anotaciones `#[OA\*]` de los controllers Symfony. Está disponible en:

- Swagger UI: <http://localhost:8080/api/doc>
- Raw JSON: <http://localhost:8080/api/doc.json>

Si el spec versionable (este fichero) y el runtime divergen, el commit que
introdujo la divergencia es defectuoso y debe corregirse.
