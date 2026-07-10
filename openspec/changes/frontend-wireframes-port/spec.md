# Spec: Frontend Wireframes Port — Todas las Pantallas

## Purpose

Portear ~29 wireframes de Open Design a React 18 + TypeScript + Tailwind v4 + shadcn/ui con mock data. Mantener coherencia visual con las páginas ya portadas (GestionUsuarios, auth). Sin integración backend — Sprint 4 puro frontend.

**Canon visual:** Seguir los patrones exactos de `GestionUsuarios.tsx` (eyebrow pill, h2, cards, botones, tablas, badges, inputs, lucide-react iconos).

---

## Batch 1 — Dashboards (Upgrade) + Páginas Compartidas

### Requirement: EstudianteDashboard — Wireframe Upgrade

Replace placeholder cards with: project hero card, 4-node phase stepper, upload zone + accordion deliveries grid, version-history table. All mock data.

| Scope | Detail |
|-------|--------|
| Layout | Eyebrow pill "Proyecto Activo" + h2 title + meta row (code, director). Phase stepper: 4 circles (done/current/future) with connecting lines. Upload card (dashed dropzone) + deliveries card (accordion list with expand/collapse). Version history table below. |
| Components | StatCard, StatusBadge, shadcn Table, lucide icons (GraduationCap, CloudUpload, Lock, CheckCircle, Pending, FileText). |
| States | Loading: Loader2 center-spin. Empty: "Sin proyecto asignado" with dashed border. Error: red banner. Data: 3 mock deliveries (1 approved with file versions, 1 pending with deadline, 2 locked). Version table: 2 rows with status badges. |
| Nav | Route `/dashboard/estudiante`, role-gated via DashboardRouter. |
| Mock | Project: PG-2026-014 "Sistema predictivo de deserción…", Director: Carlos Andrés Gómez. Deliveries: 4 entries. Stepper: 4 phases (Anteproyecto done, Presentación current, Desarrollo pending, Final pending). |

### Requirement: CoordinadorDashboard — Wireframe Upgrade

Replace placeholder with: 4 KPI stat cards, projects table, alerts section.

| Scope | Detail |
|-------|--------|
| Layout | KPI row: 4 StatCards (Proyectos Activos, En Riesgo, Alertas sin revisar, Tasa de cumplimiento). Section: "Proyectos de Grado" with table (code, title, students, director, phase, status, alerts count, actions). Section: "Alertas Activas" — 3 alert cards with icon+title+body+action. |
| Components | StatCard, StatusBadge, shadcn Table, Card. Icons: ClipboardList, TrendingDown, Bell, TrendingUp, Eye, Pencil. |
| States | Loading: skeleton KPIs + Loader2. Empty: "No hay proyectos" centered. Data: 8 mock projects, 4 KPIs, 3 alert cards. |
| Nav | Route `/dashboard/coordinador`, role-gated. |
| Mock | 8 projects (PG-2401…PG-2408), KPIs: 24/12/8/87%. Alerts: vencida, sin director, bajo rendimiento. |

### Requirement: DirectorDashboard — Wireframe Upgrade

Replace placeholder with: project bezel header, 4 KPIs, 3 project progress cards, deliveries table.

| Scope | Detail |
|-------|--------|
| Layout | Hero bezel: "Proyectos Asignados" with pulse-dot badge. KPI row: 4 StatCards. Grid: 3 project cards (code, title, students, progress bar with %). Table: "Últimas entregas" (student, project, type, date, status, action). |
| Components | StatCard, StatusBadge, shadcn Progress, shadcn Table, Card. Icons: Workspaces, RateReview, Warning, CheckCircle, Groups. |
| States | Loading, empty, data. |
| Nav | Route `/dashboard/director`, role-gated. |
| Mock | 8 proyectos supervisando, 14 entregas por revisar, 2 alertas, 12 aprobadas. 3 cards: Microgrid (85%), Deserción (92%), IoT (45%). Table: 3 rows. |

### Requirement: EvaluadorDashboard — Wireframe Upgrade

Replace placeholder with: 3 KPIs, 3 project evaluation cards (pending/evaluated with rating).

| Scope | Detail |
|-------|--------|
| Layout | KPI row: 3 StatCards. Grid: 3 project cards — each has date badge, title, meta (students, director), footer (status badge + action). Evaluated cards show star rating. |
| Components | StatCard, StatusBadge, Card. Icons: Assignment, PendingActions, CheckCircle, Star, Calendar, Users, School. |
| States | Loading, empty, data. |
| Nav | Route `/dashboard/evaluador-externo`, role-gated. |
| Mock | 6 asignados, 4 pendientes, 2 evaluados. Cards: 2 pending + 1 evaluated (4.2 stars). |

### Requirement: AnunciosPublica — Announcement List

