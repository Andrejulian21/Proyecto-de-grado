# Spec: Fix validación envío evaluación

## EV-001 — Assignment via pivot
**WHEN** an authenticated evaluator assigned in `evaluador_proyecto` posts a grade for an entrega linked only through `entrega_proyecto` (null `entregas.proyecto_id`)  
**THE SYSTEM SHALL** accept the request (201) if other validations pass.

## EV-002 — Specific denial messages
**WHEN** `POST /api/evaluaciones` is rejected  
**THE SYSTEM SHALL** return a Spanish error that identifies the failing condition, and SHALL NOT use an “not assigned” message for unrelated failures.

## EV-003 — Unassigned user
**WHEN** the entrega belongs to a project that has evaluator assignments but not for the authenticated user  
**THE SYSTEM SHALL** respond 403 with: `El usuario autenticado no corresponde al evaluador asignado.`

## EV-004 — Project without evaluators
**WHEN** the entrega’s project(s) have no rows in `evaluador_proyecto`  
**THE SYSTEM SHALL** respond 403 with: `El proyecto no tiene un evaluador asignado.`

## EV-005 — Entrega without project link
**WHEN** the entrega has neither `proyecto_id` nor pivot rows  
**THE SYSTEM SHALL** respond 422 with: `La entrega no está vinculada a ningún proyecto.`

## EV-006 — Duplicate criterion
**WHEN** the same evaluator already graded the same criterion on the entrega  
**THE SYSTEM SHALL** respond 422 with: `La evaluación para este criterio ya fue enviada.`

## EV-007 — Security preserved
**THE SYSTEM SHALL** still require authentication and shall still deny grades when the user is not assigned to any linked project.
