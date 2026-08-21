# Verify Report: historial-retroalimentacion-ia-versiones

**Date**: 2026-08-21
**Verdict**: PASS

## Completeness

| Metric | Value |
|--------|-------|
| Tasks | 5/5 complete |
| Specs existentes modificados | 0 |

## Tests

| Command | Result |
|---------|--------|
| Pest focused (historial IA, evaluacion inteligente/abet, subida, sin métricas, prompt) | 38 passed |
| Pest observaciones + VersionDocumento + schema documentos | 13 passed |
| `npm run build` | exit 0 |

## Acceptance mapping

| # | Criterion | Evidence |
|---|-----------|----------|
| 1 | Documento IA puede analizarse | `asocia el analisis al documento y version correctos con fecha` |
| 2 | Documento no IA no puede analizarse | `rechaza analizar un documento no configurado para IA` (0 llamadas al stub) |
| 3 | Análisis asociado al documento | `documento_id` = slug IA; columna `archivo_requerido_id` |
| 4 | Análisis asociado a la versión | `version_id` / `version_documento_id` |
| 5 | Fecha conservada | `analizado_en` / `created_at` |
| 6 | Historial no destructivo | `conserva el historial cuando se analiza de nuevo la misma version` |
| 7 | Estudiante consulta por versión | GET `evaluacion-inteligente?version_id=` |
| 8 | Director consulta por versión | GET `evaluacion-abet?version_id=` |
| 9 | Observación separada de IA | detalle: `director_notes` ≠ `analisis_ia[].resultado` |
| 10 | Cambio de versión muestra su IA | GET v1 vs v2; UI usa `selectedVersion.analisis_ia` y panel con `versionId` |
| 11 | Documento no IA sin UI IA | `RetroalimentacionIa` / `EvaluacionAbetPanel` solo si `analizable_ia` |
| 12 | Descripción + flujo existente | prompt test + `DocumentEvaluationService` |
| 13 | Sin métricas | `EntregaSinMetricasTest` + grep pipeline |
| 14 | Autorización | otro estudiante/director → 403 |

## Notes

- Se reutilizó `ai_document_evaluations` (columna `archivo_requerido_id`). Sin segundo orquestador.
- Análisis temporal: `version_documento_id` NULL; vínculo posterior solo si `document_hash` coincide.
- Históricos: backfill de documento desde la versión o el documento IA de la entrega; no se inventa versión.
- Specs en `openspec/specs/` no modificados.
- No se ejecutó `php artisan migrate` contra la BD local real.
