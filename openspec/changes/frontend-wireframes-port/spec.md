# Spec: Frontend Wireframes Port — Todas las Pantallas

## Purpose

Portear ~29 wireframes de Open Design a React 18 + TypeScript + Tailwind v4 + shadcn/ui con **mock data exclusivamente**. Mantener coherencia visual con las páginas ya portadas (GestionUsuarios, auth). **Alcance 100% frontend:** wireframes, UX, componentes, estados visuales simulados.

**Canon visual:** Seguir los patrones exactos de `GestionUsuarios.tsx` (eyebrow pill, h2, cards, botones, tablas, badges, inputs, lucide-react iconos).

---

## Scope Rules

1. **Datos:** fixtures en `resources/js/mocks/` cuando `FRONTEND_VALIDATION_MODE === true`; sin llamadas obligatorias al backend para visualizar pantallas.
2. **Flag:** `FRONTEND_VALIDATION_MODE` en `mocks/validationMode.ts` — temporal; `false` en producción integrada.
3. **Acciones:** guardar, firmar, enviar, eliminar → feedback visual local (`mockDelay`, stores mutables, toast/banner).
4. **Validación:** solo validación de formulario en cliente.
5. **Errores:** banners simulados con "Reintentar" opcional (query param `?demo=error` futuro).
6. **Integración futura:** ramas `apiFetch` en hooks/páginas permanecen; FVM las bypass — no eliminar.
7. **Navegación y UX:** cumplir Navigation System y UX Principles.

---

## Frontend Validation Mode (FVM)

### Requirement: Validación sin backend

| Regla | Detalle |
|-------|---------|
| FVM-01 | Con `FRONTEND_VALIDATION_MODE = true`, TODAS las pantallas del wireframe map MUST renderizar datos representativos sin servidor |
| FVM-02 | Fixtures MUST vivir en `resources/js/mocks/` — no hardcode disperso en componentes salvo IA inline ya existente |
| FVM-03 | IDs mock MUST ser consistentes cross-módulo (proyecto 1 ↔ entregas ↔ bitácoras ↔ estudiante) |
| FVM-04 | Escenarios MUST incluir: múltiples estados de proyecto, entregas on-time/late/overdue, bitácoras firmadas/pendientes/rechazadas, observaciones largas/cortas |
| FVM-05 | Login externo MUST funcionar en FVM con credenciales documentadas (`Pruebas123!` + emails del seeder) |
| FVM-06 | FVM es TEMPORAL — documentado en proposal/design/tasks; reemplazo por API en changes de integración |

### Requirement: Escenarios de demostración

| Escenario | Dónde visible |
|-----------|---------------|
| Proyecto en desarrollo con 2 estudiantes | EstudianteDashboard, Supervisión PG-2026-014 |
| Entrega con 2 versiones + observaciones | DetalleEntregaEstudiante, RevisionEntregaDirector |
| Entrega sin versiones (plazo futuro) | entregasMock id 2 |
| Entrega overdue | entregasMock `timelineStatus: overdue` |
| Bitácora rechazada con comentario | bitacorasMock meeting rechazado |
| Coordinador: 5 proyectos, 3 en riesgo | GestionProyectos, CoordinadorDashboard |
| Alertas derivadas | GestionAlertas |
| Audit log paginado | AuditLog |

---

## Navigation System

Especificación del sistema de navegación. Define **organización, jerarquía y comportamiento** — no estilos (colores, tamaños, iconos).

### Requirement: Estructura global del menú

El menú lateral por rol MUST seguir grupos en este orden (omitir grupos vacíos):

1. **Inicio** — dashboard del rol
2. **Operaciones** — flujo principal de trabajo
3. **Comunicación** — Anuncios
4. **Recursos** — Recursos
5. **Herramientas** — solo roles con IA/análisis
6. **Administración** — solo Coordinador; ítems de gestión al final

### Requirement: Consistencia cross-role

| Regla | Detalle |
|-------|---------|
| NS-01 | "Anuncios" y "Recursos" MUST usar el mismo label en todos los roles que los incluyan |
| NS-02 | Orden relativo: Anuncios antes que Recursos en todos los roles |
| NS-03 | Inicio MUST ser el primer ítem del menú |
| NS-04 | Administración MUST ir después de Recursos/Herramientas, separada estructuralmente del grupo anterior |
| NS-05 | Rutas de detalle (`/:id`, acciones puntuales) MUST NOT añadir ítems al sidebar |

### Requirement: Jerarquía primaria vs secundaria

| Nivel | Contenido | Comportamiento |
|-------|---------|----------------|
| Primaria | Inicio, Operaciones, Comunicación, Recursos | Siempre visibles; flujo diario |
| Secundaria | Herramientas, Administración | Acceso menos frecuente; grupo Administración claramente diferenciado al final |

### Requirement: Ítem activo

