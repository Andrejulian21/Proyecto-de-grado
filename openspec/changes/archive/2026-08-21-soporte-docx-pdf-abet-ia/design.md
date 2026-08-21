# Design: Soporte DOCX y PDF en evaluación ABET con IA

## Estado actual

```
Versión DOCX
  → DocumentEvaluationService
  → DocxToMarkdownConverter (PHPWord, local)
  → EvaluationContext.documentMarkdown
  → prompt ABET / preliminar
  → AiGateway
```

- `DocxToMarkdownConverter` no conoce IA. Rechaza no-`.docx`, ZIP inválido, vacío.
- ABET (`EvaluacionAbetController`) no sube archivo: analiza `VersionDocumento`.
- Estudiantes ya suben PDF/DOCX; el panel ABET bloquea no-DOCX.
- Dependencia actual: `phpoffice/phpword`. No hay parser PDF.

## Arquitectura

```
Archivo en disco
    → DocumentFormatDetector (magic + estructura)
    → DocumentMarkdownRouter
         ├─ DocumentFormat::Docx → DocxToMarkdownConverter
         └─ DocumentFormat::Pdf  → PdfToMarkdownConverter
    → Markdown
    → DocumentEvaluationService (sin cambios de prompt/IA)
```

`DocxToMarkdownConverter` **no** se reescribe. Si el path no termina en `.docx` pero el detector confirma DOCX, el router copia a un temporal `.docx` para respetar el assert de extensión existente.

## Identificación (D3)

1. Leer 5 bytes.
2. `%PDF` → `Pdf`.
3. `PK` → abrir ZIP; si existe `word/document.xml` → `Docx`; si no → no soportado (xlsx/zip).
4. Otro → no soportado.

MIME/`original_name` no anulan el magic: un `.docx` que es PDF se enruta a PDF.

## PDF → Markdown (D2)

**Librería:** `smalot/pdfparser` (^2).

- PHP puro: no requiere `pdftotext` en Azure/Windows.
- No es un proveedor IA.
- Extrae texto por página; el conversor une páginas con `\n\n` y headings ligeros `## Página N` solo si hay más de una página con texto.

Limitación: PDF solo-imagen → vacío (`EmptyDocument`). Fuera de alcance el OCR.

## Errores

| Etapa | Código conversión | HTTP / código API |
|-------|-------------------|-------------------|
| No DOCX/PDF | `unsupported_format` | 422 `invalid_document` |
| Corrupto | `corrupt_file` | 422 |
| Sin texto | `empty_document` | 422 |
| Fallo extract | `conversion_failed` | 422 |
| Proveedor IA | — | 502/503 `ai_*` |

La conversión ocurre **antes** de `AiGateway::complete`.

## Integración ABET

`DocumentEvaluationService` deja de inyectar solo el conversor DOCX; inyecta el router. `resolveDocxVersion` pasa a aceptar `.docx` y `.pdf` **después** del sniff (no solo extensión).

Mensaje de conversión: deja de decir “solo DOCX”; distingue archivo vs IA.

## Frontend

`EvaluacionAbetPanel`: `isConvertible` (docx|pdf). `RevisionEntregaDirector` deja de filtrar solo `.docx`.

## Archivos

| File | Action |
|------|--------|
| `app/Enums/DocumentFormat.php` | Create |
| `app/Services/Documents/DocumentFormatDetector.php` | Create |
| `app/Services/Documents/PdfToMarkdownConverter.php` | Create |
| `app/Services/Documents/DocumentMarkdownRouter.php` | Create |
| `DocxToMarkdownConverter` | Sin cambio funcional; opcional `implements` |
| `DocumentConversionException` | `unsupportedFormat()`; mensajes PDF |
| `DocumentEvaluationService` | Router + validación dual |
| Panel ABET + revisión director | UI |
| Tests unitarios PDF/detector/router | Create |
| `EvaluacionAbetTest` | PDF + rechazo sin IA |

## Riesgos

- Parser PDF exigente con xref: tests usarán un PDF mínimo validado contra smalot.
- No instalar binarios del sistema.
