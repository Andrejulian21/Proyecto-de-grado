# Guía técnica del frontend

> **Nota (port 2026-08-20):** la base de producto es `master`. Las pantallas de IA (asistente, análisis de entregas, panel ABET, métricas y perfil académico) se portaron desde `Miguel-Cambios-220726` sobre la SPA de master. No se usa el dashboard/rúbrica de evaluador de Miguel.

> Sistema Centralizado de Proyectos de Grado — React 18 + Vite + TypeScript + Tailwind + React Router.
> Última revisión basada en `resources/js` (pages, components, hooks, types, lib).

---

## Resumen de arquitectura

El frontend es una **SPA React** montada en `#app` vía `createRoot`. **No usa Inertia.js**: no hay props server-side; los datos llegan por **fetch API** (`apiFetch`) o **hooks** con estado local.

| Capa | Ubicación | Rol |
|------|-----------|-----|
| Entrada | `app.tsx` | Router, `AuthProvider`, lazy routes, `ProtectedRoute` |
| Layout | `components/layout/` | Shell fijo: sidebar + header + main |
| Páginas | `pages/` | Vistas por rol y flujo de negocio |
| Componentes | `components/` | UI reutilizable y dominio (bitácoras, entregas, forms) |
| Hooks | `hooks/` | Estado remoto + mutaciones CRUD |
| Utilidades | `lib/utils.ts` | `cn()`, `apiFetch()` (Sanctum CSRF + cookies) |
| Tipos | `types/` | Interfaces compartidas (mínimas; muchos tipos viven en hooks/páginas) |

**Patrón de datos:** hook dedicado (`useReducer` + `apiFetch`) o `useEffect` + `apiFetch` inline en la página.

**Patrón de auth:** `AuthProvider` → `GET /sanctum/csrf-cookie` → `GET /api/auth/user` → contexto `{ user, role, logout }`.

**Code splitting:** rutas secundarias con `React.lazy` + `SuspenseWrapper`.

**Carpeta `services/`:** no existe; la capa de acceso a API está en hooks y `apiFetch`.

**Mocks:** existe `resources/js/mocks/` (legacy wireframes); la integración Sprint 5 migra hacia API real. Algunas páginas aún usan datos estáticos.

---

## Props desde Inertia

**Ninguna página recibe props de Inertia.** Todas obtienen datos de:

- Parámetros de ruta (`useParams`)
- Query string (`useSearchParams`)
- Contexto (`useAuth`)
- Hooks de dominio
- Llamadas directas a `apiFetch`

---

# Layouts

## AppShell

**Archivo:** `components/layout/AppShell.tsx`

**Responsabilidad:** Contenedor autenticado con sidebar colapsable (mobile), header y área de contenido scrollable.

**Props:** `children: ReactNode`

**Estado:** `sidebarOpen` (boolean)

**Hijos:** `Sidebar`, `Header`, `<main>{children}</main>`

**Lógica:** `usePageTitle()` resuelve título del header por ruta (mapa `ROUTE_TITLES` + prefix match).

---

## Sidebar

**Archivo:** `components/layout/Sidebar.tsx`

**Props:** `open`, `onClose`

**Dependencias:** `useAuth` (rol), `NavLink` por rol

**Navegación por rol:**

| Rol | Enlaces principales |
|-----|---------------------|
| Coordinador | Panel, Proyectos, Directores, Evaluadores, Usuarios, Anuncios, Alertas, Entregas, Recursos Admin |
| Director | Panel, Supervisión, Evaluaciones, Anuncios, Recursos |
| Estudiante | Mi Proyecto, Bitácora, Anuncios, Recursos, Análisis IA, Asistente |
| EvaluadorExterno | Panel, Evaluaciones, Anuncios, Recursos |

---

## Header

**Archivo:** `components/layout/Header.tsx`

**Props:** `onMenuClick`, `title`

**Acciones:** logout vía `useAuth`, badge de rol, iniciales del usuario

---

# Hooks

