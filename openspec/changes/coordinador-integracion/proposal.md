# Proposal: Coordinator Integration — Full Frontend-Backend Connection

## Intent

Connect all coordinator frontend pages from mock data to real backend endpoints. Currently ~20 backend endpoints exist but the frontend uses static mocks in 9 of 11 coordinator pages. This creates data drift, blocks real usage by the coordinador role, and makes the dashboard KPIs, project tables, and alerts meaningless.

## Scope

### In Scope
- **CoordinadorDashboard** — wire KPI cards to `/api/admin/proyectos/kpis`; project table actions link to read-only supervision view; fix sidebar active state for "Panel"
- **GestionProyectos** — group/semester selector; project CRUD table; director cupo management; create-project form with student autocomplete; wire to `/api/admin/proyectos`
- **Directores** (new page `/directores`) — director cards with specialization areas; view bitácoras and view proyectos (read-only supervision)
- **AsignacionEvaluadores** — assignments table with edit/delete; registration form; visual calendar grid; results table with average scores; wire to `/api/admin/evaluador-proyecto` + `/api/evaluaciones`
- **GestionUsuarios** — unified single table with editable role dropdown, last-access timestamp, immediate whitelist reflection; wire to user/whitelist/evaluador endpoints
- **AnunciosAdmin** — fix sidebar active-state collision with public Anuncios; CRUD wired to `/api/admin/anuncios`
- **GestionAlertas** — new alert rules: unsigned bitácoras after 1h of student submission, late entregas, directors signing >2 bitácoras in 1h
- **CoordinadorEntregas** — create entregas by group + sequential fase; view/filter entregas table; wire to `/api/admin/entregas`
- **RecursosAdmin** — real file upload with preview and edit; wire to `/api/admin/recursos`
- **Sidebar/Navbar** — add `/directores` route; remove Bitácoras, Semestre, Reportes from navbar (keep in router); fix active-state logic for Panel and Anuncios

### Out of Scope
- Integration for Estudiante, Director, or EvaluadorExterno roles
- New backend endpoints (assumed existing or created by backend team)
- AI assistant, TOTP, PDF/Excel exports, push notifications
- Deleting legacy mock files until verification is complete
- Removing existing routes from React Router (only navbar entries are removed)

## Capabilities

### New Capabilities
- `directores-list`: Coordinator view of director cards, bitácora history, and project read-only supervision
- `alertas-engine`: Rule-based alert generation for late submissions and suspicious signing patterns

### Modified Capabilities
- `coordinador-dashboard`: KPI data source changes from mock to API; adds project supervision read-only view
- `gestion-proyectos`: Full reformulation with group selector, cupo management, and student autocomplete
- `asignacion-evaluadores`: Adds calendar grid and results table; full CRUD wiring
- `gestion-usuarios`: Single unified table with immediate whitelist reflection
- `coordinador-entregas`: Sequential fase logic and group-scoped creation
- `recursos-admin`: Real upload/preview/edit replacing fake timeouts

## Approach

1. **Shared hook first** — create or extend `useApiFetch` wrapper with SWR-style caching for coordinator endpoints
2. **Page-by-page wiring** — replace `MOCK_*` arrays with `useEffect` + `apiFetch` calls; keep UI skeletons
3. **Feature-branch chain** — each PR targets one functional slice; `main` stays deployable after every merge
4. **Defensive coding** — preserve empty states, loading skeletons, and error toasts; never assume array length
5. **Read-only reuse** — extract `SupervisionReadOnly` component from director supervision view for coordinator reuse

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `resources/js/pages/dashboard/CoordinadorDashboard.tsx` | Modified | API wiring, supervision link, sidebar fix |
| `resources/js/pages/coordinador/GestionProyectos.tsx` | Replaced | Group selector, cupos, create form, autocomplete |
| `resources/js/pages/coordinador/DirectoresPage.tsx` | New | Director cards, bitácora + project views |
| `resources/js/pages/coordinador/AsignacionEvaluadores.tsx` | Replaced | Calendar, results table, CRUD wiring |
| `resources/js/pages/coordinador/GestionUsuarios.tsx` | Replaced | Unified table, immediate whitelist |
| `resources/js/pages/coordinador/AnunciosAdmin.tsx` | Modified | Sidebar active-state fix |
| `resources/js/pages/coordinador/GestionAlertas.tsx` | Modified | New alert rules + API wiring |
| `resources/js/pages/coordinador/CoordinadorEntregas.tsx` | Replaced | Sequential fase, group filters |
| `resources/js/pages/coordinador/RecursosAdmin.tsx` | Modified | Real upload, preview, edit |
| `resources/js/components/layout/Sidebar.tsx` | Modified | Route map changes, active-state logic |
| `resources/js/app.tsx` | Modified | Add `/directores` route |
| `resources/js/hooks/useApiFetch.ts` | Modified | Extend for coordinator endpoints |

## Assumptions

