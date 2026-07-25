# Verify Report: 2026-07-25-modulo-evaluaciones-evaluador

| Check | Result |
|-------|--------|
| `npm run build` | PASS |
| Sidebar Panel → `/dashboard/evaluador-externo` | OK |
| Sidebar Evaluaciones → `/evaluador/evaluaciones` | OK |
| Reuse `useEvaluadorEvaluaciones` | OK |
| No mocks | OK |

## Cases

| Caso | Expected |
|------|----------|
| 1 Pendientes | Filtro + botón Evaluar |
| 2 Evaluadas | Filtro + Ver evaluación |
| 3 Sin asignaciones | Empty message |
| 4 Búsqueda | Proyecto/estudiante/director |
| 5 Filtro estado | Todos / Pendientes / Evaluadas |
| 6 Sidebar | Rutas distintas |
