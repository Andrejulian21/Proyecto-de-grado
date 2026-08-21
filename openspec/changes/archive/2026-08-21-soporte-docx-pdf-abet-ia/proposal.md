# Proposal: Soporte DOCX y PDF en evaluación ABET con IA

## Intent

La evaluación ABET (análisis preliminar del director) debe aceptar **DOCX y PDF**. El sistema identifica el formato por el contenido del archivo, lo convierte a Markdown con el conversor local correspondiente y reutiliza el pipeline ABET existente. La conversión **no** usa el proveedor de IA.

## Scope

### In Scope

- Detector de formato (magic bytes + ZIP DOCX, no solo extensión).
- Conversor PDF → Markdown, desacoplado de IA.
- Enrutador Archivo → Markdown que elige DOCX o PDF.
- Integración en `DocumentEvaluationService` (única etapa de preparación).
- Validación y mensajes por etapa (archivo / conversión / IA).
- UI ABET: aceptar PDF y DOCX.
- Tests de conversión e integración ABET.

### Out of Scope

- Modificar `openspec/specs/`.
- Reemplazar o reescribir `DocxToMarkdownConverter`.
- Segundo pipeline ABET.
- Nuevo proveedor IA.
- OCR de PDF escaneados (solo texto extraíble).
- Convertir PDF/DOCX con un LLM.

## Capabilities

### New Capabilities

- `conversion-pdf-a-markdown`
- `identificacion-formato-documento-abet`

### Modified Capabilities

- Preparación documental de `DocumentEvaluationService` (DOCX o PDF → Markdown).
- Panel ABET del director.

## Approach

`DocxToMarkdownConverter` se mantiene. Se añade `PdfToMarkdownConverter` (smalot/pdfparser, PHP puro). Un detector + router alimentan el mismo `evaluate()`. El evaluador ABET sigue recibiendo Markdown.

## Assumptions

1. Los estudiantes ya pueden subir PDF/DOCX; ABET debe analizar esas versiones.
2. Un PDF sin texto extraíble se trata como documento vacío.
3. El análisis del estudiante comparte el orquestador: también podrá procesar PDF.

## Success Criteria

- [ ] DOCX → MD → ABET (stub IA) igual que hoy.
- [ ] PDF → MD → ABET (mismo flujo).
- [ ] Formato no soportado / corrupto: 422, sin llamada al proveedor.
- [ ] Tests unitarios del conversor DOCX existentes verdes.
