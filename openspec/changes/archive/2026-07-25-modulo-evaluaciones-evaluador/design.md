# Design: Módulo Evaluaciones Evaluador

## Analysis

| Item | Finding |
|------|---------|
| Dashboard | KPIs + cards; usa `useEvaluadorEvaluaciones` |
| Sidebar | Ambas entradas → `/dashboard/evaluador-externo` |
| API | Reutilizar `/api/evaluador/evaluaciones` + kpis |
| Conflict | `/evaluaciones` es Director-only → nueva ruta `/evaluador/evaluaciones` |
| Components | `DataTable` (pagination), `StatusBadge`, `StatCard`, `PageHeader`, search/select pattern from BitácorasDirector |

## UX to implement (value-first)

1. Búsqueda única (proyecto / estudiante / director)
2. Filtro estado: Todos | Pendientes | Evaluadas
3. Contadores pendientes / realizadas (de datos filtrables o kpis)
4. Orden por fecha asignación (desc default; toggle asc/desc)
5. Empty state + resultados encontrados
6. Badges Pendiente/Evaluada
7. Paginación cliente (page size 20, `DataTable`)

No: modalidad en BD → mostrar `datoNoEncontrado('La modalidad')`.

## Files

- NEW `resources/js/pages/evaluador/EvaluacionesEvaluador.tsx`
- MOD `Sidebar.tsx`, `app.tsx`, `AppShell.tsx`, `EvaluadorDashboard.tsx` (CTA)
