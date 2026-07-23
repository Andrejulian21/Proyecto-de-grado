# Design: Frontend Wireframes Port

## Technical Approach

Port ~29 wireframes to React 18 + TypeScript + Tailwind v4, following the visual canon established by `GestionUsuarios.tsx`. Sprint 4 is pure frontend — all data is mock, no backend calls. Shared components extracted to `components/ui/` to eliminate duplication across the 29 pages. Dashboards upgraded in-place from placeholders to wireframe-faithful layouts. New pages use `React.lazy()` + `Suspense` for code-splitting.

## Architecture Decisions

| Decision | Choice | Alternatives | Rationale |
|----------|--------|-------------|-----------|
| Shared components location | `components/ui/` (new dir) | `components/shared/`, inline per-page | Matches proposal spec; clean separation from `components/layout/` |
| Mock data strategy | `const MOCK_*` at top of each page file | Central `mocks/` dir, JSON fixtures | Keeps PRs self-contained; Sprint 5 replaces inline mocks with `apiFetch()` calls — trivial find/replace |
| Route loading | `React.lazy()` + `Suspense` wrapper | Eager imports (current pattern) | 29 new pages would bloat main bundle; lazy keeps initial load fast |
| Landing page layout | Standalone, NO AppShell | Inside AppShell with hidden sidebar | Spec requires public pre-login page; AppShell adds auth guard + sidebar overhead |
| TOTP implementation | Custom 6-input component, mock validation | `otpauth` library, `speakeasy` | Sprint 4 is mock-only; custom component is ~60 lines, avoids dependency; Sprint 5 adds real TOTP verify |
| Visual canon | Raw hex Tailwind classes (matching GestionUsuarios) | Design token classes (`bg-primary`) | GestionUsuarios uses raw hex (`#c2410c`); mixing both creates inconsistency. Use raw hex to match canon. |
| Dashboard upgrades | Replace file contents in-place | New files + redirect | Existing routes/imports stay valid; no app.tsx changes for dashboards |

## Data Flow

```
app.tsx (routes)
  ├── / → LandingPage (public, no AppShell)
  ├── /login, /login/externo → existing auth pages
  └── /* → ProtectedRoute → AppShell
        ├── / → DashboardRouter → /dashboard/{role}
        ├── /dashboard/estudiante → EstudianteDashboard (upgraded)
        ├── /dashboard/coordinador → CoordinadorDashboard (upgraded)
        ├── /dashboard/director → DirectorDashboard (upgraded)
        ├── /dashboard/evaluador-externo → EvaluadorDashboard (upgraded)
        ├── /anuncios → AnunciosPublica
        ├── /anuncios/:id → AnuncioDetalle
        ├── /recursos → Recursos
        ├── /recursos/:id → RecursoDetalle
        ├── ... (batches 2-6 add ~20 more routes)
        └── * → Navigate to /
```

Each page: `MOCK_DATA` const → component state → render. No API calls in Sprint 4.

## Shared Components

### StatusBadge
- **File:** `components/ui/StatusBadge.tsx`
- **Props:** `variant: 'success' | 'warning' | 'error' | 'info' | 'en-curso' | 'inactivo' | 'riesgo'`, `children: ReactNode`
- **Renders:** `<span>` with `rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.03em]` + variant-specific bg/text colors
- **Used by:** All dashboards, Anuncios, Entregas, Bitacoras, Alertas, Evaluaciones (~20 pages)
- **Color map:** success=`#dcfce7/#14532d`, warning=`#fef3c7/#78350f`, error=`#fee2e2/#7f1d1d`, info=`#dbeafe/#1e3a8a`, en-curso=`#e0e7ff/on-secondary`, inactivo=`#e7e5e4/#57534e`, riesgo=`#fee2e2/#7f1d1d`

