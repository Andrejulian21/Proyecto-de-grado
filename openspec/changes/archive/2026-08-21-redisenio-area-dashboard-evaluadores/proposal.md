# Proposal: Rediseño del área y dashboard de evaluadores

## Intent

Sustituir el área del evaluador externo (dashboard con mocks, sidebar con anuncios/recursos) por un espacio conectado a la BD: dashboard real, calendario, pendientes, historial y enlace al sistema de notas (change 07). La calificación existente (`POST .../evaluar`) no se altera.

## Scope

### In Scope

- Sidebar EvaluadorExterno: Dashboard, Calendario, Pendientes, Historial, Notas. Sin Anuncios ni Recursos.
- Dashboard con datos de `evaluador_proyecto` / `evaluaciones_evaluador` / `users`.
- Pendientes e historial con búsqueda en servidor.
- Calendario con `fecha`/`hora_inicio`/`hora_fin` reales (sin fechas inventadas).
- API: dashboard, filtros `q`/`estado` en mis-asignaciones, calendario.
- Tests de aislamiento, búsqueda, vacío y ausencia de mocks.

### Out of Scope

- Specs existentes (`openspec/specs/`).
- Lógica académica de calificación (rango 0–5, inmutabilidad).
- Segunda tabla de evaluaciones o de notas.
- Perfil académico de evaluadores (no existe en BD; solo nombre/email).
- Área del coordinador (`AsignacionEvaluadores`) salvo reutilizar `CalendarGrid`.
- Eliminar `EvaluarProyecto.tsx` del director (mock legado); el evaluador deja de usarlo.

## Capabilities

### New Capabilities

- `area-evaluador-navegacion`: sidebar y rutas de las cinco secciones.
- `dashboard-evaluador-real`: resumen y próximas fechas desde BD.
- `evaluaciones-pendientes-historial`: listados filtrables y buscables.
- `calendario-evaluador`: eventos propios con fecha real.

### Modified Capabilities

- `evaluacion-evaluador` (archivada): el index de asignaciones gana `q`, `estado`, `fecha`, `nota`. No se reescribe el spec histórico.

## Approach

Reutilizar `EvaluadorProyecto` (`evaluado`, `fecha`, `fase`) y `EvaluacionEvaluador`. Extender `GET /api/evaluador/mis-asignaciones`. Añadir `GET /api/evaluador/dashboard` y `GET /api/evaluador/calendario`. UI: `CalendarGrid` existente, cards de `MisAsignaciones`, `/notas` del change 07.

## Affected Areas

| Area | Impact |
|------|--------|
| `EvaluadorAsignacionesController` + service | Dashboard, calendario, búsqueda |
| `EvaluadorDashboard.tsx` | Reemplazo; sin MOCK |
| Sidebar, app.tsx | Navegación |
| Páginas pendientes/historial/calendario | Nuevas |
| `EvaluarProyecto` route | Solo Director |

## Assumptions

1. Pendiente = `evaluado = false`. Completada = `evaluado = true`. No hay “en proceso” en BD.
2. Fecha de evento = `evaluador_proyecto.fecha`; si es null, no hay evento de calendario.
3. Notas = ruta existente `/notas`.
4. Datos de perfil = `users.name` y `users.email`.

## Non-Goals

- No `migrate` contra BD local.
- No mocks como fallback.

## Success Criteria

- [ ] Dashboard sin PG-2403 ni contadores 6/4/2.
- [ ] Pendientes/historial/calendario solo del evaluador autenticado.
- [ ] Búsqueda en BD.
- [ ] Sidebar sin Anuncios/Recursos.
