# Tasks: Rediseño del área y dashboard de evaluadores

> Change: `redisenio-area-dashboard-evaluadores` | Strict TDD | `vendor/bin/pest`

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1200 |
| Focused test command | `vendor/bin/pest tests/Feature/Evaluador tests/Feature/Api/ConsultaNotasTest.php` |

```text
Decision needed before apply: No
Chained PRs recommended: No
```

## T-001 — OpenSpec

- [x] T-001 Artefactos en `openspec/changes/redisenio-area-dashboard-evaluadores/`. No tocar `openspec/specs/`.

## T-002 — RED

- [x] T-002 `tests/Feature/Evaluador/EvaluadorAreaTest.php` (dashboard, búsqueda, historial, calendario, IDOR, vacío, sidebar/mocks).

## T-003 — GREEN API

- [x] T-003 Service + dashboard/calendario + filtros del index. Tests T-002 y `EvaluadorAsignacionesTest` verdes.

## T-004 — Frontend

- [x] T-004 Sidebar 5 ítems, dashboard real, pendientes, historial, calendario, `/notas`. Sin MOCK. `npm run build`.

## T-005 — Verify y archive

- [x] T-005 Pest evaluador + notas. Archivar a `openspec/changes/archive/2026-08-21-redisenio-area-dashboard-evaluadores/`.
