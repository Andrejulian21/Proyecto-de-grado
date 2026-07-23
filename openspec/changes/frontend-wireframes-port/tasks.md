# Tasks: Frontend Wireframes Port

## Change Scope

**Exclusivamente frontend.** Port de wireframes, componentes UI, navegación, estados visuales simulados, mock data y **sistema UX/navegación unificado**. Sin backend, APIs, persistencia ni integración.

**Baseline:** port de ~29 wireframes completado (verificado 2026-07-10). Tareas `[x]` = baseline. Tareas `[ ]` = refinamiento pendiente.

**Fase activa:** **Frontend Validation Mode (FVM)** + UX/navegación — ver `spec.md` (FVM-*, Navigation System, UX Principles).

**Fuera de alcance:** persistencia real, migraciones, validación servidor → changes `coordinador-integracion`, `director-integracion`, etc.

---

## Frontend Validation Mode (FVM)

Referencia: `spec.md` FVM-*, `design.md` Frontend Validation Mode.

### Infraestructura

- [x] **FVM-01** Crear `mocks/validationMode.ts` — `FRONTEND_VALIDATION_MODE`, `mockDelay()`
- [x] **FVM-02** Crear `mocks/index.ts` — re-exports
- [x] **FVM-03** Crear fixtures por módulo: `proyectosMock`, `estudianteMock`, `anunciosMock`, `recursosMock`, `usuariosMock`, `evaluacionesMock`, `coordinadorMock`
- [x] **FVM-04** Extender `bitacorasMock` + `entregasMock` — IDs alineados proyecto 1

### Hooks (rama mock)

- [x] **FVM-10** useKpis, useProyectos, useEntregas, useAlertas, useGrupos, useCupos
- [x] **FVM-11** useDirectores, useRecursos, useEvaluaciones, useEvaluadorProyecto, useStudentSearch
- [x] **FVM-12** useDirectorProyectos, useDirectorKpis

### Páginas (rama mock directa)

- [x] **FVM-20** EstudianteDashboard, BitacorasEstudiante, NuevaBitacora
- [x] **FVM-21** AnunciosPublica, AnuncioDetalle, Recursos, RecursoDetalle
- [x] **FVM-22** SupervisionProyectoDirector (detalle), SupervisionReadOnly
- [x] **FVM-23** EvaluacionesDirector, AnunciosAdmin, GestionUsuarios, AuditLog
- [x] **FVM-24** LoginExterno + useAuth (sessionStorage en FVM)

### Verificación FVM

- [x] **FVM-30** `npm run build` sin errores
- [ ] **FVM-31** Recorrido manual por rol: Estudiante → Director → Coordinador → Evaluador
- [ ] **FVM-32** Checklist escenarios spec FVM (entregas, bitácoras, alertas, empty states)
- [ ] **FVM-33** Documentar credenciales mock en README o seeder output

---

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Baseline status | ✅ Completado |
| Fase actual | UX + Navegación |
| Entregables spec | Navigation System (NS-*), UX Principles (UX-*) |
| Delivery strategy | PRs por rol o categoría (NAV → UX → CONS → RESP → A11Y) |

---

## Bitácoras — Rediseño por proyecto

Referencia: `spec.md` Módulo Bitácoras (BIT-*). **Mock exclusivo; sin apiFetch.**

### Fixtures y componentes base

- [x] **BIT-01** Crear `mocks/bitacorasMock.ts` — proyectos + reuniones con estados pendiente/firmado/rechazado
- [x] **BIT-02** Crear `BitacoraProjectGrid` — hub compartido con props `projects`, `onSelect`, `role`
- [x] **BIT-03** Crear `BitacoraMeetingCard` — campos BIT-FIELDS + acciones por rol
- [x] **BIT-04** Crear `BitacoraMeetingList` — filtro estado + empty state
- [x] **BIT-05** Crear `DirectorSignaturePanel` — nombre, estado, fecha, hora (sin tabla)
- [x] **BIT-06** Crear `BitacoraSignFlow` — solicitar → código mock → TOTP → firmar/rechazar

### Director

