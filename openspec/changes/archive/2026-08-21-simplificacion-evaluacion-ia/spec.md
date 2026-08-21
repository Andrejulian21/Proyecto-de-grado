# Spec: Simplificación de la evaluación de entregas mediante IA

> Change: `simplificacion-evaluacion-ia` | Análisis preliminar; descripción como único contexto de lo esperado

## Decisiones

| # | Decisión | Resolución |
|---|----------|-----------|
| D1 | Qué se elimina | El campo/UI de métricas configurables (`metricas_evaluacion` / `evaluation_metrics` en create/update) y el perfil de métricas ABET placeholder como insumo del prompt. |
| D2 | Qué permanece en BD | Columna `entregas.evaluation_metrics` (nullable). No hay migración `dropColumn`. Filas históricas de `ai_document_evaluations` intactas. |
| D3 | Contexto IA | Descripción de la entrega + Markdown del DOCX. Identificación (título, fase, proyecto, archivo). No métricas ni criterios de aceptación como rúbrica de IA. |
| D4 | Alcance del análisis | Preliminar y superficial: coherencia, claridad, estructura, completitud aparente, correspondencia con lo solicitado, observaciones generales. |
| D5 | Calificación | La IA MUST NOT emitir nota, puntaje, aprobado/reprobado ni lenguaje de evaluación definitiva. |
| D6 | Prompt compartido | Estudiante (pre-envío) y director reutilizan el mismo cuerpo de instrucciones; `AiEvaluationType` distingue el origen. |
| D7 | Columna histórica en update | Si el cliente envía `metricas_evaluacion`, se ignora. Un update de otros campos MUST NOT vaciar `evaluation_metrics` existente. |
| D8 | Límite de descripción | `descripcion` sigue siendo requerida; el máximo se amplía a 2000 caracteres para servir como contexto de lo esperado. |

---

## Capacidad: creacion-entregas-sin-metricas

### RF-MET-01: Crear entrega sin métricas

WHEN a Coordinador envía `POST /api/admin/entregas` con título, descripción, fechas y archivos requeridos válidos AND sin `metricas_evaluacion` ni `evaluation_metrics`, the system SHALL crear la entrega AND persistir `description` AND SHALL NOT exigir métricas.

#### Escenario: Creación solo con descripción

- GIVEN un coordinador autenticado
- WHEN POST con `descripcion` y sin campos de métricas
- THEN 201
- AND `entregas.description` coincide con el payload
- AND `entregas.evaluation_metrics` es NULL

### RF-MET-02: Campos de métricas ignorados en escritura

WHEN el payload incluye `metricas_evaluacion` o `evaluation_metrics`, the system SHALL ignorarlos (no validarlos como parte del contrato de escritura AND no persistirlos en create).

#### Escenario: Create con métricas en el body

- GIVEN POST con `metricas_evaluacion` = "Evaluar objetivos ABET"
- THEN la entrega se crea
- AND `evaluation_metrics` permanece NULL

### RF-MET-03: Update no borra métricas históricas

WHEN a Coordinador actualiza título, descripción u otros campos mutables AND no se escribe `evaluation_metrics` desde el action, the system SHALL dejar intacto el valor histórico de `evaluation_metrics`.

#### Escenario: Update de descripción con métricas previas

- GIVEN una entrega con `evaluation_metrics` no nulo
- WHEN PUT cambia `descripcion`
- THEN `evaluation_metrics` no cambia
- AND `description` se actualiza

### RF-MET-04: Descripción almacenada y visible

WHEN se crea o actualiza una entrega, the system SHALL persistir `descripcion` en `entregas.description`.

The listado de estudiante MUST incluir `descripcion` AND MUST NOT incluir `metricas_evaluacion` como campo de contrato.

---

## Capacidad: analisis-preliminar-ia

### RF-IA-01: Descripción como contexto del prompt

