# Design: Coordinator Integration — Full Frontend-Backend Connection

## Technical Approach

Replace all mock data in coordinator pages with real API calls via the existing `apiFetch` helper. Introduce domain-scoped custom hooks (`useProyectos`, `useDirectores`, etc.) that encapsulate fetch/loading/error logic. Extract shared components (`SupervisionReadOnly`, `CalendarGrid`, `StudentAutocomplete`) for cross-page reuse. Wire 6 chained PRs, each branching from the previous merge, keeping `main` deployable throughout.

## Architecture Decisions

| Decision | Choice | Alternatives | Rationale |
|----------|--------|-------------|-----------|
| Data fetching | Custom hooks with `useReducer` + `apiFetch` | SWR, React Query, TanStack Query | No new deps; project uses bare `apiFetch` already; hooks are simple enough (1 endpoint per hook) that SWR overhead is unjustified |
| Cache strategy | `sessionStorage` for student autocomplete results | SWR cache, in-memory only | Autocomplete is read-heavy, results stable within session; avoids re-fetching on every keystroke |
| State management | Local `useState`/`useReducer` per page | Zustand, Context | Each page is independent; no cross-page state needed for coordinator views |
| Component extraction | `SupervisionReadOnly` wraps director view with `readOnly` prop | Duplicate component, HOC | Reuses existing `SupervisionProyectoDirector` layout; prop-gates edit controls |
| Calendar | Custom CSS grid with date math | FullCalendar, react-big-calendar | Evaluations are sparse (5–20/month); a library adds 80KB for a simple month grid |
| Form validation | Inline per-field + `HTMLFormElement.checkValidity()` | react-hook-form + Zod | Forms are simple (3–6 fields); react-hook-form adds complexity without proportional benefit |
| Error handling | `toast.error()` for global; inline banners for section | Error boundaries, react-error-boundary | Matches existing pattern in GestionUsuarios; toast already available via AppShell |

## Data Flow

```
┌─────────────────────────────────────────────────────────┐
│  Page Component (e.g., GestionProyectos)                │
│  ├─ useProyectos(grupoId)  → { data, loading, error }   │
│  ├─ useGrupos()            → { data, loading, error }   │
│  ├─ useCupos()             → { data, loading, error }   │
│  └─ useStudents(query)     → { data, loading }          │
│                                                         │
│  Each hook:                                             │
│    useState(data) + useEffect(apiFetch) + try/catch     │
│    Returns { data: T[], loading: bool, error: string }  │
│    Refetch via returned `refetch()` callback            │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────┐
│  apiFetch(endpoint)  │  ← existing, no changes needed
│  + credentials:include
│  + XSRF token
└─────────────────────┘
```

## File Changes

### PR1: Dashboard KPIs + SupervisionReadOnly + Sidebar (~200 lines)

| File | Action | Description |
|------|--------|-------------|
| `resources/js/hooks/useKpis.ts` | Create | Hook fetching `/api/admin/proyectos/kpis` |
| `resources/js/pages/dashboard/CoordinadorDashboard.tsx` | Modify | Replace MOCK_PROJECTS/MOCK_ALERTS with API calls; wire "Ver" to navigation |
| `resources/js/components/supervision/SupervisionReadOnly.tsx` | Create | Read-only wrapper reusing director supervision layout; hides edit/sign controls via `readOnly` prop |
| `resources/js/components/layout/Sidebar.tsx` | Modify | Fix active state: Panel → `/dashboard/coordinador`; Anuncios admin → `/anuncios/admin` distinct from public `/anuncios` |

### PR2: GestionProyectos Reform (~600 lines)

| File | Action | Description |
|------|--------|-------------|
| `resources/js/hooks/useProyectos.ts` | Create | CRUD hook for `/api/admin/proyectos` with group filter |
| `resources/js/hooks/useGrupos.ts` | Create | Fetch/create groups from `/api/admin/proyectos/grupos` |
| `resources/js/hooks/useCupos.ts` | Create | Fetch/update director cupos from `/api/admin/directores/cupos` |
| `resources/js/hooks/useStudentSearch.ts` | Create | Debounced autocomplete from `/api/admin/usuarios?role=estudiante`; sessionStorage cache |
| `resources/js/components/forms/StudentAutocomplete.tsx` | Create | Multi-select (max 3) with debounced search dropdown |
| `resources/js/components/forms/GroupSelector.tsx` | Create | Dropdown + "Crear grupo" inline form |
| `resources/js/pages/coordinador/GestionProyectos.tsx` | Replace | Full rewrite: GroupSelector + ProjectTable + CupoTable + CreateProjectForm |

