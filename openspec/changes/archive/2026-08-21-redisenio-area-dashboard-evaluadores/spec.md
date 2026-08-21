# Spec: Rediseño del área y dashboard de evaluadores

> Change: `redisenio-area-dashboard-evaluadores` | No modificar `openspec/specs/`

## Decisiones

| # | Decisión | Resolución |
|---|----------|-----------|
| D1 | Fuente de asignaciones | `evaluador_proyecto` del usuario autenticado |
| D2 | Pendiente vs hecha | `evaluado` false / true. Sin estado “en proceso” |
| D3 | Fecha de calendario | `fecha` + `hora_inicio`/`hora_fin`. Null → no inventar |
| D4 | Tipo de evaluación | `fase` (anteproyecto / presentacion_final, etc.) |
| D5 | Nota | `evaluaciones_evaluador.nota` |
| D6 | Perfil | `name` + `email`; no perfil académico |
| D7 | Notas del sidebar | `/notas` (change 07), sin duplicar |
| D8 | Calificación | Sigue `EvaluadorCalificar` + POST evaluar |
| D9 | Sidebar | Solo 5 ítems del evaluador |

---

## Capacidad: area-evaluador-navegacion

### RF-EVUI-01: Sidebar del evaluador

WHEN el usuario tiene rol EvaluadorExterno, the sidebar MUST mostrar únicamente:

1. Dashboard → `/dashboard/evaluador-externo`
2. Calendario de evaluaciones → `/evaluador/calendario`
3. Evaluaciones pendientes → `/evaluador/pendientes`
4. Historial de evaluaciones → `/evaluador/historial`
5. Notas → `/notas`

The sidebar MUST NOT incluir Anuncios, Recursos ni “Mis Asignaciones” como ítem principal.

---

## Capacidad: dashboard-evaluador-real

### RF-EVUI-02: Dashboard desde BD

WHEN un evaluador autenticado llama `GET /api/evaluador/dashboard`, the system SHALL devolver su `name`/`email` y conteos calculados de **sus** filas en `evaluador_proyecto` (`pendientes`, `realizadas`, `asignadas`).

The payload MUST NOT incluir constantes de UI (p. ej. 6, 4, 2).

WHEN no hay asignaciones, the counts MUST ser 0 AND `proximas` vacía.

`proximas` MUST incluir solo asignaciones propias con `fecha` no nula y `evaluado = false`, ordenadas por fecha. Sin fecha → no aparecen en próximas.

### RF-EVUI-03: Sin mocks en dashboard

`EvaluadorDashboard` MUST obtener datos de `/api/evaluador/dashboard`. MUST NOT contener `MOCK_EVALUATIONS` ni códigos de proyecto ficticios.

---

## Capacidad: evaluaciones-pendientes-historial

### RF-EVUI-04: Pendientes del autenticado

`GET /api/evaluador/mis-asignaciones?estado=pendiente` MUST devolver solo asignaciones con `evaluado = false` AND `evaluador_id` = usuario.

Cada ítem MUST permitir identificar proyecto (código, título, estudiantes, director), `fase`, `fecha` (nullable), `estado` pendiente, y el `id` para abrir `/evaluador/asignaciones/{id}`.

WHEN no hay pendientes, the list MUST ser vacía (no mocks).

### RF-EVUI-05: Búsqueda de pendientes

WHEN se envía `q`, the system SHALL filtrar en BD por código o título de proyecto o nombre de estudiante, restringido al evaluador autenticado.

### RF-EVUI-06: Historial

`GET /api/evaluador/mis-asignaciones?estado=evaluada` MUST devolver asignaciones con `evaluado = true` del usuario, con `nota` y `evaluated_at` cuando existan en `evaluaciones_evaluador`.

WHEN no hay historial, the list MUST ser vacía.

### RF-EVUI-07: Búsqueda del historial

El mismo `q` aplica con `estado=evaluada`.

### RF-EVUI-08: IDOR

WHEN un evaluador pide detalle o evalúa `asignaciones/{id}` de otro, the system SHALL 403 (comportamiento existente).

WHEN consulta `/api/notas?proyecto_id=` de un proyecto no asignado, the system SHALL 403 (change 07).

---

## Capacidad: calendario-evaluador

### RF-EVUI-09: Eventos propios con fecha real

`GET /api/evaluador/calendario` MUST devolver eventos de asignaciones del usuario con `fecha IS NOT NULL`. Cada evento: proyecto, fase, fecha, hora si existe, estado pendiente/evaluada.

MUST NOT incluir asignaciones de otros evaluadores.

MUST NOT inventar `fecha` cuando es null; esas asignaciones se omiten del calendario.
