# Tasks: Frontend Wireframes Port

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~5400 (29 wireframes, 7 shared components) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | 14 PRs across 6 batches + evaluador |
| Delivery strategy | auto-chain |
| Chain strategy | feature-branch-chain |
| Tracker branch | `feature/frontend-wireframes-port` |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Shared components + EstudianteDashboard | PR 1 (`batch-1`) | `npm run build` compiles, verify StatusBadge + 5 components render | `npm run dev`, navigate to `/dashboard/estudiante` | `git revert` merge of batch-1 |
| 2 | 3 upgraded dashboards | PR 2 (`batch-1b`) | `npm run build`, navigate Coord/Direct/Evaluador dashboards | `npm run dev`, login as each role | `git revert` merge of batch-1b |
| 3 | Shared pages (Anuncios, Recursos) + routing | PR 3 (`batch-1c`) | `npm run build`, navigate `/anuncios`, `/recursos` | `npm run dev`, visit all new routes | `git revert` merge of batch-1c |
| 4 | Landing + BitacorasEstudiante | PR 4 (`batch-2`) | `npm run build`, navigate `/`, `/bitacora` | `npm run dev`, verify public landing + binnacle list | `git revert` merge of batch-2 |
| 5 | NuevaBitacora + DetalleEntrega | PR 5 (`batch-2b`) | `npm run build`, navigate `/bitacora/nueva`, `/mi-proyecto/entregas/:id` | `npm run dev`, TOTP mock + split-screen | `git revert` merge of batch-2b |
| 6 | SupervisionProyecto + SeleccionProyectos | PR 6 (`batch-3`) | `npm run build`, navigate `/supervision/1`, `/bitacoras/proyectos` | `npm run dev`, login as Director | `git revert` merge of batch-3 |
| 7 | BitacorasDirector + DetalleFirma | PR 7 (`batch-3b`) | `npm run build`, navigate `/bitacoras`, `/bitacoras/1/firmar` | `npm run dev`, TOTP signature flow | `git revert` merge of batch-3b |
| 8 | RevisionEntregaDirector | PR 8 (`batch-3c`) | `npm run build`, navigate `/entregas/1/revisar` | `npm run dev`, 3 decision buttons | `git revert` merge of batch-3c |
| 9 | GestionProyectos | PR 9 (`batch-4`) | `npm run build`, navigate `/proyectos` | `npm run dev`, login as Coordinador | `git revert` merge of batch-4 |
| 10 | AnunciosAdmin + AsignacionEvaluadores | PR 10 (`batch-4b`) | `npm run build`, navigate `/anuncios/admin`, `/evaluadores` | `npm run dev`, CRUD forms + table | `git revert` merge of batch-4b |
| 11 | CoordinadorEntregas + Bitacoras + Alertas | PR 11 (`batch-5`) | `npm run build`, navigate `/coordinador/entregas`, `/coordinador/bitacoras`, `/alertas` | `npm run dev`, 3 pages in one PR | `git revert` merge of batch-5 |
| 12 | Reportes + RecursosAdmin | PR 12 (`batch-5b`) | `npm run build`, navigate `/reportes`, `/recursos/admin` | `npm run dev`, chart + upload form | `git revert` merge of batch-5b |
| 13 | EvaluarProyecto + EvaluadorCalificar | PR 13 (`batch-5c`) | `npm run build`, navigate `/evaluaciones/1`, `/evaluaciones/1/calificar` | `npm run dev`, rubric + grade pane | `git revert` merge of batch-5c |
| 14 | IA mock pages | PR 14 (`batch-6`) | `npm run build`, navigate `/analisis-entregas`, `/asistente` | `npm run dev`, score circle + chatbot mock | `git revert` merge of batch-6 |

---

## Phase 1: Foundation — Shared Components

- [x] 1.1 Create `components/ui/StatusBadge.tsx` — 7 variants (success/warning/error/info/en-curso/inactivo/riesgo) with variant-specific bg/text colors per DESIGN.md. Responsive: text scales with container.
- [x] 1.2 Create `components/ui/StatCard.tsx` — title, value, LucideIcon, optional trend, variant. Responsive: grid cols adjust via parent.
- [x] 1.3 Create `components/ui/PageHeader.tsx` — eyebrow pill + h2 + subtitle + optional actions row. Responsive: actions stack below on ≤640px.
- [x] 1.4 Create `components/ui/DataTable.tsx` — columns config, T[] data, loading (Loader2), empty (EmptyState), pagination. Responsive: horizontal scroll wrapper on ≤767px.
- [x] 1.5 Create `components/ui/EmptyState.tsx` — icon + title + description + optional action button. Centered layout.
- [x] 1.6 Create `components/ui/ConfirmDialog.tsx` — overlay + card + title/message + confirm/cancel buttons, danger variant. Responsive: full-width on mobile.
- [x] 1.7 Create `components/ui/TOTPInput.tsx` — 6 individual `<input>` with autofocus cascade, paste handler, mock validation. Responsive: gap shrinks on ≤640px.

