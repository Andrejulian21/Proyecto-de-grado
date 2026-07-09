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

- ✅ **Sprint 1 completado** — Auth (Google OAuth + externo + RBAC), Layout (sidebar/header fijos), Gestión de Usuarios (whitelist CRUD, roles), Auditoría, 151 tests
- ⏳ **En progreso** — Sprint 2: Proyectos + Dashboard coordinador + Semestres
- ❌ Pendientes: Entregas, Bitácoras+TOTP, Directores+Evaluadores, Anuncios+Recursos, IA, QA+Deploy

## Convenciones

- **Commits:** convencionales (`feat:`, `fix:`, `docs:`, `chore:`, `test:`). Mensajes cortos y descriptivos.
- **Ramificación:** `feature/*` para features, `fix/*` para bugs. PR a `main`.
- **Archivos < 500 líneas.** Sin secretos en el repo.
- **Lenguaje:** UI en español, código en inglés.
