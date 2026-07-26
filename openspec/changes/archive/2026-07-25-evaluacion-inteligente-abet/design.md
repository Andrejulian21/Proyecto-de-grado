# Design: Evaluación Inteligente ABET

## Phase 1–2 findings

| Area | Finding |
|------|---------|
| Pipeline | `DocumentEvaluationService` reuses converter + composer + gateway + `AiDocumentEvaluation` |
| Gap access | Hardcodes `resolveStudentProject` — blocks Director |
| Gap result | Hardcodes `EvaluationResultParser` / student JSON shape |
| Enum | `AiEvaluationType::Abet` already reserved |
| Metrics DB | `entregas.evaluation_metrics` free text (coordinador) — usable as complementary context |
| UI Director | `RevisionEntregaDirector` — natural place; no AI UI yet |
| AI core | Must stay untouched (`AiGateway`, providers, composer) |

## Alternatives evaluated

| Option | Verdict |
|--------|---------|
| A. Solo nueva Strategy | Rejected alone — access + parser still student-coupled |
| B. Nueva tabla `evaluation_profiles` | Rejected for v1 — overbuilt; metrics sets can live as code definitions |
| C. Factory/Registry de proveedores IA | Rejected — already exists; not the right layer |
| D. **Evaluation Definition = Strategy + MetricsDefinition + AccessResolver + ResultInterpreter** | **Adopted** — Open/Closed, minimal refactor, fits existing architecture |

## Superior alternative (documented justification)

Desacoplar del orquestador únicamente lo que varía por tipo de evaluación:

1. **Quién puede evaluar** → `EvaluationAccessResolver`
2. **Qué métricas/criterios** → `EvaluationMetricsDefinition`
3. **Cómo se parsea el JSON** → `EvaluationResultInterpreter`
4. **Prompt/contexto** → `EvaluationPromptStrategy` (ya existía)

El núcleo IA y el pipeline documental permanecen iguales. PreSubmission se reexpresa como una definición concreta (sin cambiar comportamiento). ABET es otra definición. Futuros tipos (institucional, competencias) = nuevas clases, cero cambios a `AiGateway` / converter.

## Architecture

```text
Director UI (RevisionEntregaDirector + EvaluacionAbetPanel)
    → POST /api/director/entregas/{id}/evaluacion-abet
        → EvaluacionAbetController
            → DocumentEvaluationService::evaluate(
                 strategy: AbetDirectorEvaluationStrategy,
                 access: DirectorEntregaAccessResolver,
                 interpreter: AbetEvaluationResultInterpreter,
               )
                 ├─ DocxToMarkdownConverter
                 ├─ EvaluationContext (+ metrics from PlaceholderAbetMetrics)
                 ├─ AiPromptComposer + AiGateway
                 ├─ interpret → result_json
                 └─ AiDocumentEvaluation type=abet
```

### Metrics (NOT definitive ABET)

`PlaceholderAbetMetricsDefinition` (`abet_placeholder_v1`) exposes a small provisional set of student outcomes / criteria for prompt scaffolding only. Replacing with definitive ABET = swap the metrics class (or compose with entrega free-text metrics) without touching the orchestrator.

### Structured ABET result

```json
{
  "resumen_ejecutivo": "string",
  "criterios_evaluados": [
    {
      "id": "string",
      "nombre": "string",
      "cumplimiento": "alto|medio|bajo|no_evidencia",
      "evidencias": ["string"],
      "observaciones": "string"
    }
  ],
  "fortalezas": ["string"],
  "oportunidades_mejora": ["string"],
  "observaciones": ["string"],
  "recomendaciones": ["string"],
  "riesgos": ["string"],
  "conclusion": "string",
  "perfil_metricas": "abet_placeholder_v1"
}
```

### Persistence

**Reuse** `ai_document_evaluations` with `type=abet`. No new tables. Audit/trazabilidad already covered (hash, provider, timing, errors, result_json).

### Without provider

Same contract as Evaluador Inteligente: full pipeline → `NullAiProvider` → HTTP **503** `{ code: "ai_unavailable", error: "..." }`.

### Frontend

Extract `EvaluacionAbetPanel` (keeps page under line budget) and mount it in `RevisionEntregaDirector` after the document card. Uses selected version id. Friendly 503 banner.

## How to add a future evaluation type

1. Implement `EvaluationMetricsDefinition` (criteria set).
2. Implement `EvaluationPromptStrategy` (prompt + context sections).
3. Implement `EvaluationResultInterpreter` (JSON shape).
4. Implement/reuse `EvaluationAccessResolver` (who can run it).
5. Thin controller + route + optional UI panel.
6. **Do not** modify `AiGateway`, converter, or composer.