## Phase 2: Dashboards (Upgrade) + Shared Pages — PRs 1-3

- [x] 2.1 **T-002: EstudianteDashboard** — Replace placeholder in `pages/dashboard/EstudianteDashboard.tsx`: project hero, stepper (4 phases), upload zone, accordion deliveries, version table. Mock: PG-2026-014, 4 phases. Responsive: stepper → vertical stack ≤640px; accordion full-width.
- [x] 2.2 **T-003: CoordinadorDashboard** — Replace placeholder in `pages/dashboard/CoordinadorDashboard.tsx`: 4 StatCards KPIs, projects table (8 rows), 3 alert cards. Responsive: KPI grid 2×2 on ≤640px, table scroll.
- [x] 2.3 **T-004: DirectorDashboard** — Replace placeholder in `pages/dashboard/DirectorDashboard.tsx`: bezel header, 4 KPIs, 3 progress cards with shadcn Progress, deliveries table. Responsive: progress cards stack single-col on ≤767px.
- [x] 2.4 **T-005: EvaluadorDashboard** — Replace placeholder in `pages/dashboard/EvaluadorDashboard.tsx`: 3 KPIs, 3 evaluation cards with star rating. Responsive: cards stack on mobile.
- [x] 2.5 **T-006: AnunciosPublica** — Create `pages/shared/AnunciosPublica.tsx`: PageHeader + card list (2 mock). Responsive: cards full-width on mobile.
- [x] 2.6 **T-007: AnuncioDetalle** — Create `pages/shared/AnuncioDetalle.tsx`: back link + card: badge, h1, meta, body, attachments. Responsive: attachments grid → stack ≤640px.
- [x] 2.7 **T-008: Recursos** — Create `pages/shared/Recursos.tsx`: search + tabs + 3-col resource card grid (4 mock). Responsive: 1-col on mobile, 2-col on tablet.
- [x] 2.8 **T-009: RecursoDetalle** — Create `pages/shared/RecursoDetalle.tsx`: breadcrumb + hero + description card + sticky sidebar. Responsive: stack sidebar below on ≤767px.
- [x] 2.9 **T-010: Routing + Shell** — Update `app.tsx` (lazy imports, Suspense, Landing at `/` outside ProtectedRoute, new routes per batch); `AppShell.tsx` (~15 ROUTE_TITLES additions); `Sidebar.tsx` (navConfig: Coordinador add `/anuncios/admin`, `/recursos/admin`, `/coordinador/entregas`, `/coordinador/bitacoras`; Director add `/supervision/:proyectoId`, `/bitacoras/proyectos`; EvaluadorExterno add `/anuncios`, `/recursos`).

## Phase 3: Landing + Estudiante — PRs 4-5

- [x] 3.1 **T-011: LandingPage** — Create `pages/landing/LandingPage.tsx`: hero h1 + 5 role cards (Estudiante, Director, Coordinador, Evaluador, Admin) linking to `/login`. Full-width, NO AppShell. Footer. Responsive: role cards 1-col mobile, 3-col tablet, 5-col desktop.
- [x] 3.2 **T-012: BitacorasEstudiante** — Create `pages/estudiante/BitacorasEstudiante.tsx`: PageHeader + "Nueva Bitácora" button + DataTable (8 mock rows, 3 signature states). Responsive: table horizontal scroll on mobile.
- [ ] 3.3 **T-013: NuevaBitacora** — Create `pages/estudiante/NuevaBitacora.tsx`: two-column form (date, topic, description + info alert + "Enviar y generar clave" → mock TOTP). Responsive: stack form cols on ≤767px.
- [ ] 3.4 **T-014: DetalleEntregaEstudiante** — Create `pages/estudiante/DetalleEntregaEstudiante.tsx`: split-screen left=document viewer, right=review panel (badge, observations, score checklist, upload button). Responsive: stack vertical on ≤767px.

## Phase 4: Director Flow — PRs 6-8