| Hook | Propósito | Endpoints API | Retorno principal |
|------|-----------|---------------|-------------------|
| `useAuth` | Sesión Sanctum, bootstrap CSRF | `/sanctum/csrf-cookie`, `/api/auth/user`, `/api/auth/logout` | `user`, `role`, `isAuthenticated`, `isLoading`, `logout`, `sessionCheck` |
| `useProyectos` | CRUD proyectos coordinador | `/api/admin/proyectos` | `data`, `loading`, `error`, `crear`, `actualizar`, `eliminar`, `refetch` |
| `useGrupos` | Semestres (grupos) | `/api/admin/semestres` | `data`, `crear`, `actualizar`, `refetch` |
| `useCupos` | Cupos de directores | `/api/admin/directores/cupos`, `PUT .../cupo` | `data`, `updateCupo`, `refetch` |
| `useEntregas` | CRUD entregas + secuencia de fases | `/api/admin/entregas` | `data`, `crear`, `actualizar`, `eliminar`, `getNextFase`, tipos `Entrega`, `Fase` |
| `useKpis` | KPIs coordinador | `/api/admin/proyectos/kpis` | `data`, `loading`, `refetch` |
| `useAlertas` | Alertas derivadas client-side | `/api/admin/entregas`, bitácoras admin | `data: Alerta[]`, `refetch` |
| `useRecursos` | CRUD recursos (multipart) | `/api/admin/recursos` | `data`, `crear`, `actualizar`, `eliminar`, `uploadProgress` |
| `useEvaluadorProyecto` | Asignación evaluadores | `/api/admin/evaluador-proyecto` | `data`, `crear`, `actualizar`, `eliminar` |
| `useEvaluadorUsers` | Pool evaluadores/directores | `/api/admin/usuarios?role=...` | `data: EvaluadorUser[]` |
| `useEvaluaciones` | Resultados evaluación | `/api/evaluaciones` | `data: EvaluacionResult[]` |
| `useDirectores` | Drill-down directores | `/api/admin/directores`, proyectos, bitácoras | lista + selección multinivel |
| `useDirectorProyectos` | Proyectos del director | `/api/director/proyectos` | `data: DirectorProyecto[]` |
| `useDirectorKpis` | KPIs director | `/api/director/kpis` | contadores dashboard |
| `useDirectorEntregas` | Entregas pendientes | `/api/director/entregas` | top entregas `enviada` |
| `useDirectorBitacoras` | Bitácoras agregadas | `/api/director/proyectos`, `.../bitacoras` | `data: BitacoraEntry[]` |
| `useDirectorEvaluaciones` | Evaluaciones asignadas | `/api/director/evaluaciones` | *(hook definido; no usado aún en páginas)* |
| `useStudentSearch` | Autocomplete estudiantes | `/api/admin/usuarios?role=estudiante&search=` | `results`, `search`, `loading` |
| `useUnifiedUsers` | Fusión usuarios/whitelist | varios `/api/admin/*` | *(hook definido; no usado en páginas — `GestionUsuarios` usa lógica inline)* |

**Convención:** hooks de mutación exponen `mutationLoading`, `mutationError` y `refetch` tras éxito.

---

# Servicios

No hay capa `services/`. Equivalente funcional:

| Responsabilidad | Implementación |
|-----------------|----------------|
| HTTP autenticado | `apiFetch()` en `lib/utils.ts` |
| Dominio por entidad | hooks en `hooks/` |
| Auth global | `useAuth` context |

---

# Tipos TypeScript

## `types/estudiante.ts`

| Tipo | Campos clave | Consumidores |
|------|--------------|--------------|
| `EntregaData` | `id`, `fase`, `label`, `status`, `deadline`, `grade`, `versions` | `EstudianteDashboard`, `DeliveryAccordion` |
| `VersionData` | `version`, `date`, `status`, `fileName` | `DeliveryAccordion` |
| `PhaseStep` | `id`, `label`, `status` (`done/current/future`) | `PhaseStepper`, dashboards |

## Tipos en hooks (exportados)

- `useProyectos`: `Proyecto`, `CreateProyectoPayload`, `UpdateProyectoPayload`
- `useEntregas`: `Entrega`, `Fase`, `FASE_SEQUENCE`, payloads create/update
- `useAlertas`: `Alerta`
- `useRecursos`: `Recurso`
- `useDirectores`: `Director`, `DirectorProyecto`, `Bitacora`
- `useDirectorEntregas`: `DirectorEntrega`
- `useEvaluadorProyecto`: tipos de asignación agrupada

