# Tasks: Coordinator Integration — Full Frontend-Backend Connection

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~2,400 (across 6 PRs) |
| 400-line budget risk | High (PR2 ~600, PR4 ~500, PR6 ~500) |
| Chained PRs recommended | Yes |
| Suggested split | PR1 → PR2 → PR3 → PR4 → PR5 → PR6 |
| Delivery strategy | auto-chain |
| Chain strategy | feature-branch-chain |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Dashboard KPIs + SupervisionReadOnly + sidebar fixes | PR1 (coordinador-dashboard) | `vendor/bin/pest --filter=CoordinadorDashboard` | Login as coordinador → navigate `/dashboard/coordinador` | `git revert <pr1-sha>` — mocks preserved |
| 2 | GestionProyectos reform — groups, cupos, create form | PR2 (gestion-proyectos) | `vendor/bin/pest --filter=GestionProyectos` | Login as coordinador → navigate `/proyectos` | `git revert <pr2-sha>` — mocks work until PR2 |
| 3 | New Directores page + route | PR3 (directores-page) | `vendor/bin/pest --filter=DirectoresPage` | Login as coordinador → navigate `/directores` | `git revert <pr3-sha>` — PR2 unaffected |
| 4 | AsignacionEvaluadores reform — calendar, results | PR4 (asignacion-evaluadores) | `vendor/bin/pest --filter=AsignacionEvaluadores` | Login as coordinador → navigate `/evaluadores` | `git revert <pr4-sha>` — PR3 unaffected |
| 5 | GestionUsuarios unified table + whitelist | PR5 (gestion-usuarios) | `vendor/bin/pest --filter=GestionUsuarios` | Login as coordinador → navigate `/coordinador/usuarios` | `git revert <pr5-sha>` — PR4 unaffected |
| 6 | Entregas + Alertas + RecursosAdmin real upload | PR6 (entregas-alertas-recursos) | `vendor/bin/pest --filter=Entregas,Alertas,RecursosAdmin` | Login as coordinador → navigate each page | `git revert <pr6-sha>` — mocks can be removed after verify |

---

## PR1: Dashboard KPIs + SupervisionReadOnly + Sidebar (~200 lines)

### 1.1 Create `useKpis` hook

**Files**: `resources/js/hooks/useKpis.ts` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`

**Requirements**: R1.1, R1.2, R1.3, R1.4

**Acceptance Criteria**: Hook returns `{data: KpiResponse, loading, error, refetch}`. Fetches `/api/admin/proyectos/kpis`. Loading true during fetch, error string on failure.

**Dependencies**: None

**Notes**: Implement `UseApiResult<KpiResponse>` pattern. `apiFetch` is global. Handle null/undefined KPI values gracefully (display "—").

### 1.2 Wire CoordinadorDashboard to real API + add supervision navigation

**Files**: `resources/js/pages/dashboard/CoordinadorDashboard.tsx` (MODIFY)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\tailwind-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\shadcn-ui\SKILL.md`

**Requirements**: R1.2, R1.3, R1.4, R1.5

**Acceptance Criteria**: 4 KPI cards render live data; skeleton cards while loading; error toast on failure; "Ver" navigates to `/dashboard/coordinador/proyecto/:id`.

**Dependencies**: 1.1

**Notes**: Remove `MOCK_PROJECTS` and `MOCK_ALERTS` arrays. Retain skeleton/loading/error state management. Use `useNavigate` for supervision link.

### 1.3 Create SupervisionReadOnly component

**Files**: `resources/js/components/supervision/SupervisionReadOnly.tsx` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\accessibility\SKILL.md`

**Requirements**: R1.6, R1.7, R1.8

**Acceptance Criteria**: Shows project name, students, phase, deliveries, submissions. Hides all edit/sign/observation controls. Reuses `SupervisionProyectoDirector` layout via `readOnly` prop.

**Dependencies**: None

**Notes**: Import existing `SupervisionProyectoDirector` pattern. Wrap with `readOnly={true}` to gate control visibility.

### 1.4 Fix sidebar active state for Panel + Anuncios

**Files**: `resources/js/components/layout/Sidebar.tsx` (MODIFY)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\accessibility\SKILL.md`

**Requirements**: R1.9, R1.10

**Acceptance Criteria**: "Panel" (label `Panel de Control`) active on `/dashboard/coordinador`. Admin Anuncios (`/anuncios/admin`) does not activate public Anuncios (`/anuncios`) entry.

**Dependencies**: None

**Notes**: Change `end={item.to === '/'}` to `end={item.to === '/' || item.to === '/dashboard/coordinador'}` for the Panel row. For Anuncios entries, use `end` prop on both to prevent cross-activation.

---

## PR2: GestionProyectos Reform (~600 lines)

