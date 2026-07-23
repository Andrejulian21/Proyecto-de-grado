# Design: Frontend Wireframes Port

## Scope Statement

**Alcance exclusivamente frontend.** Este design document describe layout, componentes, rutas, mock data, estados visuales simulados y convenciones de UI. No define contratos de API, persistencia, validación en servidor ni integración con servicios externos.

Cuando una pantalla requiera datos dinámicos, se usan constantes mock, estado local de React y comportamiento visual esperado. La estructura de tipos TypeScript prepara la UI para futuras integraciones, pero **este change no implementa** esa comunicación.

---

## Technical Approach

Port ~29 wireframes to React 18 + TypeScript + Tailwind v4, following the visual canon established by `GestionUsuarios.tsx`. Shared components live in `components/ui/`. Dashboards upgraded in-place from placeholders to wireframe-faithful layouts. New pages use `React.lazy()` + `Suspense` for code-splitting.

All user-visible actions (guardar, firmar, enviar, eliminar) produce **feedback visual local**: spinners, banners, toasts, actualización de listas en memoria o navegación simulada.

---

## Architecture Decisions

| Decision | Choice | Alternatives | Rationale |
|----------|--------|-------------|-----------|
| Shared components location | `components/ui/` | `components/shared/`, inline per-page | Clean separation from `components/layout/` |
| Mock data strategy | `resources/js/mocks/` por módulo + `FRONTEND_VALIDATION_MODE` | `const MOCK_*` inline, JSON fixtures | Reemplazo centralizado en Sprint 5; hooks conservan interfaz |
| Route loading | `React.lazy()` + `Suspense` wrapper | Eager imports | Muchas páginas; lazy mantiene bundle inicial pequeño |
| Landing page layout | Standalone, NO AppShell | Inside AppShell with hidden sidebar | Página pública pre-login |
| TOTP implementation | Custom 6-input component, validación simulada | Librerías OTP con verificación real | Solo UX de firma; verificación real fuera de alcance |
| Visual canon | Raw hex Tailwind classes (matching GestionUsuarios) | Design token classes only | Consistencia con páginas ya portadas |
| Dashboard upgrades | Replace file contents in-place | New files + redirect | Rutas existentes permanecen válidas |
| Form submit behavior | `preventDefault` + estado local + feedback visual | POST a API | Sin backend en este change |
| IA screens | Thread y análisis pre-renderizados + input deshabilitado o respuesta simulada | Conexión a LLM | Solo wireframe UX |
| Navigation config | `navConfig` con grupos semánticos + reglas de activo | Lista plana por rol | Agrupación lógica, orden cross-role, jerarquía primaria/secundaria |
| Page title source | Header (shell) = título de sección; PageHeader = contexto de pantalla | Duplicar h1+h2 con mismo texto | Reduce ruido visual; UX-02 |
| Back navigation | `PageHeader.actions` con botón "Volver a {sección}" | Links sueltos, breadcrumbs mixtos | Patrón único en vistas de detalle/flujo |

---

## Data Flow (UI only)

```
app.tsx (routes)
  ├── / → LandingPage (public, no AppShell)
  ├── /login, /login/externo → existing auth pages
  └── /* → ProtectedRoute → AppShell
        ├── / → DashboardRouter → /dashboard/{role}
        ├── /dashboard/* → role dashboards (mock KPIs)
        ├── /anuncios, /recursos → shared pages (mock lists)
        ├── /bitacora/*, /supervision/*, /proyectos, … → role flows
        └── * → Navigate to /

Each page:
  MOCK_* constants → useState (filtros, tabs, modales) → render
  User action → setState / setTimeout / navigate (sin red)
```

No `apiFetch` obligatorio cuando `FRONTEND_VALIDATION_MODE === true`. Hooks y páginas ramifican: mock → `mockDelay` → estado local; integración futura → rama API existente intacta.

---

## Frontend Validation Mode (FVM)

### Purpose