## Tipos locales en componentes

- `RevisionBitacoraView`: `BitacoraDetail`, `BitacoraSignature` (compartido entre páginas de revisión)

## Tipos inline en páginas

Muchas páginas de entrega (`DetalleEntregaEstudiante`, `RevisionEntregaDirector`, etc.) definen interfaces locales `EntregaDetail`, `Version` — **candidatos a centralizar** en `types/`.

---

# Utilidades

## `lib/utils.ts`

| Export | Propósito |
|--------|-----------|
| `cn(...)` | Merge de clases Tailwind (`clsx` + `tailwind-merge`) |
| `apiFetch(url, options)` | Fetch con `credentials: 'include'`, header `Accept: application/json`, `X-XSRF-TOKEN` en mutaciones, redirect a `/login` en 401 |

---

# Páginas React

Formato estándar por página. **Props Inertia:** siempre *N/A*.

---

## Auth y enrutamiento

### `pages/auth/LoginInstitucional.tsx`

| Campo | Detalle |
|-------|---------|
| **Ruta** | `/login` |
| **Responsabilidad** | Login institucional vía Google OAuth |
| **Componentes** | — (UI inline) |
| **Hooks** | — |
| **Servicios** | Redirect a `/auth/redirect` |
| **Acciones** | Iniciar OAuth; enlace a login externo |
| **Estado** | Prop opcional `error` desde query string |

### `pages/auth/LoginExterno.tsx`

| Campo | Detalle |
|-------|---------|
| **Ruta** | `/login/externo` |
| **Responsabilidad** | Login evaluador externo con email/contraseña |
| **Hooks** | `useAuth` |
| **Servicios** | `POST /api/auth/externo/login` |
| **Acciones** | Submit formulario; redirect por rol; flujo cambio contraseña |
| **Estado** | `email`, `password`, `errors`, `isSubmitting`, `showPassword` |

### `pages/landing/LandingPage.tsx`

| Campo | Detalle |
|-------|---------|
| **Ruta** | `/` (pública) |
| **Responsabilidad** | Landing + redirect si ya autenticado |
| **Hooks** | `useAuth` |
| **Acciones** | Links a login; auto-redirect a dashboard |

### `pages/DashboardRouter.tsx`

| Campo | Detalle |
|-------|---------|
| **Ruta** | `/` (protegida) |
| **Responsabilidad** | Redirige al dashboard según rol |
| **Hooks** | `useAuth` |
| **Estado** | `isLoading`, `role` |

---

## Dashboards

### `pages/dashboard/EstudianteDashboard.tsx`

| Campo | Detalle |
|-------|---------|
| **Ruta** | `/dashboard/estudiante` |
| **Responsabilidad** | Resumen del proyecto, fases y entregas del estudiante |
| **Componentes** | `PageHeader`, `StatusBadge`, `DeliveryAccordion`, `PhaseStepper` |
| **Hooks** | — |
| **Servicios** | `GET /api/estudiante/proyecto`, mapeo a `EntregaData` |
| **Acciones** | Filtrar por fase; navegar a detalle entrega |
| **Estado** | `proyecto`, `entregas`, `loading`, `error`, `selectedPhaseId` |

### `pages/dashboard/DirectorDashboard.tsx`

| Campo | Detalle |
|-------|---------|
| **Ruta** | `/dashboard/director` |
| **Responsabilidad** | KPIs, carrusel de proyectos, tabla entregas urgentes |
| **Componentes** | `StatCard`, `StatusBadge`, `DataTable` |
| **Hooks** | `useDirectorProyectos`, `useDirectorKpis`, `useDirectorEntregas` |
| **Acciones** | Ver supervisión; revisar entrega (ícono ojo) |

### `pages/dashboard/CoordinadorDashboard.tsx`

| Campo | Detalle |
|-------|---------|
| **Ruta** | `/dashboard/coordinador` |
| **Responsabilidad** | KPIs globales, tabla proyectos, preview alertas |
| **Componentes** | `PageHeader`, `StatCard`, `StatusBadge`, `DataTable` |
| **Hooks** | `useKpis`, `useProyectos`, `useAlertas` |
| **Acciones** | Ir a supervisión proyecto; refrescar alertas |

