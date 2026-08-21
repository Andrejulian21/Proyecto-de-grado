# Verify Report: soporte-docx-pdf-abet-ia

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
| `vendor/bin/pest tests/Unit/Services/Documents tests/Feature/Api/EvaluacionAbetTest.php tests/Feature/Api/EvaluacionInteligenteTest.php` | 40 passed (114 assertions) |
| `vendor/bin/pest tests/Feature/Api/HistorialAnalisisIaPorVersionTest.php` | 11 passed |
| `npm run build` | exit 0 |

## Acceptance mapping

| # | Criterion | Evidence |
|---|-----------|----------|
| RF-PDF-01 | PDF válido → Markdown | `convierte un PDF valido a Markdown con el texto extraido` |
| RF-PDF-02 | PDF corrupto | `rechaza un PDF corrupto con error controlado` + ABET sin IA |
| RF-PDF-03 | PDF sin texto | `rechaza un PDF sin texto utilizable` |
| RF-FMT-01 | Enrutado DOCX/PDF | `DocumentMarkdownRouterTest` |
| RF-FMT-02 | No soportado | detector + ABET `rechaza un formato no soportado sin llamar al proveedor IA` |
| RF-ABET-DOC-01 | DOCX sigue | `completa analisis preliminar del director con proveedor stub` + `DocxToMarkdownConverterTest` |
| RF-ABET-DOC-02 | PDF mismo flujo | `completa analisis preliminar ABET a partir de un PDF convertido a Markdown` |
| RF-ABET-DOC-03 | Inválido no llama IA | `lastRequest` null en no soportado / corrupto |
| RF-ABET-DOC-04 | IA vs conversión | 422 `invalid_document` vs 503 `ai_unavailable` |

## Notes

- `DocxToMarkdownConverter` no se reescribió. El router copia a `.docx` temporal solo si el path no termina en esa extensión.
- PDF: `smalot/pdfparser` ^2.12 (PHP puro, sin OCR ni proveedor IA).
- Identificación por magic (`%PDF`, ZIP + `word/document.xml`), no por el nombre del usuario.
