# Design: Simplificación de la evaluación de entregas mediante IA

## Enfoque técnico

Hoy el coordinador escribe **métricas de evaluación** (`entregas.evaluation_metrics`) y el prompt de pre-envío las usa como “guía principal”; el flujo del director inyecta además `PlaceholderAbetMetricsDefinition` y pide `criterios_evaluados` + `perfil_metricas`. El estudiante ve un **puntaje orientativo 0–100**.

Se elimina ese contrato de escritura y de prompt. El orquestador existente (`DocumentEvaluationService`) sigue igual: DOCX → Markdown → strategy → `AiGateway` → interpreter. Solo cambian contexto, instrucciones y forma del resultado.

## Decisiones de arquitectura

### D1 — Conservar la columna `evaluation_metrics`

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| `dropColumn` | Pierde histórico; requiere migrate en local (prohibido contra BD real) | ❌ |
| **Dejar la columna; dejar de escribirla** | Histórico intacto; el flujo nuevo no depende de ella | ✅ |

**Racional**: hay entregas reales con texto de métricas. El change no necesita borrarlas. Create no setea el campo (NULL). Update no lo toca aunque el cliente lo envíe (campos fuera de `validated()`).

No hay migración nueva.

### D2 — Prompt compartido, dos types

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| Dos prompts distintos (estudiante vs ABET) | Duplica el nuevo alcance | ❌ |
| **`PreliminaryAnalysisPrompt` usado por ambas strategies** | Un solo texto; `type()` / `promptVersion()` distinguen origen | ✅ |

- `PreSubmissionDeliveryStrategy`: `AiEvaluationType::PreSubmission`, `promptVersion = preliminary_analysis_v1`
- `AbetDirectorEvaluationStrategy`: `AiEvaluationType::Abet` (ruta existente), mismo prompt body

Ambas dejan de inyectar métricas. El director deja de depender de `EvaluationMetricsDefinition`.

### D3 — Forma del resultado

JSON pedido al modelo (sin nota):

```json
{
  "resumen": "string",
  "coherencia": "string",
  "claridad": "string",
  "estructura": "string",
  "completitud_aparente": "string",
  "correspondencia": "string",
  "observaciones": ["string"],
  "recomendaciones": ["string"],
  "conclusion": "string"
}
```

`StructuredEvaluationResult` y `EvaluationResultParser` se alinean a este contrato. Campos viejos (`puntaje_orientativo`, `fortalezas`, …) se ignoran en `toArray()` para no exponer calificación. Si el modelo devuelve puntaje, el parser no lo incluye en la respuesta.

El director usa el mismo interpreter (`PreSubmissionResultInterpreter` / parser compartido). `AbetEvaluationResultInterpreter` deja de usarse en el POST.

Clases de métricas ABET (`PlaceholderAbetMetricsDefinition`, `EvaluationMetricsDefinition`) dejan de formar parte del pipeline. Se retiran del wiring; no se mantiene un sistema paralelo oculto.

### D4 — `EvaluationContext`

Campos: Markdown, título, fase, proyecto, código, **descripción**, nombre de archivo. Se eliminan `evaluationMetrics` y `acceptanceCriteria` del DTO (ya no viajan al prompt).

### D5 — API de escritura de entregas

Quitar de `StoreEntregaRequest` / `UpdateEntregaRequest`:

- `metricas_evaluacion`
- `evaluation_metrics`

`StoreEntregaAction` / `UpdateEntregaAction` no asignan `evaluation_metrics`.

`descripcion`: `required` en store; `max:2000` (D8 spec). Placeholder de UI: qué debe entregar el estudiante (contexto de IA).

Listado estudiante (`EstudianteController`): `descripcion` sí; `metricas_evaluacion` no.

### D6 — UI

| Superficie | Cambio |
|------------|--------|
| `CoordinadorEntregas` | Quitar `MetricasEvaluacionField` y el bloque “Métricas IA” de las cards |
| `MetricasEvaluacionField.tsx` | Eliminar el archivo |
| `AnalisisAutomaticoEntregas` | Mostrar descripción; título “Análisis preliminar de IA”; sin puntaje / 100; disclaimer |
| `EvaluacionAbetPanel` | Mismo lenguaje; no renderizar `criterios_evaluados` ni `perfil_metricas` |
| Hooks/types | Quitar payloads de métricas en create/update |