### `pages/dashboard/EvaluadorDashboard.tsx`

| Campo | Detalle |
|-------|---------|
| **Ruta** | `/dashboard/evaluador-externo` |
| **Responsabilidad** | Panel evaluador con evaluaciones pendientes |
| **Componentes** | `PageHeader`, `StatCard`, `StatusBadge` |
| **Servicios** | **Mock** — datos estáticos `MOCK_EVALUATIONS` |
| **Acciones** | Navegar a calificar |

---

## Estudiante

### `pages/estudiante/BitacorasEstudiante.tsx`

| Ruta | `/bitacora` |
| Componentes | `PageHeader`, `DataTable`, `StatusBadge`, `EmptyState` |
| Servicios | `GET /api/bitacoras?proyecto_id=` |
| Acciones | Nueva bitácora; ver revisión |
| Estado | `binnacles`, `loading`, `error`, paginación UI |

### `pages/estudiante/NuevaBitacora.tsx`

| Ruta | `/bitacora/nueva` |
| Servicios | `POST /api/bitacoras` |
| Acciones | Crear bitácora; cancelar |
| Estado | campos formulario, `submitting`, `error` |

### `pages/estudiante/RevisionBitacora.tsx`

| Ruta | `/bitacora/:id/revision` |
| Componentes | `RevisionBitacoraView` |
| Hooks | `useAuth` |
| Servicios | `GET/PUT /api/bitacoras/:id`, `POST .../firmar` |
| Acciones | Editar contenido; firmar como estudiante |

### `pages/estudiante/DetalleEntregaEstudiante.tsx`

| Ruta | `/mi-proyecto/entregas/:id`, `/estudiante/entregas/:entregaId` |
| Servicios | `GET /api/admin/entregas/:id`, versiones, subir/eliminar versión, solicitar |
| Acciones | Subir PDF/DOCX; eliminar versión; solicitar habilitación; selector de versiones |
| Estado | `entrega`, `selectedVersionIdx`, upload/delete, `isLocked` |
| Nota | UI inline (no usa componentes `components/entregas/*`) |

### `pages/estudiante/AnalisisAutomaticoEntregas.tsx`

| Ruta | `/analisis-entregas` |
| Servicios | **Mock IA** — checklist simulado |
| Acciones | Ejecutar análisis; volver |

### `pages/estudiante/AsistenteOrientacion.tsx`

| Ruta | `/asistente` |
| Servicios | **Mock chat** — respuestas simuladas |
| Acciones | Enviar mensaje; sugerencias rápidas |

---

## Director

### `pages/director/SupervisionProyectoDirector.tsx`

| Ruta | `/supervision`, `/supervision/:proyectoId` |
| Componentes | `PageHeader`, `StatusBadge`, `EmptyState`, `PhaseStepper` |
| Hooks | `useDirectorProyectos` |
| Servicios | `GET /api/director/proyectos/:id`, entregas inline |
| Acciones | Lista/detalle proyecto; ir a bitácoras o revisar entrega |
| Estado | `search`, `project`, `expandedDelivery`, `selectedPhaseId` |

### `pages/director/BitacorasProyecto.tsx`

| Ruta | `/supervision/:proyectoId/bitacoras` |
| Componentes | `PageHeader`, `DataTable`, `StatusBadge`, `ConfirmDialog` |
| Servicios | API bitácoras + firma rápida |
| Acciones | Filtrar; revisar; firmar con confirmación |

### `pages/director/SeleccionProyectosBitacoras.tsx`

| Ruta | `/bitacoras/proyectos` |
| Servicios | **Mock** proyectos locales |
| Acciones | Buscar/filtrar; navegar (ruta destino puede estar desalineada) |

### `pages/director/BitacorasDirector.tsx`

| Ruta | `/bitacoras` |
| Hooks | `useDirectorBitacoras` |
| Componentes | `DataTable`, `StatCard`, `ConfirmDialog` |
| Acciones | Filtrar estado; firmar vía API |

### `pages/director/RevisionBitacora.tsx`

| Ruta | `/bitacoras/:id/revision` |
| Componentes | `RevisionBitacoraView` (mode director) |
| Hooks | `useAuth` |

