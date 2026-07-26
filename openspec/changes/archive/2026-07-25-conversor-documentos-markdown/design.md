# Design: Conversor de documentos DOCX → Markdown

## Phase 1 — Architecture findings

Sources: `docs/Architecture.md`, `docs/Backend.md`, `docs/Frontend.md`, `docs/ARQUITECTURA.md`, `constitution.md`.

| Finding | Implication |
|---------|-------------|
| Backend = Laravel API JSON; controllers orchestrate; models persist | Conversion is pure domain utility → **not** a controller/model concern |
| No `app/Services/` yet; Architecture.md lists “service class futuro” for non-ML clients | First service layer is the natural extension point |
| AI workloads live in FastAPI (ADR-007); Laravel must not host ML | Conversion stays in Laravel as **pre-processing**, never as AI |
| Frontend has no services layer; this change creates **zero** UI | No React/hooks/endpoints |
| Existing reusable non-HTTP logic lives outside controllers (`app/Auth/LoginAttemptPolicy`) | Confirms extracting pure PHP services is already an accepted pattern |
| Files under ~500 lines; English code; Spanish user messages | Errors exposed as Spanish messages for future UI consumers |

## Phase 2 — Architectural location

### Decision

```text
app/Services/Documents/DocxToMarkdownConverter.php
app/Enums/DocumentConversionError.php
app/Exceptions/DocumentConversionException.php
tests/Unit/Services/Documents/DocxToMarkdownConverterTest.php
```

### Justification

1. **Independent of functional modules** — no import of Entrega/Proyecto/User/Evaluacion.
2. **Matches Architecture.md extension point** — “Cliente Laravel (service class futuro)”.
3. **Consumable by any future module** — Evaluador IA, ABET, Chat, reportes, etc. call one method.
4. **Not in Controllers / Models / role folders / FastAPI** — avoids coupling and keeps ML boundary intact.
5. **No DB / no HTTP surface** — aligns with “no UI, no endpoints, no migrations”.

### Rejected placements

| Location | Why rejected |
|----------|--------------|
| Controller / FormRequest | Would imply HTTP surface |
| Model / Observer | Couples to persistence |
| `app/Http/…` role modules | Couples to Estudiante/Director/Coordinador |
| FastAPI microservice | Overkill for deterministic local conversion; adds HMAC/network; AI boundary pollution |
| Frontend | Conversion must be server-side for future AI pipelines |

## Phase 3 — Technology comparison

| Criterion | Pandoc (CLI) | Paperdoc / similar young libs | PHPWord → HTML → Markdown | Custom OOXML parser |
|-----------|--------------|-------------------------------|---------------------------|---------------------|
| Precision / structure | Excellent | Good (claimed) | Good for headings/lists/tables; known limits on complex Word styles | High effort, brittle |
| Titles / lists / tables / numbering | Excellent | Good | Supported via HTML writer + Markdown converter | DIY |
| Maintenance / activity | Mature binary | Early / unstable Packagist presence | Mature (`phpoffice/phpword` + element walker) | Ours alone |
| Laravel integration | Process spawn + PATH | Package-specific | Composer, pure PHP | Pure PHP |
| Reuse / low coupling | OS dependency | Extra facade surface | Thin wrapper service | High ownership cost |
| Unit testing | Needs binary in CI/Docker | Package maturity risk | Easy: generate fixtures with PHPWord | Hard |
| Performance | Process overhead | In-process | In-process, acceptable for docs ≤50 MB | Unknown |
| Deployment (Docker/Azure) | Extra system package | Composer only | Composer only | Composer only |
| AI coupling | None | Some libs advertise AI features | None | None |

### Selected stack

**`phpoffice/phpword` + walker de elementos + lectura de `word/numbering.xml`**

Pipeline (internal, hidden from consumers):

```text
DOCX path
  → validate (extensión, ZIP OOXML, no vacío)
  → PHPWord::load (incluye numbering styles en Style registry)
  → recorrer Sections/Elements (Title, ListItem*, Table, Text*)
  → resolver bullet vs ordered vía NumberingLevel::getFormat()
  → emitir Markdown
```

### Why this wins for this project

- Local, pure PHP, no AI, no external binary (fits Docker/Azure without new system deps).
- Mature reader (`phpoffice/phpword`), easy Pest fixtures.
- Consumers only see `DocxToMarkdownConverter::convert(string $path): string`.
- **Public result is Markdown only** (no HTML API).

### Superior alternative adopted during analysis

The initially considered pipeline `PHPWord HTML writer → league/html-to-markdown` loses lists
(HTML writer flattens `ListItemRun` to `<p>`). Walking the PHPWord element tree preserves
titles/lists/tables; `numbering.xml` distinguishes bullets vs ordered lists.
`league/html-to-markdown` was **not** kept (unnecessary dependency).

### Explicitly not chosen

- **Pandoc**: best fidelity, but OS binary breaks “simple Laravel-compatible” deploy/test story.
- **PHPWord HTML → Markdown**: lower structure fidelity for lists (rejected after spike).
- **Paperdoc / unproven Packagist packages**: promising API, but maturity/availability risk.
- **Hosting conversion in FastAPI**: violates ADR-007 boundary and adds network for no ML benefit.

## Phase 4 — Service design

### Public API

```php
final class DocxToMarkdownConverter
{
    public function convert(string $absolutePath): string;
}
```

- Input: absolute filesystem path to a `.docx` file.
- Output: Markdown `string`.
- Side effects: none (read-only).
- No knowledge of Storage disks, UploadedFile, Entrega, or AI.

### Error strategy (reusable)

`DocumentConversionException` carries:

| Code (`DocumentConversionError`) | Trigger |
|----------------------------------|---------|
| `InvalidExtension` | Path missing `.docx` (case-insensitive) |
| `EmptyDocument` | Missing file, zero bytes, or no extractable text |
| `CorruptFile` | Not a valid OOXML/ZIP DOCX / load failure |
| `UnprocessableContent` | Loaded but content cannot yield usable Markdown |
| `ConversionFailed` | Pipeline error during HTML↔Markdown steps |
| `Unexpected` | Any other throwable wrapped for consumers |

Messages in **Spanish** (constitution: user-facing errors in Spanish). Future modules catch one exception type and branch on `error` enum — no need to know PHPWord.

### SOLID (pragmatic)

| Principle | Application |
|-----------|-------------|
| S | Converter only converts; validation helpers stay private |
| O | Future PDF converter can be a sibling service; no change to this class |
| L/I | No fat interfaces; single concrete service until a second implementation exists |
| D | Consumers depend on this service class (or can type-hint later); no AI/provider deps |

Avoid overengineering: **no** interface, facade, or ServiceProvider binding until a second implementation appears.

### Future consumption (out of scope now)

```text
Entrega IA / ABET / Chat
        │
        ▼
DocxToMarkdownConverter::convert($path)
        │
        ▼
Markdown → (future) FastAPI HMAC client
```

## Risks

| Risk | Mitigation |
|------|------------|
| Complex Word styles lost | Document accepted fidelity limits; structure basics covered by tests |
| Large DOCX memory use | Same 50 MB upload ceiling already in domain; no new public upload here |
| Dependency bloat | Only two focused packages |
| Accidental HTTP surface | No routes/controllers in this change |
