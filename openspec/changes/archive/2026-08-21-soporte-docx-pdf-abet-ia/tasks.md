# Tasks: Soporte DOCX y PDF en evaluación ABET con IA

> Change: `soporte-docx-pdf-abet-ia` | Strict TDD | `vendor/bin/pest`

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Focused test command | `vendor/bin/pest tests/Unit/Services/Documents tests/Feature/Api/EvaluacionAbetTest.php tests/Feature/Api/EvaluacionInteligenteTest.php` |

```text
Decision needed before apply: No
Chained PRs recommended: No
```

## T-001 — OpenSpec

- [x] T-001 Artefactos en `openspec/changes/soporte-docx-pdf-abet-ia/`. No tocar `openspec/specs/`.

## T-002 — RED conversión

- [x] T-002 Tests detector, PDF→MD, no soportado, corruptos, vacío. DOCX existentes siguen RED/GREEN igual.

## T-003 — GREEN conversores

- [x] T-003 `smalot/pdfparser`, detector, `PdfToMarkdownConverter`, router. `DocxToMarkdownConverter` intacto.

## T-004 — RED/GREEN ABET

- [x] T-004 Integración ABET DOCX+PDF mismo flujo; inválido sin llamada IA; UI formatos.

## T-005 — Verify y archive

- [x] T-005 Pest documentos + ABET + evaluacion inteligente. Archivar `openspec/changes/archive/2026-08-21-soporte-docx-pdf-abet-ia/`.