- [ ] 4.1 **T-015: SupervisionProyectoDirector** — Create `pages/director/SupervisionProyectoDirector.tsx`: bezel header + 4-node stepper + 3 info cards + expandable delivery list. Responsive: stepper → vertical on ≤640px.
- [ ] 4.2 **T-016: SeleccionProyectosBitacoras** — Create `pages/director/SeleccionProyectosBitacoras.tsx`: search input + status filter + 3-col project card grid. Responsive: 1-col mobile, 2-col tablet.
- [ ] 4.3 **T-017: BitacorasDirector** — Create `pages/director/BitacorasDirector.tsx`: 3 StatCards (Total/Firmadas/Pendientes) + filter pills + DataTable. Responsive: stat cards 1-col mobile, 3-col desktop.
- [ ] 4.4 **T-018: DetalleFirmaBitacora** — Create `pages/director/DetalleFirmaBitacora.tsx`: 3 stat cards + topic card + sticky TOTPInput (6 digits) + "Firmar" button. Responsive: TOTP full-width on mobile.
- [ ] 4.5 **T-019: RevisionEntregaDirector** — Create `pages/director/RevisionEntregaDirector.tsx`: split-screen left=document, right=delivery meta + 3 decision buttons (Aprobar/Correcciones/Rechazar) + comments + grade input. Responsive: stack on ≤767px.

## Phase 5: Coordinador Gestión — PRs 9-10

- [ ] 5.1 **T-020: GestionProyectos** — Create `pages/coordinador/GestionProyectos.tsx`: semester bar + DataTable (8 projects) + "Crear Grupo" form + cupos table. Responsive: form expands full-width on mobile.
- [ ] 5.2 **T-021: AnunciosAdmin** — Create `pages/coordinador/AnunciosAdmin.tsx`: 3 announcement cards with Ver/Editar/Eliminar + "Nuevo Anuncio" form. ConfirmDialog on delete. Responsive: cards stack mobile.
- [ ] 5.3 **T-022: AsignacionEvaluadores** — Create `pages/coordinador/AsignacionEvaluadores.tsx`: register form + "Evaluadores Asignados" table + "Agenda Sustentaciones" list. Responsive: table scroll mobile.

## Phase 6: Coordinador Continuación — PRs 11-12

- [ ] 6.1 **T-023: CoordinadorEntregas** — Create `pages/coordinador/CoordinadorEntregas.tsx`: project selector + 4-node stepper + 3 info cards + expandable deliveries + bitácoras mini-table. Responsive: stepper vertical mobile.
- [ ] 6.2 **T-024: CoordinadorBitacoras** — Create `pages/coordinador/CoordinadorBitacoras.tsx`: filter bar (4 controls) + DataTable with pagination. Responsive: filters collapse into accordion on mobile.
- [ ] 6.3 **T-025: GestionAlertas** — Create `pages/coordinador/GestionAlertas.tsx`: 3 StatCards + filter tabs + expandable alert cards. Responsive: stat cards 1-col mobile.
- [ ] 6.4 **T-026: ReportesConsolidados** — Create `pages/coordinador/ReportesConsolidados.tsx`: filter card + DataTable (grades) + bar chart mock + export buttons. Responsive: chart scales down on mobile.
- [ ] 6.5 **T-027: RecursosAdmin** — Create `pages/coordinador/RecursosAdmin.tsx`: upload form (drag-drop mock) + search + 6 resource cards with edit/delete + ConfirmDialog. Responsive: form stack mobile, grid 1-col.

## Phase 7: Evaluador — PR 13

- [ ] 7.1 **T-028: EvaluarProyecto** — Create `pages/evaluador/EvaluarProyecto.tsx`: split-screen left=document, right=rubric (4 criteria 1-5 with weights 30/25/25/20, nota final 0-5 + observations). Responsive: stack on ≤767px.
- [ ] 7.2 **T-029: EvaluadorCalificar** — Create `pages/evaluador/EvaluadorCalificar.tsx`: split-screen left=document (5-page mock), right=grade pane (nota /5.0, 3 criteria + badges + Guardar/Enviar). Responsive: stack on ≤767px.

## Phase 8: IA Mock — PR 14

- [ ] 8.1 **T-030: AnalisisAutomaticoEntregas** — Create `pages/estudiante/AnalisisAutomaticoEntregas.tsx`: split-screen document + analysis panel (coherence 82/100 circle, checklist, "Confimar Recepción" + disclaimer). Responsive: stack on ≤767px.
- [ ] 8.2 **T-031: AsistenteOrientacion** — Create `pages/estudiante/AsistenteOrientacion.tsx`: chat layout header + pre-rendered thread (user + assistant with 3 director suggestion cards). Responsive: chat full-width all breakpoints.