Shared page listing official announcements. Accessible from sidebar for all roles.

| Scope | Detail |
|-------|--------|
| Layout | Page header (eyebrow "Comunicados oficiales" + h2 + subtitle). Card list: each has title row (h3 + badge), date, excerpt, "Ver más" outline button. |
| Components | Card, StatusBadge, Button. Icons: Campaign, ArrowForward. |
| States | Loading, empty ("No hay anuncios"), data (2 mock). |
| Nav | Route `/anuncios`, sidebar entry "Anuncios" for all roles. |
| Mock | 2 announcements: "Cronograma de sustentaciones" (badge: Importante, date: 28/06/2026) and "Recordatorio: Entrega de informes finales" (badge: Recordatorio, date: 25/06/2026). |

### Requirement: AnuncioDetalle — Announcement Detail

Detailed view of a single announcement.

| Scope | Detail |
|-------|--------|
| Layout | Back link "← Volver a Anuncios". Card: badge + h1 title + meta row (date, author) + sectioned body + attachments section. |
| Components | Card, StatusBadge, Button. Icons: ArrowBack, Calendar, User, AttachFile, Download, PictureAsPdf. |
| States | Loading, not-found ("Anuncio no encontrado"), data. |
| Nav | Route `/anuncios/:id`. |

### Requirement: Recursos — Resource Library

Grid of resource cards with search, filter, category tabs.

| Scope | Detail |
|-------|--------|
| Layout | Page header + filters (search + category dropdown) + tabs (Todos/Reglamento/Guías/Plantillas/Tutoriales) + 3-col grid of resource cards. |
| Components | Card, StatusBadge, shadcn Input, Select, Tabs. Icons: Gavel, BookOpen, FileText, PlaySquare, Search, Download. |
| States | Loading, empty, filtered-empty ("Sin resultados"), data (4 mock). |
| Nav | Route `/recursos`. |

### Requirement: RecursoDetalle — Resource Detail

Two-column layout: description + sticky metadata sidebar.

| Scope | Detail |
|-------|--------|
| Layout | Breadcrumb + hero card + description card + sticky sidebar (type, size, author, accesses). |
| Components | Card, StatusBadge, Button. Icons: BookOpen, Download, Share, Eye. |
| States | Loading, not-found, data. |
| Nav | Route `/recursos/:id`. |

---

## Batch 2 — Landing + Estudiante

### Requirement: LandingPage — Public Institutional Landing

Route `/` (public, pre-login). Eyebrow pill + hero h1 + subtitle + 5 role-cards (Estudiante, Director, Coordinador, Evaluador, Admin) linking to login. Footer with institutional info. Full-width layout, NO AppShell.

### Requirement: BitacorasEstudiante — Student Binnacle List

Route `/bitacora`, role Estudiante. PageHeader + "Nueva Bitácora" button → table (Fecha, Tema, Descripción, Duración, Estado firma, Acciones). Signed: Ver only. Pending: Ver + Edit. Pagination.

| State | Behavior |
|-------|----------|
| Data | 8 mock binnacles, 3 signed + 3 pending + 2 unsigned |
| Loading | Loader2 center-spin py-16 |
| Empty | "No has registrado bitácoras" |
| Error | Red banner |

### Requirement: NuevaBitacora — Create Binnacle Form

Route `/bitacora/nueva`, role Estudiante. Two-column form: fecha (date), tema (text), descripción (textarea 8 rows). Info alert. "Enviar y generar clave" button → mock TOTP key.

### Requirement: DetalleEntregaEstudiante — Delivery Detail + Review

Route `/mi-proyecto/entregas/:id`, role Estudiante. Split-screen: left = document viewer (delivery meta + PDF mock), right = review panel (APROBADO badge, observaciones, 5-item criteria checklist with scores, "Subir Nueva Versión" button).

---

## Batch 3 — Director Flow

### Requirement: SupervisionProyecto — Project Supervision

Route `/supervision/:proyectoId`, role Director. Bezel header (project code, status, title) + 4-node stepper + 3 info cards (Equipo, Avance 65%, Fechas clave) + expandable delivery list (2 completed + 2 locked).

### Requirement: SeleccionProyectosBitacoras — Project Grid

Route `/bitacoras/proyectos`, role Director. Search + status filter + 3-col project card grid (code, title, students, progress bar, pending count, "Gestionar Bitácoras" button).

### Requirement: BitacorasDirector — Director Binnacle List

Route `/bitacoras`, role Director. 3 stat cards (Total, Firmadas, Pendientes) + project filter pill + table (Proyecto, Fecha, Tema, Estado, Acciones). "Revisar" for pending/unsigned.

### Requirement: DetalleFirmaBitacora — TOTP Digital Signature

Route `/bitacoras/:id/firmar`, role Director. Session details (3 stat cards) + topic card + sticky signature card: 6-digit TOTP grid input + "Firmar Bitácora" button + disclaimer.