WHEN el orquestador construye el prompt (estudiante o director), the system SHALL incluir la descripción de la entrega como sección de “lo esperado” AND SHALL NOT incluir métricas configurables ni el perfil ABET placeholder.

#### Escenario: El documento se analiza contra la descripción

- GIVEN una entrega con descripción “planteamiento del problema…”
- WHEN se ejecuta el análisis con proveedor stub
- THEN el mensaje de usuario enviado al `AiProvider` contiene esa descripción
- AND no contiene el texto de `evaluation_metrics` aunque exista en BD

### RF-IA-02: Documento Markdown vía conversor existente

WHEN se analiza un DOCX (versión oficial o archivo temporal), the system SHALL convertir con `DocxToMarkdownConverter` AND pasar el Markdown al prompt. The converter MUST remain independent of AI providers.

### RF-IA-03: Resultado preliminar sin calificación

WHEN el análisis completa, the system SHALL devolver observaciones generales (resumen, aspectos de coherencia/claridad/estructura/completitud/correspondencia, recomendaciones, conclusión) AND SHALL NOT incluir `puntaje_orientativo`, nota, aprobado/reprobado ni `perfil_metricas` / `criterios_evaluados` en el contrato nuevo.

#### Escenario: Stub sin puntaje

- GIVEN un JSON de proveedor con observaciones y sin calificación
- WHEN POST evaluación inteligente
- THEN 200 AND `resultado` tiene observaciones
- AND `resultado` no tiene `puntaje_orientativo`

#### Escenario: Stub que intenta devolver puntaje

- GIVEN un JSON de proveedor que incluye `puntaje_orientativo`
- WHEN se interpreta
- THEN la respuesta al cliente no expone una calificación académica (`puntaje_orientativo` ausente o nulo no mostrado como nota)

### RF-IA-04: Infraestructura IA existente

WHEN se ejecuta el análisis, the system SHALL usar `DocumentEvaluationService` → `AiPromptComposer` → `AiGateway` → `AiProvider` registrado. The system SHALL NOT introducir un proveedor nuevo.

### RF-IA-05: Rol de la IA

The system instructions MUST indicar que el análisis es preliminar, no sustituye al director y no asigna calificación.

---

## Capacidad: evaluador-inteligente-estudiante

### RF-EST-01: Análisis previo a la entrega oficial

WHEN un estudiante autenticado envía `POST /api/estudiante/entregas/{id}/evaluacion-inteligente` con DOCX temporal o `version_id`, the system SHALL ejecutar el análisis preliminar (RF-IA-01 a RF-IA-04).

The archivo temporal MUST NOT crear `VersionDocumento`.

### RF-EST-02: UI sin métricas ni puntaje

The pantalla de análisis MUST mostrar la descripción de la entrega (no “métricas de evaluación”) AND MUST presentar el resultado como análisis/retroalimentación preliminar AND MUST NOT mostrar puntaje / 100 ni checklist de aprobación académica.

The UI MUST aclarar que la evaluación de IA es orientativa y no reemplaza la del director.

---

## Capacidad: analisis-ia-director

### RF-DIR-IA-01: Mismo análisis preliminar

WHEN un director autenticado que dirige el proyecto llama `POST /api/director/entregas/{id}/evaluacion-abet`, the system SHALL usar el prompt preliminar (D6), no el perfil de métricas ABET.

The respuesta MUST NOT incluir `perfil_metricas` como contrato de evaluación por rúbrica.

### RF-DIR-IA-02: UI del director

The panel MUST titularse o describirse como análisis preliminar de IA (o equivalente) AND MUST NOT listar criterios/métricas con cumplimiento alto|medio|bajo como evaluación académica.

The UI MUST aclarar que no reemplaza la evaluación del director.

### RF-DIR-IA-03: Resultados históricos ABET

WHEN `GET` devuelve un `result_json` antiguo con `criterios_evaluados`, the UI SHALL NOT presentarlos como resultados por métrica de evaluación académica. MAY mostrar resumen/observaciones/conclusión cualitativos si existen.