### D7 — Independencia del conversor

`DocxToMarkdownConverter` no cambia. Sigue invocándose desde el orquestador antes de componer el prompt. Cero dependencia de proveedores.

## Data flow

```
CREAR ENTREGA
  POST /api/admin/entregas { descripcion, … }
    → StoreEntregaRequest (sin métricas)
    → StoreEntregaAction (description; evaluation_metrics no escrito)

ANÁLISIS IA (estudiante o director)
  DOCX (temporal o VersionDocumento)
    → DocxToMarkdownConverter
    → EvaluationContext(description, markdown, …)
    → PreliminaryAnalysisPrompt
    → AiPromptComposer → AiGateway → AiProvider
    → EvaluationResultParser (sin puntaje)
    → AiDocumentEvaluation.result_json
```

## Integridad de datos

- Create: `evaluation_metrics` queda NULL (default de columna).
- Update: el Action no incluye el atributo → Eloquent no lo modifica.
- Tests con `RefreshDatabase` / `:memory:` únicamente.
- Históricos de `ai_document_evaluations` con JSON ABET o puntaje: no se migran; la UI nueva no los presenta como nota ni como rúbrica.

## Archivos

| File | Action |
|------|--------|
| `openspec/changes/simplificacion-evaluacion-ia/*` | Create |
| `app/Services/Evaluation/Prompts/PreliminaryAnalysisPrompt.php` | Create |
| `app/Services/Evaluation/DTO/EvaluationContext.php` | Modify |
| `app/Services/Evaluation/DTO/StructuredEvaluationResult.php` | Modify |
| `app/Services/Evaluation/EvaluationResultParser.php` | Modify |
| `app/Services/Evaluation/DocumentEvaluationService.php` | Modify |
| `app/Services/Evaluation/Strategies/PreSubmissionDeliveryStrategy.php` | Modify |
| `app/Services/Evaluation/Strategies/AbetDirectorEvaluationStrategy.php` | Modify |
| `app/Http/Requests/StoreEntregaRequest.php` | Modify |
| `app/Http/Requests/UpdateEntregaRequest.php` | Modify |
| `app/Actions/Entrega/StoreEntregaAction.php` | Modify |
| `app/Actions/Entrega/UpdateEntregaAction.php` | Modify |
| `app/Http/Controllers/Api/EstudianteController.php` | Modify |
| `app/Http/Controllers/Api/EvaluacionAbetController.php` | Modify |
| `app/Http/Requests` attributes de métricas | Remove |
| `resources/js/components/forms/MetricasEvaluacionField.tsx` | Delete |
| `resources/js/pages/coordinador/CoordinadorEntregas.tsx` | Modify |
| `resources/js/hooks/useEntregas.ts` | Modify |
| `resources/js/hooks/useEstudianteEntregas.ts` | Modify |
| `resources/js/pages/estudiante/AnalisisAutomaticoEntregas.tsx` | Modify |
| `resources/js/components/director/EvaluacionAbetPanel.tsx` | Modify |
| `tests/Feature/Admin/EntregaSinMetricasTest.php` | Create |
| `tests/Feature/Api/EvaluacionInteligenteTest.php` | Modify |
| `tests/Feature/Api/EvaluacionAbetTest.php` | Modify |
| `tests/Unit/Services/Evaluation/PreliminaryAnalysisPromptTest.php` | Create |

Clases de métricas ABET no usadas tras el cambio: no se re-wirean. Si quedan huérfanas, se eliminan para no dejar un sistema paralelo.

## Permisos

Sin cambios: coordinador crea entregas; estudiante analiza las suyas; director analiza las de sus proyectos. Mismos resolvers de acceso.

## Open questions (resueltos en spec)

- ¿Drop de columna? No (D1).
- ¿Unificar endpoints estudiante/director? No: rutas existentes; prompt compartido (D2).
- ¿Criterios de aceptación en el prompt? No (D3 spec): solo descripción como lo esperado.
