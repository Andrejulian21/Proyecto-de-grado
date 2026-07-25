# Spec: Módulo Evaluaciones Evaluador

## EV-NAV-001
**WHEN** EvaluadorExterno clicks "Panel de Control"  
**THE SYSTEM SHALL** navigate to `/dashboard/evaluador-externo`.

## EV-NAV-002
**WHEN** EvaluadorExterno clicks "Evaluaciones"  
**THE SYSTEM SHALL** navigate to `/evaluador/evaluaciones` (distinct page).

## EV-LIST-001
**WHEN** the Evaluaciones page loads  
**THE SYSTEM SHALL** list only projects assigned to the authenticated evaluator from the existing evaluator API (no mocks).

## EV-LIST-002
Each row SHALL show project name/code, students, director, modalidad (or missing-data message), phase, assignment date, and evaluation status (Pendiente | Evaluada).

## EV-ACT-001
**WHEN** status is pending  
**THE SYSTEM SHALL** offer "Evaluar" → `/evaluaciones/:id`.

## EV-ACT-002
**WHEN** status is evaluated  
**THE SYSTEM SHALL** offer "Ver evaluación" → `/evaluaciones/:id/calificar`.

## EV-UX-001
**THE SYSTEM SHALL** provide search (project/student/director), status filter, pending/done counts, sort by assignment date, results count, and empty state.