### `pages/director/DetalleFirmaBitacora.tsx`

| Ruta | `/bitacoras/:id/firmar` |
| Componentes | `TOTPInput`, `PageHeader`, `StatusBadge` |
| Servicios | **Mock TOTP** — sin llamada API real aún |
| Acciones | Ingresar código TOTP simulado |

### `pages/director/RevisionEntregaDirector.tsx`

| Ruta | `/entregas/:id/revisar` |
| Servicios | `GET /api/admin/entregas/:id`, `PUT .../revisar` |
| Acciones | Aprobar/rechazar/revisar; notas al director; selector versión |
| Estado | `decision`, `directorNotes`, `selectedVersionIdx` |

### `pages/director/DetalleEntregaDirector.tsx`

| Ruta | **Sin registrar en `app.tsx`** |
| Servicios | Similar a revisión con nota consolidada |
| Nota | Página huérfana — duplica lógica de `RevisionEntregaDirector` |

### `pages/director/EvaluacionesDirector.tsx`

| Ruta | `/evaluaciones` |
| Servicios | `GET /api/director/evaluaciones`, `GET .../entrega-fase`, `POST /api/evaluaciones` |
| Acciones | Seleccionar proyecto; armar rúbrica; enviar evaluación |
| Estado | `useReducer` para criterios, selección de proyecto |

---

## Coordinador

### `pages/coordinador/GestionUsuarios.tsx`

| Ruta | `/coordinador/usuarios` |
| Hooks | `useAuth` |
| Servicios | whitelist, usuarios, evaluadores — múltiples endpoints admin |
| Acciones | CRUD whitelist; crear evaluador externo; cambiar rol; eliminar |
| Estado | formularios, modales, paginación, búsqueda (muy extenso) |

### `pages/coordinador/AuditLog.tsx`

| Ruta | `/coordinador/audit-log` |
| Servicios | `GET /api/admin/audit-logs` |
| Acciones | Filtrar por usuario/acción/fecha; paginar |

### `pages/coordinador/GestionProyectos.tsx`

| Ruta | `/proyectos` |
| Hooks | `useProyectos`, `useGrupos`, `useCupos` |
| Componentes | `DataTable`, `GroupSelector`, `StudentAutocomplete`, `ConfirmDialog` |
| Acciones | Crear/editar/eliminar proyecto; asignar director y estudiantes |

### `pages/coordinador/DirectoresPage.tsx`

| Ruta | `/directores` |
| Hooks | `useDirectores` |
| Componentes | `SupervisionReadOnly` (embed) |
| Acciones | Drill-down 3 niveles: director → proyecto → bitácora |

### `pages/coordinador/VerBitacorasCoordinador.tsx`

| Ruta | `/directores/proyectos/:proyectoId/bitacoras` |
| Servicios | `GET /api/admin/proyectos/:id/bitacoras` |

### `pages/coordinador/RevisionBitacoraCoordinador.tsx`

| Ruta | `/directores/bitacoras/:id/revision` |
| Componentes | `RevisionBitacoraView` (solo lectura) |

### `pages/coordinador/DetalleEntregaCoordinador.tsx`

| Ruta | `/directores/proyectos/:proyectoId/entregas/:entregaId` |
| Servicios | `GET /api/admin/entregas/:id` (solo lectura) |

### `pages/coordinador/AnunciosAdmin.tsx`

| Ruta | `/anuncios/admin` |
| Servicios | CRUD `/api/admin/anuncios` |

### `pages/coordinador/AsignacionEvaluadores.tsx`

| Ruta | `/evaluadores` |
| Hooks | `useEvaluadorProyecto`, `useEvaluadorUsers`, `useEvaluaciones`, `useProyectos` |
| Componentes | `CalendarGrid`, `ResultsTable`, `DataTable`, `ConfirmDialog` |
| Acciones | Crear/editar/eliminar asignaciones; filtro calendario |

### `pages/coordinador/CoordinadorEntregas.tsx`

| Ruta | `/coordinador/entregas` |
| Hooks | `useEntregas` |
| Componentes | `GroupSelector` |
| Acciones | CRUD entregas por semestre/grupo |

### `pages/coordinador/CoordinadorBitacoras.tsx`

