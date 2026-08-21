# Spec: Historial de retroalimentación de análisis IA por versión

> Change: `historial-retroalimentacion-ia-versiones` | Entrega → documento → versión → análisis IA

## Decisiones

| # | Decisión | Resolución |
|---|----------|-----------|
| D1 | Persistencia | Se **reutiliza** `ai_document_evaluations`. No hay tabla nueva de “análisis”. |
| D2 | Asociación | Cada fila tiene `entrega_id`, `archivo_requerido_id` (documento) y `version_documento_id` nullable. |
| D3 | Historial | Cada ejecución **inserta**. Prohibido update/delete de `result_json` de filas completed anteriores. |
| D4 | Temporal | Archivo DOCX temporal: `version_documento_id = NULL`. No se crea `VersionDocumento`. |
| D5 | Vínculo posterior | Al subir una versión del documento IA, se asigna `version_documento_id` a análisis temporales completed del mismo `document_hash`. Si el hash no coincide, no se inventa la relación. |
| D6 | Históricos | Backfill: si hay versión, copiar su `archivo_requerido_id`; si no, el documento IA de la entrega (si existe). Sin conjeturas de versión. |
| D7 | Fecha | `analizado_en` = `created_at` ISO-8601. |
| D8 | Separación | Observación = `versiones_documento.director_notes`. IA = `result_json`. Nunca se copian entre sí. |
| D9 | Cambio 04 | Prompt preliminar + descripción de la entrega. Sin métricas ni nota académica. |
| D10 | Cambio 05 | Solo el documento `analizable_ia` puede analizarse o mostrar UI de IA. |
| D11 | Specs históricas | `openspec/specs/` no se modifica. |

---

## Capacidad: historial-analisis-ia-por-version

### RF-HIST-01: Asociación inequívoca

WHEN se completa un análisis IA de una versión persistida, the system SHALL guardar una fila en `ai_document_evaluations` con `entrega_id`, `archivo_requerido_id` del documento analizable AND `version_documento_id` de esa versión.

#### Escenario: Versión del documento IA

- GIVEN una entrega con documentos A (IA) y B (no IA) AND una versión de A
- WHEN POST análisis con `version_id` de A
- THEN 200
- AND la fila tiene `documento_id` = A AND `version_id` = esa versión
- AND `analizado_en` no es nulo

### RF-HIST-02: Documento no analizable

WHEN se solicita análisis de una versión cuyo documento no es `analizable_ia`, the system SHALL responder 422 (`document_not_analyzable`) AND SHALL NOT invocar el proveedor AND SHALL NOT persistir una fila completed.

### RF-HIST-03: Sin documento IA en la entrega

WHEN la entrega no tiene documento `analizable_ia`, the system SHALL rechazar análisis (versión o temporal) con 422.

### RF-HIST-04: Historial no destructivo

WHEN existe un análisis completed de una versión AND se ejecuta un nuevo análisis de la misma versión, the system SHALL insertar una fila nueva AND SHALL NOT borrar ni vaciar el `result_json` de la fila anterior.

#### Escenario: Dos análisis de v1

- GIVEN v1 con un análisis completed
- WHEN se analiza de nuevo v1
- THEN hay 2 filas completed para esa versión
- AND ambas conservan su retroalimentación y fecha

### RF-HIST-05: Análisis temporal

WHEN el estudiante envía un DOCX temporal (sin `version_id`), the system SHALL convertir con `DocxToMarkdownConverter`, ejecutar el análisis preliminar (descripción de la entrega como contexto) AND persistir con `archivo_requerido_id` del documento IA AND `version_documento_id` NULL.

The system MUST NOT crear `VersionDocumento`.

### RF-HIST-06: Asociación cuando corresponde

WHEN el estudiante sube después una versión oficial del documento IA cuyo `sha256` coincide con `document_hash` de análisis temporales completed de ese documento, the system SHALL asignarles `version_documento_id` de la nueva versión.

WHEN el hash no coincide, the system SHALL dejar esos análisis sin versión.

### RF-HIST-07: Validación de pertenencia

WHEN `version_id` no pertenece a la entrega, o no pertenece al documento analizable, the system SHALL rechazar (404/422) AND SHALL NOT invocar el proveedor.

### RF-HIST-08: Infraestructura existente

WHEN se analiza, the system SHALL usar `DocumentEvaluationService` → conversor → `PreliminaryAnalysisPrompt` → `AiGateway`. The system SHALL NOT introducir proveedor, orquestador ni métricas configurables.

---

## Capacidad: consulta-retroalimentacion-ia

### RF-CONS-01: Estudiante consulta por versión

WHEN un estudiante autenticado del proyecto solicita el historial IA de una entrega (y opcionalmente `version_id`), the system SHALL devolver los análisis completed del documento IA, filtrados a esa versión si se indicó, con `resultado`, `analizado_en`, `documento_id` y `version_id`.

#### Escenario: Navegar entre versiones

- GIVEN v1 y v2 del documento IA, cada una con su análisis
- WHEN se consulta con `version_id` = v1
- THEN solo aparece la retroalimentación de v1
- WHEN se consulta con `version_id` = v2
- THEN solo aparece la de v2

### RF-CONS-02: Director consulta por versión

WHEN un director del proyecto hace GET `evaluacion-abet` con `version_id`, the system SHALL devolver el último completed de **esa** versión AND el historial de esa versión. The system SHALL NOT devolver el análisis de otra versión como si fuera de la seleccionada.

### RF-CONS-03: Detalle de entrega

WHEN estudiante o director carga el detalle de la entrega, each versión del documento IA MUST incluir su lista `analisis_ia` (completed) AND su `director_notes` / observación por separado.

### RF-CONS-04: Autorización

WHEN un usuario no es estudiante del proyecto ni director de ese proyecto (ni coordinador en show admin), the system SHALL denegar (401/403/404).

### RF-CONS-05: Separación visual y de datos

The observación del director MUST NOT copiarse a `result_json` AND the retroalimentación IA MUST NOT escribirse en `director_notes`.

The UI MUST titular “Observación del director” y “Retroalimentación de IA” (o equivalente) AND MUST mostrar la fecha del análisis IA AND MUST aclarar que la IA es informativa / no es calificación académica.

### RF-CONS-06: Documento sin IA

WHEN un documento no tiene `analizable_ia`, the UI MUST NOT mostrar sección, botones ni mensajes genéricos de análisis IA para ese documento.

### RF-CONS-07: Cambio 04 intacto

The análisis MUST usar la descripción de la entrega AND MUST NOT exponer `puntaje_orientativo` ni `perfil_metricas` / criterios como calificación.
