# Proposal: Simplificación de la evaluación de entregas mediante IA

## Intent

La evaluación mediante IA deja de construirse con métricas configurables por el coordinador y pasa a ser un **análisis preliminar y superficial** del documento. El único contexto de lo esperado es la **descripción de la entrega**. El resultado es orientativo y no sustituye la evaluación académica del director.

## Scope

### In Scope

- Eliminar el concepto de métricas configurables del flujo de creación, edición y evaluación de entregas (formulario, validaciones, requests, actions, prompts, types y UI).
- Usar la descripción de la entrega como contexto principal del análisis IA (junto al Markdown del documento convertido).
- Reformular el prompt del evaluador inteligente (estudiante) y del análisis IA del director para que solicite observaciones generales, no una calificación ni resultados por métrica.
- Adaptar interfaces de coordinador, estudiante y director: lenguaje de “análisis preliminar / retroalimentación preliminar”.
- Conservar la columna `entregas.evaluation_metrics` y los `result_json` históricos (sin migración destructiva).
- Tests Pest de creación sin métricas, contexto de descripción, ausencia de nota académica y no dependencia del flujo de métricas.

### Out of Scope

- Modificar specs existentes en `openspec/specs/` u otros changes.
- Nuevo proveedor IA o cambio del desacoplamiento `AiGateway` / `AiProvider`.
- Cambiar el conversor DOCX → Markdown ni acoplarlo a la IA.
- Evaluación académica del director (`director_grade`), rúbrica del evaluador externo, pesos `grade_percentage`, criterios de aceptación como regla de negocio.
- Borrar físicamente la columna `evaluation_metrics` ni filas de `ai_document_evaluations`.
- Mocks que sustituyan la base de datos.

## Capabilities

### New Capabilities

- `analisis-preliminar-ia`: El análisis IA de una entrega es preliminar, usa la descripción como contexto de lo esperado y no emite calificación académica.

### Modified Capabilities

- `creacion-entregas-sin-metricas`: Crear/editar una entrega no exige ni persiste métricas configurables; la descripción explica qué debe entregar el estudiante.
- `evaluador-inteligente-estudiante`: El análisis previo a la entrega oficial deja de usar métricas y puntaje orientativo.
- `analisis-ia-director`: El panel del director deja de mostrar evaluación por criterios/métricas ABET y muestra el mismo tipo de análisis preliminar.

## Approach

Reutilizar `DocumentEvaluationService`, `DocxToMarkdownConverter`, `AiPromptComposer` y `AiGateway`. Extraer un prompt compartido de análisis preliminar (estudiante y director) para no duplicar lógica. Dejar de aceptar `metricas_evaluacion` / `evaluation_metrics` en FormRequests y de inyectarlos en el contexto. No drop de columna.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Http/Requests/StoreEntregaRequest.php` | Modified | Quitar reglas de métricas. |
| `app/Http/Requests/UpdateEntregaRequest.php` | Modified | Quitar reglas de métricas; no sobrescribir históricos. |
| `app/Actions/Entrega/*` | Modified | No escribir `evaluation_metrics` en create/update. |
| `app/Services/Evaluation/*` | Modified | Contexto = descripción + Markdown; prompt preliminar. |
| `app/Http/Controllers/Api/EvaluacionInteligenteController.php` | Modified | Mismo orquestador; resultado sin nota. |
| `app/Http/Controllers/Api/EvaluacionAbetController.php` | Modified | Mismo análisis preliminar; sin perfil de métricas. |
| `app/Http/Controllers/Api/EstudianteController.php` | Modified | Listado expone descripción, no métricas. |
| `resources/js/pages/coordinador/CoordinadorEntregas.tsx` | Modified | Quitar campo y listado de métricas. |
| `resources/js/components/forms/MetricasEvaluacionField.tsx` | Delete | Componente de métricas. |
| `resources/js/pages/estudiante/AnalisisAutomaticoEntregas.tsx` | Modified | Retroalimentación preliminar sin puntaje. |
| `resources/js/components/director/EvaluacionAbetPanel.tsx` | Modified | Análisis preliminar; sin criterios por métrica. |
| `tests/Feature/Api/` y `tests/Feature/Admin/` | New/Modified | Cobertura de los 10 criterios. |

## Assumptions

1. “Métricas configurables” = campo libre `evaluation_metrics` / UI `MetricasEvaluacionField` y el perfil ABET placeholder usado para construir el prompt.
2. “Descripción” = `entregas.description` (payload `descripcion`), ya requerida al crear.
3. El conversor DOCX → Markdown sigue siendo independiente de la IA.
4. Estudiante y director comparten el mismo tipo de análisis; el endpoint del director se mantiene por compatibilidad de rutas.
5. Datos históricos de métricas y de `result_json` antiguos pueden permanecer; la UI nueva no los presenta como calificación ni como resultados por métrica.

## Non-Goals

- No migraciones contra la BD local real.
- No un sistema alternativo de rúbricas/métricas para la IA.
- No cambiar RBAC ni el pipeline de proveedores.
- No modificar `openspec/specs/`.

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Resultados IA antiguos (ABET / puntaje) se muestran como nota | Med | UI no renderiza puntaje ni criterios por métrica; parser nuevo no expone calificación |
| Coordinador pierde un canal para “instruir” a la IA | Med | La descripción pasa a ser ese canal, con copy en el formulario |
| Tests de ABET y evaluador inteligente se rompen | High | Actualizar contratos de resultado en los mismos tests |

## Rollback Plan

Revertir el commit del change. La columna `evaluation_metrics` no se elimina; los datos históricos siguen ahí. Restaurar FormRequests y prompts anteriores.

## Success Criteria

- [ ] Una entrega se crea sin configurar métricas; la descripción se persiste.
- [ ] El prompt IA incluye la descripción y no métricas configurables.
- [ ] El resultado tiene observaciones generales y no una calificación académica.
- [ ] La UI de creación no muestra métricas; la de análisis no muestra resultados por métrica ni puntaje.
- [ ] El análisis sigue usando `AiGateway` / proveedores existentes y el conversor DOCX independiente.
- [ ] `entregas.evaluation_metrics` históricos no se borran al actualizar otros campos.
- [ ] Suite Pest del change en verde; no se tocan specs existentes.
