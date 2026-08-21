# Tasks: Gestión de documentos, versiones y observaciones de entregas

> Change: `gestion-documentos-versiones-entregas` | Strict TDD | Runner: `vendor/bin/pest`
> Todas las tareas inician sin checkear. Archivar solo con tests verdes y AC cubiertos.

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1400 (migración + requests + UI + tests) |
| 400-line budget risk | Medium — un solo change; extraer secciones de UI si un archivo >500 |
| Chained PRs recommended | No |
| Focused test command | `vendor/bin/pest tests/Feature/Admin/DocumentosSolicitadosEntregaTest.php tests/Feature/Estudiante/SubidaArchivoTest.php tests/Feature/Admin/ObservacionesSoloDocumentoProyectoTest.php tests/Feature/Api/EvaluacionInteligenteTest.php tests/Feature/Api/EvaluacionAbetTest.php tests/Feature/database/VersionesPorDocumentoSchemaTest.php tests/Unit/Models/VersionDocumentoTest.php tests/Feature/Admin/EntregaPesoTest.php tests/Feature/Admin/UpdateEntregaContratoTest.php tests/Feature/Admin/StoreEntregaTest.php` |

```text
Decision needed before apply: No
Chained PRs recommended: No
```

## T-001 — OpenSpec

- [x] T-001 Artefactos `proposal.md`, `spec.md`, `design.md`, `tasks.md` en `openspec/changes/gestion-documentos-versiones-entregas/`. No modificar `openspec/specs/` ni otros changes.

## T-002 — RED: documentos, unique de versión, IA única, observaciones

- [x] T-002 Tests que fallen con el código actual:
  - Store con 3 documentos titulados (sin `documento-proyecto`) → 201.
  - Store con dos `analizable_ia: true` → 422 mensaje único IA.
  - Store con IA en un documento que no es `documento-proyecto` → 201.
  - Subir v1 a dos slugs distintos no colisiona.
  - Review persiste `director_notes` en un documento que no es `documento-proyecto`.
  - Dos versiones del mismo documento tienen observaciones independientes.
  - Schema: unique viejo ausente / unique nuevo presente (tras T-003).
  - Evaluación IA de una versión no analizable → 422; de la analizable → 200 (stub).

## T-003 — GREEN: migración + validaciones + review + orquestador IA

- [x] T-003 Migración unique + backfill. Requests: quitar slug obligatorio; ≤1 IA. `Entrega` helpers. `ReviewEntregaAction` observa cualquier versión. `DocumentEvaluationService` solo documento IA. Tests T-002 verdes. Actualizar tests RF-ENT-01/02, unique unitario y observaciones antiguas.

## T-004 — Frontend

- [x] T-004 Builder: documentos con título; IA en cualquiera con validación de segundo check. Coordinador: default sin slug bloqueado. Estudiante: sección independiente por documento, observación por versión (o vacío). Director: documentos/versiones/observación de la seleccionada; panel IA solo si `analizable_ia`. Tipos `DocumentoSolicitado`. `npm run build` sin errores.

## T-005 — Verify y archive

- [x] T-005 Pest del comando enfocado (+ Store/Update/autorización relevantes) en verde. Grep: no queda “solo documento-proyecto” en flujo de observaciones/IA de create/UI. Marcar tareas. Mover a `openspec/changes/archive/2026-08-21-gestion-documentos-versiones-entregas/` solo si no queda nada pendiente.

## Notas de ejecución

- **Prohibido**: `git clean`, tocar `database/database.sqlite`, `php artisan migrate*` contra BD real. Tests solo `:memory:`.
- **Prohibido**: modificar specs existentes; segunda tabla de documentos; duplicar orquestador IA.
- **TDD**: no marcar tarea done sin el criterio de aceptación verificado.
