# Proposal: Frontend Wireframes Port

## Intent

Portear los ~29 wireframes de Open Design a React 18 + TypeScript + Tailwind v4 + shadcn/ui, manteniendo el layout existente (AppShell/Sidebar/Header), el design system UNAB y la coherencia visual con las páginas ya portadas (auth, gestión usuarios, audit-log).

**Este change tiene alcance EXCLUSIVAMENTE FRONTEND:** port de wireframes, experiencia de usuario, componentes reutilizables, estados visuales simulados y datos mock. No incluye implementación de backend, APIs, persistencia ni integración con servicios externos.

**Estado:** Baseline de porteo completado (2026-07-10). Fase activa: **Frontend Validation Mode** — validación funcional y visual completa sin dependencia del backend, seguida de refinamiento UX/navegación.

**Fase temporal — Frontend Validation Mode (FVM):** Todas las pantallas del change deben ser navegables y demostrables usando datos mock organizados en `resources/js/mocks/`. El flag `FRONTEND_VALIDATION_MODE` en `mocks/validationMode.ts` activa/desactiva la fuente mock vs API real. Esta fase es **estrictamente temporal**; en Sprint 5+ se reemplazan los mocks por integraciones reales sin rediseñar la UI.

**Objetivo de la fase actual:** validación UX/wireframes/navegación/flujos/responsive/a11y con datos representativos; refinamiento UX/navegación; rediseño bitácoras y entregas (mock).

**Fase activa (entregas):** rediseñar la experiencia de revisión de entregas para que las observaciones del director estén asociadas **individualmente** a cada versión entregada; y **estados visuales de plazo** en listados del Director (gris / verde / naranja / rojo) para supervisión rápida sin abrir detalle.

**Fase activa (Director — plazo de entrega):** rediseñar la representación visual del estado de cumplimiento de plazo de cada entrega en la interfaz del Director, mediante colores y etiquetas diferenciados (solo frontend mock; sin lógica de cálculo).

---

## Scope Boundary

| Pertenece a este change | NO pertenece (changes separados) |
|-------------------------|----------------------------------|
| Layout y composición de pantallas según wireframes | Endpoints, controladores, migraciones |
| Datos mock (`resources/js/mocks/`, flag `FRONTEND_VALIDATION_MODE`) | Endpoints, controladores, migraciones |
| Estados visuales simulados (loading, empty, error) | Validación/persistencia en servidor |
| Interacciones UI locales (expand, tabs, modales, TOTP visual) | Autenticación/autorización en backend |
| Navegación por rol en Sidebar + rutas React | FastAPI, LLM real, firma TOTP real |
| Sistema de navegación unificado (grupos, jerarquía, activo, responsive) | Tests E2E (Sprint 6) |
| Principios de UX reutilizables (acciones, estados, flujos) | |
| Accesibilidad, responsive, canon visual UNAB | |

La integración backend se documenta e implementa en changes dedicados (`coordinador-integracion`, `director-integracion`, etc.). Este change **no** debe introducir ni mantener referencias a esos flujos como tareas propias.

---

## Scope

### In Scope

- Portear ~29 wireframes a React con **mock data exclusivamente** (KPIs estáticos, tablas con datos de ejemplo, cards de dashboard)
- Reorganizar el sistema de navegación (Sidebar + Header + rutas relacionadas) con grupos lógicos, jerarquía clara y comportamiento consistente entre roles
- Navbars por rol (`navConfig` en `Sidebar.tsx`) siguiendo las reglas de organización del spec — **sin rediseño visual completo** del shell
- Coherencia visual con las páginas ya portadas: `GestionUsuarios.tsx`, `AuditLog.tsx`, `LoginInstitucional.tsx`, `LoginExterno.tsx`
- Usar **lucide-react** para iconos (estándar del proyecto, no Material Symbols)
- Componentes compartidos: StatusBadge, DataTable, StatCard, PageHeader, EmptyState, ConfirmDialog, TOTPInput
- Landing page institucional (pre-login)
- Flujo TOTP **visual** para firma de bitácoras (validación simulada en cliente)
- Pantallas IA con conversación y análisis **pre-renderizados** (sin LLM ni servicios externos)
- Estados visuales: loading (Loader2), empty, error (banner), data — todos simulados localmente
- Responsive mobile-first y accesibilidad WCAG AA en componentes y páginas
- **Rediseño bitácoras Director/Coordinador:** agrupación por proyecto, cards de reunión, firma solo del director (sin tabla de firmas), estados Pendiente/Firmado/Rechazado, flujo TOTP visual simulado, comentario de rechazo mock
- **Rediseño entregas — observaciones por versión:** historial de versiones cronológico, relación 1:1 versión↔observación, flujos Director (revisar/editar por versión) y Estudiante (consultar historial), indicadores visuales de versiones revisadas vs pendientes; fixtures en `mocks/entregasMock.ts`
- **Estados visuales de plazo (Director):** cuatro estados de cumplimiento (aún no entregado, en horario, fuera de plazo, atrasado) representados con `StatusBadge` + iconografía; fixtures con campo `timelineStatus` preasignado; componente `DeliveryTimelineStatusBadge`; listados en Supervisión y Dashboard Director

