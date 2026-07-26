# Design: Integración flujo Evaluaciones IA

## Findings

| Area | Finding |
|------|---------|
| Architecture | SPA + `apiFetch`; **no Inertia** (`docs/Architecture.md`) |
| Student UI | `AnalisisAutomaticoEntregas` lists entregas + official versions |
| Service | `DocumentEvaluationService` always resolves `VersionDocumento` |
| Persistence | `ai_document_evaluations.version_documento_id` NOT NULL |
| Director | `EvaluacionAbetPanel` already uses selected official version; no upload |

## Superior alternative: React Router (not Inertia)

**Justification:** Architecture.md / Frontend.md define JSON API + SPA. Passing entrega context via `navigate(path, { state })` + route params is the compatible equivalent of “server props”. Using Inertia would violate the project architecture.

Fallback on hard refresh: `GET /api/estudiante/entregas` filtered by `:entregaId` (minimal re-fetch).

## Student flow

```text
Sidebar → /analisis-entregas (SeleccionEntregaAnalisisIA)
  PhaseStepper + DeliveryAccordion (reuse)
  CTA “Analizar con IA” → /analisis-entregas/:entregaId  state:{entrega}
       → AnalisisAutomaticoEntregas
            file input DOCX temporal
            POST multipart …/evaluacion-inteligente  (file, no version_id)
            DocumentEvaluationService (temp path)
            PreSubmission strategy + AiGateway
```

## Temp file handling

1. Validate DOCX upload.
2. Store under `storage/app/tmp/ai-eval/{userId}/{uuid}.docx`.
3. Convert → prompt → gateway → parse.
4. Persist `AiDocumentEvaluation` with `version_documento_id = null`, `entrega_id` set, hash of temp file.
5. Delete temp file in `finally`.
6. **Never** create `VersionDocumento`.

Migration: make `version_documento_id` nullable (official ABET/pre_submission with version keep FK).

## Director flow

Unchanged behaviorally: official version only via `RevisionEntregaDirector` + `EvaluacionAbetPanel`. UX copy clarifying “documento oficial”. No file input.

## Shared vs different

| Shared | Different |
|--------|-----------|
| Converter, Gateway, Composer, errors | Document source (temp vs VersionDocumento) |
| DocumentEvaluationService | Strategy (pre_submission vs abet) |
| AiDocumentEvaluation | Response shape / interpreter |

## Frontend reuse

- `PhaseStepper`, `DeliveryAccordion`, `PageHeader`, mapping patterns from `EstudianteDashboard`
- Optional thin hook `useEstudianteEntregas` to avoid copy-paste of fetch/map
