# AGENTS.md — Instrucciones universales para cualquier agente AI/CLI

> Este archivo es el **punto de entrada estándar** para cualquier asistente de código
> (Claude Code, Cursor, GitHub Copilot, opencode, Windsurf…).
> Si eres un agente trabajando en este repo: **lee esto primero**, luego `docs/`.
> `CLAUDE.md` apunta aquí.

## Qué es este proyecto

**Sistema Centralizado de Proyectos de Grado** — plataforma web para gestionar proyectos de grado
de Ingeniería de Sistemas en la UNAB. Permite a coordinadores, directores, estudiantes y evaluadores
externos gestionar el ciclo de vida completo: desde la inscripción de proyectos, pasando por entregas
con versionado, bitácoras firmadas con TOTP, evaluación y generación de reportes.

**Stack:** Laravel 11 + React/Vite + Tailwind + shadcn/ui + PostgreSQL 16 + Redis + FastAPI (IA)

## Cómo está organizado

- **`AGENTS.md`** — este archivo. Léeme primero.
- **`README.md`** — visión general del proyecto + stack.
- **`docs/PLAN-MAESTRO.md`** — ⭐ **documento de ejecución definitivo. Si dos docs se contradicen, este GANA.**
- **`docs/PRINCIPIOS.md`** — leyes de producto y arquitectura.
- **`docs/ARQUITECTURA.md`** — arquitectura del sistema por capas.
- **`docs/DECISIONES.md`** — ADRs del proyecto (decisiones con contexto, opciones, consecuencia).
- **`docs/ROADMAP.md`** — plan de 7 sprints con entregables y orden de sacrificio.
- **`constitution.md`** — reglas inmutables del proyecto en formato EARS.
- **`openspec/`** — artefactos SDD por change (proposal → spec → design → tasks → archive).
- **`contexto/`** — documentos de contexto originales (fase 2, HU, Gantt, cronograma).
- **`sdd/`** — registro de sesiones SDD e init del proyecto.

## Cómo trabajar aquí (SDD workflow)

1. **Lee** `constitution.md` + el change activo en `openspec/changes/` antes de tocar código.
2. Si vas a implementar algo nuevo: sigue el flujo **`/sdd-new → propose → spec → design → tasks → apply → verify → archive`**.
3. **Gates duros:** no implementes sin un `tasks.md` aprobado. No marques una tarea como hecha sin verificarla contra sus criterios de aceptación.
4. **Strict TDD:** test que falla → código mínimo → refactor. 151 tests baseline.
5. **Una tarea a la vez**, respetando dependencias.
6. **UI en español, código/documentación en inglés.** Los mensajes de error en español.

## Stack confirmado

| Capa | Tecnología |
|------|-----------|
| Backend | Laravel 11 (PHP 8.3+) |
| Frontend | React 18 + Vite + TypeScript |
| UI | Tailwind CSS v4 + shadcn/ui |
| Base de datos | PostgreSQL 16 + pgvector |
| Cache/Session | Redis 7 |
| IA | FastAPI + Sentence-Transformers + Azure OpenAI |
| Auth | Sanctum (cookie SPA) + Google OAuth + credenciales |
| Testing | Pest (PHP) + Playwright (E2E) |
| Infra | Docker Compose + Azure VM |

## Estado actual / pendientes

### ✅ Completado

| Sprint / Change | Logros | Tests |
|----------------|--------|-------|
| **Sprint 1** — `auth-access-module` | Google OAuth, login externo, RBAC, SingleSession, Whitelist CRUD, Auditoría inmutable, Layout (sidebar/header), Login pages React | 151 |
| **Sprint 2** — `backend-completo` | 13 migraciones, 12 modelos, 5 enums, 7 controladores, ~40 endpoints, auto-código PG-xxxx, KPIs dashboard | 373 |
| **Sprint 3** — `test-qa-backend` | 7 factories, tests unitarios/feature, cobertura configurada | 439 |
| `fix-critical-issues` | CI pipeline, inmutabilidad DB, single-session cookie, lockout sliding window | 452 |
| `hardening-audit-fixes` | 15 hallazgos: rate limiting, CSRF, FormRequests, índices, SoftDeletes, constant-time login | 495 |
| **Sprint 4** — ✅ `frontend-wireframes-port` | **29 wireframes porteados a React.** 7 shared components. 14 PRs chained. Dashboards reales con KPIs/stepper/progress. Landing page. Flujo Estudiante (bitácoras, entregas, TOTP, IA mock). Flujo Director (supervisión, firmas, revisión). Flujo Coordinador (proyectos, anuncios, evaluadores, alertas, reportes). Flujo Evaluador (rúbrica, calificar). Responsive. WCAG AA. Build 0 errores. | 495 |

### 🔜 Próximos sprints

| Sprint | Área | Prioridad |
|--------|------|-----------|
| **5** | Integración backend — reemplazar mock data con apiFetch() a endpoints reales | Crítica |
| **6** | Docker refinado + CI/CD, E2E con Playwright | Media |
| **7** | Despliegue Azure VM + Nginx TLS | Media |

### 🎯 Orden de sacrificio

Si el tiempo no alcanza: IA (asistente + análisis) → TOTP → Despliegue Azure → Exportación PDF/Excel → Notificaciones push

**NUNCA se sacrifica:** Auth · Proyectos · Entregas · Bitácoras · Evaluación · Reportes · Tests

## Convenciones

- **Commits:** convencionales (`feat:`, `fix:`, `docs:`, `chore:`, `test:`). Mensajes cortos y descriptivos.
- **Ramificación:** `feature/*` para features, `fix/*` para bugs. PR a `main`.
- **Archivos < 500 líneas.** Sin secretos en el repo.
- **Lenguaje:** UI en español, código en inglés.