### PR3: Directores Page (~300 lines)

| File | Action | Description |
|------|--------|-------------|
| `resources/js/hooks/useDirectores.ts` | Create | Fetch directors + their projects/bitacoras |
| `resources/js/pages/coordinador/DirectoresPage.tsx` | Create | Card grid → drill-down to bitacoras or SupervisionReadOnly |
| `resources/js/app.tsx` | Modify | Add `/directores` route with Coordinador role guard |
| `resources/js/components/layout/Sidebar.tsx` | Modify | Add "Directores" entry (already present in navConfig — verify route match) |

### PR4: AsignacionEvaluadores Reform (~500 lines)

| File | Action | Description |
|------|--------|-------------|
| `resources/js/hooks/useEvaluadorProyecto.ts` | Create | CRUD for `/api/admin/evaluador-proyecto` |
| `resources/js/hooks/useEvaluaciones.ts` | Create | Fetch results from `/api/evaluaciones` |
| `resources/js/components/calendar/CalendarGrid.tsx` | Create | Month-view CSS grid; marks dates with scheduled evaluations |
| `resources/js/components/tables/ResultsTable.tsx` | Create | Evaluation results with average scores |
| `resources/js/pages/coordinador/AsignacionEvaluadores.tsx` | Replace | AssignmentTable + RegistrationForm + CalendarGrid + ResultsTable |

### PR5: GestionUsuarios Reform (~300 lines)

| File | Action | Description |
|------|--------|-------------|
| `resources/js/hooks/useUnifiedUsers.ts` | Create | Merges `/api/admin/usuarios` + `/api/admin/whitelist` + `/api/admin/evaluadores`; deduplicates by email |
| `resources/js/pages/coordinador/GestionUsuarios.tsx` | Replace | Single unified table with editable role dropdown, last-access column, immediate whitelist reflection |

### PR6: Entregas + Alertas + RecursosAdmin (~500 lines)

| File | Action | Description |
|------|--------|-------------|
| `resources/js/hooks/useEntregas.ts` | Create | CRUD + group/fase filters for `/api/admin/entregas` |
| `resources/js/hooks/useAlertas.ts` | Create | Derives alerts from bitacora/entrega endpoints (3 rules) |
| `resources/js/hooks/useRecursos.ts` | Create | Upload/CRUD for `/api/admin/recursos` with FormData |
| `resources/js/pages/coordinador/CoordinadorEntregas.tsx` | Replace | Sequential fase logic + group filters |
| `resources/js/pages/coordinador/GestionAlertas.tsx` | Modify | Wire 3 alert rules to real data |
| `resources/js/pages/coordinador/RecursosAdmin.tsx` | Modify | Replace setTimeout mocks with real FormData upload + preview |

## Interfaces / Contracts

```typescript
// Shared hook return type
interface UseApiResult<T> {
  data: T[];
  loading: boolean;
  error: string | null;
  refetch: () => void;
}

// API response shapes
interface KpiResponse {
  proyectos_activos: number;
  en_riesgo: number;
  alertas_sin_revisar: number;
  tasa_cumplimiento: number; // 0–100
}

interface Proyecto {
  id: number;
  code: string;
  title: string;
  students: { id: number; name: string }[];
  director: { id: number; name: string };
  phase: 'Anteproyecto' | 'Presentacion' | 'Desarrollo' | 'Final';
  status: 'active' | 'at-risk' | 'completed';
  grupo_id: number;
}

interface DirectorCupo {
  id: number;
  name: string;
  areas: string[];
  active_projects: number;
  max_capacity: number;
}

interface EvaluadorProyecto {
  id: number;
  proyecto_id: number;
  proyecto_code: string;
  evaluadores: { id: number; name: string }[];
  evaluation_date: string;
  evaluation_time: string;
  phase: 'Anteproyecto' | 'Final';
  status: 'scheduled' | 'completed' | 'pending';
}

interface UnifiedUser {
  id: number | null; // null for whitelist-only entries
  email: string;
  name: string;
  role: 'Estudiante' | 'Director' | 'Coordinador' | 'EvaluadorExterno' | 'Pendiente';
  last_access: string | null;
  source: 'usuarios' | 'whitelist' | 'evaluadores';
}

interface Entrega {
  id: number;
  grupo_id: number;
  fase: 'anteproyecto' | 'presentacion_anteproyecto' | 'desarrollo' | 'presentacion_final';
  delivery_date: string;
  max_time_minutes: number;
  description: string;
  acceptance_criteria: string;
}

const FASE_SEQUENCE = [
  'anteproyecto',
  'presentacion_anteproyecto',
  'desarrollo',
  'presentacion_final',
] as const;
```