Fase **temporal** para validar UX, wireframes, navegación, flujos, componentes, responsive, estados visuales y accesibilidad **sin servidor backend**.

### Activation

| Artefacto | Ubicación |
|-----------|-----------|
| Flag global | `resources/js/mocks/validationMode.ts` → `FRONTEND_VALIDATION_MODE` |
| Re-export | `resources/js/mocks/index.ts` |
| Latencia simulada | `mockDelay(ms?)` |

### Mock modules

| Módulo | Archivo | Consumidores |
|--------|---------|--------------|
| Proyectos / KPIs / directores | `proyectosMock.ts` | Dashboards, GestionProyectos, Supervisión, hooks admin/director |
| Estudiante | `estudianteMock.ts` | EstudianteDashboard, BitacorasEstudiante, NuevaBitacora |
| Entregas | `entregasMock.ts` | Detalle/revisión entregas, supervisión, accordion |
| Bitácoras | `bitacorasMock.ts` | Flujos Director/Coordinador/Estudiante revisión |
| Anuncios | `anunciosMock.ts` | AnunciosPublica, AnuncioDetalle, AnunciosAdmin |
| Recursos | `recursosMock.ts` | Recursos, RecursoDetalle, RecursosAdmin |
| Usuarios / evaluadores | `usuariosMock.ts` | GestionUsuarios, AsignacionEvaluadores |
| Evaluaciones | `evaluacionesMock.ts` | EvaluacionesDirector, Evaluador |
| Coordinador ops | `coordinadorMock.ts` | Alertas, AuditLog, entregas admin |

### Integration pattern

```typescript
if (FRONTEND_VALIDATION_MODE) {
  await mockDelay();
  setData(getMockX());
  return;
}
// existing apiFetch path unchanged
```

### Exit criteria (desactivar FVM)

1. Sprint 5 integration change activo por módulo.
2. `FRONTEND_VALIDATION_MODE = false`.
3. Verificar pantallas con API real; eliminar ramas mock cuando el servicio real esté estable.

### Impact analysis (pre-implementación)

| Área | Dependencia backend previa | Resolución FVM |
|------|---------------------------|----------------|
| Estudiante dashboard/bitácora | `/api/estudiante/*`, `/api/bitacoras` | `estudianteMock` + IDs alineados con `bitacorasMock` |
| Coordinador admin | 15+ hooks con `apiFetch` | Ramas mock en hooks |
| Director supervisión/evaluaciones | API proyecto + evaluaciones | `proyectosMock` + `evaluacionesMock` |
| Shared anuncios/recursos | GET público | `anunciosMock`, `recursosMock` |
| Auth externo | POST login | Mock login en `LoginExterno` + `sessionStorage` |
| Auth session | GET `/api/auth/user` | `useAuth` confía en `sessionStorage` en FVM |

---

## Navigation Architecture

### Componentes del sistema

| Componente | Responsabilidad | Archivo |
|-----------|-------------------|---------|
| **Sidebar** | Navegación principal por rol; grupos; ítem activo | `components/layout/Sidebar.tsx` |
| **Header** | Título de sección actual; menú móvil; usuario/sesión | `components/layout/Header.tsx` |
| **AppShell** | Resuelve título vía `ROUTE_TITLES`; envuelve main | `components/layout/AppShell.tsx` |
| **PageHeader** | Contexto de pantalla (eyebrow, título, subtítulo, acciones) | `components/ui/PageHeader.tsx` |
| **Landing** | Sin Sidebar; acceso pre-login | `pages/landing/LandingPage.tsx` |

### Grupos de navegación (orden global)

Toda role nav sigue esta secuencia. Los grupos vacíos se omiten.

