# Verify Report: 2026-07-25-fix-carga-calificaciones-evaluador

| Check | Result |
|-------|--------|
| Root cause | Calificar hidrataba contra nombres distintos a los guardados por EvaluarProyecto |
| BD | Grades + comment correctos (5/4/3/5 + observación) |
| Fix | `hydrateCriteriaFromSaved` usa filas API como fuente de verdad |
| `npm run build` | PASS |

## Cases

| Caso | Status |
|------|--------|
| 1 Store grades | Persistencia ya OK (sin cambio backend) |
| 2 Reopen scores | Hydrate from saved rows |
| 3 Total = sum | Derived from hydrated criteria |
| 4 Observation | `extractComment` |
| 5 Match BD | Same criterio/grade fields from GET |
