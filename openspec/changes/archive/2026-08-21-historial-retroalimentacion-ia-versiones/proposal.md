# Proposal: Historial de retroalimentación de análisis IA por versión

## Intent

La retroalimentación generada por IA debe quedar asociada de forma inequívoca a **entrega → documento solicitado → versión**, conservar fecha y hora, y estar disponible para estudiante y director. No se mezcla con la observación académica del director. El análisis sigue siendo preliminar (cambio 04) y aplica solo al documento `analizable_ia` (cambio 05).

## Scope

### In Scope

- Reutilizar `ai_document_evaluations` (no crear un segundo sistema de análisis).
- Asociar cada análisis al documento analizable y, cuando exista, a la `VersionDocumento`.
- Conservar historial: un nuevo análisis no sobrescribe ni borra filas anteriores.
- Análisis previo a una versión oficial (archivo temporal): persistir sin inventar versión; asociar a la versión solo cuando corresponda (mismo archivo).
- Exponer la retroalimentación IA (con fecha) al estudiante y al director, por versión.
- Distinguir en UI observación del director vs retroalimentación IA.
- Ocultar por completo controles y secciones IA en documentos no configurados para IA.
- Tests Pest de asociación, historial, consulta, autorización y no mezcla con observaciones.

### Out of Scope

- Modificar specs existentes en `openspec/specs/` u otros changes.
- Nuevo proveedor IA, nuevo orquestador o métricas configurables (cambio 04 permanece).
- Tabla paralela de documentos (cambio 05: JSON `archivos_requeridos`).
- Calificación académica, rúbrica de evaluador, TOTP, bitácoras.
- Inventar `version_documento_id` para análisis históricos que no pueden asociarse con precisión.

## Capabilities

### New Capabilities

- `historial-analisis-ia-por-version`: Cada análisis IA queda ligado a documento y versión (si hay), con fecha, y se consulta como historial sin borrar anteriores.
- `consulta-retroalimentacion-ia`: Estudiante y director consultan la retroalimentación IA de la versión (y documento) correspondiente, separada de `director_notes`.

### Modified Capabilities

- `evaluador-inteligente-estudiante`: el POST persiste `archivo_requerido_id`; el análisis temporal no crea versión; GET permite consultar historial.
- `analisis-ia-director`: GET filtra por versión; no muestra el último análisis de otra versión como si fuera de la seleccionada.
- `documento-unico-analizable-ia`: la UI de detalle sigue ocultando IA en documentos no marcados; ahora también muestra historial solo en el documento IA.

## Approach

Evolucionar `ai_document_evaluations`: añadir identidad del documento solicitado (`archivo_requerido_id`). Seguir usando `version_documento_id` nullable (análisis temporal). Cada ejecución **inserta** una fila. Consultas por `(entrega, documento, versión)`. Reutilizar `DocumentEvaluationService`, `DocxToMarkdownConverter`, `PreliminaryAnalysisPrompt` y `AiGateway`.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `database/migrations/` | New | Columna `archivo_requerido_id` + backfill no inventado |
| `app/Models/AiDocumentEvaluation.php` | Modified | Campo, relaciones |
| `app/Models/VersionDocumento.php` | Modified | Relación `analisisIa` |
| `app/Services/Evaluation/DocumentEvaluationService.php` | Modified | Persistir documento; no update de filas previas |
| `app/Http/Controllers/Api/EvaluacionInteligenteController.php` | Modified | GET historial; POST con documento |
| `app/Http/Controllers/Api/EvaluacionAbetController.php` | Modified | GET por versión + historial |
| `app/Http/Controllers/Admin/EntregaController.php` | Modified | Incluir análisis IA en versiones (show) |
| `app/Http/Controllers/Api/EntregaEstudianteController.php` | Modified | Vincular análisis temporal si el hash coincide |
| Páginas estudiante/director + panel IA | Modified | Historial por versión; separación de observación |
| `tests/` | New/Modified | 14 criterios de aceptación |

## Assumptions

1. Fuente de verdad de documentos/versiones = cambio 05 (`archivos_requeridos` + `versiones_documento.archivo_requerido_id`).
2. Fuente de verdad de resultados IA = `ai_document_evaluations.result_json` (no `director_notes`).
3. Fecha del análisis = `created_at` de la fila (inicio de la ejecución persistida); se expone como `analizado_en`.
4. “Cuando corresponda” asociar temporal→versión = mismo `document_hash` al subir el archivo oficial del documento IA.
5. Filas históricas sin documento/versión precisos se conservan; no se rellenan con conjeturas.

## Non-Goals

- No `php artisan migrate` contra la BD local real (solo tests `:memory:`).
- No modificar `openspec/specs/`.
- No reintroducir métricas ni un segundo pipeline IA.
- No mezclar observación del director con retroalimentación IA.

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| GET director actual muestra el último análisis de la entrega, no de la versión | High | Filtrar por `version_id`; UI recarga al cambiar versión |
| Vincular por hash un archivo distinto | Low | Solo si `document_hash` coincide; si no, el temporal queda sin versión |
| Históricos sin `archivo_requerido_id` | Med | Backfill desde la versión si existe; si no, desde el documento IA de la entrega; si no hay certeza, NULL |

## Rollback Plan

Revertir el commit. La columna nueva es aditiva; `down()` la elimina. Las filas de análisis no se borran en `up()`.

## Success Criteria

- [ ] Un análisis de una versión queda con documento + versión + fecha.
- [ ] Un segundo análisis no elimina el primero.
- [ ] Estudiante y director ven IA y observación del director por separado en la misma versión.
- [ ] Documentos no IA no exponen análisis.
- [ ] Análisis temporal no crea `VersionDocumento`.
- [ ] No hay métricas configurables ni segundo orquestador.