| # | Grupo | Propósito | Jerarquía |
|---|-------|-----------|-----------|
| 1 | **Inicio** | Punto de entrada del rol (dashboard) | Primaria — siempre primera |
| 2 | **Operaciones** | Flujo principal de trabajo del rol | Primaria |
| 3 | **Comunicación** | Anuncios institucionales | Primaria compartida |
| 4 | **Recursos** | Biblioteca documental | Primaria compartida |
| 5 | **Herramientas** | IA, análisis, asistencia (solo roles que aplican) | Secundaria |
| 6 | **Administración** | Gestión, auditoría, reportes (solo Coordinador) | Secundaria — al final, separada visualmente del grupo anterior |

**Regla de separación:** entre grupo 5 y 6 (cuando exista Administración) debe haber un separador estructural en el DOM (ej. `<li aria-hidden>` o heading de grupo) — sin prescribir estilos.

### Matriz de ítems por rol

Labels unificados para ítems compartidos. Orden dentro de cada grupo según tabla.

#### Estudiante

| Grupo | Label | Ruta | Notas |
|-------|-------|------|-------|
| Inicio | Mi Proyecto | `/dashboard/estudiante` | Dashboard = inicio |
| Operaciones | Bitácora | `/bitacora` | Incluye `/bitacora/nueva` como hijo |
| Comunicación | Anuncios | `/anuncios` | Mismo label en todos los roles |
| Recursos | Recursos | `/recursos` | Mismo label en todos los roles |
| Herramientas | Análisis de Entregas | `/analisis-entregas` | |
| Herramientas | Asistente | `/asistente` | |

#### Director

| Grupo | Label | Ruta | Notas |
|-------|-------|------|-------|
| Inicio | Panel | `/dashboard/director` | |
| Operaciones | Supervisión | `/supervision` | Activo también en `/supervision/:id`, `/bitacoras/*` hijos de supervisión |
| Operaciones | Evaluaciones | `/evaluaciones` | Activo en `/evaluaciones/:id` |
| Comunicación | Anuncios | `/anuncios` | |
| Recursos | Recursos | `/recursos` | |

> Rutas operativas de bitácoras (`/bitacoras`, `/bitacoras/proyectos`, firmar, revisar) **no** son ítems de sidebar; se alcanzan desde Supervisión. El ítem Supervisión permanece activo en esas rutas.

#### Coordinador

| Grupo | Label | Ruta | Notas |
|-------|-------|------|-------|
| Inicio | Panel de Control | `/dashboard/coordinador` | |
| Operaciones | Proyectos | `/proyectos` | |
| Operaciones | Directores | `/directores` | |
| Operaciones | Evaluadores | `/evaluadores` | |
| Operaciones | Entregas | `/coordinador/entregas` | |
| Operaciones | Bitácoras | `/coordinador/bitacoras` | **Faltante en nav actual** |
| Comunicación | Anuncios | `/anuncios` | Vista pública |
| Recursos | Recursos | `/recursos` | Vista pública |
| Administración | Anuncios Admin | `/anuncios/admin` | |
| Administración | Alertas | `/alertas` | |
| Administración | Reportes | `/reportes` | **Faltante en nav actual** |
| Administración | Recursos Admin | `/recursos/admin` | |
| Administración | Usuarios | `/coordinador/usuarios` | |
| Administración | Auditoría | `/coordinador/audit-log` | **Faltante en nav actual** |

> "Anuncios" (lectura) y "Anuncios Admin" (gestión) son ítems distintos en grupos distintos.

#### Evaluador Externo

| Grupo | Label | Ruta | Notas |
|-------|-------|------|-------|
| Inicio | Panel | `/dashboard/evaluador-externo` | **Corregir:** nav actual apunta a `/` |
| Operaciones | Evaluaciones | `/evaluaciones` | Activo en `/evaluaciones/:id/calificar` |
| Comunicación | Anuncios | `/anuncios` | |
| Recursos | Recursos | `/recursos` | |

### Reglas de ítem activo