1. Backend endpoints `/api/admin/*` already return JSON shapes compatible with frontend tables (or backend team adjusts them in parallel)
2. Student search autocomplete uses existing users index filtered by `rol = Estudiante`
3. Director cupo max is never allowed below currently assigned active projects (enforced in backend; frontend validates pre-submit)
4. Whitelist addition triggers immediate user creation/visibility in the unified users table
5. Entrega fases are strictly sequential: anteproyecto → presentación anteproyecto → desarrollo → presentación final
6. "Read-only supervision" reuses the director supervision view component but hides all edit/observation controls
7. UI copy remains in Spanish; technical artifacts in English

## Non-Goals

- No integration for non-coordinator roles (Sprint 6 scope)
- No new AI, TOTP, or notification features
- No changes to Laravel API authentication or RBAC middleware
- No database schema changes (work with existing migrations)
- No removal of mock files until full verification passes

## PR Breakdown (Chained)

| PR | Title | Scope | Size |
|----|-------|-------|------|
| **PR1** | `feat: coordinator dashboard KPIs + supervision read-only + sidebar fixes` | CoordinadorDashboard API wiring, SupervisionReadOnly component, Sidebar active-state fixes | ~200 lines |
| **PR2** | `feat: reform GestionProyectos — groups, cupos, create form, student autocomplete` | GestionProyectos rewrite, group selector, director cupo table, create-project form with student search | ~600 lines |
| **PR3** | `feat: new Directores page + navbar route` | DirectoresPage component, bitácora viewer, project supervision reuse, add `/directores` to router + sidebar | ~300 lines |
| **PR4** | `feat: reform AsignacionEvaluadores — calendar, results, full CRUD` | AsignacionEvaluadores rewrite, calendar grid, results table, evaluador-proyecto API wiring | ~500 lines |
| **PR5** | `feat: reform GestionUsuarios unified table + whitelist immediate reflection` | Single users table, editable role dropdown, last-access column, whitelist instant visibility | ~300 lines |
| **PR6** | `feat: reform Entregas + Alertas + RecursosAdmin real upload` | CoordinadorEntregas sequential fase + group filters, GestionAlertas rules engine, RecursosAdmin real upload/preview/edit | ~500 lines |

**Chain order:** PR1 → PR2 → PR3 → PR4 → PR5 → PR6. Each PR branches from the merged result of the previous one.

## Dependencies

| Dependency | Required For | Status |
|------------|-------------|--------|
| `/api/admin/proyectos/kpis` | PR1 | Exists |
| `/api/admin/proyectos` (+ related) | PR2 | Exists |
| Users endpoint with role filter | PR2, PR3, PR5 | Exists |
| `/api/admin/evaluador-proyecto` | PR4 | Exists |
| `/api/evaluaciones` | PR4 | Exists |
| `/api/admin/entregas` | PR6 | Exists |
| `/api/admin/recursos` | PR6 | Exists |
| Bitácora endpoints for alert rules | PR6 | Exists |
| Backend validation: cupo >= assigned | PR2 | Verify |
| Backend: whitelist creates visible user | PR5 | Verify |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Backend response shape differs from mock, breaking tables | Medium | Add runtime type guards + fallback empty states; coordinate with backend owner before each PR |
| Student autocomplete slow with large user base | Medium | Debounce input + paginated search endpoint; cache results in sessionStorage |
| Removing navbar items breaks deep links users bookmarked | Low | Keep routes in router; only remove sidebar entries. Add redirect notices if needed |
| Sequential fase logic conflicts with existing entregas data | Medium | Frontend derives next fase from latest entrega; backend validates on create |
| Large PRs cause review bottleneck | Medium | Strict 600-line cap per PR; split further if review feedback is large |

## Rollback Plan

Each PR is independently revertible via `git revert`. Mock data files are kept in-repo until PR6 merges and verification passes. If a critical bug is found post-merge, revert the specific PR and the app falls back to the previous mock behavior for that page only. Feature flags are not required because coordinator pages are role-gated and the coordinador is the single stakeholder.

## Success Criteria

- [ ] CoordinadorDashboard shows live KPIs from `/api/admin/proyectos/kpis`
- [ ] GestionProyectos lists real projects by group; create/edit/delete functional
- [ ] Director cupo edits persist and validate minimums
- [ ] Student autocomplete returns real Estudiante users
- [ ] Directores page renders all directors with bitácora and project read-only views
- [ ] AsignacionEvaluadores CRUD + calendar + results table consume real endpoints
- [ ] GestionUsuarios shows unified table; whitelist additions appear immediately
- [ ] AnunciosAdmin sidebar active state isolated from public Anuncios
- [ ] GestionAlertas displays rule-based alerts from real data
- [ ] CoordinadorEntregas creates entregas with sequential fase logic
- [ ] RecursosAdmin uploads real files with preview and edit
- [ ] Sidebar shows only: Panel, Proyectos, Directores, Evaluadores, Usuarios, Anuncios, Alertas, Entregas, Recursos Admin
- [ ] All 6 PRs pass CI (lint, type-check, tests)
