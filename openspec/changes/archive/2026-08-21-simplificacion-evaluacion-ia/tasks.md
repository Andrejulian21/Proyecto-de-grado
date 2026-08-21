# Tasks: Simplificación de la evaluación de entregas mediante IA

> Change: `simplificacion-evaluacion-ia` | Strict TDD | Runner: `vendor/bin/pest`
> Todas las tareas inician sin checkear. Archivar solo con tests verdes y AC cubiertos.

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~900 (prompts + parser + UI + tests) |
| 400-line budget risk | Medium — un solo change |
| Chained PRs recommended | No |
| Focused test command | `vendor/bin/pest tests/Feature/Admin/EntregaSinMetricasTest.php tests/Feature/Api/EvaluacionInteligenteTest.php tests/Feature/Api/EvaluacionAbetTest.php tests/Unit/Services/Evaluation/PreliminaryAnalysisPromptTest.php tests/Feature/Admin/StoreEntregaTest.php tests/Feature/Admin/UpdateEntregaContratoTest.php` |

```text
Decision needed before apply: No
Chained PRs recommended: No
```

## T-001 — OpenSpec

- [x] T-001 Artefactos `proposal.md`, `spec.md`, `design.md`, `tasks.md` en `openspec/changes/simplificacion-evaluacion-ia/`. No modificar `openspec/specs/` ni otros changes.

## T-002 — RED: creación sin métricas y descripción

- [x] T-002 `tests/Feature/Admin/EntregaSinMetricasTest.php`: POST crea entrega sin métricas y persiste `descripcion`; POST con `metricas_evaluacion` no las guarda; PUT de descripción no borra `evaluation_metrics` histórico; listado estudiante trae `descripcion` y no `metricas_evaluacion`. Tests en rojo.

## T-003 — GREEN: API de entregas

- [x] T-003 Quitar métricas de FormRequests y Actions; `descripcion` max 2000; EstudianteController sin `metricas_evaluacion`. Tests T-002 verdes.

## T-004 — RED: prompt y resultado preliminar

- [x] T-004 En `EvaluacionInteligenteTest` (+ unit del prompt): el `AiRequest` incluye la descripción y no las métricas de BD; el resultado tiene observaciones y no `puntaje_orientativo`; el stub sigue siendo un `AiProvider` existente (no un proveedor de producción nuevo). Tests en rojo.

## T-005 — GREEN: contexto, prompt, parser, orquestador

- [x] T-005 `PreliminaryAnalysisPrompt`; `EvaluationContext` con descripción; parser sin calificación; strategies estudiante/director delegan al prompt compartido; `DocumentEvaluationService` deja de pasar métricas. Tests T-004 verdes. `EvaluacionAbetTest` alineado al contrato preliminar (sin `perfil_metricas` / criterios).

## T-006 — Frontend

- [x] T-006 Coordinador: sin `MetricasEvaluacionField` ni listado de métricas; descripción con copy de “qué debe entregar el estudiante”. Estudiante: análisis preliminar, descripción, sin puntaje. Director: mismo análisis, sin resultados por métrica. Eliminar el componente de métricas. `npm run build` sin errores.

## T-007 — Verify y archive

- [x] T-007 Pest de T-002/T-004/T-005 (+ Store/Update de entregas) en verde. Grep: no quedan referencias funcionales a métricas en create/UI/prompt. Marcar tareas. Mover la carpeta a `openspec/changes/archive/2026-08-21-simplificacion-evaluacion-ia/` solo si no queda nada pendiente.

## Notas de ejecución

- **Prohibido**: `git clean`, tocar `database/database.sqlite`, `php artisan migrate*` contra BD real. Tests solo `:memory:`.
- **Prohibido**: modificar specs existentes; nuevo proveedor IA; sistema paralelo de métricas.
- **TDD**: no marcar tarea done sin el criterio de aceptación verificado.