1. **Coincidencia exacta** para rutas de listado/panel cuando `end={true}` aplica.
2. **Coincidencia por prefijo** para rutas hijas: el ítem padre se marca activo si `pathname.startsWith(parentRoute + '/')` o coincide con rutas hijas declaradas en `activePaths?: string[]`.
3. **Un solo ítem activo** por nivel principal; no activar Anuncios y Anuncios Admin simultáneamente.
4. **Vistas de detalle** (`/:id`, `/firmar`, `/revisar`): activan el ítem padre, no añaden entradas al sidebar.
5. **Excepción formalizada:** Supervisión activa en `/bitacoras/*` y `/supervision/*` (reemplaza hack ad-hoc actual).

### Reglas responsive

| Breakpoint | Comportamiento |
|------------|----------------|
| `< lg` | Sidebar oculta; Header muestra botón menú; overlay al abrir |
| `≥ lg` | Sidebar fija; contenido con offset |
| Al navegar (móvil) | Cerrar drawer (`onClose`) tras click en NavLink — ya implementado |
| Scroll | Sidebar y main scroll independientes |

### Reglas Header ↔ PageHeader

| Capa | Contenido | Ejemplo |
|------|-----------|---------|
| Header (h1) | Nombre de sección desde `ROUTE_TITLES` | "Bitácora", "Proyectos" |
| PageHeader (h2) | Título específico de la vista | "Nueva Bitácora", "PG-2026-014" |
| Evitar | Repetir el mismo texto en h1 y h2 | Si PageHeader title = sección, omitir subtítulo redundante |

### Rutas hijas sin sidebar (navegación contextual)

Acceso vía acciones in-page o botón Volver en `PageHeader.actions`:

| Vista hija | Padre sidebar | Patrón retorno |
|-----------|---------------|----------------|
| `/anuncios/:id` | Anuncios | "Volver a Anuncios" |
| `/recursos/:id` | Recursos | "Volver a Recursos" |
| `/bitacora/nueva` | Bitácora | "Volver a Bitácora" |
| `/bitacoras/:id/firmar` | Supervisión | "Volver a Bitácoras" o supervisión |
| `/entregas/:id/revisar` | Supervisión | "Volver a Supervisión" |
| `/evaluaciones/:id/calificar` | Evaluaciones | "Volver a Evaluaciones" |

---

## UX Architecture (reusable principles)

Principios que **toda pantalla modificada** debe cumplir. Detalle normativo en `spec.md` → UX Principles.

| ID | Principio | Implementación esperada |
|----|-----------|------------------------|
| UX-01 | Acción principal única | Un botón primary por vista/flujo; resto outline o ghost |
| UX-02 | Un título principal | Header O PageHeader dominante; no duplicar |
| UX-03 | Flujo de lectura | Orden: contexto → datos → acciones; F-pattern en formularios |
| UX-04 | Agrupación lógica | Cards/secciones por tema; espaciado consistente (`gap-6`) |
| UX-05 | Menos pasos | Acciones frecuentes en listado; detalle solo cuando aporta |
| UX-06 | Retorno contextual | Volver siempre al listado/padre, no a dashboard genérico |
| UX-07 | Estados completos | loading, empty, error, data en toda pantalla de listado/detalle |
| UX-08 | Confirmación visual | Toast/banner tras guardar/firmar/eliminar (simulado) |
| UX-09 | Consistencia de acciones | Mismos verbos: Guardar, Enviar, Firmar, Cancelar, Volver |
| UX-10 | Tablas y formularios | Reutilizar DataTable, PageHeader, ConfirmDialog, patrones GestionUsuarios |

---

## Shared Components

### StatusBadge
- **File:** `components/ui/StatusBadge.tsx`
- **Props:** `variant: 'success' | 'warning' | 'error' | 'info' | 'en-curso' | 'inactivo' | 'riesgo'`, `children: ReactNode`
- **Used by:** Dashboards, Anuncios, Entregas, Bitácoras, Alertas, Evaluaciones

### StatCard
- **File:** `components/ui/StatCard.tsx`
- **Props:** `title`, `value`, `icon: LucideIcon`, `trend?`, `variant?`
- **Used by:** Dashboards, Alertas, Reportes, BitacorasDirector