| Ruta | `/coordinador/bitacoras` |
| Servicios | **Mock** — 25 entradas estáticas |
| Acciones | Buscar, filtrar, paginar UI |

### `pages/coordinador/CoordinadorBitacorasProyecto.tsx`

| Ruta | **Sin registrar** — wrapper de `BitacorasProyectoList` |

### `pages/coordinador/GestionAlertas.tsx`

| Ruta | `/alertas` |
| Hooks | `useAlertas` |
| Acciones | Tabs activas/resueltas; expandir tarjetas |

### `pages/coordinador/RecursosAdmin.tsx`

| Ruta | `/recursos/admin` |
| Hooks | `useRecursos` |
| Acciones | Subir/editar/eliminar recursos |

---

## Shared

### `pages/shared/AnunciosPublica.tsx` — `/anuncios`
### `pages/shared/AnuncioDetalle.tsx` — `/anuncios/:id`
### `pages/shared/Recursos.tsx` — `/recursos`
### `pages/shared/RecursoDetalle.tsx` — `/recursos/:id`

Todas consumen `GET /api/anuncios` o `/api/recursos` vía `apiFetch`.

### `pages/shared/BitacorasProyectoList.tsx`

Componente-página reutilizable con props `role`, `backPath`, `revisionPath`. Usado solo por `CoordinadorBitacorasProyecto` (sin ruta). Usa `BitacoraMeetingList`.

---

## Evaluador

### `pages/evaluador/EvaluarProyecto.tsx`

| Ruta | `/evaluaciones/:id` (Director + EvaluadorExterno) |
| Servicios | **Mock** rúbrica |

### `pages/evaluador/EvaluadorCalificar.tsx`

| Ruta | `/evaluaciones/:id/calificar` |
| Servicios | **Mock** — debería usar `POST /api/evaluaciones` |

---

## Componente enrutado (no en `pages/`)

### `components/supervision/SupervisionReadOnly.tsx`

| Ruta | `/dashboard/coordinador/proyecto/:id` |
| Props | `projectId` desde URL |
| Servicios | `GET /api/admin/proyectos/:id` |
| Acciones | Vista read-only de fases y entregas; enlace a detalle entrega coordinador |

---

# Componentes reutilizables

## UI (`components/ui/`)

| Componente | Propósito | Props principales | Usado en |
|------------|-----------|-------------------|----------|
| `PageHeader` | Cabecera de página con eyebrow/título/acciones | `eyebrow`, `title`, `subtitle?`, `actions?` | ~35 páginas |
| `StatusBadge` | Badge semántico de estado | `variant?`, `children` | ~40 usos |
| `StatCard` | Tarjeta KPI | `icon`, `label`, `value`, `variant?`, `trend?` | Dashboards, admin |
| `DataTable<T>` | Tabla genérica paginable | `columns`, `data`, `loading?`, `pagination?`, `getRowKey` | Dashboards, listas |
| `EmptyState` | Placeholder vacío | `icon`, `title`, `description?`, `action?` | Listas sin datos |
| `ConfirmDialog` | Modal confirmación | `open`, `title`, `message`, `onConfirm`, `onCancel`, `variant?` | CRUD destructivo |
| `TOTPInput` | Input 6 dígitos TOTP | `onComplete`, `disabled?`, `error?` | Firma bitácora |

## Dominio — proyecto

| Componente | Propósito | Props | Usado en |
|------------|-----------|-------|----------|
| `PhaseStepper` | Stepper de fases del PG | `phases`, `selectedPhaseId`, `onSelectPhase`, `deliveryCountByPhase?` | `EstudianteDashboard`, `SupervisionProyectoDirector`, `SupervisionReadOnly` |
| `DeliveryAccordion` | Acordeón entrega con versiones | `delivery: EntregaData` | `EstudianteDashboard` |

## Dominio — bitácoras