| Regla | Detalle |
|-------|---------|
| NS-06 | El usuario MUST identificar qué sección está activa en todo momento |
| NS-07 | En rutas hijas, el ítem padre MUST mostrarse activo |
| NS-08 | Supervisión (Director) MUST permanecer activo en `/supervision/*` y `/bitacoras/*` |
| NS-09 | Evaluaciones MUST permanecer activo en `/evaluaciones/:id` y `/evaluaciones/:id/calificar` |
| NS-10 | Solo un ítem primario activo a la vez |

### Requirement: Matriz de ítems por rol

Ver `design.md` → Navigation Architecture → Matriz de ítems por rol. Implementación MUST reflejar esa matriz.

Correcciones obligatorias respecto al estado actual:

- EvaluadorExterno Inicio → `/dashboard/evaluador-externo` (no `/`)
- Coordinador → añadir Bitácoras, Reportes, Auditoría
- Director → bitácoras accesibles vía Supervisión, no como ítems sueltos

### Requirement: Header y títulos

| Regla | Detalle |
|-------|---------|
| NS-11 | Header (shell) MUST mostrar título de sección vía `ROUTE_TITLES` |
| NS-12 | Toda ruta en sidebar MUST tener entrada en `ROUTE_TITLES` |
| NS-13 | PageHeader MUST aportar contexto adicional (no repetir verbatim el título del Header) |

### Requirement: Navegación contextual (vistas hijas)

| Regla | Detalle |
|-------|---------|
| NS-14 | Vistas de detalle/flujo MUST incluir retorno al listado padre |
| NS-15 | Retorno MUST usar `PageHeader.actions` con label "Volver a {sección}" |
| NS-16 | Destino del retorno MUST ser el listado padre lógico, no el dashboard salvo que sea el único padre |

### Requirement: Responsive

| Regla | Detalle |
|-------|---------|
| NS-17 | En móvil, menú MUST ocultarse tras seleccionar un ítem |
| NS-18 | Overlay MUST cerrar el menú al tocar fuera |
| NS-19 | Navegación MUST ser operable por teclado (NavLink focusable, orden lógico) |

---

## UX Principles

Principios reutilizables para **toda pantalla modificada** en solicitudes futuras (bitácoras, entregas, proyectos, observaciones, etc.).

### Requirement: Acciones

| ID | Regla | Criterio de aceptación |
|----|-------|------------------------|
| UX-01 | Acción principal visible | Una acción primaria destacada por vista; secundarias menos prominentes |
| UX-02 | Un título principal | No duplicar el mismo texto en Header h1 y PageHeader h2 |
| UX-09 | Verbos consistentes | Guardar, Enviar, Firmar, Eliminar, Cancelar, Volver — en español |

### Requirement: Información y layout

| ID | Regla | Criterio de aceptación |
|----|-------|------------------------|
| UX-03 | Flujo de lectura natural | Contexto arriba → contenido → acciones abajo o a la derecha |
| UX-04 | Agrupación lógica | Información relacionada en la misma card/sección |
| UX-05 | Reducir pasos | Acciones frecuentes accesibles desde listados; evitar clics extra |
| UX-10 | Consistencia de componentes | Reutilizar PageHeader, DataTable, EmptyState, ConfirmDialog, patrones GestionUsuarios |

### Requirement: Navegación entre vistas

| ID | Regla | Criterio de aceptación |
|----|-------|------------------------|
| UX-06 | Retorno contextual | Volver al padre lógico (NS-14…NS-16) |
| UX-05b | Menos cambio de contexto | En flujos relacionados (bitácora → firmar → volver), mantener hilo visual |

### Requirement: Estados

| ID | Regla | Criterio de aceptación |
|----|-------|------------------------|
| UX-07 | Estados completos | loading, empty, error, data en listados y detalles |
| UX-07a | Empty claro | Mensaje + acción sugerida cuando aplique (ej. "Crear primera bitácora") |
| UX-07b | Loading | Loader2 o skeleton coherente con el tipo de contenido |
| UX-07c | Error | Banner rojo + Reintentar (simulado) |

### Requirement: Feedback

| ID | Regla | Criterio de aceptación |
|----|-------|------------------------|
| UX-08 | Confirmación visual | Tras guardar/firmar/eliminar: toast o banner de éxito antes de navegar |
| UX-08a | Acciones destructivas | ConfirmDialog obligatorio antes de eliminar |
| UX-08b | Submitting | Botón deshabilitado + spinner inline durante acción simulada |

### Requirement: Formularios y tablas

| Regla | Criterio de aceptación |
|-------|------------------------|
| UX-F01 | Campos required marcados; errores inline en español |
| UX-F02 | Acciones de fila en tablas alineadas a la derecha; verbos claros (Ver, Editar, Revisar) |
| UX-F03 | Paginación visual consistente cuando hay >1 página de mock |

### Requirement: Aplicación por módulo (checklist futuro)

Al modificar un módulo, verificar:

- [ ] Cumple Navigation System (NS-*)
- [ ] Cumple UX Principles (UX-*)
- [ ] 4 estados visuales presentes
- [ ] Retorno contextual en detalles
- [ ] Responsive verificado ≤767px
- [ ] Accesibilidad: labels, focus, aria-live en loading

