# Proposal: Frontend Wireframes Port

## Intent

Portear los ~30 wireframes de Open Design a React 18 + TypeScript + Tailwind v4 + shadcn/ui, manteniendo el layout existente (AppShell/Sidebar/Header), el design system UNAB y la coherencia visual con las páginas ya portadas (auth, gestión usuarios, audit-log).

**Sprint:** 4 (Frontend puro — sin integración con backend)
**Integración backend:** Sprint 5

---

## Scope

### In Scope

- Portear ~24 wireframes a React con mock data (KPIs estáticos, tablas con datos de ejemplo, cards de dashboard)
- Mantener el AppShell/Sidebar/Header exactamente como están (cambiando solo la navegación si es necesario)
- Navbars diferentes por rol (ya implementado en Sidebar.tsx con navConfig por rol)
- Coherencia visual con las páginas ya portadas: GestionUsuarios.tsx, AuditLog.tsx, LoginInstitucional.tsx, LoginExterno.tsx
- Usar **lucide-react** para iconos (estándar del proyecto, no Material Symbols)
- Extraer componentes compartidos: StatusBadge, DataTable, StatCard, PageHeader, EmptyState, ConfirmDialog, TOTPInput
- Landing page institucional (pre-login)
- TOTP para firma de bitácoras
- Pantallas IA con mock data (placeholder)

| Batch | Focus | Wireframes |
|-------|-------|------------|
| 1 | Dashboards reales (porteados de wireframes) + páginas compartidas | panel-estudiante, panel-coordinador, panel-director, evaluador-panel, anuncios-publica, anuncio-detalle, recursos, recurso-detalle |
| 2 | Landing + flujo Estudiante | index (landing), bitacoras-estudiante, nueva-bitacora, detalle-entrega-estudiante |
| 3 | Flujo Director | supervision-proyecto, seleccion-proyectos-bitacoras, bitacoras-director, detalle-firma-bitacora, revision-entrega-director |
| 4 | Gestión Coordinador | gestion-proyectos, anuncios-admin, asignacion-evaluadores, coordinador-entregas |
| 5 | Coordinador resto + Evaluador | coordinador-bitacoras, gestion-alertas, reportes-consolidados, recursos-admin, evaluador-calificar, evaluar-proyecto |
| 6 | IA + Landing | analisis-automatico-entregas, asistente-orientacion |

### Out of Scope

- **Backend API integration** — se hace en Sprint 5. Sprint 4 usa mock data.
- FastAPI IA module — solo mock UI.
- Rediseñar AppShell/Sidebar/Header existentes.
- Nuevos endpoints backend.
- Tests E2E (Playwright) — postergado a Sprint 6.

---

## Design Approach

### Mantener patrones existentes (de GestionUsuarios.tsx)

El estilo de GestionUsuarios.tsx es el canon visual a seguir:
- **Cards:** `rounded-xl border border-border bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]`
- **Botones primary:** `rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover`
- **Botones outline:** `rounded-lg border border-border bg-transparent px-4 py-2.5 text-sm font-semibold text-text hover:border-primary hover:bg-primary-container hover:text-primary`
- **Tablas:** contenedor `rounded-lg border border-border`, thead `bg-surface-alt text-[11px] font-bold uppercase tracking-[0.05em] text-text-muted`
- **Badges:** `rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.03em]`
- **Inputs:** `min-h-[40px] rounded-lg border border-border bg-white px-3 py-2 text-sm text-text outline-none placeholder:text-text-subtle focus:border-primary focus:shadow-[0_0_0_3px_#fed7aa]`
- **Page header:** Eyebrow pill + h2 título + p subtítulo
- **Iconos:** lucide-react en lugar de Material Symbols

### Componentes compartidos a extraer

| Componente | Ubicación | Basado en |
|-----------|-----------|-----------|
| StatusBadge | `components/ui/StatusBadge.tsx` | wireframes + shadcn Badge |
| StatCard | `components/ui/StatCard.tsx` | Wireframes dashboard |
| PageHeader | `components/ui/PageHeader.tsx` | GestionUsuarios pattern |
| DataTable | `components/ui/DataTable.tsx` | shadcn Table |
| EmptyState | `components/ui/EmptyState.tsx` | GestionUsuarios pattern |
| ConfirmDialog | `components/ui/ConfirmDialog.tsx` | GestionUsuarios modal |
| TOTPInput | `components/ui/TOTPInput.tsx` | Wireframes bitácora firma |

### Navegación por rol

La Sidebar.tsx ya tiene `navConfig` por rol (Coordinador, Director, Estudiante, EvaluadorExterno). Solo se agregarán entradas faltantes según los wireframes de cada rol.

---

## Wireframe Map

Cada wireframe HTML se portea a su equivalente React con mock data:

