# Spec: Conversor de documentos DOCX → Markdown

## CDM-001 — Servicio reutilizable
**THE SYSTEM SHALL** provide a reusable Laravel service that converts a local DOCX file path into a Markdown string without requiring AI providers, HTTP endpoints, or UI.

## CDM-002 — Ubicación desacoplada
**THE SYSTEM SHALL** place the converter under `app/Services/Documents/` (or an equivalent non-role service namespace) and **SHALL NOT** place conversion logic in Controllers, Models, role modules, or the FastAPI AI boundary.

## CDM-003 — API de conversión
**WHEN** a caller invokes the converter with an absolute path to a valid non-empty DOCX  
**THE SYSTEM SHALL** return Markdown that preserves, when present in the source: headings hierarchy, bullet lists, ordered/numbered lists, and basic tables where the conversion library allows.

## CDM-004 — Resultado Markdown (no HTML)
**THE SYSTEM SHALL** expose Markdown as the public conversion result and **SHALL NOT** return HTML as the final consumer-facing output.

## CDM-005 — Extensión inválida
**WHEN** the path does not end with `.docx` (case-insensitive)  
**THE SYSTEM SHALL** throw `DocumentConversionException` with error `InvalidExtension`.

## CDM-006 — Documento vacío
**WHEN** the file is missing, has zero bytes, or yields no extractable textual content  
**THE SYSTEM SHALL** throw `DocumentConversionException` with error `EmptyDocument`.

## CDM-007 — Archivo corrupto
**WHEN** the file is not a readable/valid DOCX (corrupt OOXML/ZIP or unloadable)  
**THE SYSTEM SHALL** throw `DocumentConversionException` with error `CorruptFile`.

## CDM-008 — Contenido no procesable / fallo de conversión
**WHEN** the document loads but conversion cannot produce usable Markdown, or an internal pipeline step fails  
**THE SYSTEM SHALL** throw `DocumentConversionException` with `UnprocessableContent` or `ConversionFailed` as appropriate.

## CDM-009 — Errores inesperados
**WHEN** an unexpected throwable occurs during conversion  
**THE SYSTEM SHALL** wrap it in `DocumentConversionException` with error `Unexpected` (preserving the previous exception when available).

## CDM-010 — Sin superficie funcional
**THE SYSTEM SHALL NOT** add React pages, components, hooks, test-only controllers, public API endpoints, models, or migrations as part of this change.

## CDM-011 — Sin IA
**THE SYSTEM SHALL NOT** invoke FastAPI, Azure OpenAI, prompts, embeddings, or any AI analysis during conversion.

## CDM-012 — Pruebas automáticas
**THE SYSTEM SHALL** include Pest unit tests covering at least: simple DOCX conversion, headings, lists, numbering, basic tables (when supported), empty document, corrupt file, and invalid format — without AI providers.