### PageHeader
- **File:** `components/ui/PageHeader.tsx`
- **Props:** `eyebrow`, `title`, `subtitle?`, `actions?`
- **Used by:** Todas las páginas del port

### DataTable
- **File:** `components/ui/DataTable.tsx`
- **Props:** `columns`, `data`, `loading?`, `emptyMessage?`, `pagination?`
- **States:** loading → Loader2; empty → EmptyState; data → tabla con estilos canon

### EmptyState
- **File:** `components/ui/EmptyState.tsx`
- **Props:** `icon`, `title`, `description?`, `action?`

### ConfirmDialog
- **File:** `components/ui/ConfirmDialog.tsx`
- **Props:** `open`, `title`, `message`, `onConfirm`, `onCancel`, `variant?`
- **Behavior:** `onConfirm` actualiza estado local (ej. elimina fila del mock array)

### TOTPInput
- **File:** `components/ui/TOTPInput.tsx`
- **Props:** `onComplete`, `disabled?`, `error?`
- **Behavior:** 6 inputs numéricos; `onComplete` dispara flujo visual simulado en el padre

---

## UI Domain Types (mock fixtures)

Tipos TypeScript para estructurar fixtures locales. **No son contratos de API.**

```typescript
interface Project {
  id: number; code: string; title: string; studentNames: string[];
  directorName: string; phase: 'Anteproyecto' | 'Presentacion' | 'Desarrollo' | 'Final';
  status: 'active' | 'at-risk' | 'completed'; alertCount: number;
}

interface Announcement {
  id: number; title: string; category: 'importante' | 'recordatorio' | 'informativo';
  date: string; author: string; excerpt: string; body: string;
  attachments?: { name: string; size: string }[];
}

interface Resource {
  id: number; title: string; type: 'reglamento' | 'guia' | 'plantilla' | 'tutorial';
  description: string; author: string; size: string; downloads: number; url: string;
}

interface Delivery {
  id: number; version: number; type: string; date: string;
  status: 'approved' | 'pending' | 'rejected' | 'locked';
  fileName: string; observations?: string;
  scores?: { criteria: string; score: number; weight: number }[];
}

/** Entregas mock — observación 1:1 por versión (ver spec ENT-TYPES) */
type VersionReviewStatus = 'sin_revisar' | 'aprobada' | 'necesita_ajustes';

interface DeliveryVersionObservation {
  text: string | null;
  reviewedAt: string | null;
  reviewStatus: VersionReviewStatus;
}

interface DeliveryVersionMock {
  id: number;
  versionNumber: number;
  fileName: string;
  uploadedAt: string;
  observation: DeliveryVersionObservation;
}

interface EntregaMock {
  id: number;
  title: string;
  phase: string;
  status: string;
  description: string | null;
  dueDate: string | null;
  project: { id: number; code: string; title: string };
  versiones: DeliveryVersionMock[];
  /** Preasignado en mock — cumplimiento de plazo (ENT-TL-TYPES). No calcular en UI. */
  timelineStatus: DeliveryTimelineStatus;
}

type DeliveryTimelineStatus = 'not_delivered' | 'on_time' | 'late' | 'overdue';

interface Binnacle {
  id: number; date: string; topic: string; description: string; duration: string;
  signatureStatus: 'signed' | 'pending' | 'unsigned'; projectName: string;
}
```

---

## Routing Plan

- `React.lazy()` + `<Suspense>` para páginas del port
- Landing en `/` fuera de ProtectedRoute
- Rutas agrupadas por rol con `ProtectedRoute allowedRoles={[...]}`
- `Sidebar.tsx`: entradas por rol alineadas a wireframes
- `AppShell.tsx`: `ROUTE_TITLES` para títulos de header

---

## Testing Strategy (frontend only)