- [x] **BIT-07** Refactor `SeleccionProyectosBitacoras` — hub mock solo proyectos dirigidos
- [x] **BIT-08** `BitacorasDirector` — redirect a `/bitacoras/proyectos`
- [x] **BIT-09** Refactor `BitacorasProyecto` — meeting list mock; ruta `/bitacoras/proyectos/:proyectoId`
- [x] **BIT-10** Refactor `RevisionBitacora` director — mock local; integrar SignFlow + SignaturePanel
- [x] **BIT-11** Deprecar `DetalleFirmaBitacora` — redirect a `/bitacoras/:id/revision`

### Coordinador

- [x] **BIT-12** Refactor `CoordinadorBitacoras` — hub todos los proyectos
- [x] **BIT-13** Refactor listado proyecto — ruta `/coordinador/bitacoras/proyectos/:proyectoId`
- [x] **BIT-14** Refactor `RevisionBitacoraCoordinador` — lectura mock + comentario rechazo visible

### Shared / navegación

- [x] **BIT-15** Refactor `RevisionBitacoraView` — eliminar tabla firmas; usar nuevos componentes
- [x] **BIT-16** `app.tsx` — rutas + redirects BIT-NAV
- [x] **BIT-17** `AppShell.tsx` — ROUTE_TITLES bitácoras
- [ ] **BIT-18** Verificación manual: Director y Coordinador flujo completo mock
- [ ] **BIT-19** `npm run build` sin errores (pendiente entorno PostCSS local)

---

## Entregas — Observaciones por versión

Referencia: `spec.md` Módulo Entregas (ENT-*). **Mock exclusivo; sin apiFetch.**

### Fixtures y componentes base

- [x] **ENT-01** Crear `mocks/entregasMock.ts` — entregas con versiones y observación 1:1 (ENT-TYPES)
- [x] **ENT-02** Crear `types/entregas.ts` — tipos compartidos `DeliveryVersionMock`, `VersionReviewStatus`
- [x] **ENT-03** Crear `DeliveryVersionSelector` — pills vN + indicador revisada/pendiente (ENT-SELECTOR)
- [x] **ENT-04** Crear `DeliveryVersionHistory` — lista cronológica clickeable (ENT-HISTORY)
- [x] **ENT-05** Crear `VersionObservationPanel` — lectura observación versión activa (ENT-OBS-PANEL)
- [x] **ENT-06** Crear `DirectorVersionReviewPanel` — formulario revisión scoped a versión (ENT-DIRECTOR)

### Director

- [x] **ENT-07** Refactor `RevisionEntregaDirector` — mock local; revisión por versión activa; recarga form al cambiar versión
- [x] **ENT-08** Guardar revisión → actualizar `observation` de versión activa en estado local + banner éxito

### Estudiante

- [x] **ENT-09** Refactor `DetalleEntregaEstudiante` — mock; historial + observación por versión; upload simulado
- [x] **ENT-10** Extender `types/estudiante.ts` + `DeliveryAccordion` — indicador observación por versión (ENT-ACCORDION)

### Coordinador

- [x] **ENT-11** Refactor `DetalleEntregaCoordinador` — mock lectura; mismos componentes historial/observación

### Verificación

- [ ] **ENT-12** Verificación manual: cada versión muestra solo su observación (Director + Estudiante)
- [ ] **ENT-13** Verificación manual: selector e historial sincronizados; versiones pendientes identificables
- [ ] **ENT-14** `npm run build` sin errores

---

## Entregas — Estados de plazo (Director)

Referencia: `spec.md` ENT-TL-*. **Mock exclusivo; sin cálculo de plazo; sin apiFetch en listados.**

### Tipos y componentes

- [x] **ENT-TL-01** Extender `types/entregas.ts` — `DeliveryTimelineStatus` + mapas label/variant/icon (ENT-TL-TYPES)
- [x] **ENT-TL-02** Extender `entregasMock.ts` — campo `timelineStatus` en cada entrega; helpers `getEntregasByProjectId`, `getDirectorDashboardEntregas`
- [x] **ENT-TL-03** Crear `DeliveryTimelineStatusBadge` — StatusBadge + icono (ENT-TL-BADGE)
- [x] **ENT-TL-04** Crear `DeliveryTimelineStatusLegend` — leyenda compacta 4 estados
- [x] **ENT-TL-05** Crear `DeliverySupervisionRow` — fila expandible con badge + acento overdue