### 2.1 Create `useProyectos` hook

**Files**: `resources/js/hooks/useProyectos.ts` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\react-state-management\SKILL.md`
- `C:\Users\Owner\.claude\skills\typescript-expert\SKILL.md`
- `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md`

**Requirements**: R2.2, R2.3, R2.4

**Acceptance Criteria**: CRUD via `/api/admin/proyectos`. `refetch()` after mutations. Filterable by `grupo_id`.

**Dependencies**: 1.2, 1.4

**Notes**: Implement `useReducer` for CRUD state transitions. Write hook test with `renderHook` + mocked `apiFetch`.

### 2.2 Create `useGrupos` hook

**Files**: `resources/js/hooks/useGrupos.ts` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md`

**Requirements**: R2.1, R2.5

**Acceptance Criteria**: List/create groups via `/api/admin/proyectos/grupos`. Returns `{data, loading, error, refetch}`.

**Dependencies**: None

**Notes**: Simple fetch + create. No update/delete needed.

### 2.3 Create `useCupos` hook

**Files**: `resources/js/hooks/useCupos.ts` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\typescript-expert\SKILL.md`
- `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md`

**Requirements**: R2.6, R2.7

**Acceptance Criteria**: Fetch `/api/admin/directores/cupos`. PUT update per director. Validates `max >= active_projects` before submit.

**Dependencies**: None

**Notes**: Test validation logic: attempt save with max < active projects → block.

### 2.4 Create `useStudentSearch` hook + StudentAutocomplete component

**Files**:
- `resources/js/hooks/useStudentSearch.ts` (CREATE)
- `resources/js/components/forms/StudentAutocomplete.tsx` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\react-state-management\SKILL.md`
- `C:\Users\Owner\.claude\skills\tailwind-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\shadcn-ui\SKILL.md`
- `C:\Users\Owner\.claude\skills\typescript-expert\SKILL.md`
- `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md`

**Requirements**: R2.8, R2.9, R2.10

**Acceptance Criteria**: Debounced search (300ms) via `/api/admin/usuarios?role=estudiante`. Max 3 selected. SessionStorage cache on results. Component shows selected chips + remove.

**Dependencies**: None

**Notes**: Test debounce timing, max-selection enforcement, cache hit vs miss.

### 2.5 Create GroupSelector component

**Files**: `resources/js/components/forms/GroupSelector.tsx` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\tailwind-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\shadcn-ui\SKILL.md`

**Requirements**: R2.1, R2.5

**Acceptance Criteria**: Dropdown populated from `useGrupos`. "Crear grupo" inline form. Emits `onSelect(groupId)`.

**Dependencies**: 2.2

**Notes**: Use shadcn `Select` component.

### 2.6 Replace GestionProyectos page

**Files**: `resources/js/pages/coordinador/GestionProyectos.tsx` (REPLACE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\react-state-management\SKILL.md`
- `C:\Users\Owner\.claude\skills\tailwind-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\shadcn-ui\SKILL.md`

**Requirements**: R2.1–R2.10

**Acceptance Criteria**: GroupSelector filters project table. Create/edit/delete projects works. Cupo table editable. Student autocomplete functional. Confirmation modal before delete. Inline validation errors.

**Dependencies**: 2.1, 2.2, 2.3, 2.4, 2.5

**Notes**: Compose all hooks + sub-components. Use `DataTable`, `ConfirmDialog` from shared UI. Remove `MOCK_PROJECTS`/`MOCK_CUPOS`. Add loading/empty/error states per section.

---

## PR3: Directores Page (~300 lines)

### 3.1 Create `useDirectores` hook

**Files**: `resources/js/hooks/useDirectores.ts` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`

**Requirements**: R3.1, R3.3, R3.4

**Acceptance Criteria**: Fetch directors list. Fetch director's projects. Fetch project bitácoras. Return `{data, loading, error}` per sub-resource.

**Dependencies**: 2.6

**Notes**: Three fetch methods: `fetchDirectores()`, `fetchDirectorProyectos(id)`, `fetchProjectBitacoras(id)`.

### 3.2 Create DirectoresPage component

**Files**: `resources/js/pages/coordinador/DirectoresPage.tsx` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\tailwind-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\shadcn-ui\SKILL.md`
- `C:\Users\Owner\.claude\skills\accessibility\SKILL.md`

**Requirements**: R3.1, R3.2, R3.3, R3.4

**Acceptance Criteria**: Card grid with director name + specialization areas. "Ver bitácoras" opens drill-down → project cards → bitácora viewer. "Ver proyectos" opens drill-down → project cards → SupervisionReadOnly. Back navigation from sub-views.

**Dependencies**: 1.3, 3.1