---

## Batch 1 — Dashboards (Upgrade) + Páginas Compartidas

### Requirement: EstudianteDashboard — Wireframe Upgrade

Replace placeholder cards with: project hero card, 4-node phase stepper, upload zone + accordion deliveries grid, version-history table. All mock data.

| Scope | Detail |
|-------|--------|
| Layout | Eyebrow pill "Proyecto Activo" + h2 title + meta row (code, director). Phase stepper: 4 circles (done/current/future) with connecting lines. Upload card (dashed dropzone) + deliveries card (accordion list with expand/collapse). Version history table below. |
| Components | StatCard, StatusBadge, shadcn Table, lucide icons (GraduationCap, CloudUpload, Lock, CheckCircle, Pending, FileText). |
| States | Loading: Loader2 center-spin. Empty: "Sin proyecto asignado" with dashed border. Error: red banner (simulado). Data: 3 mock deliveries (1 approved with file versions, 1 pending with deadline, 2 locked). Version table: 2 rows with status badges. |
| Nav | Route `/dashboard/estudiante`, role-gated via DashboardRouter. |
| Mock | Project: PG-2026-014 "Sistema predictivo de deserción…", Director: Carlos Andrés Gómez. Deliveries: 4 entries. Stepper: 4 phases (Anteproyecto done, Presentación current, Desarrollo pending, Final pending). |
| Interacción UI | Dropzone: highlight on drag (sin upload real). Accordion: expand/collapse local. |

### Requirement: CoordinadorDashboard — Wireframe Upgrade

Replace placeholder with: 4 KPI stat cards, projects table, alerts section.

| Scope | Detail |
|-------|--------|
| Layout | KPI row: 4 StatCards. Section: "Proyectos de Grado" table. Section: "Alertas Activas" — 3 alert cards. |
| Components | StatCard, StatusBadge, shadcn Table, Card. |
| States | Loading: skeleton KPIs + Loader2. Empty: "No hay proyectos". Data: 8 mock projects, 4 KPIs, 3 alert cards. |
| Nav | Route `/dashboard/coordinador`. |
| Mock | 8 projects (PG-2401…PG-2408), KPIs: 24/12/8/87%. Alerts: vencida, sin director, bajo rendimiento. |
| Interacción UI | Botones "Ver"/"Revisar" → `navigate()` a rutas mock con `:id` fijo. |

### Requirement: DirectorDashboard — Wireframe Upgrade

Replace placeholder with: project bezel header, 4 KPIs, 3 project progress cards, deliveries table.

| Scope | Detail |
|-------|--------|
| Layout | Hero bezel + KPI row + 3 progress cards + deliveries table. |
| Components | StatCard, StatusBadge, shadcn Progress, shadcn Table, Card. |
| States | Loading, empty, data (mock). |
| Nav | Route `/dashboard/director`. |
| Mock | 8 proyectos, 14 entregas por revisar, 2 alertas, 12 aprobadas. 3 cards con %. Table: 3 rows. |

### Requirement: EvaluadorDashboard — Wireframe Upgrade

Replace placeholder with: 3 KPIs, 3 project evaluation cards (pending/evaluated with rating).

| Scope | Detail |
|-------|--------|
| Layout | KPI row + 3 project cards with star rating on evaluated. |
| Components | StatCard, StatusBadge, Card. |
| States | Loading, empty, data. |
| Nav | Route `/dashboard/evaluador-externo`. |
| Mock | 6 asignados, 4 pendientes, 2 evaluados. Cards: 2 pending + 1 evaluated (4.2 stars). |

### Requirement: AnunciosPublica — Announcement List

Shared page listing official announcements.

| Scope | Detail |
|-------|--------|
| Layout | PageHeader + card list with badge, date, excerpt, "Ver más". |
| States | Loading, empty ("No hay anuncios"), data (2 mock). |
| Nav | Route `/anuncios`. |
| Mock | 2 announcements with categories Importante/Recordatorio. |
| Interacción UI | "Ver más" → `/anuncios/:id`. |

### Requirement: AnuncioDetalle — Announcement Detail

| Scope | Detail |
|-------|--------|
| Layout | Back link + card: badge + h1 + meta + body + attachments. |
| States | Loading, not-found ("Anuncio no encontrado"), data. |
| Nav | Route `/anuncios/:id`. |
| Mock | Lookup por `id` en array local; id inexistente → not-found. |

### Requirement: Recursos — Resource Library

| Scope | Detail |
|-------|--------|
| Layout | PageHeader + search + category tabs + 3-col grid. |
| States | Loading, empty, filtered-empty, data (4 mock). |
| Nav | Route `/recursos`. |
| Interacción UI | Filtros y tabs aplicados sobre array mock en memoria. |

### Requirement: RecursoDetalle — Resource Detail

| Scope | Detail |
|-------|--------|
| Layout | Breadcrumb + hero + description + sticky sidebar. |
| States | Loading, not-found, data. |
| Nav | Route `/recursos/:id`. |
| Interacción UI | Botón descargar → toast "Descarga simulada" o estado disabled. |