### StatCard
- **File:** `components/ui/StatCard.tsx`
- **Props:** `title: string`, `value: string | number`, `icon: LucideIcon`, `trend?: { direction: 'up' | 'down', value: string }`, `variant?: 'default' | 'warning' | 'success'`
- **Used by:** All 4 dashboards, Alertas, Reportes, BitacorasDirector
- **Layout:** Card with icon top-left, title muted, value bold 2xl, optional trend arrow bottom-right

### PageHeader
- **File:** `components/ui/PageHeader.tsx`
- **Props:** `eyebrow: string`, `title: string`, `subtitle?: string`, `actions?: ReactNode`
- **Used by:** All ~29 pages (replaces duplicated header pattern)
- **Layout:** Eyebrow pill + h2 + optional subtitle + optional action buttons row (flex justify-between)

### DataTable
- **File:** `components/ui/DataTable.tsx`
- **Props:** `columns: { key: string, label: string, render?: (row) => ReactNode }[]`, `data: T[]`, `loading?: boolean`, `emptyMessage?: string`, `pagination?: { page, totalPages, onPageChange }`
- **Used by:** CoordinadorDashboard, GestionProyectos, Bitacoras*, Entregas, AnunciosAdmin, AsignacionEvaluadores, Reportes (~15 pages)
- **States:** loading → Loader2 spinner; empty → centered message; data → table with GestionUsuarios canon styles

### EmptyState
- **File:** `components/ui/EmptyState.tsx`
- **Props:** `icon: LucideIcon`, `title: string`, `description?: string`, `action?: { label: string, onClick: () => void }`
- **Used by:** All pages with empty states (~20 pages)
- **Layout:** `py-16 text-center`, icon muted, title, description, optional outline button

### ConfirmDialog
- **File:** `components/ui/ConfirmDialog.tsx`
- **Props:** `open: boolean`, `title: string`, `message: string`, `confirmLabel?: string`, `cancelLabel?: string`, `onConfirm: () => void`, `onCancel: () => void`, `variant?: 'danger' | 'default'`
- **Used by:** AnunciosAdmin (delete), RecursosAdmin (delete), AsignacionEvaluadores, GestionProyectos
- **Layout:** Fixed overlay `bg-black/40`, centered white card, title + message + 2 buttons

### TOTPInput
- **File:** `components/ui/TOTPInput.tsx`
- **Props:** `onComplete: (code: string) => void`, `disabled?: boolean`, `error?: string`
- **Internal state:** `values: string[6]`, `status: 'entering' | 'verifying' | 'verified' | 'error'`
- **Behavior:** 6 individual `<input type="text" inputMode="numeric" maxLength={1}>`, autofocus cascade on keydown, paste handler splits 6-digit paste across inputs, backspace jumps to previous
- **Mock validation:** `onComplete` fires when 6 digits entered; parent handles mock async verify
- **Used by:** DetalleFirmaBitacora, NuevaBitacora

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `components/ui/StatusBadge.tsx` | Create | Shared status badge with 7 variants |
| `components/ui/StatCard.tsx` | Create | KPI card for dashboards |
| `components/ui/PageHeader.tsx` | Create | Eyebrow + title + subtitle + actions |
| `components/ui/DataTable.tsx` | Create | Generic table with loading/empty/pagination |
| `components/ui/EmptyState.tsx` | Create | Centered empty state with icon + action |
| `components/ui/ConfirmDialog.tsx` | Create | Modal confirm dialog |
| `components/ui/TOTPInput.tsx` | Create | 6-digit TOTP input grid |
| `pages/dashboard/EstudianteDashboard.tsx` | Modify | Replace placeholder with wireframe layout |
| `pages/dashboard/CoordinadorDashboard.tsx` | Modify | Replace placeholder with wireframe layout |
| `pages/dashboard/DirectorDashboard.tsx` | Modify | Replace placeholder with wireframe layout |
| `pages/dashboard/EvaluadorDashboard.tsx` | Modify | Replace placeholder with wireframe layout |
| `pages/shared/AnunciosPublica.tsx` | Create | Public announcement list |
| `pages/shared/AnuncioDetalle.tsx` | Create | Announcement detail view |
| `pages/shared/Recursos.tsx` | Create | Resource library grid |
| `pages/shared/RecursoDetalle.tsx` | Create | Resource detail view |
| `pages/landing/LandingPage.tsx` | Create | Public institutional landing (no AppShell) |
| `app.tsx` | Modify | Add lazy imports + new routes + Landing |
| `components/layout/AppShell.tsx` | Modify | Add new ROUTE_TITLES entries |
| `components/layout/Sidebar.tsx` | Modify | Add missing navConfig entries per role |

