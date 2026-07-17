# Proposal: Director Integration

## Intent
Replace all Director frontend mock data with live `apiFetch()` calls. The Director role currently shows static projects, deliveries, and binnacles — this makes supervision, evaluation, and resources functional.

## Scope

### In Scope
- **Navbar**: Fix active state; remove "Bitácoras" and "Bitácoras Proyectos" entries
- **Dashboard** (`/dashboard/director`): Remove PhaseStepper; horizontal carousel of supervised projects in active semesters; table of latest deliveries with actions
- **Supervision** (`/supervision`): Cards with assigned projects; drill-down to deliveries (view/comment/approve/reject) and binnacle table (view detail, sign)
- **Evaluations**: Projects where director is evaluator; show approved phase delivery; grade
- **Resources**: Wire to `/api/recursos`; enable download

### Out of Scope
- TOTP binnacle signing (state-only for now), AI, deployment, reports

## Capabilities

### New Capabilities
- `director-dashboard-api`: Endpoints for KPIs, supervised projects, pending deliveries
- `supervision-api`: Project detail, delivery review, binnacle list by project
- `evaluator-grade-api`: Grade submission on approved phase delivery

### Modified Capabilities
- `director-ui`: Replace mock with `apiFetch` across all Director pages
- `sidebar-navigation`: Active state fix + trimmed entries

## Approach
Reuse existing controllers. Add `/api/director/*` routes for aggregations. Create hooks: `useDirectorProyectos`, `useDirectorEntregas`, `useDirectorEvaluaciones`. Minimal UI changes — only data sources.

## PR Estimation (chained, ≤400 lines each)

| # | Slice | Files | Lines (est) |
|---|-------|-------|-------------|
| 1 | Navbar fix | `Sidebar.tsx` | ~30 |
| 2 | Dashboard real data | `DirectorDashboard.tsx`, `DirectorController.php`, routes | ~180 |
| 3 | Supervision flow | `SupervisionProyectoDirector.tsx`, `BitacorasDirector.tsx`, hooks | ~220 |
| 4 | Evaluations | `EvaluacionesDirector.tsx`, grade endpoint | ~150 |
| 5 | Resources | `RecursosDirector.tsx`, download action | ~80 |

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `Sidebar.tsx` | Modified | Active state fix; remove 2 entries |
| `DirectorDashboard.tsx` | Modified | Remove PhaseStepper; carousel + table |
| `SupervisionProyectoDirector.tsx` | Modified | Real data; "Ver Bitácora" flow |
| `BitacorasDirector.tsx` | Modified | Load from `/api/proyectos/{id}/bitacoras` |
| `EvaluacionesDirector.tsx` | New | Evaluator grading view |
| `DirectorController.php` | New | Aggregated endpoints |
| `routes/api.php` | Modified | Register `/director/*` routes |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Missing "supervised projects in active semester" endpoint | Med | Extend `ProyectoController::index` with `?director_id` + `?semestre_activo` filters |
| Evaluator-grade data model ambiguity | Med | Align with Coordinador evaluator schema before coding |
| Mock-to-real switch breaks UI assumptions | Low | Playwright smoke tests per PR |
| Conflict with coordinador-integracion in flight | Med | Rebase daily; isolate `/director/*` routes |

## Rollback Plan
Each slice is an independent PR. Revert merge commit of failing slice. Mock data deleted; restore from Sprint 4 git history if needed.

## Dependencies
- Sprint 2 backend endpoints stable
- Coordinador evaluator-assignment schema finalized

## Success Criteria
- [ ] Navbar correct active state + trimmed entries
- [ ] Dashboard loads real supervised projects and deliveries in < 500ms
- [ ] Supervision lists only projects assigned to authenticated director
- [ ] Evaluations shows correct phase delivery per evaluator assignment
- [ ] Resources lists real files with working download
- [ ] All slices pass Playwright smoke tests
