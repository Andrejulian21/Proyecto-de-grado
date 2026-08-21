# Tasks: Sistema general de consulta de notas por proyecto y entrega

> Change: `consulta-notas-proyecto-entrega` | Strict TDD | Runner: `vendor/bin/pest`

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~900 |
| 400-line budget risk | Low — service + una página |
| Chained PRs recommended | No |
| Focused test command | `vendor/bin/pest tests/Feature/Api/ConsultaNotasTest.php tests/Feature/Admin/DirectorGradeTest.php tests/Feature/Evaluador/EvaluadorAsignacionesTest.php` |

```text
Decision needed before apply: No
Chained PRs recommended: No
```

## T-001 — OpenSpec

- [x] T-001 Artefactos en `openspec/changes/consulta-notas-proyecto-entrega/`. No modificar `openspec/specs/`.

## T-002 — RED

- [x] T-002 `tests/Feature/Api/ConsultaNotasTest.php` cubriendo los 10 criterios (roles, asociación, 0 vs null, filtros, 403).

## T-003 — GREEN backend

- [x] T-003 `ConsultaNotasService` + controller + ruta. Relación `entregaProyectos`. Tests T-002 verdes. No se altera el cálculo de notas.

## T-004 — Frontend

- [x] T-004 Página `/notas`, sidebar cuatro roles, tipos. Semestre/búsqueda/estado desde API. “Sin calificar” vs `0.00`. `npm run build`.

## T-005 — Verify y archive

- [x] T-005 Pest enfocado + DirectorGrade/Evaluador verdes. Grep: no hay notas hardcodeadas en la página. Archivar a `openspec/changes/archive/2026-08-21-consulta-notas-proyecto-entrega/`.

## Notas de ejecución

- Prohibido: `git clean`, tocar sqlite local, `migrate*` contra BD real.
- Prohibido: specs existentes; segunda tabla de notas; mocks.
