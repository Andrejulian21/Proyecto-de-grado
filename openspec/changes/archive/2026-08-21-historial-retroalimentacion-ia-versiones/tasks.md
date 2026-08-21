# Tasks: Historial de retroalimentación de análisis IA por versión

> Change: `historial-retroalimentacion-ia-versiones` | Strict TDD | Runner: `vendor/bin/pest`
> Todas las tareas inician sin checkear. Archivar solo con tests verdes y AC cubiertos.

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1100 (migración + presenter + UI extraída + tests) |
| 400-line budget risk | Medium — extraer `RetroalimentacionIa` y presenter PHP |
| Chained PRs recommended | No |
| Focused test command | `vendor/bin/pest tests/Feature/Api/HistorialAnalisisIaPorVersionTest.php tests/Feature/Api/EvaluacionInteligenteTest.php tests/Feature/Api/EvaluacionAbetTest.php tests/Feature/Estudiante/SubidaArchivoTest.php tests/Feature/Admin/EntregaSinMetricasTest.php tests/Unit/Services/Evaluation/PreliminaryAnalysisPromptTest.php` |

```text
Decision needed before apply: No
Chained PRs recommended: No
```

## T-001 — OpenSpec

- [x] T-001 Artefactos `proposal.md`, `spec.md`, `design.md`, `tasks.md` en `openspec/changes/historial-retroalimentacion-ia-versiones/`. No modificar `openspec/specs/` ni otros changes.

## T-002 — RED: asociación, historial, consulta, autorización

- [x] T-002 Tests que fallen con el código actual:
  1. Análisis de documento IA queda con `documento_id` + `version_id` + `analizado_en`.
  2. Documento no IA → 422, sin invocación de proveedor.
  3. Segundo análisis de la misma versión no borra el primero.
  4. Temporal: `version_id` null, `documento_id` del IA, sin `VersionDocumento`.
  5. Subida posterior con el mismo hash vincula el temporal a la versión; hash distinto no.
  6. GET estudiante/director por `version_id` no mezcla v1 y v2.
  7. Detalle (`GET /api/admin/entregas/{id}`) incluye `analisis_ia` y `director_notes` separados.
  8. Otro estudiante / no-director → denegado.
  9. Prompt sigue incluyendo descripción; resultado sin `puntaje_orientativo`.

## T-003 — GREEN: migración + orquestador + API

- [x] T-003 Migración `archivo_requerido_id` + backfill conservador. Service persiste documento. GET estudiante. GET director filtra por versión + `historial`. Presenter. Attach por hash en subida. Relaciones Eloquent. Tests T-002 verdes. Actualizar GET director “último análisis” para que con `version_id` sea de esa versión.

## T-004 — Frontend

- [x] T-004 Tipos `AnalisisIa` en la versión. Componente `RetroalimentacionIa`. Estudiante: IA solo si `analizable_ia`; cambia al navegar versiones. Director: observación vs IA; panel recarga por `versionId`; disclaimer informativo. Sin sección/botones IA en documentos no marcados. `npm run build` sin errores.

## T-005 — Verify y archive

- [x] T-005 Pest del comando enfocado + tests de métricas/prompt relevantes en verde. Grep: no se reintroducen métricas en el pipeline IA; no se escribe IA en `director_notes`. Marcar tareas. Mover a `openspec/changes/archive/2026-08-21-historial-retroalimentacion-ia-versiones/` solo si no queda nada pendiente.

## Notas de ejecución

- **Prohibido**: `git clean`, tocar `database/database.sqlite`, `php artisan migrate*` contra BD real. Tests solo `:memory:`.
- **Prohibido**: modificar specs existentes; segundo orquestador IA; métricas configurables; inventar versiones para temporales.
- **TDD**: no marcar tarea done sin el criterio de aceptación verificado.
