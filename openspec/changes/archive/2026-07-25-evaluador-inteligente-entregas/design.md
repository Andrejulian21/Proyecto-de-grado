# Design: Evaluador Inteligente de Entregas

## Phase 1–3 findings

| Area | Finding |
|------|---------|
| Architecture | API JSON + SPA; AI via Laravel services → future FastAPI provider (ADR-007) |
| Infra | `DocxToMarkdownConverter`, `AiGateway`, `AiPromptComposer`, `NullAiProvider` |
| Entregas | Versions on `public` disk; metrics in `entregas.evaluation_metrics` |
| Student API | Omits version `id` and metrics — must extend |
| UI | `AnalisisAutomaticoEntregas.tsx` fully mocked |
| Planned `analisis_ia` | Mentioned in older docs; not implemented |

## Persistence decision

**Persist** in a **generic** table `ai_document_evaluations` (not stuffing AI output into `evaluation_metrics`).

| Why | Detail |
|-----|--------|
| Reuse | `type` column supports `pre_submission`, future `abet`, `director` |
| Audit | hash, provider, timing, status, errors |
| UX | optional later “last analysis” without re-calling IA |
| Scope | Store structured JSON result + metadata; not vendor raw dumps beyond content |

## Architecture

```text
Estudiante UI
    → POST /api/estudiante/entregas/{id}/evaluacion-inteligente
        → EvaluacionInteligenteController
            → DocumentEvaluationService
                 ├─ resolve entrega/version (RBAC student)
                 ├─ DocxToMarkdownConverter
                 ├─ EvaluationPromptStrategy (PreSubmission…)
                 ├─ AiPromptComposer + AiGateway
                 ├─ EvaluationResultParser
                 └─ AiDocumentEvaluation (persist)
```

### Strategy (Open/Closed for future evaluations)

```php
interface EvaluationPromptStrategy {
    public function type(): AiEvaluationType;
    public function promptVersion(): string;
    public function systemInstructions(): string;
    public function contextSections(EvaluationContext $ctx): array;
}
```

ABET/Director later = new strategy class + same service/controller pattern. **No provider knowledge** in strategies or service.

### Structured result (minimum + enrichments)

```json
{
  "resumen": "...",
  "fortalezas": ["..."],
  "aspectos_mejorar": ["..."],
  "errores": ["..."],
  "recomendaciones": ["..."],
  "conclusion": "...",
  "prioridades": [{"item":"...","criticidad":"alta|media|baja"}],
  "confianza": 0.0,
  "puntaje_orientativo": 0
}
```

Prompt asks for JSON-only; parser validates. UI maps lists into the existing checklist panel + summary blocks.

### Without configured provider

1. Full pipeline runs until `AiGateway::complete`.
2. `NullAiProvider` → `AiException` `ProviderNotConfigured`.
3. Persist `status=failed`, friendly `error_message`.
4. HTTP **503** `{ error: "No fue posible conectarse al servicio de Inteligencia Artificial. Inténtalo más tarde.", code: "ai_unavailable" }`.
5. UI shows amigable banner (no stack traces).

### Superior alternative adopted

Generic `DocumentEvaluationService` + strategies instead of a hard-coded “solo entregas” service: same orchestrator serves future ABET/Director without duplicating convert/gateway/persist.

## API

`POST /api/estudiante/entregas/{entrega}/evaluacion-inteligente`  
Body: `{ "version_id": optional }` (defaults to latest DOCX version).

`GET /api/estudiante/entregas` extended with:
- `metricas_evaluacion`
- `versiones[].id`

## Frontend

Reuse `AnalisisAutomaticoEntregas.tsx`:
- Load real entregas/versions.
- Select DOCX version + analyze.
- Render structured result; unavailable state for 503.
- Keep disclaimer; remove mock checklist/score.