---

## Batch 2 — Landing + Estudiante

### Requirement: LandingPage — Public Institutional Landing

Route `/` (public, pre-login). Eyebrow pill + hero h1 + subtitle + 5 role-cards linking to login. Footer. Full-width, NO AppShell.

### Requirement: BitacorasEstudiante — Student Binnacle List

Route `/bitacora`, role Estudiante. PageHeader + "Nueva Bitácora" button + table (Fecha, Tema, Descripción, Duración, Estado firma, Acciones). Signed: Ver only. Pending: Ver + Edit. Pagination visual.

| State | Behavior |
|-------|----------|
| Data | 8 mock binnacles, 3 signed + 3 pending + 2 unsigned |
| Loading | Loader2 center-spin py-16 |
| Empty | "No has registrado bitácoras" |
| Error | Red banner simulado |

### Requirement: NuevaBitacora — Create Binnacle Form

Route `/bitacora/nueva`, role Estudiante. Two-column form: fecha, tema, descripción. Info alert. "Enviar y generar clave" → muestra clave TOTP mock en pantalla (sin persistencia).

| Validación UI | fecha/tema/descripción required en cliente |
| Interacción UI | Submit → spinner → panel con código mock de 6 dígitos |

### Requirement: DetalleEntregaEstudiante — Delivery Detail + Review

Route `/mi-proyecto/entregas/:id` (alias `/estudiante/entregas/:entregaId`), role Estudiante. Ver **Módulo Entregas — Observaciones por versión** (`ENT-STUDENT`).

| Interacción UI | "Subir Nueva Versión" → modal o banner simulado; sin upload real |

---

## Módulo Entregas — Observaciones por versión

### Alcance

Rediseñar consulta y revisión de entregas para que **cada versión** tenga su **propia observación del director**, independiente de otras versiones. Solo frontend mock. Aplica a Estudiante (consulta), Director (revisión) y Coordinador (solo lectura).

**Regla central:** las observaciones de la versión N **nunca** se muestran al seleccionar la versión M (N ≠ M).

### Requirement: ENT-TYPES — Modelo de datos (mock)

Cada entrega mock MUST incluir un array `versiones` ordenable cronológicamente. Cada versión MUST tener:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | number | Identificador único de la versión |
| `versionNumber` | number | Número secuencial (1, 2, 3…) |
| `fileName` | string | Nombre del archivo entregado |
| `uploadedAt` | string (ISO) | Fecha y hora de la entrega |
| `observation` | objeto | Observación **exclusiva** de esta versión |

Objeto `observation`:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `text` | string \| null | Texto de la observación del director |
| `reviewedAt` | string \| null | Fecha/hora en que se registró la observación |
| `reviewStatus` | enum | `sin_revisar` \| `aprobada` \| `necesita_ajustes` |

Fixtures en `mocks/entregasMock.ts`. Sin `apiFetch`.

### Requirement: ENT-HISTORY — Historial de versiones

Componente compartido `DeliveryVersionHistory` MUST mostrar, por cada versión (orden cronológico descendente — más reciente arriba):

| Campo visible | Detalle |
|---------------|---------|
| Número de versión | "Versión N" o pill `vN` |
| Fecha/hora entrega | Formato locale `es-CO` |
| Estado de revisión | StatusBadge según `reviewStatus` |
| Indicador observación | Icono/dot: revisada vs pendiente |
| Extracto observación | 1 línea truncada si existe; "Sin observaciones" si no |

Al hacer click en una fila/pill MUST seleccionar esa versión como activa en toda la vista.

### Requirement: ENT-SELECTOR — Selector de versión

Componente `DeliveryVersionSelector` (pills o tabs):

| Regla | Detalle |
|-------|---------|
| ENT-S01 | Una versión activa a la vez |
| ENT-S02 | Pills MUST indicar si la versión tiene observación (dot verde/ámbar) |
| ENT-S03 | Versión más reciente seleccionada por defecto al cargar |
| ENT-S04 | Cambiar versión MUST actualizar documento + panel observación + formulario director (si aplica) |

### Requirement: ENT-OBS-PANEL — Panel de observación (lectura)

Componente `VersionObservationPanel`:

| Regla | Detalle |
|-------|---------|
| ENT-O01 | MUST mostrar **solo** la observación de la versión activa |
| ENT-O02 | Campos: texto observación, fecha observación (`reviewedAt`), estado revisión |
| ENT-O03 | Sin observación → mensaje "Sin observaciones del director" (italic, muted) |
| ENT-O04 | MUST NOT mostrar observaciones agregadas ni de otras versiones |

### Requirement: ENT-DIRECTOR — Flujo de revisión (Director)

Route `/entregas/:id/revisar`, role Director.