### Pantallas Director

- [x] **ENT-TL-06** Refactor `SupervisionProyectoDirector` — listado entregas mock; badge plazo; orden crítico primero
- [x] **ENT-TL-07** Refactor `DirectorDashboard` — tabla Últimas Entregas mock con `DeliveryTimelineStatusBadge`

### Verificación

- [ ] **ENT-TL-08** Manual: los 4 estados visibles con color/label/icono correctos
- [ ] **ENT-TL-09** Manual: entregas overdue destacadas sin abrir detalle
- [ ] **ENT-TL-10** `npm run build` sin errores

---

Referencia: `spec.md` Navigation System · `design.md` Navigation Architecture

- [ ] **NAV-10** Definir tipo `NavGroup` / estructura agrupada en `Sidebar.tsx` (grupos Inicio → Operaciones → Comunicación → Recursos → Herramientas → Administración)
- [ ] **NAV-11** Implementar separador estructural entre grupo Operaciones/Recursos y Administración (Coordinador)
- [ ] **NAV-12** Corregir EvaluadorExterno: Inicio → `/dashboard/evaluador-externo` (eliminar link a `/`)
- [ ] **NAV-13** Coordinador: añadir Bitácoras (`/coordinador/bitacoras`), Reportes (`/reportes`), Auditoría (`/coordinador/audit-log`)
- [ ] **NAV-14** Reordenar ítems Coordinador según matriz design.md (Operaciones antes de Comunicación; Admin al final)
- [ ] **NAV-15** Unificar labels cross-role: "Anuncios", "Recursos" (NS-01, NS-02)
- [ ] **NAV-16** Formalizar reglas de ítem activo: `activePaths`, prefijo hijo, Supervisión ↔ `/bitacoras/*` (NS-06…NS-10)
- [ ] **NAV-17** Sincronizar `ROUTE_TITLES` con todos los ítems sidebar + rutas hijas frecuentes (NS-11, NS-12)
- [ ] **NAV-18** Checklist nav ↔ rutas: verificar cada fila de matriz design.md contra `app.tsx` (0 rutas huérfanas)
- [ ] **NAV-19** Responsive: verificar cierre drawer al navegar, overlay, focus (NS-17…NS-19)
- [ ] **NAV-20** Suspense fallback en `app.tsx` — colores canon `#c2410c`

### Tareas baseline navegación (completadas)

- [x] **NAV-01** Rutas lazy + Suspense en `app.tsx`
- [x] **NAV-02** Landing `/` fuera de ProtectedRoute
- [x] **NAV-03…NAV-07** navConfig inicial por rol + ROUTE_TITLES base

---

## Navegación — Estandarización navbar / retorno contextual

- [ ] **NAV-21** Crear patrón `BackAction` reutilizable en `PageHeader.actions` ("Volver a {sección}")
- [ ] **NAV-22** AnuncioDetalle — migrar link suelto a PageHeader.actions (NS-14, NS-15)
- [ ] **NAV-23** RecursoDetalle — breadcrumb + Volver consistente
- [ ] **NAV-24** Vistas detalle Estudiante (NuevaBitacora, DetalleEntrega, RevisionBitacora) — retorno a Bitácora/Mi Proyecto
- [ ] **NAV-25** Vistas detalle Director (firmar, revisar entrega, revision bitácora) — retorno a Supervisión/Bitácoras
- [ ] **NAV-26** Vistas detalle Evaluador (calificar) — retorno a Evaluaciones
- [ ] **NAV-27** Vistas detalle Coordinador (entrega, bitácora director) — retorno al listado padre
- [ ] **NAV-28** Header h1 vs PageHeader h2 — eliminar redundancia por pantalla (UX-02)

---

## UX — Principios generales

Referencia: `spec.md` UX Principles · `design.md` UX Architecture

