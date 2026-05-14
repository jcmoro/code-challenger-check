# ADR-001 — Symfony 7.3 + Vue 3 como stack

| Campo        | Valor                                                            |
| ------------ | ---------------------------------------------------------------- |
| **Estado**   | Aceptado                                                          |
| **Fecha**    | 2026-05-13                                                        |
| **Autores**  | Project owner                                                     |
| **Contexto** | Elegir el stack del code challenge antes de empezar Fase 1        |

---

## Contexto

El PDF del code challenge especifica explícitamente Symfony como backend y
Vue.js como frontend. Quedan abiertos los detalles de versión, runtime, build
y herramientas auxiliares.

Restricciones:

- Todo debe correr en Docker (requisito propio del proyecto, no del PDF).
- El reviewer debe poder arrancar el proyecto con sólo Docker + Make.
- Los criterios de evaluación valoran calidad de código y testabilidad.

## Decisión

- Backend: **Symfony 7.3** sobre **PHP 8.4-fpm-alpine**.
- Frontend: **Vue 3** + **TypeScript** + **Vite**.
- Orquestación: **Docker Compose v2** + un único `Makefile` en la raíz.

## Alternativas consideradas

| Opción                                          | Descripción                                                            | Decisión                                                            |
| ----------------------------------------------- | ---------------------------------------------------------------------- | ------------------------------------------------------------------- |
| A — Symfony 7.1                                  | Versión inicialmente propuesta por la plantilla `symfony/skeleton`      | ❌ Descartada: bloqueada por security advisories en transitivas      |
| B — Symfony 7.3                                  | Versión actual estable                                                  | ✅ Elegida                                                           |
| C — PHP 8.3                                      | Versión inicialmente planificada                                        | ❌ Descartada: PHPUnit 13 requiere PHP ≥ 8.4.1                       |
| D — PHP 8.4                                      | Última versión estable                                                  | ✅ Elegida                                                           |
| E — Vue 3 + Options API                          | Sintaxis tradicional                                                    | ❌ Descartada: `<script setup>` + Composition API es estándar moderno |
| F — Vue 3 + Composition API + TS                 | Sintaxis moderna con tipado estricto                                    | ✅ Elegida                                                           |
| G — Webpack                                      | Bundler legacy                                                          | ❌ Descartada: Vite es el estándar para Vue 3                        |
| H — Vite                                         | Bundler moderno con HMR rápido                                          | ✅ Elegida                                                           |

## Consecuencias

**Positivas:**

- Stack moderno con tooling maduro (Symfony Flex, Vite HMR).
- Tipado fuerte en ambos lados (PHP 8.4 + TypeScript strict).
- Test runners de primer nivel (PHPUnit 13, Vitest 4).
- API doc bundle gratuita (`nelmio/api-doc-bundle`) para Swagger UI.

**Negativas:**

- PHP 8.4 todavía no es la versión por defecto en muchas distros — mitigado
  porque todo corre en Docker.
- Vue 3 Composition API tiene curva de aprendizaje para devs de Vue 2.

## Riesgos operativos

| Dependencia / Riesgo                          | Impacto si falla                          | Comportamiento de degradación                                  |
| --------------------------------------------- | ----------------------------------------- | -------------------------------------------------------------- |
| `php:8.4-fpm-alpine` deprecated del repo Docker | Build de imagen falla                   | Pin minor (`8.4.21`) si lo necesitamos                          |
| PHPUnit 13 introduce breaking changes         | Tests rotos tras `composer update`        | Pin a `^13.0` en `composer.json`                                |
| Vite 8 cambia API de plugin                   | Build / dev server rotos                  | Pin a `^8.0` en `package.json`                                  |

## Referencias

- `docs/plan/implementation.md` — Fase 0 (bootstrap).
- `docs/plan/replanning.md` — entradas #7 (Symfony 7.1→7.3) y #8 (PHP 8.3→8.4).