**Notes**: Use `SupervisionReadOnly` from PR1. Drill-down navigation with parent state (selected director → selected project → view). Add `aria-label` to action buttons.

### 3.3 Add `/directores` route to app.tsx + sidebar

**Files**:
- `resources/js/app.tsx` (MODIFY)
- `resources/js/components/layout/Sidebar.tsx` (MODIFY)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`

**Requirements**: R3.5

**Acceptance Criteria**: `/directores` renders `DirectoresPage` (Coordinador only). "Directores" entry visible in sidebar for coordinador role.

**Dependencies**: 3.2

**Notes**: Add lazy import + route with `allowedRoles={['Coordinador']}`. Sidebar already has `/directores` in navConfig — verify route match.

---

## PR4: AsignacionEvaluadores Reform (~500 lines)

### 4.1 Create `useEvaluadorProyecto` hook

**Files**: `resources/js/hooks/useEvaluadorProyecto.ts` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\react-state-management\SKILL.md`
- `C:\Users\Owner\.claude\skills\typescript-expert\SKILL.md`
- `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md`

**Requirements**: R4.1, R4.2, R4.3, R4.7

**Acceptance Criteria**: Full CRUD via `/api/admin/evaluador-proyecto`. Returns `{data, loading, error, refetch}`.

**Dependencies**: 3.2

**Notes**: Use `useReducer` for CRUD. Test create → list → edit → delete cycle.

### 4.2 Create `useEvaluaciones` hook

**Files**: `resources/js/hooks/useEvaluaciones.ts` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md`

**Requirements**: R4.5, R4.7

**Acceptance Criteria**: Fetch `/api/evaluaciones`. Results include average score per project.

**Dependencies**: None

**Notes**: Read-only fetch. Calculate average from evaluator scores client-side if not in response.

### 4.3 Create CalendarGrid component

**Files**: `resources/js/components/calendar/CalendarGrid.tsx` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\tailwind-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\shadcn-ui\SKILL.md`

**Requirements**: R4.4

**Acceptance Criteria**: Month-view CSS grid. Dates with scheduled evaluations are visually marked. "Sin eventos" empty state.

**Dependencies**: 4.1

**Notes**: Custom grid (no FullCalendar dependency). Derive marked dates from assignment data. Test date math + cell marking.

### 4.4 Create ResultsTable component

**Files**: `resources/js/components/tables/ResultsTable.tsx` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\tailwind-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\shadcn-ui\SKILL.md`

**Requirements**: R4.5

**Acceptance Criteria**: Shows project, students, director, phase, evaluators, average score. "Pendiente" for evaluations with 0 responses.

**Dependencies**: 4.2

**Notes**: Reuse `DataTable` with custom scoring column.

### 4.5 Replace AsignacionEvaluadores page

**Files**: `resources/js/pages/coordinador/AsignacionEvaluadores.tsx` (REPLACE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\react-state-management\SKILL.md`
- `C:\Users\Owner\.claude\skills\tailwind-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\shadcn-ui\SKILL.md`
- `C:\Users\Owner\.claude\skills\typescript-expert\SKILL.md`

**Requirements**: R4.1–R4.7

**Acceptance Criteria**: AssignmentTable + RegistrationForm + CalendarGrid + ResultsTable all render and wire. Edit modal. Delete confirmation. Form enforces 2–3 evaluators.

**Dependencies**: 4.1, 4.2, 4.3, 4.4

**Notes**: Registration form: project dropdown, phase (Anteproyecto/Final), 2 required + 1 optional evaluator, date/time pickers. CalendarGrid derived from assignments. Error states per section.

---

## PR5: GestionUsuarios Reform (~300 lines)

### 5.1 Create `useUnifiedUsers` hook

**Files**: `resources/js/hooks/useUnifiedUsers.ts` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\typescript-expert\SKILL.md`
- `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md`

**Requirements**: R5.1, R5.4, R5.5

**Acceptance Criteria**: Merges `/api/admin/usuarios` + `/api/admin/whitelist` + `/api/admin/evaluadores`. Deduplicates by email (prefer usuarios row). Whitelist-only entries show "Pendiente" role + no ID. Return `UnifiedUser[]`.

**Dependencies**: 4.5

**Notes**: Test deduplication logic (same email in usuarios + evaluadores → one row). Test optimistic whitelist reflection.

### 5.2 Replace GestionUsuarios page

**Files**: `resources/js/pages/coordinador/GestionUsuarios.tsx` (REPLACE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\tailwind-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\shadcn-ui\SKILL.md`

**Requirements**: R5.1, R5.2, R5.3, R5.4, R5.5

**Acceptance Criteria**: Single table with name, email, role dropdown, last-access timestamp. Role save via PUT. Delete with confirmation. Whitelist addition shows row immediately. Self-role-change blocked.

**Dependencies**: 5.1