| Componente | Propósito | Props | Usado en |
|------------|-----------|-------|----------|
| `RevisionBitacoraView` | Vista unificada revisión/firma | `mode`, `bitacora`, callbacks `onSign`, `onSaveContent`, etc. | Revisiones estudiante/director/coordinador |
| `BitacoraMeetingList` | Lista reuniones | `meetings`, `onSelect`, filtros | `BitacorasProyectoList` |
| `BitacoraMeetingCard` | Tarjeta reunión | props de meeting | Usado por `BitacoraMeetingList` |
| `BitacoraProjectGrid` | Grid proyectos bitácora | `projects`, `onSelect` | *(sin uso en páginas enrutadas)* |
| `BitacoraSignFlow` | Flujo firmas paso a paso | callbacks de firma | *(sin uso en páginas)* |
| `DirectorSignaturePanel` | Panel firma director | estado firma | *(sin uso en páginas)* |

## Dominio — entregas (`components/entregas/`)

| Componente | Propósito | Estado de uso |
|------------|-----------|---------------|
| `DeliveryVersionHistory` | Historial versiones | **Huérfano** — ninguna página importa |
| `DeliveryVersionSelector` | Selector versión | **Huérfano** |
| `DeliveryDocumentPreview` | Preview documento | **Huérfano** |
| `DirectorVersionReviewPanel` | Panel revisión director | **Huérfano** |
| `VersionObservationPanel` | Observaciones director | **Huérfano** |
| `DeliverySupervisionRow` | Fila entrega supervisión | **Huérfano** |
| `DeliveryTimelineStatusBadge` | Badge timeline | **Huérfano** |
| `DeliveryTimelineStatusLegend` | Leyenda timeline | **Huérfano** |

> Las páginas de detalle/revisión de entrega reimplementaron la UI inline (~600 líneas cada una).

## Forms

| Componente | Propósito | Props | Usado en |
|------------|-----------|-------|----------|
| `GroupSelector` | Selector semestre/grupo | `value`, `onChange`, `error?`, `readonly?` | `GestionProyectos`, `CoordinadorEntregas` |
| `StudentAutocomplete` | Autocomplete estudiantes | `value[]`, `onChange`, `max?` | `GestionProyectos` |

## Otros

| Componente | Propósito | Usado en |
|------------|-----------|----------|
| `SupervisionReadOnly` | Supervisión read-only coordinador | Ruta coordinador + embed `DirectoresPage` |
| `CalendarGrid` | Calendario asignaciones evaluadores | `AsignacionEvaluadores` |
| `ResultsTable` | Tabla resultados evaluación | `AsignacionEvaluadores` |

---

# Mapa de navegación

Flujo típico **Página → Componentes → Hooks → API**:

```
EstudianteDashboard
    ├── PhaseStepper          → (estado local + fases mapeadas)
    ├── DeliveryAccordion     → types/estudiante
    └── apiFetch              → GET /api/estudiante/proyecto

GestionProyectos
    ├── DataTable             → useProyectos.data
    ├── GroupSelector         → useGrupos.data
    ├── StudentAutocomplete   → useStudentSearch.search
    └── useProyectos          → /api/admin/proyectos

DirectorDashboard
    ├── StatCard              → useDirectorKpis
    ├── DataTable             → useDirectorEntregas
    └── useDirectorProyectos  → /api/director/proyectos

RevisionBitacora (cualquier rol)
    └── RevisionBitacoraView  → apiFetch → /api/bitacoras/:id
                              → POST /api/bitacoras/:id/firmar

AsignacionEvaluadores
    ├── CalendarGrid          → useEvaluadorProyecto.data
    ├── ResultsTable          → useEvaluaciones.data
    └── useEvaluadorProyecto  → /api/admin/evaluador-proyecto

CoordinadorDashboard
    ├── StatCard              → useKpis
    ├── DataTable             → useProyectos
    └── useAlertas            → derivado de entregas/bitácoras API
```

## Árbol de rutas por rol

```
/login, /login/externo, /                    (público)
└── AppShell (autenticado)
    ├── /dashboard/{rol}
    ├── Estudiante: /bitacora*, /mi-proyecto/entregas/:id, /analisis-entregas, /asistente
    ├── Director: /supervision*, /bitacoras*, /entregas/:id/revisar, /evaluaciones
    ├── Coordinador: /proyectos, /directores*, /coordinador/*, /evaluadores, /alertas
    ├── Evaluador: /evaluaciones/:id/calificar
    └── Shared: /anuncios*, /recursos*
```

---

# Componentes más reutilizados

