# Base de datos — code-challenger-check24

> **N/A en este proyecto.** No hay base de datos persistente, ni motor de
> búsqueda, ni broker de mensajes. Toda la información vive en memoria durante
> la request y se descarta al terminar.

## Por qué no hay base de datos

El servicio es un orquestador stateless:

- No almacena los presupuestos calculados (cada request es independiente).
- No autentica usuarios ni mantiene sesiones de servidor.
- El estado del formulario del frontend vive en `sessionStorage` (cliente).
- La campaña on/off se lee de una variable de entorno (`CAMPAIGN_ACTIVE`), no
  de un feature flag persistido.

Si en el futuro el alcance creciera (histórico de cotizaciones, A/B testing,
usuarios autenticados), los ficheros documentados a continuación quedarían
poblados según la directriz `directives/DIR_api_docs.md` §9.

## Subcarpetas previstas pero **N/A**

| Carpeta              | Para qué sería                                            | Estado en este proyecto |
| -------------------- | --------------------------------------------------------- | ----------------------- |
| `postgres/`          | Schemas de tablas PostgreSQL                              | N/A — sin DB            |
| `mysql/`             | Schemas de tablas MySQL                                   | N/A — sin DB            |
| `elasticsearch/`     | Mappings, alias, ILM                                      | N/A — sin ES            |
| `kafka/topics.md`    | Listado de topics con particiones / retention             | N/A — sin Kafka         |
| `kafka/messages.md`  | Catálogo de eventos producidos / consumidos               | N/A — sin Kafka         |

## Mapa de dependencias

Aunque no haya base de datos, [`dependency-map.md`](dependency-map.md) sí
existe y es obligatorio: documenta el sistema completo (frontend, nginx,
backend, los tres proveedores simulados) y las variables de entorno que
configuran el comportamiento.
