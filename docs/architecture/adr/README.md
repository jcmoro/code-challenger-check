# ADRs — code-challenger-check24

> Architecture Decision Records. Cada ADR documenta una decisión técnica
> significativa con su contexto, alternativas y consecuencias.

## Índice

| ADR                                                                  | Título                                                     | Estado    | Fecha       |
| -------------------------------------------------------------------- | ---------------------------------------------------------- | --------- | ----------- |
| [ADR-001](ADR-001-symfony-vue-stack.md)                               | Symfony 7.3 + Vue 3 como stack del code challenge          | Aceptado  | 2026-05-13  |
| [ADR-002](ADR-002-parallel-fetch-via-httpclient-stream.md)            | Fan-out paralelo via `HttpClient::stream()`                | Aceptado  | 2026-05-13  |
| [ADR-003](ADR-003-quotefetcher-interface-for-testability.md)          | Interface `QuoteFetcher` introducida para testabilidad     | Aceptado  | 2026-05-13  |

## Cómo añadir un ADR

1. Copiar la plantilla de la directriz `directives/DIR_api_docs.md` §11.4.
2. Asignar el siguiente número (ADR-004, ADR-005, …).
3. Rellenar Estado, Fecha, Autores, Contexto, Decisión, Alternativas,
   Consecuencias y **Riesgos operativos**.
4. Añadir la fila a la tabla de arriba.
5. Si el ADR sustituye a uno anterior, marcar el anterior como `Sustituido por ADR-NNN`.

## Decisiones que NO son ADR

Las desviaciones del plan de implementación documentadas en
[`docs/plan/replanning.md`](../../plan/replanning.md) son la fuente
principal del "por qué" del proyecto (24 entradas). Sólo las decisiones de
alto nivel y mayor impacto se elevan a ADR.
