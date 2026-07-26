# Spec: Integración flujo Evaluaciones IA

## Requirements

#### REQ-IF-001 — Student selector screen
The Sidebar entry for student IA analysis SHALL open a selection screen that shows project phases and entregas in the same visual pattern as `EstudianteDashboard`, reusing existing stepper/accordion components.

#### REQ-IF-002 — Navigation to analysis
WHEN the student chooses an entrega, the app SHALL navigate to the analysis screen with that entrega identified (route param) and contextual data available via React Router state (Architecture: SPA, not Inertia).

#### REQ-IF-003 — Temporary DOCX only for student analysis
The student analysis screen SHALL accept a temporary DOCX upload used solely for IA evaluation. The system SHALL NOT create a `VersionDocumento` nor alter official delivery history.

#### REQ-IF-004 — Pipeline reuse
Temporary and official evaluations SHALL use `DocumentEvaluationService` + existing strategies/gateway/converter.

#### REQ-IF-005 — Persistence for temp runs
Temp evaluations MAY be persisted in `ai_document_evaluations` with null `version_documento_id` and a valid `entrega_id`.

#### REQ-IF-006 — Director official document only
Director ABET evaluation SHALL use only an official uploaded version linked to the entrega. The UI SHALL NOT offer manual file upload for ABET.

#### REQ-IF-007 — Controlled AI failures
Without a configured provider, both flows SHALL return the existing friendly 503 `ai_unavailable` contract.

### Scenarios

#### Student temp analysis
GIVEN an authenticated student and a selected entrega  
WHEN they upload a DOCX and request evaluation  
THEN the system SHALL return structured pre_submission feedback AND SHALL NOT create a new document version.

#### Student selector
GIVEN Sidebar → Análisis  
WHEN the page loads  
THEN phases/entregas SHALL be shown and selecting an entrega SHALL open the analysis route for that id.

#### Director ABET
GIVEN a Director on entrega revision with an official DOCX version  
WHEN they run ABET evaluation  
THEN the system SHALL analyze that version only (no upload field).