## Interfaces / Contracts

```typescript
// Mock data interfaces (defined per-page, mimicking backend Sprint 2/3 models)

interface Project {
  id: number; code: string; title: string; studentNames: string[];
  directorName: string; phase: 'Anteproyecto' | 'Presentacion' | 'Desarrollo' | 'Final';
  status: 'active' | 'at-risk' | 'completed'; alertCount: number;
}

interface Announcement {
  id: number; title: string; category: 'importante' | 'recordatorio' | 'informativo';
  date: string; author: string; excerpt: string; body: string; attachments?: { name: string; size: string }[];
}

interface Resource {
  id: number; title: string; type: 'reglamento' | 'guia' | 'plantilla' | 'tutorial';
  description: string; author: string; size: string; downloads: number; url: string;
}

interface Delivery {
  id: number; version: number; type: string; date: string; status: 'approved' | 'pending' | 'rejected' | 'locked';
  fileName: string; observations?: string; scores?: { criteria: string; score: number; weight: number }[];
}

interface Binnacle {
  id: number; date: string; topic: string; description: string; duration: string;
  signatureStatus: 'signed' | 'pending' | 'unsigned'; projectName: string;
}
```

## Routing Plan

**app.tsx changes:**
- Add `React.lazy()` imports for all new pages
- Add `<Suspense fallback={<Loader2 />}>` wrapper inside AppShell routes
- Landing at `/` outside ProtectedRoute (before the `/*` catch-all)
- New routes grouped by batch, each with `ProtectedRoute allowedRoles={[...]}`

**Sidebar navConfig additions:**
- Coordinador: add `/recursos` (Recursos), `/anuncios/admin` (Anuncios Admin), `/recursos/admin` (Recursos Admin), `/coordinador/entregas` (Entregas), `/coordinador/bitacoras` (Bitacoras)
- Director: add `/supervision` placeholder, `/bitacoras/proyectos` (Proyectos)
- Estudiante: already complete
- EvaluadorExterno: add `/anuncios`, `/recursos`

**ROUTE_TITLES additions:** ~15 new entries for nested routes (detail pages use prefix matching).

## Testing Strategy

| Layer | What | Approach |
|-------|------|----------|
| Visual | All pages render without crash | Manual browser check per batch |
| Component | StatusBadge variants, TOTPInput input/paste | Verify each variant renders correct colors; paste 6 digits auto-fills |
| Integration | Routing + role gating | Login as each role, verify sidebar + dashboard + accessible pages |
| Mock data | All pages show data (not empty/loading) | Each page renders with MOCK_DATA populated |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary.

## Migration / Rollout

No migration required. All changes are additive (new files) or in-place upgrades (dashboard placeholders → wireframe content). Existing routes and imports remain valid. Rollback: `git revert` of any batch merge commit.

## Open Questions

- [ ] Should `DataTable` pagination be controlled (parent manages state) or internal (component manages own state)? **Recommendation:** Controlled — matches GestionUsuarios pattern where parent owns page state.
- [ ] Landing page: should it use the existing Tailwind config tokens or can it introduce new visual elements (gradients, animations) since it's public-facing and outside AppShell? **Recommendation:** Stay within design system; use primary/secondary colors but allow full-width layout freedom.