- [ ] **UX-08** AsistenteOrientacion — send simulado: append user + respuesta canned local
- [ ] **UX-09** Toasts/banners de éxito unificados (guardar, firmar, exportar, eliminar)
- [ ] **UX-10** Transiciones accordion/expand suaves en entregas y alertas
- [ ] **UX-11** Acción principal única por vista — auditoría y corrección en forms/detalle (UX-01)
- [ ] **UX-12** Verbos consistentes en botones (UX-09): Guardar, Enviar, Firmar, Cancelar, Volver
- [ ] **UX-13** Empty states con acción sugerida donde aplique (UX-07a)
- [ ] **UX-14** Submitting state en todos los forms (UX-08b, FORM-09)
- [ ] **UX-15** ConfirmDialog en todas las acciones destructivas pendientes

### Tareas baseline UX (completadas)

- [x] **UX-01…UX-07** Landing, split-screens, TOTP visual, IA mock baseline

---

## Consistencia visual entre pantallas

- [ ] **CONS-01** Auditoría PageHeader: eyebrow + title + subtitle en todas las páginas de listado
- [ ] **CONS-02** Unificar posición de CTA primario (PageHeader.actions, esquina superior derecha)
- [ ] **CONS-03** Migrar tablas inline a `DataTable` (`AsignacionEvaluadores`, otros) — UX-10
- [ ] **CONS-04** Extraer `PhaseStepper` — unificar EstudianteDashboard + SupervisionProyectoDirector
- [ ] **CONS-05** Extraer `DocumentViewer` mock — split-screens entregas/evaluaciones/IA
- [ ] **CONS-06** Paginación visual consistente en listados (LIST-10)
- [ ] **CONS-07** Remover imports no usados (Recursos `FolderKanban`, etc.)
- [ ] **CONS-08** `MockBarChart` CSS/SVG en ReportesConsolidados

---

## Layouts reutilizables

- [ ] **LAY-01** Extender contrato `PageHeaderProps` si hace falta slot `backAction` opcional
- [ ] **LAY-02** Documentar en código (JSDoc) patrón Header + PageHeader por tipo de vista
- [ ] **LAY-03** AppShell: revisar padding main (`p-4 lg:p-8`) consistente en todas las páginas
- [ ] **LAY-04** Evaluar componente `NavGroup` wrapper semántico (opcional, design.md)

---

## Validación consistencia entre roles

- [ ] **ROLE-01** Login Estudiante → recorrer sidebar matriz → verificar orden, labels, activo
- [ ] **ROLE-02** Login Director → Supervisión → bitácoras hijas → ítem activo correcto
- [ ] **ROLE-03** Login Coordinador → 14 ítems matriz → Admin separado al final
- [ ] **ROLE-04** Login EvaluadorExterno → panel correcto → evaluaciones → calificar → retorno
- [ ] **ROLE-05** Cross-role: Anuncios y Recursos mismo label y orden relativo (NS-01, NS-02)
- [ ] **ROLE-06** Documentar resultados en checklist por módulo (spec UX Principles)

---

## Componentes UI (baseline + refinamiento)

- [x] **UI-01…UI-07** StatusBadge, StatCard, PageHeader, DataTable, EmptyState, ConfirmDialog, TOTPInput
- [ ] **UI-08** PhaseStepper (ver CONS-04)
- [ ] **UI-09** DocumentViewer (ver CONS-05)
- [ ] **UI-10** DataTable migration (ver CONS-03)
- [ ] **UI-11** MockBarChart (ver CONS-08)

---

## Dashboards

- [x] **DASH-01…DASH-04** Baseline dashboards
- [ ] **DASH-05** Toggle demo loading/empty/error (local, sin API)
- [ ] **DASH-06** Stepper unificado (CONS-04)

---

## Listados

- [x] **LIST-01…LIST-09** Baseline listados
- [ ] **LIST-10** Paginación consistente (CONS-06)
- [ ] **LIST-11** Limpieza imports (CONS-07)

---

## Formularios

- [x] **FORM-01…FORM-07** Baseline forms
- [ ] **FORM-08** Validación visual consistente (UX-F01)
- [ ] **FORM-09** Submitting states (UX-14)

