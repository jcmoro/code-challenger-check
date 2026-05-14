# JSON Schemas — code-challenger-check24

> Schemas formales de los bodies que cruzan el límite HTTP. Versión:
> JSON Schema draft 2020-12.

## Estructura

```
schemas/
├── README.md               # Este fichero
├── requests/               # Bodies aceptados por el servicio
│   ├── calculate.json
│   └── provider-a-quote.json
├── responses/              # Bodies devueltos por el servicio
│   ├── calculate.json
│   ├── provider-a-quote.json
│   └── problem-details.json
└── shared/                 # Schemas reutilizables ($ref desde otros)
    ├── money.json
    └── quote.json
```

## Convención de `$id`

Ruta relativa al repositorio:

- `schemas/requests/calculate.json`
- `schemas/responses/problem-details.json`
- `schemas/shared/money.json`

## Schemas que **no** existen en este proyecto

| Carpeta del directorio   | Razón                                          |
| ------------------------ | ---------------------------------------------- |
| `events/`                | El servicio no produce ni consume eventos.     |
| `jsonb/`                 | No hay base de datos.                          |

Si se introdujeran en el futuro, se crearían siguiendo las mismas convenciones.

## Cómo se referencian desde OpenAPI

Desde `docs/specs/openapi/v1/openapi.yaml` con `$ref` relativo:

```yaml
requestBody:
  content:
    application/json:
      schema:
        $ref: "../../schemas/requests/calculate.json"
```

## Schemas para Provider B / C

Provider B (XML) y Provider C (CSV) **no tienen JSON Schema** porque sus
bodies no son JSON. Su contrato vive en el spec OpenAPI con un `example` en
texto plano más la descripción del formato.
