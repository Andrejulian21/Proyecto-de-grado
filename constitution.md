# Constitution — Sistema Centralizado de Proyectos de Grado UNAB

> Reglas de gobernanza del proyecto. Inmutables salvo enmienda explícita.
> Todo agente las carga al inicio de cada sesión. Formato EARS ("SHALL").

## Artículo I — Stack tecnológico

- The system SHALL be built on **Laravel 11** with PHP 8.3+ for the backend. ✅
- The frontend SHALL use **React 18 + Vite + TypeScript** with **Tailwind CSS v4** and **shadcn/ui**. ✅
- The database SHALL be **PostgreSQL 16** with **pgvector** for embeddings. ✅
- Session and cache SHALL use **Redis 7**. ✅
- The AI module SHALL be a **FastAPI** microservice with **Sentence-Transformers** and **Azure OpenAI**. ✅
- Authentication SHALL use **Laravel Sanctum** in cookie SPA mode. ✅
- Infrastructure SHALL run on **Docker Compose** deployed to **Azure VM**.

## Artículo II — Arquitectura

- The system SHALL follow a **monolithic Laravel backend** with a **separate FastAPI microservice** for AI workloads.
- Communication between Laravel and FastAPI SHALL use **HMAC-signed HTTP requests**.
- The frontend SHALL be a **SPA** served by Laravel, authenticated via Sanctum cookies.
- Files SHALL stay **under 500 lines** where practical. Public APIs SHALL have validation and type safety.
- The database schema SHALL use **migrations** with reversible `down()` methods.

## Artículo III — Testing (test-first)

- New behavior SHALL be developed test-first (**Strict TDD**: RED → GREEN → REFACTOR).
- A task SHALL NOT be marked done until verified against its acceptance criteria.
- The PHP test suite (Pest) SHALL pass before any commit. Baseline: **151 tests**.
- Frontend build SHALL be verified with `npm run build` before commit.
- Coverage SHALL NOT regress below the established baseline.

## Artículo IV — Seguridad

- Secrets, credentials, and `.env` files SHALL NEVER be committed.
- Every protected endpoint SHALL validate the user's role server-side (RNF03).
- Tampering with frontend state SHALL NOT escalate privileges (RNF03).
- All auth endpoints SHALL serve over HTTPS only (RNF02).
- The audit log SHALL be **append-only**: no UPDATE/DELETE routes exposed (RNF05).
- Input SHALL be validated at all system boundaries; file paths SHALL be sanitized.

## Artículo V — Spec-Driven Development (SDD)

- Features SHALL originate from an SDD change: `proposal → spec → design → tasks → apply → verify → archive`.
- The `spec.md` SHALL use EARS format for acceptance criteria.
- Implementation SHALL NOT begin without an approved `tasks.md`.
- Gates SHALL be enforced: no `apply` without approved `tasks`, no `archive` without passing `verify`.
- The `openspec/` directory SHALL be the single source of truth for all change artifacts.

## Artículo VI — UI/UX

- The UI language SHALL be **Spanish** for all user-facing text.
- Error messages SHALL be clear, in Spanish, and actionable.
- The system SHALL be **responsive** (mobile, tablet, desktop).
- Accessibility SHALL follow WCAG AA standards (skip-link, aria-labels, focus-visible, contrast).
- The design system SHALL follow the tokens defined in Open Design (burnt orange `#c2410c`, indigo `#4f46e5`, Open Sans).

## Artículo VII — Estilo de código

- Code identifiers (variables, functions, classes) SHALL be in **English**.
- Comments SHALL be in English for code, Spanish for business logic context.
- Routes and API endpoints SHALL use **kebab-case** URLs.
- Database columns SHALL use **snake_case**.
- No file SHALL exceed 500 lines; prefer extraction over long files.

## Artículo VIII — Gobernanza del repo

- The repo SHALL maintain an up-to-date **`AGENTS.md`** as the universal entry point.
- `CLAUDE.md` SHALL point to `AGENTS.md` (single source of truth).
- Commits SHALL follow conventional commits (`feat:`, `fix:`, `docs:`, `chore:`, `test:`).
- Branches SHALL follow `feature/<name>` for features, `fix/<name>` for bugfixes.
