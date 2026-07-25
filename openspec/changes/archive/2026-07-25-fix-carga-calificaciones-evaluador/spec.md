# Spec: Fix carga calificaciones evaluador

## LC-001 — Hydrate from stored rows
**WHEN** an EvaluadorExterno opens a project that already has `evaluaciones` rows for that entrega  
**THE SYSTEM SHALL** display each stored `criterio` with its stored `grade` (not default zeros).

## LC-002 — Total score
**WHEN** stored grades are displayed  
**THE SYSTEM SHALL** show a total equal to the sum of those grades.

## LC-003 — Observation
**WHEN** any stored row has a `comment`  
**THE SYSTEM SHALL** show that comment in the observation field.

## LC-004 — EvaluarProyecto reload
**WHEN** `EvaluarProyecto` loads an already graded assignment  
**THE SYSTEM SHALL** hydrate the rubric from stored rows and present it read-only.

## LC-005 — No schema change
**THE SYSTEM SHALL NOT** require migrations; reconstruction uses existing `GET /api/evaluaciones?entrega_id=`.
