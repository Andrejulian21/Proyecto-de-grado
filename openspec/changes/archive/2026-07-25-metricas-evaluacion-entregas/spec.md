# Spec: Métricas de evaluación en entregas

## ME-001 — Persistencia del campo
**WHEN** an authenticated Coordinador creates an entrega with optional `metricas_evaluacion`  
**THE SYSTEM SHALL** store the text in `entregas.evaluation_metrics` and return it in the API response.

## ME-002 — Campo opcional
**WHEN** an entrega is created or updated without métricas  
**THE SYSTEM SHALL** accept the request and keep `evaluation_metrics` null (or unchanged if not sent on update).

## ME-003 — Edición carga valor
**WHEN** the Coordinador opens the edit form for an entrega that has `evaluation_metrics`  
**THE SYSTEM SHALL** prefill the «Métricas de evaluación» textarea with the stored value.

## ME-004 — Actualización
**WHEN** an authenticated Coordinador updates an entrega with `evaluation_metrics`  
**THE SYSTEM SHALL** persist the new value (including clearing to null when an empty value is sent).

## ME-005 — Compatibilidad
**THE SYSTEM SHALL** leave existing entregas without metrics valid and queryable; the new column SHALL be nullable.

## ME-006 — Separación de conceptos
**THE SYSTEM SHALL NOT** overwrite or reuse `acceptance_criteria` for métricas de evaluación; both fields SHALL coexist independently.

## ME-007 — Ayuda contextual (tooltip)
**WHEN** the user hovers the help control next to «Métricas de evaluación»  
**THE SYSTEM SHALL** show the brief tip: «Guía para redactar métricas de evaluación.»

## ME-008 — Modal de guía
**WHEN** the user activates the help control  
**THE SYSTEM SHALL** open a centered modal titled «¿Cómo redactar buenas métricas de evaluación?» with the practical guide and examples list, and a «Cerrar» action that dismisses it.

## ME-009 — Sin IA
**THE SYSTEM SHALL NOT** invoke AI services or generate automatic feedback as part of this change.

## ME-010 — Autorización intacta
**THE SYSTEM SHALL** continue to allow only Coordinador to create/update entregas via `/api/admin/entregas`; other roles remain denied as today.