| Paso | Comportamiento UI (mock) |
|------|--------------------------|
| 1 | Cargar entrega desde `entregasMock.ts` por `:id` |
| 2 | Mostrar historial + selector; versión más reciente activa |
| 3 | Panel documento mock de la versión activa |
| 4 | `DirectorVersionReviewPanel`: textarea + decisión (Aprobada / Necesita ajustes) **scoped a versión activa** |
| 5 | Al cambiar versión: cargar en el formulario la observación existente de **esa** versión (o vacío si pendiente) |
| 6 | Guardar → actualizar `observation` de la versión activa en estado local; banner éxito; `reviewedAt` = now mock |
| 7 | Versiones ya revisadas vs pendientes identificables en selector e historial |

**Prohibido:** un único textarea global que mezcle observaciones entre versiones.

### Requirement: ENT-STUDENT — Flujo de consulta (Estudiante)

Route `/mi-proyecto/entregas/:id` o `/estudiante/entregas/:entregaId`, role Estudiante.

| Regla | Detalle |
|-------|---------|
| ENT-ST01 | Historial completo de versiones visible (selector + panel) |
| ENT-ST02 | Observación mostrada MUST corresponder **únicamente** a la versión seleccionada |
| ENT-ST03 | Navegación entre versiones intuitiva (pills + historial clickeable) |
| ENT-ST04 | Subir nueva versión → feedback visual local; nueva versión sin observación |
| ENT-ST05 | MUST NOT confundir comentarios de versiones distintas |

### Requirement: ENT-COORDINADOR — Vista lectura (Coordinador)

Route `/directores/proyectos/:proyectoId/entregas/:entregaId`, role Coordinador.

| Regla | Detalle |
|-------|---------|
| ENT-C01 | Mismo historial y selector que Estudiante |
| ENT-C02 | Solo lectura — sin formulario de revisión |
| ENT-C03 | Observaciones por versión visibles según ENT-OBS-PANEL |

### Requirement: ENT-ACCORDION — Resumen en dashboard

Componente `DeliveryAccordion` (EstudianteDashboard):

| Regla | Detalle |
|-------|---------|
| ENT-A01 | Tabla expandida MUST incluir columna o indicador "Observación" por versión |
| ENT-A02 | Badge: Revisada / Pendiente / Sin entregar según `reviewStatus` |

### Requirement: ENT-UX — Principios UX aplicables

| ID | Regla |
|----|-------|
| ENT-UX01 | Jerarquía: metadata entrega → historial versiones → contenido versión activa → observación |
| ENT-UX02 | Versión activa destacada visualmente en selector e historial |
| ENT-UX03 | Reducir ambigüedad: label explícito "Observación — Versión N" |
| ENT-UX04 | Retorno contextual: "Volver a Supervisión" (Director) / "Volver a Mi Proyecto" (Estudiante) |
| ENT-UX05 | Estados loading/empty/error con mock toggle o fixtures (UX-07) |
| ENT-UX06 | Responsive: historial stack vertical ≤767px; selector scroll horizontal si >4 versiones |

### Requirement: ENT-TL-TYPES — Estado visual de plazo (mock)

Estado de **cumplimiento de plazo de entrega** (distinto de `reviewStatus` de revisión del director). Valor preasignado en fixtures — **sin calcular** en frontend.

| Valor mock | Color | Etiqueta UI | Significado |
|------------|-------|-------------|-------------|
| `not_delivered` | Gris (`inactivo`) | Aún no entregado | Dentro del plazo; sin entrega registrada |
| `on_time` | Verde (`success`) | Entregado en horario | Entrega antes del vencimiento |
| `late` | Naranja (`warning`) | Entregado fuera del plazo | Entrega después del vencimiento |
| `overdue` | Rojo (`error`) | Atrasado / No entregado | Venció el plazo sin entrega |

Campo en fixture: `timelineStatus: DeliveryTimelineStatus`. Helpers de **solo lectura** para label/variant/icon — no inferir desde fechas en este change.

### Requirement: ENT-TL-BADGE — Componente de estado de plazo

Componente `DeliveryTimelineStatusBadge`:

| Regla | Detalle |
|-------|---------|
| ENT-TL01 | MUST usar variantes existentes de `StatusBadge` (sin nuevo design system) |
| ENT-TL02 | MUST incluir icono lucide coherente por estado |
| ENT-TL03 | MUST aceptar prop `status: DeliveryTimelineStatus` |
| ENT-TL04 | Labels MUST coincidir con tabla ENT-TL-TYPES |

Iconografía recomendada:

| Estado | Icono |
|--------|-------|
| `not_delivered` | `Clock` |
| `on_time` | `CheckCircle2` |
| `late` | `AlertTriangle` |
| `overdue` | `AlertCircle` |

### Requirement: ENT-TL-DIRECTOR-LIST — Listados del Director

Pantallas MUST mostrar `DeliveryTimelineStatusBadge` en cada fila/card de entrega:

| Pantalla | Ruta | Detalle |
|----------|------|---------|
| Supervisión proyecto | `/supervision/:proyectoId` | Lista expandible de entregas por fase |
| Dashboard Director | `/dashboard/director` | Tabla "Últimas Entregas" |

Reglas UX:

| ID | Regla |
|----|-------|
| ENT-TL05 | Estado visible sin expandir fila / sin abrir detalle |
| ENT-TL06 | Filas `overdue` MUST tener mayor énfasis (borde acento rojo o fondo sutil) |
| ENT-TL07 | Orden sugerido en listado: `overdue` → `late` → `not_delivered` → `on_time` |
| ENT-TL08 | Leyenda opcional compacta (`DeliveryTimelineStatusLegend`) bajo el título de sección |
| ENT-TL09 | Datos desde `entregasMock.ts`; sin `apiFetch` en estas secciones bajo este change |

**Distinción:** `timelineStatus` (plazo) y `reviewStatus` (revisión por versión) son dimensiones independientes y MUST NOT mezclarse en un solo badge.

### Requirement: ENT-TL-SUPERVISION — Supervisión proyecto (actualizado)

Route `/supervision/:proyectoId`, role Director. Bezel header + stepper + lista entregas con **ENT-TL-BADGE** por ítem. Enlace a revisión y bitácoras. Mock entregas vía `getEntregasByProjectId()`.

### Requirement: RevisionEntregaDirector — Split-Screen Review (actualizado)

Route `/entregas/:id/revisar`, role Director. Reemplaza requirement plano anterior. Ver **ENT-DIRECTOR**, **ENT-HISTORY**, **ENT-SELECTOR**, **ENT-OBS-PANEL**.

---

## Batch 3 — Director Flow (actualizado: bitácoras por proyecto)

> **Reemplaza** los requirements planos `BitacorasDirector`, `DetalleFirmaBitacora` y listados tabulares cross-proyecto. Ver **Módulo Bitácoras — Rediseño** más abajo.

### Requirement: SupervisionProyecto — Project Supervision

Route `/supervision/:proyectoId`, role Director. Ver **ENT-TL-SUPERVISION** y **ENT-TL-DIRECTOR-LIST**.

### Requirement: RevisionEntregaDirector — Split-Screen Review

Route `/entregas/:id/revisar`, role Director. Ver **Módulo Entregas — Observaciones por versión** (`ENT-DIRECTOR`). Left: document viewer mock por versión. Right: historial + observación de versión activa + panel revisión scoped a versión. Submit → toast/banner de éxito simulado en versión activa.

---

## Módulo Bitácoras — Rediseño (Director + Coordinador)

### Alcance

Rediseñar consulta y revisión de bitácoras **organizadas por proyecto**. Solo frontend mock. Estudiante mantiene flujo propio (`/bitacora`); este módulo aplica a Director y Coordinador.

### Requirement: BIT-HUB-DIRECTOR — Hub de proyectos (Director)

Route `/bitacoras/proyectos`, role Director.

| Scope | Detail |
|-------|--------|
| Datos | Solo proyectos que el director supervisa (mock filtrado por `directorId` o lista fija) |
| Layout | PageHeader + búsqueda + grid de project cards |
| Card info | Código, título, integrantes, contador bitácoras, pendientes de firma |
| Nav | Click card → `/bitacoras/proyectos/:proyectoId` |
| Mock | Sin `apiFetch`; fixtures en `mocks/bitacorasMock.ts` |

### Requirement: BIT-HUB-COORDINADOR — Hub de proyectos (Coordinador)

Route `/coordinador/bitacoras`, role Coordinador.

| Scope | Detail |
|-------|--------|
| Datos | **Todos** los proyectos registrados |
| Layout | Igual patrón que Director (componente compartido `BitacoraProjectGrid`) |
| Nav | Click card → `/coordinador/bitacoras/proyectos/:proyectoId` |

### Requirement: BIT-LIST — Historial de reuniones por proyecto

Routes:

- Director: `/bitacoras/proyectos/:proyectoId`
- Coordinador: `/coordinador/bitacoras/proyectos/:proyectoId`

| Scope | Detail |
|-------|--------|
| Layout | PageHeader con meta del proyecto + lista de **BitacoraMeetingCard** (no tabla plana cross-proyecto) |
| Card fields | Ver BIT-FIELDS abajo |
| Filtro | Por estado firma (Pendiente / Firmado / Rechazado) |
| Acciones Director | Ver detalle; Firmar/Revisar si Pendiente |
| Acciones Coordinador | Solo Ver detalle (lectura) |
| Retorno | "Volver a proyectos" → hub del rol |

### Requirement: BIT-FIELDS — Información visible por bitácora

Cada registro MUST mostrar (priorizando lectura rápida):

| Campo | Ubicación UI |
|-------|--------------|
| Nombre del proyecto | Header de lista o badge en card |
| Director | Meta row |
| Integrantes | Meta row (truncar + title) |
| Fecha creación bitácora | Meta |
| Fecha reunión | Destacada |
| Resumen/paráfrasis | 2–3 líneas max (`summary`); no contenido completo |
| Estado firma | StatusBadge |
| Firmante (director) | Solo si Firmado/Rechazado |
| Acciones | Según rol |

### Requirement: BIT-DETAIL — Detalle y revisión

Routes:

- Director: `/bitacoras/:id/revision`
- Coordinador: `/coordinador/bitacoras/:id/revision`