---

## Estados visuales

- [x] **STATE-01…STATE-03** Patrones baseline
- [ ] **STATE-04** Toggle demo 4 estados por pantalla (dev-only o query param)
- [ ] **STATE-05** Export simulado ReportesConsolidados
- [ ] **STATE-06** Descarga simulada Recursos

---

## Responsive

- [x] **RESP-01…RESP-05** Baseline patterns
- [ ] **RESP-06** Mobile: CoordinadorEntregas, GestionProyectos
- [ ] **RESP-07** Landing role cards breakpoints
- [ ] **RESP-08** Sidebar drawer: touch targets y scroll en listas largas (Coordinador 14 ítems)

---

## Accesibilidad

- [x] **A11Y-01…A11Y-03** Baseline a11y
- [ ] **A11Y-04** Focus order modales y split-screens
- [ ] **A11Y-05** Contraste StatusBadge warning/info
- [ ] **A11Y-06** `aria-live="polite"` en DataTable loading
- [ ] **A11Y-07** Separadores nav: `aria-hidden` o sr-only group labels
- [ ] **A11Y-08** NavLink activo: `aria-current="page"`

---

## Módulos futuros — aplicar lineamientos

Al intervenir bitácoras, entregas, proyectos u observaciones, usar checklist spec:

| Módulo | Pantallas | Tareas nav/UX relacionadas |
|--------|-----------|---------------------------|
| Bitácoras | BitacorasEstudiante, NuevaBitacora, BitacorasDirector, DetalleFirmaBitacora, CoordinadorBitacoras, RevisionBitacora* | NAV-24, NAV-25, UX-04, UX-08 |
| Entregas | DetalleEntregaEstudiante, RevisionEntregaDirector, DetalleEntregaCoordinador, DeliveryAccordion | ENT-07…ENT-14, CONS-05, UX-08 |
| Proyectos | GestionProyectos, SupervisionProyectoDirector | NAV-14, CONS-04, UX-01 |
| Observaciones | RevisionEntregaDirector, DetalleEntrega* | UX-08, UX-12, NAV-25 |
| Anuncios/Recursos | AnunciosPublica, AnunciosAdmin, Recursos* | NAV-22, NAV-23, NAV-15 |
| Evaluaciones | EvaluacionesDirector, EvaluarProyecto, EvaluadorCalificar | NAV-12, NAV-26 |
| IA | AnalisisAutomaticoEntregas, AsistenteOrientacion | UX-08, CONS-05 |

---

## Acceptance Gates

### Baseline (completados)

| Gate | Criterio | Status |
|------|----------|--------|
| G1 | 29 wireframes navegables con mock | ✅ |
| G2 | 7 componentes UI compartidos | ✅ |
| G3 | Build 0 errores | ✅ |
| G4 | lucide-react exclusivo | ✅ |
| G5 | Canon visual GestionUsuarios | ✅ |
| G6 | Sin red para datos de negocio (baseline) | ✅ |

### UX & Navegación (pendientes)

| Gate | Criterio | Status |
|------|----------|--------|
| FVM1 | FVM-01…FVM-24 implementados; build OK | ✅ |
| FVM2 | Recorrido manual 4 roles sin backend | ⬜ |
| N1 | Matriz nav por rol implementada (design.md) | ⬜ |
| N2 | NS-01…NS-19 verificados (ROLE-01…ROLE-06) | ⬜ |
| N3 | Retorno contextual en todas las vistas hijas (NAV-21…NAV-27) | ⬜ |
| U1 | UX-01…UX-10 en pantallas intervenidas | ⬜ |
| U2 | Checklist por módulo documentado | ⬜ |
| C1 | Sin redundancia Header/PageHeader (NAV-28) | ⬜ |
| A1 | A11Y-04…A11Y-08 sin issues críticos | ⬜ |

---

## Verification Commands (frontend only)

```bash
npm run build
npm run dev
# Por rol: login → sidebar completa → detalle → Volver → ítem activo correcto
# Mobile: drawer → navegar → drawer cierra
```

No ejecutar tests backend ni endpoints API como criterio de este change.
