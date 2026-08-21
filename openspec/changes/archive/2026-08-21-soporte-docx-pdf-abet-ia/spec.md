# Spec: Soporte DOCX y PDF en evaluación ABET con IA

> Change: `soporte-docx-pdf-abet-ia` | No modificar `openspec/specs/`

## Decisiones

| # | Decisión | Resolución |
|---|----------|-----------|
| D1 | Conversor DOCX | Se conserva `DocxToMarkdownConverter` sin cambiar su contrato `convert(path)` |
| D2 | Conversor PDF | Clase nueva; PHP puro (`smalot/pdfparser`); sin IA |
| D3 | Identificación | Magic bytes (`%PDF`, ZIP `PK` + `word/document.xml`). Extensión y MIME son pistas, no la única fuente |
| D4 | Pipeline ABET | Uno solo: Markdown → prompt → AiGateway |
| D5 | PDF escaneado / sin texto | `EmptyDocument`; no OCR |
| D6 | Mensajes | Conversión ≠ error de proveedor IA |

---

## Capacidad: conversion-pdf-a-markdown

### RF-PDF-01: PDF válido a Markdown

WHEN se convierte un PDF con texto extraíble, the system SHALL devolver Markdown no vacío **sin** llamar a un proveedor IA.

### RF-PDF-02: PDF corrupto

WHEN el archivo no es un PDF parseable (magic inválido o parser falla), the system SHALL lanzar `DocumentConversionException` con `CorruptFile` AND MUST NOT invocar IA.

### RF-PDF-03: PDF sin texto

WHEN el PDF no tiene texto utilizable, the system SHALL lanzar `EmptyDocument`.

---

## Capacidad: identificacion-formato-documento-abet

### RF-FMT-01: Enrutado

WHEN el archivo es DOCX (ZIP + documento Word), the system SHALL usar el conversor DOCX.

WHEN el archivo es PDF (`%PDF`), the system SHALL usar el conversor PDF.

### RF-FMT-02: No soportado

WHEN el contenido no es DOCX ni PDF, the system SHALL rechazar con mensaje de que solo se aceptan DOCX y PDF AND MUST NOT convertir ni llamar IA.

Un `.pdf` cuyo contenido no es PDF MUST NOT tratarse como PDF.

---

## Capacidad: abet-docx-pdf

### RF-ABET-DOC-01: DOCX sigue funcionando

POST evaluación ABET con versión DOCX válida MUST completar el análisis (con stub de IA en tests) como hasta ahora.

### RF-ABET-DOC-02: PDF usa el mismo flujo

POST con versión PDF válida MUST convertir a Markdown y usar el mismo `DocumentEvaluationService` / estrategia ABET. El prompt MUST incluir el texto extraído.

### RF-ABET-DOC-03: Inválido no llega a IA

Archivo no soportado o corrupto MUST responder error de documento (422) AND the stub provider MUST NOT recibir `complete()`.

### RF-ABET-DOC-04: Errores diferenciados

Error de conversión MUST mapearse a mensaje de documento. Error de proveedor MUST seguir usando códigos `ai_unavailable` / 502-503.