| Scope | Detail |
|-------|--------|
| Layout | PageHeader + grid 2 cols: contenido (izq) + firma/flujo (der, solo Director) |
| Contenido | Tema, fechas, integrantes, resumen, contenido expandible opcional |
| Firma | **Solo director** — ver BIT-SIGNATURE |
| Rechazo previo | Si estado Rechazado, banner con comentario visible |
| Retorno | Al listado del proyecto padre |

### Requirement: BIT-SIGNATURE — Firma del director (sin tabla)

**Eliminar** "Tabla de firmas" y cualquier fila de firmas de estudiantes.

Panel único `DirectorSignaturePanel`:

| Campo | Visible cuando |
|-------|----------------|
| Nombre del director | Siempre |
| Estado | Pendiente / Firmado / Rechazado (StatusBadge) |
| Fecha firma | Firmado |
| Hora firma | Firmado |

Estados y variantes StatusBadge:

| Estado | Label | Variant |
|--------|-------|---------|
| pendiente | Pendiente | warning |
| firmado | Firmado | success |
| rechazado | Rechazado | error |

### Requirement: BIT-REJECT — Rechazo con comentario

Solo Director, estado Pendiente, dentro del flujo de firma.

| Paso | Comportamiento UI (mock) |
|------|--------------------------|
| 1 | Botón "Rechazar" en panel de firma |
| 2 | Textarea obligatorio: motivo del rechazo |
| 3 | Confirmar → estado local `rechazado` + guardar comentario en mock |
| 4 | Comentario visible en detalle para Director, Coordinador y Estudiante (preparado visualmente) |

Sin persistencia. Actualización de array/estado React local.

### Requirement: BIT-TOTP-FLOW — Autenticación visual para firma

Flujo multi-paso simulado (`BitacoraSignFlow`):

| Paso | UI |
|------|-----|
| 1 Solicitar | Botón "Solicitar firma" |
| 2 Código | Banner "Código enviado" (mock, sin email) + botón "Ingresar código" |
| 3 Ingreso | TOTPInput 6 dígitos |
| 4 Validación | Delay simulado; código mock aceptado (ej. cualquier 6 dígitos o `123456`) |
| 5 Confirmación | Banner éxito + estado Firmado con fecha/hora local |
| Rechazo | Rama alternativa desde paso 1–3 → BIT-REJECT |

Sin envío de correo, sin generación OTP real, sin API.

### Requirement: BIT-NAV — Rutas deprecadas

| Ruta antigua | Acción |
|--------------|--------|
| `/bitacoras` (lista plana Director) | Redirect → `/bitacoras/proyectos` |
| `/bitacoras/:id/firmar` | Redirect → `/bitacoras/:id/revision` |
| `/directores/proyectos/:id/bitacoras` | Redirect → `/coordinador/bitacoras/proyectos/:id` |
| `/supervision/:id/bitacoras` | Redirect → `/bitacoras/proyectos/:id` |

---

## Batch 4 — Coordinador Gestión

### Requirement: GestionProyectos — Projects Hub (Visual CRUD)

Route `/proyectos`, role Coordinador. Semester bar + projects table + "Crear Nuevo Grupo" form + cupos table.

| Interacción UI | Crear/editar actualiza arrays mock locales; sin POST |

### Requirement: AnunciosAdmin — Announcement CRUD (Visual)

Route `/anuncios/admin`, role Coordinador. Cards + form + ConfirmDialog on delete. Delete/remove en memoria.

### Requirement: AsignacionEvaluadores — Evaluator Assignment (Visual)

Route `/evaluadores`, role Coordinador. Register form + table + agenda list. Submit añade fila mock.

### Requirement: CoordinadorEntregas — Cross-Project Delivery Viewer

Route `/coordinador/entregas`, role Coordinador. Project selector + stepper + info cards + delivery list + mini-table bitácoras. Selector cambia mock activo.

---

## Batch 5 — Coordinador Resto + Evaluador

### Requirement: CoordinadorBitacoras — Hub por proyecto (reemplaza listado plano)

Route `/coordinador/bitacoras`. Ver **BIT-HUB-COORDINADOR** y **BIT-LIST**.

### Requirement: GestionAlertas — Alert Management

Route `/alertas`, role Coordinador. 3 KPI cards + filter tabs + expandable alert cards. Acciones simulan cambio de estado local.

### Requirement: ReportesConsolidados — Grade Reports

Route `/reportes`, role Coordinador. Filter card + grades table + bar chart mock + export buttons.

| Interacción UI | Export → toast "Exportación simulada" |

### Requirement: RecursosAdmin — Resource CRUD (Visual)

Route `/recursos/admin`, role Coordinador. Upload form mock + cards + ConfirmDialog.

### Requirement: EvaluarProyecto — Rubric Evaluation

Route `/evaluaciones/:id`, roles Director+Evaluador. Split-screen + rubric form. Guardar → feedback visual local.

### Requirement: EvaluadorCalificar — External Evaluator Grading