| Batch | Focus | Wireframes |
|-------|-------|------------|
| 1 | Dashboards reales + páginas compartidas | panel-estudiante, panel-coordinador, panel-director, evaluador-panel, anuncios-publica, anuncio-detalle, recursos, recurso-detalle |
| 2 | Landing + flujo Estudiante | index (landing), bitacoras-estudiante, nueva-bitacora, detalle-entrega-estudiante |
| 3 | Flujo Director | supervision-proyecto, seleccion-proyectos-bitacoras, bitacoras-director, detalle-firma-bitacora, revision-entrega-director |
| 4 | Gestión Coordinador | gestion-proyectos, anuncios-admin, asignacion-evaluadores, coordinador-entregas |
| 5 | Coordinador resto + Evaluador | coordinador-bitacoras, gestion-alertas, reportes-consolidados, recursos-admin, evaluador-calificar, evaluar-proyecto |
| 6 | IA + Landing | analisis-automatico-entregas, asistente-orientacion |

### Out of Scope

- **Cualquier integración backend** — APIs, `apiFetch()`, hooks de datos, persistencia, base de datos
- **Lógica de servidor** — validaciones dependientes del backend, autorización más allá del gating visual por rol en rutas existente
- **Servicios externos** — FastAPI, Azure OpenAI, firma TOTP real, envío de correos
- Rediseñar desde cero AppShell/Sidebar/Header (colores, tipografía, branding)
- Tests E2E (Playwright) — fuera de este change
- Cambios en `routes/api.php`, controladores, modelos o migraciones

---

## Design Approach

### Mantener patrones existentes (de GestionUsuarios.tsx)

El estilo de `GestionUsuarios.tsx` es el canon visual a seguir:

- **Cards:** `rounded-xl border border-border bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]`
- **Botones primary:** `rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover`
- **Botones outline:** `rounded-lg border border-border bg-transparent px-4 py-2.5 text-sm font-semibold text-text hover:border-primary hover:bg-primary-container hover:text-primary`
- **Tablas:** contenedor `rounded-lg border border-border`, thead `bg-surface-alt text-[11px] font-bold uppercase tracking-[0.05em] text-text-muted`
- **Badges:** `rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.03em]`
- **Inputs:** `min-h-[40px] rounded-lg border border-border bg-white px-3 py-2 text-sm text-text outline-none placeholder:text-text-subtle focus:border-primary focus:shadow-[0_0_0_3px_#fed7aa]`
- **Page header:** Eyebrow pill + h2 título + p subtítulo
- **Iconos:** lucide-react en lugar de Material Symbols

### Estrategia de datos (solo UI)

- **Frontend Validation Mode:** `resources/js/mocks/validationMode.ts` exporta `FRONTEND_VALIDATION_MODE = true`. Hooks y páginas consultan este flag antes de llamar `apiFetch`.
- Fixtures por módulo en `resources/js/mocks/` (`proyectosMock`, `estudianteMock`, `anunciosMock`, `recursosMock`, `usuariosMock`, `evaluacionesMock`, `coordinadorMock`, `bitacorasMock`, `entregasMock`).
- Interacciones que implican guardar/enviar/firmar: **feedback visual simulado** (`mockDelay`, actualización de estado local, stores mutables en mocks).
- Estructura de tipos TypeScript alineada con la forma esperada de la UI, **sin acoplar** a contratos de API.
- **Migración futura:** poner `FRONTEND_VALIDATION_MODE = false` y retirar ramas mock en hooks (o reemplazar por servicios reales) — la UI no cambia.

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

La navegación se define en `spec.md` → **Navigation System** y `design.md` → **Navigation Architecture**. Reglas clave:

- Grupos lógicos: Inicio → Operaciones del rol → Comunicación → Recursos → Herramientas → Administración
- Opciones compartidas (Anuncios, Recursos) con **mismo label y orden relativo** en todos los roles
- Entradas administrativas al final, claramente separadas del flujo operativo
- Pantalla activa identificable; rutas hijas resaltan el ítem padre
- Comportamiento idéntico en escritorio (sidebar fija) y móvil (drawer + cierre al navegar)

Ver `tasks.md` → secciones **Navegación**, **UX**, **Consistencia visual**.

### Principios de UX (resumen)

Criterios reutilizables documentados en `spec.md` → **UX Principles**. Toda pantalla modificada en solicitudes futuras debe cumplirlos: acción principal visible, jerarquía de información, estados empty/loading/error, confirmación visual de acciones, patrón consistente de retorno contextual.

