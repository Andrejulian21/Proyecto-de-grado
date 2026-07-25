# Spec: Integración módulo Evaluador

## Domain: evaluador-dashboard-api

### AD-001 — List assigned projects
**WHEN** an authenticated user with role EvaluadorExterno (or any user assigned in `evaluador_proyecto`) requests `GET /api/evaluador/evaluaciones`  
**THE SYSTEM SHALL** return only projects linked to that user via `evaluador_proyecto` in active semesters, including code, title, director (nullable), students, assigned phase/date, `assigned_at`, `evaluation_status`, and optional `rating`.

### AD-002 — KPIs
**WHEN** the same user requests `GET /api/evaluador/kpis`  
**THE SYSTEM SHALL** return counts: `proyectos_asignados`, `evaluaciones_pendientes`, `evaluaciones_completadas` consistent with the list endpoint semantics.

### AD-003 — Phase delivery for grading
**WHEN** an assigned evaluator requests `GET /api/evaluador/proyectos/{id}/entrega-fase?fase=`  
**THE SYSTEM SHALL** return the approved entrega for that phase with document versions if the user is assigned; otherwise 403. If no approved entrega exists, return 404 with a Spanish error message.

### AD-004 — Isolation
**WHEN** a user requests evaluator endpoints  
**THE SYSTEM SHALL NOT** include projects assigned only to other evaluators.

## Domain: evaluador-ui

### UI-001 — Dashboard real data
**WHEN** EvaluadorExterno opens `/dashboard/evaluador-externo`  
**THE SYSTEM SHALL** render KPIs and cards from the evaluator API with no hardcoded mock project list.

### UI-002 — Navigation
**WHEN** the user activates "Evaluar proyecto" on a pending card  
**THE SYSTEM SHALL** navigate to `/evaluaciones/{proyectoId}` using the real project id.

### UI-003 — Evaluated navigation
**WHEN** the user activates "Ver evaluación" on an evaluated card  
**THE SYSTEM SHALL** navigate to `/evaluaciones/{proyectoId}/calificar` (or the detail route with loaded grades) using the real project id.

### UI-004 — Detail pages real data
**WHEN** EvaluarProyecto or EvaluadorCalificar loads with a valid `:id`  
**THE SYSTEM SHALL** display project code/title, students, director, and document from the API (not mock strings).

### UI-005 — Missing data messages
**WHEN** a displayed field cannot be resolved from the database  
**THE SYSTEM SHALL** show `"<Dato> no se ha podido encontrar."` (e.g. `El director no se ha podido encontrar.`, `La modalidad no se ha podido encontrar.`) and SHALL NOT leave the field blank or substitute mock values.

### UI-006 — Grade submission
**WHEN** the evaluator submits the rubric/criteria with a valid approved entrega  
**THE SYSTEM SHALL** persist grades via `POST /api/evaluaciones` and show the existing success UI.

### UI-007 — Sidebar
**WHEN** EvaluadorExterno uses the sidebar  
**THE SYSTEM SHALL** link Panel/Evaluaciones to routes reachable by that role (not Director-only `/evaluaciones` list).

## Non-requirements
- Modalidad column / migration.
- Visual redesign.
- Changes to Coordinador assignment UI.