### Requirement: RevisionEntregaDirector — Split-Screen Review

Route `/entregas/:id/revisar`, role Director. Left: document viewer. Right: delivery meta + 3 decision buttons (Aprobar/Correcciones/Rechazar) + comments textarea + private notes + grade input (0-5) + actions.

---

## Batch 4 — Coordinador Gestión

### Requirement: GestionProyectos — CRUD Projects Hub

Route `/proyectos`, role Coordinador. Semester bar (active pill + dates + toggle) + projects table (code, title, students, director, phase, status, actions) + "Crear Nuevo Grupo" form (title, director, phase, 1-3 student emails) + "Cupos de Directores" table + "Participantes del Semestre".

### Requirement: AnunciosAdmin — Announcement CRUD

Route `/anuncios/admin`, role Coordinador. PageHeader + 3 announcement cards (title + badge + status + date + excerpt + Ver/Editar/Eliminar) + "Nuevo Anuncio" form (title, category, content, Publicar/Guardar).

### Requirement: AsignacionEvaluadores — Evaluator Assignment

Route `/evaluadores`, role Coordinador. Register form (name, email, project, send invitation) + "Evaluadores Asignados" table (project, evaluator 1/2/3 with status) + "Agenda Sustentaciones" list.

### Requirement: CoordinadorEntregas — Cross-Project Delivery Viewer

Route `/coordinador/entregas`, role Coordinador. Project selector + 4-node stepper + 3 info cards + expandable delivery list + binnacles mini-table.

---

## Batch 5 — Coordinador Resto + Evaluador

### Requirement: CoordinadorBitacoras — Cross-Project Binnacle Audit

Route `/coordinador/bitacoras`, role Coordinador. Filter bar (project, date range, signature status) + table (Fecha, Proyecto, Estudiantes, Tema, Duración, Estado firma, Ver). Pagination.

### Requirement: GestionAlertas — Alert Management

Route `/alertas`, role Coordinador. 3 KPI cards (Activas/Críticas/Resueltas) + filter tabs + expandable alert cards with severity, meta, actions (Revisar/Notificar/Descartar).

### Requirement: ReportesConsolidados — Grade Reports

Route `/reportes`, role Coordinador. Filter card (semester, type, generar) + grades table (code, title, students, director, scores, status) + distribution bar chart + average promo block. Export buttons (mock).

### Requirement: RecursosAdmin — Resource CRUD

Route `/recursos/admin`, role Coordinador. Upload form (title, description, type, target role, drag-drop zone) + search + 6 resource cards with edit/delete.

### Requirement: EvaluarProyecto — Rubric Evaluation

Route `/evaluaciones/:id`, roles Director+Evaluador. Split-screen: document viewer + evaluation form (nota final 0-5, 4 rubric criteria 1-5 with weights, observations). Weights: 30/25/25/20.

### Requirement: EvaluadorCalificar — External Evaluator Grading

Route `/evaluaciones/:id/calificar`, role Evaluador. Split-screen: document viewer (5-page mock) + grade pane (nota /5.0, 3 criteria with badges + notes, observations, Guardar/Enviar).

---

## Batch 6 — IA (Mock)

### Requirement: AnalisisAutomaticoEntregas — AI Analysis (Mock)

Route `/analisis-entregas`, role Estudiante. Split-screen: document viewer + analysis panel: coherence score 82/100 circle, checklist (2/2 passed), "Requiere atención" (1/3 items), "Confirmar Recepción". Banner: "No sustituye revisión del director."

### Requirement: AsistenteOrientacion — AI Chatbot (Mock)

Route `/asistente`, role Estudiante. Chat layout: header + thread (user message → assistant reply with 3 director suggestion cards: name + expertise badge) + footer. Pre-rendered mock conversation, no LLM.

---

## Acceptance Criteria Summary

| Batch | Pages | Key Gates |
|-------|-------|-----------|
| 1 | 8 | 4 dashboards render KPIs/cards/tables; anuncios list+detail; recursos grid+detail |
| 2 | 4 | Landing renders 5 role cards; binnacle table shows 3 signature states; TOTP button in form; split-screen review |
| 3 | 5 | Stepper with 4 phases; delivery expand/collapse; TOTP 6-digit grid; 3 decision buttons |
| 4 | 4 | Semester form toggle; projects table 8 rows; evaluator 3-column table; delivery version history |
| 5 | 6 | Filter bar 4 controls; alert cards expand; grade table 6 rows; rubric score toggle |
| 6 | 2 | Coherence score circle 82/100; chatbot pre-rendered with 3 director cards |

All pages: loading (Loader2) · empty (centered message) · error (red banner) · data (mock entries). GestionUsuarios visual canon. lucide-react icons. Sin llamadas backend.