| Layer | What | Approach |
|-------|------|----------|
| Visual | Páginas renderizan sin crash | Revisión manual por pantalla |
| Component | StatusBadge, TOTPInput, DataTable | Verificar variantes, paste TOTP, estados loading/empty |
| Routing | Navegación + gating por rol | Login por rol, verificar sidebar y rutas accesibles |
| Mock data | Datos visibles en estado `data` | Cada pantalla muestra fixtures poblados |
| Build | Compilación Vite | `npm run build` sin errores |

No tests de integración con backend ni contratos de API en este change.

---

## Threat Matrix

N/A — no routing de procesos, subprocess, VCS automation ni boundaries de integración de servidor.

---

## Migration / Rollout

Cambios aditivos o upgrades in-place de placeholders. Rollback: `git revert` por PR.

---

## Open Questions

- [x] ¿`DataTable` con paginación controlada o interna? **Decidido:** controlada por el padre.
- [ ] ¿Separador de grupos en Sidebar como `<p className="sr-only">` + visual, o solo spacing? **Recomendación:** heading sr-only + margin-top en primer ítem del grupo.
- [ ] ¿Coordinador mantiene sidebar oscuro vs claro en otros roles? **Fuera de alcance UX/navegación** — no cambiar en esta fase salvo unificar comportamiento de activo.

---

## Impact Analysis — UX & Navigation Phase

Documentación del impacto esperado. **Sin implementación en esta fase.**

### Inconsistencias detectadas (baseline actual)

| # | Área | Inconsistencia | Resolución spec |
|---|------|----------------|-----------------|
| I1 | Sidebar Evaluador | Panel apunta a `/` en lugar de `/dashboard/evaluador-externo` | Matriz Evaluador |
| I2 | Sidebar Coordinador | Faltan `/coordinador/bitacoras`, `/reportes`, `/coordinador/audit-log` | Matriz Coordinador |
| I3 | Labels | "Panel" vs "Panel de Control" vs "Mi Proyecto" sin regla | Grupo Inicio: label por rol documentado |
| I4 | Header + PageHeader | h1 (shell) + h2 (página) a veces redundantes | UX-02 |
| I5 | Retorno | AnuncioDetalle usa link suelto; otras usan PageHeader.actions | Patrón único PageHeader |
| I6 | Director bitácoras | Rutas `/bitacoras/*` no en sidebar; hack `isSupervisionActive` | Reglas ítem activo #5 |
| I7 | Anuncios coord | "Anuncios" y "Anuncios Admin" adyacentes sin separación de grupo | Grupos Comunicación vs Administración |
| I8 | Iconos repetidos | Mismo icon para Proyectos/Entregas/Recursos | Fuera de alcance (no prescribir iconos); agrupación reduce ambigüedad |

### Layouts afectados

| Layout | Cambio esperado |
|--------|-----------------|
| `AppShell` | `ROUTE_TITLES` ampliado/sincronizado; posible ajuste resolución título |
| `Sidebar` | `navConfig` reestructurado por grupos; separadores; reglas activo |
| `Header` | Título coherente; sin cambios estructurales |
| `PageHeader` | Extender uso de `actions` para Volver + CTA primario |

### Pantallas por navbar actual

| Rol | Ítems sidebar actuales | Rutas en app.tsx no en sidebar |
|-----|------------------------|--------------------------------|
| Coordinador | 11 ítems planos | `/coordinador/bitacoras`, `/reportes`, `/coordinador/audit-log`, rutas `/directores/*` hijas |
| Director | 5 ítems | `/bitacoras`, `/bitacoras/proyectos`, rutas hijas supervisión |
| Estudiante | 6 ítems | `/bitacora/nueva`, entregas, revisiones |
| EvaluadorExterno | 4 ítems (panel roto) | `/evaluaciones/:id/calificar` |

### Componentes reutilizables (sin romper arquitectura)