## Routing

### New route (PR3)
- `/directores` → `DirectoresPage` (Coordinador only)

### Routes kept in router but removed from sidebar
- `/coordinador/bitacoras` (CoordinadorBitacoras)
- `/semestre` (no component — was placeholder)
- `/reportes` (ReportesConsolidados)

### Sidebar final config for Coordinador
```
Panel          → /dashboard/coordinador    (LayoutDashboard)
Proyectos      → /proyectos                (FolderKanban)
Directores     → /directores               (UserCheck)
Evaluadores    → /evaluadores              (ClipboardCheck)
Usuarios       → /coordinador/usuarios     (Users)
Anuncios       → /anuncios/admin           (Megaphone)
Alertas        → /alertas                  (Bell)
Entregas       → /coordinador/entregas     (FolderKanban)
Recursos Admin → /recursos/admin           (FolderKanban)
```

### Sidebar active-state fix (PR1)
Current bug: "Panel" uses `to="/"` with `end` which matches any route. Fix: change to `to="/dashboard/coordinador"` with `end`. Anuncios collision: split into two entries — public `/anuncios` (all roles) and admin `/anuncios/admin` (coordinador only). Use `end` prop on both to prevent cross-activation.

## Testing Strategy

| Layer | What | Approach |
|-------|------|----------|
| Unit | Custom hooks | Render with `renderHook`, mock `apiFetch`, assert data/loading/error transitions |
| Unit | StudentAutocomplete | Mock fetch, verify debounce (300ms), max-3 selection, sessionStorage cache hit |
| Unit | CalendarGrid | Pass assignment dates, verify correct cells marked |
| Integration | Page → hook → apiFetch | Mock `global.fetch`, verify full render cycle with loading → success |
| E2E | Full flow per PR | Playwright: login as coordinador → navigate → verify data loads → perform CRUD |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary.

## Migration / Rollout

No migration required. Each PR replaces mock data with API calls on coordinator-only pages. Mock files are preserved until PR6 verification passes. Rollback: `git revert <pr-commit>` per PR.

## Skills per PR

| PR | Skills to Load | Rationale |
|----|---------------|-----------|
| **PR1** | react-patterns, tailwind-patterns, shadcn-ui, impeccable, accessibility | Component composition, skeleton loaders, sidebar active-state logic, WCAG aria-labels |
| **PR2** | react-patterns, react-state-management, typescript-expert, tailwind-patterns, shadcn-ui, strict-tdd | Complex form state, autocomplete debounce, TypeScript interfaces, TDD for hooks |
| **PR3** | react-patterns, tailwind-patterns, shadcn-ui, accessibility | Card grid layout, drill-down navigation, ARIA for card actions |
| **PR4** | react-patterns, react-state-management, tailwind-patterns, shadcn-ui, typescript-expert, strict-tdd | Calendar grid state, multi-evaluator form, TypeScript for date math |
| **PR5** | react-patterns, typescript-expert, tailwind-patterns, shadcn-ui, strict-tdd | Data merging logic, deduplication, role dropdown, unified table types |
| **PR6** | react-patterns, react-state-management, tailwind-patterns, shadcn-ui, typescript-expert, strict-tdd | Sequential fase logic, FormData upload, alert rule derivation |
| **All** | work-unit-commits, branch-pr, chained-pr, comment-writer | Every PR: atomic commits, chained PR workflow, warm review comments |

## Open Questions

- [ ] Verify `/api/admin/proyectos/kpis` response shape matches `KpiResponse` interface — backend team to confirm
- [ ] Confirm student autocomplete endpoint supports `?search=` query param or if we need `?q=`
- [ ] Clarify if `/api/admin/directores` returns `areas` (specialization) field or if it needs backend addition
- [ ] Verify whitelist POST creates a visible user row immediately or if frontend must optimistically render