---

## Wireframe Map

Cada wireframe HTML se portea a su equivalente React con mock data:

| Wireframe | Ruta React | Rol |
|-----------|-----------|-----|
| `login-institucional.html` | `/login` | ✅ Ya portado |
| `login-evaluadores-externos.html` | `/login/externo` | ✅ Ya portado |
| `gestion-usuarios.html` | `/coordinador/usuarios` | ✅ Ya portado |
| `audit-log` | `/coordinador/audit-log` | ✅ Ya portado |
| `panel-estudiante.html` | `/dashboard/estudiante` | Estudiante |
| `panel-coordinador.html` | `/dashboard/coordinador` | Coordinador |
| `panel-director.html` | `/dashboard/director` | Director |
| `evaluador-panel.html` | `/dashboard/evaluador-externo` | Evaluador |
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

## Affected Areas (solo frontend)

| Área | Impacto |
|------|---------|
| `resources/js/pages/` | Páginas por rol + shared + landing |
| `resources/js/components/ui/` | Componentes compartidos |
| `resources/js/components/layout/Sidebar.tsx` | Reorganización `navConfig` por grupos y jerarquía |
| `resources/js/components/layout/AppShell.tsx` | `ROUTE_TITLES` sincronizados con navegación |
| `resources/js/components/layout/Header.tsx` | Título contextual coherente con PageHeader |
| `resources/js/components/ui/PageHeader.tsx` | Acciones primarias, retorno contextual |
| `resources/js/app.tsx` | Rutas con lazy loading |
| Todas las páginas intervenidas | Aplicación de UX Principles al modificar |

**Fuera de alcance:** `app/`, `routes/api.php`, `database/`, servicios FastAPI.

---

## Risks

| Riesgo | Probabilidad | Mitigación |
|--------|-------------|------------|
| Exceder budget 400 líneas/PR | Media | PRs pequeños por categoría o pantalla |
| Inconsistencia visual con páginas existentes | Baja | Usar GestionUsuarios como canon visual |
| Iconos Material Symbols en wireframes vs lucide-react | Baja | Mapear a equivalentes lucide |
| Confusión de alcance con changes de integración | Media | Mantener explícito el boundary en spec/tasks; no mezclar `apiFetch` en tareas de este change |
| Mezclar mock con datos reales en la misma pantalla | Media | En trabajo bajo este change, usar solo fixtures locales |
| Sidebar desincronizada vs rutas en `app.tsx` | Alta | Matriz nav ↔ rutas en design.md; verificar tras cada tarea NAV-* |
| Doble encabezado Header h1 + PageHeader h2 redundante | Media | Regla UX-02: un solo título principal por vista |
| Etiquetas inconsistentes entre roles | Media | Glosario de labels en spec Navigation System |

---

## Rollback Plan

- Feature-branch por unidad de trabajo frontend
- `git revert` del merge commit de cualquier PR problemático
- `main` siempre desplegable

---

## Dependencias

- Wireframes HTML + DESIGN.md en `Open Design/data/projects/proyecto-de-grado-0554/`
- Layout existente: AppShell, Sidebar, Header
- shadcn/ui + Tailwind v4 + lucide-react ya configurados
- Auth y rutas protegidas ya existentes (solo consumidas visualmente; no se modifican en este change salvo gating de rutas en `app.tsx`)

---

## Success Criteria

- [x] ~29 wireframes porteados como páginas React navegables
- [x] Mock data en todas las pantallas del baseline (sin llamadas a backend en el port original)
- [x] Coherencia visual con GestionUsuarios y páginas de auth
- [x] Navbars diferentes por rol según wireframes
- [x] Landing page institucional en `/`
- [x] TOTP signing con validación visual simulada
- [x] IA screens renderizadas con contenido pre-renderizado
- [x] AppShell/Sidebar/Header sin regresiones visuales
- [x] Refinamiento frontend documentado en `tasks.md`
- [ ] Frontend Validation Mode: todas las pantallas navegables sin backend obligatorio
- [ ] Sistema de navegación reorganizado según spec (grupos, orden, activo, responsive)
- [x] Rediseño bitácoras por proyecto (Director + Coordinador) según spec BIT-*
- [x] Rediseño entregas — observaciones por versión según spec ENT-* (Director + Estudiante + Coordinador lectura)
- [x] Estados visuales de plazo en listados Director según spec ENT-TL-* (Supervisión + Dashboard)
- [ ] Principios de UX aplicados en pantallas intervenidas
- [ ] Consistencia cross-role validada (matriz en design.md)
- [ ] Layouts reutilizables actualizados (PageHeader acciones, retorno contextual)

---

## Estimated Effort

Baseline completado. Fase UX/navegación: ~1–2 semanas según `tasks.md` (NAV-*, UX-*, CONS-*, RESP-*, A11Y-*).