| Wireframe | Ruta React | Rol |
|-----------|-----------|-----|
| `login-institucional.html` | `/login` | ✅ Ya portado |
| `login-evaluadores-externos.html` | `/login/externo` | ✅ Ya portado |
| `gestion-usuarios.html` | `/coordinador/usuarios` | ✅ Ya portado |
| `audit-log` | `/coordinador/audit-log` | ✅ Ya portado |
| `panel-estudiante.html` | `/dashboard/estudiante` | Estudiante (upgrade de placeholder) |
| `panel-coordinador.html` | `/dashboard/coordinador` | Coordinador (upgrade de placeholder) |
| `panel-director.html` | `/dashboard/director` | Director (upgrade de placeholder) |
| `evaluador-panel.html` | `/dashboard/evaluador-externo` | Evaluador (upgrade de placeholder) |
| `anuncios-publica.html` | `/anuncios` | Todos |
| `anuncio-detalle.html` | `/anuncios/:id` | Todos |
| `recursos.html` | `/recursos` | Todos |
| `recurso-detalle.html` | `/recursos/:id` | Todos |
| `index.html` (landing) | `/` (público) | Todos (pre-login) |
| `bitacoras-estudiante.html` | `/bitacora` | Estudiante |
| `nueva-bitacora-estudiante.html` | `/bitacora/nueva` | Estudiante |
| `detalle-entrega-estudiante.html` | `/mi-proyecto/entregas/:id` | Estudiante |
| `supervision-proyecto-director.html` | `/supervision/:proyectoId` | Director |
| `seleccion-proyectos-bitacoras.html` | `/bitacoras/proyectos` | Director |
| `bitacoras-director.html` | `/bitacoras` | Director |
| `detalle-firma-bitacora.html` | `/bitacoras/:id/firmar` | Director |
| `revision-entrega-director.html` | `/entregas/:id/revisar` | Director |
| `gestion-proyectos.html` | `/proyectos` | Coordinador |
| `anuncios.html` | `/anuncios/admin` | Coordinador |
| `asignacion-evaluadores.html` | `/evaluadores` | Coordinador |
| `coordinador-entregas.html` | `/coordinador/entregas` | Coordinador |
| `coordinador-bitacoras.html` | `/coordinador/bitacoras` | Coordinador |
| `gestion-alertas.html` | `/alertas` | Coordinador |
| `reportes-consolidados.html` | `/reportes` | Coordinador |
| `recursos-admin.html` | `/recursos/admin` | Coordinador |
| `evaluar-proyecto.html` | `/evaluaciones/:id` | Evaluador/Director |
| `evaluador-calificar.html` | `/evaluaciones/:id/calificar` | Evaluador |
| `analisis-automatico-entregas.html` | `/analisis-entregas` | Estudiante (mock) |
| `asistente-orientacion.html` | `/asistente` | Estudiante (mock) |

---

## Affected Areas

| Área | Impacto |
|------|---------|
| `resources/js/pages/` | ~24 nuevas páginas |
| `resources/js/components/ui/` | Nuevos componentes compartidos |
| `resources/js/components/layout/Sidebar.tsx` | Posibles ajustes menores de navegación |
| `resources/js/app.tsx` | Nuevas rutas (con lazy loading) |
| `resources/js/hooks/` | Posibles hooks compartidos |

---

## Risks

| Riesgo | Probabilidad | Mitigación |
|--------|-------------|------------|
| Exceder budget 400 líneas/PR | Alta | 6 batches, PRs chained por batch |
| Inconsistencia visual con páginas existentes | Baja | Usar GestionUsuarios como canon visual |
| Iconos Material Symbols en wireframes vs lucide-react | Baja | Mapear a equivalentes lucide |
| TOTP library | Baja | Usar `otpauth` o `speakeasy`, test temprano Batch 2 |

---

## Rollback Plan

- Feature-branch chain: `feature/frontend-wireframes-port/batch-N` → `feature/frontend-wireframes-port`
- `git revert` del merge commit de cualquier batch problemático
- `main` siempre desplegable

---

## Dependencias

- Wireframes HTML + DESIGN.md en `Open Design/data/projects/proyecto-de-grado-0554/`
- Layout existente: AppShell, Sidebar, Header
- shadcn/ui + Tailwind v4 + lucide-react ya configurados
- Skills: react-patterns, shadcn-ui, tailwind-patterns, chained-pr, work-unit-commits

---

## Success Criteria

- [ ] ~24 wireframes porteados como páginas React navegables
- [ ] Mock data en todas las páginas (sin llamadas a backend)
- [ ] Coherencia visual con GestionUsuarios y páginas de auth
- [ ] Navbars diferentes por rol según wireframes
- [ ] Landing page institucional en `/`
- [ ] TOTP signing funcionando con mock
- [ ] IA screens renderizadas con mock data
- [ ] AppShell/Sidebar/Header sin regresiones
- [ ] Cada batch ≤ 400 líneas por PR

---

## Estimated Effort

~6 batches, ~2-3 días por batch = ~2-3 semanas para Sprint 4.