| Rank | Componente | Motivo |
|------|------------|--------|
| 1 | `PageHeader` | Cabecera estándar en casi todas las páginas autenticadas |
| 2 | `StatusBadge` | Estados de entrega, bitácora, proyecto, anuncio |
| 3 | `StatCard` | KPIs en todos los dashboards |
| 4 | `DataTable` | Listados coordinador/director/estudiante |
| 5 | `ConfirmDialog` | Patrón CRUD seguro |
| 6 | `RevisionBitacoraView` | Unifica 3 roles en un solo flujo bitácora |
| 7 | `PhaseStepper` | Progreso de fases PG en 3 contextos |

---

# Componentes candidatos a reutilización

| Candidato | Oportunidad |
|-----------|-------------|
| Bloque detalle entrega (estudiante + director + coordinador) | Extraer de ~600 líneas duplicadas; usar o refactorizar `components/entregas/*` existentes |
| `useDirectorEvaluaciones` | Reemplazar fetch inline en `EvaluacionesDirector` |
| `useUnifiedUsers` | Simplificar `GestionUsuarios` (hoy lógica duplicada) |
| Tipos `EntregaDetail` / `Version` | Mover a `types/entregas.ts` (archivo eliminado en migración — recrear) |
| `BitacoraProjectGrid` / `BitacoraSignFlow` | Conectar a rutas director o eliminar dead code |
| Formulario rúbrica evaluación | Compartir entre `EvaluacionesDirector` y `EvaluadorCalificar` |

---

# Componentes muy acoplados

| Componente | Acoplamiento | Riesgo |
|------------|--------------|--------|
| `RevisionBitacoraView` | Mezcla UI, TOTP, edición, tabla firmas y lógica por `mode` | Difícil de testear; cambios afectan 3 roles |
| `GestionUsuarios` | Página monolítica: whitelist + evaluadores + roles + paginación | Mantenimiento costoso |
| `SupervisionReadOnly` | Fetch + mock fallback + PhaseStepper + navegación entregas | Dos modos (API vs mock) en un componente |
| `DetalleEntregaEstudiante` / `RevisionEntregaDirector` | UI completa inline sin composición | Duplicación ~90% entre ambas |
| `EvaluacionesDirector` | `useReducer` + mapeo fases + POST evaluaciones en una página | Lógica de dominio en vista |

---

# Matriz de integración API

| Área | Estado |
|------|--------|
| Auth, usuarios, proyectos, entregas admin, recursos, anuncios | Integrado con API |
| Dashboards estudiante/director/coordinador | Integrado |
| Bitácoras CRUD + firma (excepto TOTP mock) | Integrado |
| Detalle/revisión entregas | Integrado (UI inline) |
| Evaluador externo (calificar) | Parcial / mock |
| IA (análisis, asistente) | Mock |
| CoordinadorBitacoras list | Mock |
| DetalleFirmaBitacora TOTP | Mock |
| Páginas sin ruta (`DetalleEntregaDirector`, `CoordinadorBitacorasProyecto`) | Huérfanas |

---

# Referencia rápida de archivos

```
resources/js/
├── app.tsx                 # Router + AuthProvider
├── lib/utils.ts            # cn, apiFetch
├── types/estudiante.ts     # EntregaData, PhaseStep
├── hooks/                  # 17 módulos de datos + useAuth
├── components/
│   ├── layout/             # AppShell, Sidebar, Header
│   ├── ui/                 # Design system interno
│   ├── forms/              # GroupSelector, StudentAutocomplete
│   ├── project/            # PhaseStepper
│   ├── bitacoras/          # RevisionBitacoraView + auxiliares
│   ├── entregas/           # Componentes extraídos (mayoría huérfanos)
│   ├── supervision/        # SupervisionReadOnly
│   ├── calendar/           # CalendarGrid
│   └── tables/             # ResultsTable
├── pages/
│   ├── auth/               # Login
│   ├── dashboard/          # 4 dashboards
│   ├── estudiante/         # 6 páginas
│   ├── director/           # 10 páginas
│   ├── coordinador/        # 14 páginas
│   ├── evaluador/          # 2 páginas
│   ├── shared/             # Anuncios, recursos, bitácoras list
│   └── landing/            # LandingPage
└── mocks/                  # Legacy (migración en curso)
```
