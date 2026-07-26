# Spec: Evaluador Inteligente de Entregas

## EIE-001 — Flujo completo
**WHEN** an authenticated Estudiante requests intelligent evaluation for a DOCX version of their entrega  
**THE SYSTEM SHALL** convert the file with `DocxToMarkdownConverter`, build context (including coordinator `evaluation_metrics` when present), assemble prompts via the pre-submission strategy + `AiPromptComposer`, call `AiGateway`, and return a structured result or a controlled error.

## EIE-002 — Sin proveedor directo
**THE SYSTEM SHALL NOT** call OpenAI, Azure, Gemini, or any vendor SDK from the Evaluador Inteligente; communication SHALL go only through `AiGateway` / `AiProvider`.

## EIE-003 — Sin mocks
**THE SYSTEM SHALL NOT** return simulated analysis content when the AI provider is unavailable.

## EIE-004 — Proveedor no configurado
**WHEN** the gateway fails with provider-not-configured (or equivalent AI unavailability)  
**THE SYSTEM SHALL** respond with HTTP 503 and a clear Spanish message that the AI service could not be reached, without exposing technical exceptions to the UI.

## EIE-005 — Solo DOCX
**WHEN** the selected version is not DOCX  
**THE SYSTEM SHALL** reject the request with HTTP 422 and a Spanish message indicating only DOCX is supported for intelligent analysis.

## EIE-006 — Autorización
**WHEN** the student is not a member of the entrega’s project  
**THE SYSTEM SHALL** deny the request (403 or 404 consistent with existing student APIs).

## EIE-007 — Resultado estructurado
**WHEN** evaluation completes successfully  
**THE SYSTEM SHALL** return at least: resumen, fortalezas, aspectos_mejorar, errores, recomendaciones, conclusion (plus optional prioridades/confianza/puntaje_orientativo when present).

## EIE-008 — Persistencia genérica
**THE SYSTEM SHALL** persist each attempt in `ai_document_evaluations` with type, status, provider, document hash, timing, result or error — reusable for future evaluation types.

## EIE-009 — UI existente
**THE SYSTEM SHALL** integrate the real flow into `AnalisisAutomaticoEntregas` without a full redesign, removing mock analysis data.

## EIE-010 — Extensibilidad
**THE SYSTEM SHALL** isolate pre-submission prompt logic in a strategy so future ABET/Director evaluations can reuse the same orchestrator.