| Componente | Reutilizar | Extensión permitida |
|-----------|------------|---------------------|
| `Sidebar` | Sí | Añadir `NavGroup` type; no nuevo layout shell |
| `PageHeader` | Sí | Slot `actions` para Volver + primary |
| `EmptyState` | Sí | Usar en todos los listados vacíos |
| `ConfirmDialog` | Sí | Acciones destructivas |
| `DataTable` | Sí | Listados tabulares |
| Nuevo `NavGroup` (opcional) | — | Wrapper semántico `<ul>` por grupo |

### Mejoras propagables a otros módulos

Tras aplicar esta spec, los changes de bitácoras, entregas, proyectos y observaciones heredan:

- Misma estructura nav por rol
- Patrón Volver en PageHeader
- UX Principles UX-01…UX-10
- Estados loading/empty/error uniformes
- Matriz padre-hijo para rutas de detalle

### Riesgos de impacto cruzado

| Riesgo | Mitigación |
|--------|------------|
| Changes de integración modifican las mismas páginas | Trabajo bajo este change limitado a layout/mock/UX; no revertir hooks de API en tareas de integración |
| Sidebar desincronizada vs rutas en `app.tsx` | Matriz nav ↔ rutas; tarea NAV-10 checklist |
| Regresión visual en auth/admin | No modificar login; GestionUsuarios/AuditLog solo si alinean PageHeader |
| Evaluador panel `/` | Corregir en NAV-12 antes de otras tareas Evaluador |

---

## Impact Analysis — Bitácoras Redesign

Ver spec.md Módulo Bitácoras (BIT-*). Resumen:

- **Eliminar:** tabla de firmas, listados planos cross-proyecto, `DetalleFirmaBitacora` duplicado, `apiFetch` en pantallas del módulo
- **Nuevo flujo:** hub proyectos → reuniones por proyecto → detalle/revisión
- **Componentes nuevos:** `bitacorasMock`, `BitacoraProjectGrid`, `BitacoraMeetingCard`, `DirectorSignaturePanel`, `BitacoraSignFlow`
- **Estados firma:** pendiente | firmado | rechazado + comentario rechazo visible
- **Redirects:** `/bitacoras` → `/bitacoras/proyectos`; `/bitacoras/:id/firmar` → revision; rutas `/directores/*` bitácoras → coordinador

---

## Impact Analysis — Entregas: observaciones por versión

Ver spec.md Módulo Entregas (ENT-*). Resumen de impacto **antes de implementar**.

### Problema detectado (baseline actual)

| # | Área | Problema | Resolución spec |
|---|------|----------|-----------------|
| E1 | `RevisionEntregaDirector` | Panel "Observaciones" global desacoplado del selector de versión; guardar no asocia nota a versión activa | ENT-DIRECTOR: formulario scoped + recarga al cambiar versión |
| E2 | `DetalleEntregaDirector` | Formulario revisión a nivel entrega; versiones solo listan comentarios | Deprecar o alinear a ENT-DIRECTOR (ruta principal `/entregas/:id/revisar`) |
| E3 | `DetalleEntregaEstudiante` | Muestra `director_notes` por versión en lectura, pero usa `apiFetch` (fuera de alcance wireframes) | ENT-STUDENT: mock + componentes compartidos |
| E4 | `DetalleEntregaCoordinador` | Lectura parcial por versión; integrado API | ENT-COORDINADOR: mock + `VersionObservationPanel` |
| E5 | `DeliveryAccordion` | Tabla versiones sin columna observación / estado revisión | ENT-ACCORDION |
| E6 | `types/estudiante.ts` | `VersionData` sin campos observación | Extender con `hasObservation`, `reviewStatus` |
| E7 | Duplicación | Lógica selector versión repetida en 3+ páginas | Extraer `DeliveryVersionSelector`, `DeliveryVersionHistory` |

### Pantallas afectadas