**Notes**: Remove `MOCK_USERS`. Use shadcn `Select` for role dropdown. Deduplication happens in hook. Add `"No puedes cambiar tu propio rol"` toast when user tries to edit own role.

---

## PR6: Entregas + Alertas + RecursosAdmin (~500 lines)

### 6.1 Create `useEntregas` hook

**Files**: `resources/js/hooks/useEntregas.ts` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\react-state-management\SKILL.md`
- `C:\Users\Owner\.claude\skills\typescript-expert\SKILL.md`
- `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md`

**Requirements**: R6.1, R6.2, R6.3, R6.4

**Acceptance Criteria**: CRUD via `/api/admin/entregas`. Filters by group + fase + delivery. Auto-computes next fase from `FASE_SEQUENCE`. Group without entregas → defaults to "Anteproyecto".

**Dependencies**: 2.1, 5.2

**Notes**: Implement `FASE_SEQUENCE` constant. `getNextFase(grupoId)` queries last entrega → returns next in sequence. Test fase computation with 0, 1, N entregas.

### 6.2 Create `useAlertas` hook

**Files**: `resources/js/hooks/useAlertas.ts` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\typescript-expert\SKILL.md`
- `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md`

**Requirements**: R6.5, R6.6, R6.7, R6.8

**Acceptance Criteria**: Rule 1: unsigned bitácoras > 1h. Rule 2: entregas past deadline with no submission. Rule 3: directors signed >2 bitácoras in 1h window. All derived from bitácora/entrega endpoints.

**Dependencies**: 2.1

**Notes**: Alert derivation is client-side from fetched data (not backend polling). Test each rule with mock data.

### 6.3 Create `useRecursos` hook

**Files**: `resources/js/hooks/useRecursos.ts` (CREATE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\react-state-management\SKILL.md`
- `C:\Users\Owner\.claude\skills\typescript-expert\SKILL.md`
- `C:\Users\Owner\.claude\skills\strict-tdd\SKILL.md`

**Requirements**: R6.9, R6.10, R6.11

**Acceptance Criteria**: Upload via `FormData` to `/api/admin/recursos`. Preview before save. Edit title, description, file. Delete with confirmation.

**Dependencies**: None

**Notes**: Set `Content-Type: multipart/form-data`. Remove all `setTimeout`/mock delay logic. Test upload progress indication.

### 6.4 Replace CoordinadorEntregas page

**Files**: `resources/js/pages/coordinador/CoordinadorEntregas.tsx` (REPLACE)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\tailwind-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\shadcn-ui\SKILL.md`

**Requirements**: R6.1, R6.2, R6.3, R6.4

**Acceptance Criteria**: Group selector + fase auto-compute + entregas table with filters. Create form auto-selects next fase.

**Dependencies**: 2.5, 6.1, 6.2

**Notes**: Reuse `GroupSelector` from PR2. Compose `useEntregas` for table + create.

### 6.5 Wire GestionAlertas to real data

**Files**: `resources/js/pages/coordinador/GestionAlertas.tsx` (MODIFY)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\tailwind-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\shadcn-ui\SKILL.md`

**Requirements**: R6.5, R6.6, R6.7, R6.8

**Acceptance Criteria**: Alert cards display derived alerts. "Sin alertas activas" empty state. Each alert shows relevant details (project, director, time).

**Dependencies**: 6.2

**Notes**: Alert cards with icon, title, description, timestamp. Group by rule type.

### 6.6 Rewire RecursosAdmin to real upload

**Files**: `resources/js/pages/coordinador/RecursosAdmin.tsx` (MODIFY)

**Skills**:
- `C:\Users\Owner\.claude\skills\react-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\react-state-management\SKILL.md`
- `C:\Users\Owner\.claude\skills\tailwind-patterns\SKILL.md`
- `C:\Users\Owner\.claude\skills\shadcn-ui\SKILL.md`

**Requirements**: R6.9, R6.10, R6.11

**Acceptance Criteria**: Real FormData upload replaces setTimeout mock. Preview modal shows file. Edit metadata works. Upload errors show validation toast.

**Dependencies**: 6.3

**Notes**: Remove all `setTimeout` fake delays. Use `<input type="file">` with accept filters. Preview before upload (object URL).

---

## Cross-Cutting Verification

- [ ] All 6 PRs pass `npm run build` (0 TS errors)
- [ ] All 6 PRs pass `vendor/bin/pest` with no regressions
- [ ] Each PR independently revertible via `git revert`
- [ ] Mock files NOT removed until PR6 verification passes
- [ ] UI copy in Spanish; code/comments/docs in English
- [ ] All interactive elements have `aria-label`; tables have `aria-describedby`
- [ ] Each PR targets its feature branch; child PR base = previous PR branch
