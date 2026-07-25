# Capability: evaluador-dashboard-api

## Requirements

### Requirement: Assigned evaluations list
The system SHALL expose `GET /api/evaluador/evaluaciones` returning projects assigned to the authenticated user via `evaluador_proyecto` in active semesters.

#### Scenario: Evaluator with assignments
- **WHEN** the user has one or more `evaluador_proyecto` rows for active-semester projects
- **THEN** the response `data` array contains those projects with code, title, director, students, phase, dates, evaluation_status

#### Scenario: Evaluator without assignments
- **WHEN** the user has no assignments
- **THEN** the response `data` is an empty array

#### Scenario: Isolation
- **WHEN** another evaluator is assigned to a different project
- **THEN** that project MUST NOT appear in the current user's list

### Requirement: KPIs
The system SHALL expose `GET /api/evaluador/kpis` with `proyectos_asignados`, `evaluaciones_pendientes`, `evaluaciones_completadas`.

### Requirement: Entrega by phase
The system SHALL expose `GET /api/evaluador/proyectos/{id}/entrega-fase` gated by assignment, returning approved entrega + versiones or 404/403.