| Pantalla | Rol | Cambio |
|----------|-----|--------|
| `RevisionEntregaDirector.tsx` | Director | Refactor mock; revisión por versión |
| `DetalleEntregaEstudiante.tsx` | Estudiante | Refactor mock; historial + observación por versión |
| `DetalleEntregaCoordinador.tsx` | Coordinador | Refactor mock; solo lectura por versión |
| `DeliveryAccordion.tsx` | Estudiante (dashboard) | Indicador observación por versión |
| `EstudianteDashboard.tsx` | Estudiante | Mapear datos mock con observaciones (si usa accordion mock) |

### Componentes nuevos

| Componente | Archivo | Responsabilidad |
|-----------|---------|-----------------|
| `DeliveryVersionSelector` | `components/entregas/DeliveryVersionSelector.tsx` | Pills vN + indicador revisada/pendiente |
| `DeliveryVersionHistory` | `components/entregas/DeliveryVersionHistory.tsx` | Lista cronológica clickeable |
| `VersionObservationPanel` | `components/entregas/VersionObservationPanel.tsx` | Lectura observación versión activa |
| `DirectorVersionReviewPanel` | `components/entregas/DirectorVersionReviewPanel.tsx` | Formulario revisión scoped a versión |
| `entregasMock` | `mocks/entregasMock.ts` | Fixtures + helpers lookup/update |

### Componentes reutilizables (sin romper)

| Componente | Uso |
|-----------|-----|
| `PageHeader` | Retorno contextual ENT-UX04 |
| `StatusBadge` | Estados revisión por versión |
| `EmptyState` | Sin versiones / entrega no encontrada |

### Navegación

Sin nuevas rutas. Rutas existentes mantienen paths; comportamiento interno cambia a mock.

### Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Conflicto con `director-integracion` / API existente | Este change usa mock exclusivo; integración futura reemplaza fixtures |
| Regresión UX al quitar upload real en estudiante | Upload simulado con banner/toast (ENT-ST04) |
| Formulario director mezcla versiones | ENT-S04 + recarga estado al cambiar versión activa |

---

## Impact Analysis — Entregas: estados de plazo (Director)

Ver spec.md ENT-TL-*. Resumen **antes de implementar**.

### Problema detectado (baseline actual)

| # | Área | Problema | Resolución spec |
|---|------|----------|-----------------|
| T1 | `SupervisionProyectoDirector` | Badges genéricos (`pending`/`approved`/`corrections`); no comunican plazo | ENT-TL-BADGE + ENT-TL-SUPERVISION |
| T2 | `DirectorDashboard` | Tabla entregas: badge fijo "Pendiente" | ENT-TL-DIRECTOR-LIST |
| T3 | Modelo mock | `EntregaMock` sin `timelineStatus` | ENT-TL-TYPES |
| T4 | Semántica | Confundir estado revisión vs plazo | Dos badges independientes (ENT-TL distinción) |

### Pantallas afectadas

| Pantalla | Cambio |
|----------|--------|
| `SupervisionProyectoDirector.tsx` | Lista entregas mock + `DeliveryTimelineStatusBadge` + orden ENT-TL07 |
| `DirectorDashboard.tsx` | Tabla Últimas Entregas mock + badge por fila |

### Componentes nuevos

| Componente | Archivo |
|-----------|---------|
| `DeliveryTimelineStatusBadge` | `components/entregas/DeliveryTimelineStatusBadge.tsx` |
| `DeliveryTimelineStatusLegend` | `components/entregas/DeliveryTimelineStatusLegend.tsx` |
| `DeliverySupervisionRow` | `components/entregas/DeliverySupervisionRow.tsx` (fila expandible reutilizable) |

### Componentes reutilizados

| Componente | Uso |
|-----------|-----|
| `StatusBadge` | Variantes `inactivo`, `success`, `warning`, `error` |
| `DataTable` | Dashboard entregas |
| `PhaseStepper` | Sin cambios estructurales |

### Fuera de alcance

- Calcular `timelineStatus` desde `dueDate` / `uploadedAt`
- Modificar backend o hooks de integración como fuente de verdad
- Aplicar estados de plazo en flujo Estudiante (solo Director en esta fase)

---