Route `/evaluaciones/:id/calificar`, role Evaluador. Split-screen + grade pane. Guardar/Enviar → simulado.

---

## Batch 6 — IA (Mock UI)

### Requirement: AnalisisAutomaticoEntregas — AI Analysis (Mock)

Route `/analisis-entregas`, role Estudiante. Split-screen: coherence score 82/100 circle, checklist, "Confirmar Recepción". Banner disclaimer. Sin llamada a servicio de análisis.

### Requirement: AsistenteOrientacion — AI Chatbot (Mock)

Route `/asistente`, role Estudiante. Chat layout: header + thread pre-renderizado + input footer.

| Interacción UI | Input + Send → respuesta canned local tras delay; sin LLM |

---

## Cross-Cutting Requirements

Aplica a todas las pantallas. Complementa **UX Principles** y **Navigation System**.

### Estados visuales (todas las pantallas)

| Estado | Comportamiento UI |
|--------|-------------------|
| Loading | Loader2 centrado o skeleton |
| Empty | EmptyState o mensaje centrado |
| Error | Banner rojo + acción "Reintentar" que restaura mock |
| Data | Fixtures poblados visibles |

### Responsive

- Tablas: `overflow-x-auto` en ≤767px
- KPI grids: 2×2 mobile, 4 col desktop
- Split-screens: stack vertical ≤767px
- Stepper: vertical ≤640px

### Accesibilidad

- Labels en inputs y TOTPInput
- `aria-modal` en ConfirmDialog
- Sidebar: `aria-label="Navegación principal"`
- Contraste WCAG AA con colores canon

### Navegación

- `navConfig` por rol MUST seguir Navigation System (grupos, orden, matriz design.md)
- Rutas lazy-loaded con Suspense fallback en colores canon
- Vistas hijas: retorno vía PageHeader.actions (NS-14…NS-16)

---

## Acceptance Criteria Summary

| Batch | Pages | Key Gates (solo UI) |
|-------|-------|---------------------|
| 1 | 8 | 4 dashboards render KPIs/cards/tables mock; anuncios list+detail; recursos grid+detail |
| 2 | 4 | Landing 5 role cards; binnacle 3 signature states; TOTP mock en form; split-screen review |
| 3 | 5 | Stepper 4 fases; delivery expand; TOTP 6-digit grid; 3 decision buttons |
| 4 | 4 | Semester toggle visual; projects table 8 rows; evaluator table; delivery accordion |
| 5 | 6 | Filter bar; alert cards expand; grade table; rubric toggles |
| 6 | 2 | Score circle 82/100; chat pre-rendered + send simulado |

**Gate global:** ninguna pantalla del baseline realiza llamadas de red para datos de negocio. GestionUsuarios visual canon. lucide-react icons. Mock en estado `data`.

**Gate bitácoras (fase actual):**

- [x] Hub por proyecto Director y Coordinador (BIT-HUB-*)
- [x] Listado reuniones por proyecto con BIT-FIELDS
- [x] Sin tabla de firmas; solo DirectorSignaturePanel
- [x] Estados Pendiente/Firmado/Rechazado + comentario rechazo visible
- [x] Flujo TOTP visual BIT-TOTP-FLOW sin API
- [x] Rutas deprecadas redirigen (BIT-NAV)
- [x] Mock exclusivo en pantallas del módulo Director/Coordinador

**Gate entregas — observaciones por versión:**

- [x] Fixtures `entregasMock.ts` con observación 1:1 por versión (ENT-TYPES)
- [x] `DeliveryVersionHistory` + `DeliveryVersionSelector` + `VersionObservationPanel`
- [x] Director: revisión scoped a versión activa; formulario recarga al cambiar versión (ENT-DIRECTOR)
- [x] Estudiante: historial + observación por versión sin mezcla (ENT-STUDENT)
- [x] Coordinador: lectura por versión (ENT-COORDINADOR)
- [x] `DeliveryAccordion` indica estado observación por versión (ENT-ACCORDION)
- [x] Sin `apiFetch` en pantallas de detalle/revisión entregas bajo este change

**Gate entregas — estados de plazo (Director):**

- [x] Campo `timelineStatus` en fixtures con los 4 estados representados (ENT-TL-TYPES)
- [x] `DeliveryTimelineStatusBadge` + `DeliveryTimelineStatusLegend` (ENT-TL-BADGE)
- [x] Supervisión proyecto: badge por entrega + énfasis overdue (ENT-TL-SUPERVISION)
- [x] Dashboard Director: badge en tabla Últimas Entregas (ENT-TL-DIRECTOR-LIST)
- [x] Orden visual crítico primero (ENT-TL07)

---

## Explicitly Out of Scope

- `apiFetch`, hooks de datos remotos, React Query, SWR
- Endpoints REST, FormRequests Laravel, policies
- Persistencia PostgreSQL, Redis, archivos en storage
- Firma TOTP real, envío de email, OAuth más allá de páginas auth existentes
- FastAPI, embeddings, Azure OpenAI
- Tests E2E Playwright
