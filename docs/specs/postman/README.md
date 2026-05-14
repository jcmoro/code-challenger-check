# Postman — code-challenger-check24

> Colección Postman para validación manual del backend. Contiene los 4
> endpoints del servicio agrupados por tag y un entorno `local`.

## Ficheros

| Fichero                                                | Contenido                                                |
| ------------------------------------------------------ | -------------------------------------------------------- |
| `code-challenger-check24.postman_collection.json`      | Colección con `/calculate` + tres `/provider-*/quote`    |
| `code-challenger-check24.env.local.json`               | Entorno con `base_url=http://localhost:8080`             |

## Variables del entorno

| Variable     | Descripción                                                    | Origen   |
| ------------ | -------------------------------------------------------------- | -------- |
| `base_url`   | URL base del servicio (nginx)                                  | Manual   |
| `birthday`   | Fecha de nacimiento por defecto para los ejemplos              | Manual   |
| `car_type`   | Tipo de coche por defecto                                      | Manual   |
| `car_use`    | Uso del coche por defecto                                      | Manual   |

## Por qué no hay scripts de login

A diferencia del original (DR_0012 §5.5), este servicio **no tiene JWT** ni
multi-tenancy. Por tanto:

- No hay endpoint de Login.
- No hay pre-request script que inyecte headers `X-Redis-Claim-*`.
- No hay test script que decodifique JWT.

Si el alcance creciera para incluir autenticación, los scripts se añadirían
siguiendo el patrón del directorio original.

## Cómo usar

1. Importar la colección y el entorno en Postman / Insomnia / Bruno.
2. Seleccionar el entorno `local`.
3. Arrancar el stack: `make up-d`.
4. Ejecutar las requests en cualquier orden (no hay dependencias entre ellas).

`/calculate` es la operación más interesante: tardará ~5 s (gateado por
provider-b) y devolverá entre 0 y 3 quotes según la randomness simulada.
