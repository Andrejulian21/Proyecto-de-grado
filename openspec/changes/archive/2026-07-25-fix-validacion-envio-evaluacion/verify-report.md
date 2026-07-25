# Verify Report: 2026-07-25-fix-validacion-envio-evaluacion

| Check | Result |
|-------|--------|
| Root cause | `store` usaba solo `entrega.proyecto_id`; entregas grupales tienen FK null + pivot |
| Fix | Resolver IDs vía FK + `entrega_proyecto`; mensajes por condición |
| `vendor/bin/pest tests/Feature/Admin/EvaluacionCrudTest.php` | PASS (18 tests) |
| Tasks | T-001…T-005 complete |

## Cases

| Caso | Expected | Status |
|------|----------|--------|
| 1 Asignado + entrega pivot | 201 | Covered by new Pest test |
| 2 Proyecto sin evaluadores | 403 mensaje específico | Covered |
| 3 Usuario distinto | 403 mensaje específico | Covered |
| 4 Criterio duplicado | 422 mensaje específico | Covered |
