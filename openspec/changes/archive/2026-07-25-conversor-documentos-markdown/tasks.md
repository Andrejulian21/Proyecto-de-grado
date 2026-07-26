# Tasks: Conversor de documentos DOCX → Markdown

- [x] T-001 **OpenSpec docs** — proposal / design / spec / tasks (este change).
- [x] T-002 **Dependencias** — añadir `phpoffice/phpword` vía Composer (única dep nueva justificada; HTML→MD descartado tras spike).
- [x] T-003 **Errores tipados** — `DocumentConversionError` enum + `DocumentConversionException` reutilizable.
- [x] T-004 **Servicio** — `App\Services\Documents\DocxToMarkdownConverter` con `convert(string $absolutePath): string` (walker de elementos + numbering styles).
- [x] T-005 **Tests Pest (RED→GREEN)** — conversión simple, títulos, listas, numeración, tablas básicas, vacío, corrupto, extensión inválida.
- [x] T-006 **Verify** — ejecutar suite unitaria del servicio; confirmar ausencia de UI/endpoints/IA/migraciones.
- [x] T-007 **Archive** → `openspec/changes/archive/2026-07-25-conversor-documentos-markdown` solo tras verify OK.
