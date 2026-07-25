# Capability: evaluador-ui

## Requirements

### Requirement: Dashboard without mocks
`EvaluadorDashboard` SHALL load KPIs and evaluation cards exclusively from evaluator APIs.

#### Scenario: Assigned projects visible
- **WHEN** the authenticated EvaluadorExterno has assignments
- **THEN** only those projects are rendered as cards

### Requirement: Navigation
Card actions SHALL navigate with the real project id to `/evaluaciones/:id` or `/evaluaciones/:id/calificar`.

### Requirement: Detail pages
`EvaluarProyecto` and `EvaluadorCalificar` SHALL use route param `:id`, fetch project/entrega data, and submit grades via `POST /api/evaluaciones`.

### Requirement: Missing data copy
Missing fields SHALL use the pattern `"<Dato> no se ha podido encontrar."` (Spanish), never empty strings or mock placeholders.
