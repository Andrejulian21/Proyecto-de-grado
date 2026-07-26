# Spec: Evaluación Inteligente ABET

## Capability: ABET-oriented document evaluation for Directors

### Requirements

#### REQ-ABET-001 — Reuse evaluation pipeline
The system SHALL execute ABET document evaluations through the existing `DocumentEvaluationService` orchestration (DOCX→Markdown→context→prompt→`AiGateway`→persist). It SHALL NOT create a parallel conversion or AI gateway path.

#### REQ-ABET-002 — No direct provider calls
Controllers and evaluation definitions SHALL NOT call AI vendors directly. All completions SHALL go through `AiGateway`.

#### REQ-ABET-003 — Extensible evaluation definition
The orchestrator SHALL accept pluggable access resolution, prompt strategy, and result interpretation so new evaluation types can be added without modifying AI infrastructure.

#### REQ-ABET-004 — Independent metrics definitions
Metric sets SHALL be modeled as replaceable `EvaluationMetricsDefinition` implementations. Incorporating a new metric set SHALL NOT require changes to `AiGateway`, `AiPromptComposer`, or `DocxToMarkdownConverter`.

#### REQ-ABET-005 — Placeholder ABET metrics
The first ABET metrics definition SHALL be explicitly placeholder (`abet_placeholder_v1`) and SHALL NOT claim to be the definitive institutional ABET rubric.

#### REQ-ABET-006 — Structured ABET result
Successful ABET completions SHALL persist a structured JSON result including at least: executive summary, evaluated criteria with compliance and evidence, strengths, improvement opportunities, observations, recommendations, risks, conclusion, and metrics profile key.

#### REQ-ABET-007 — Persist via existing table
ABET evaluations SHALL be stored in `ai_document_evaluations` with `type=abet`.

#### REQ-ABET-008 — Director authorization
Only the Director of a project linked to the entrega SHALL be allowed to run ABET evaluation for that entrega.

#### REQ-ABET-009 — Controlled failure without provider
WHEN no AI provider is configured, the system SHALL return HTTP 503 with `code: ai_unavailable` and a Spanish friendly message. It SHALL NOT return a fabricated evaluation.

#### REQ-ABET-010 — Director UI
The Director review screen SHALL expose ABET evaluation for the selected DOCX version, rendering structured results or the unavailable state without technical stack traces.

#### REQ-ABET-011 — No regression for pre-submission
Student pre-submission evaluation SHALL keep working with the same API contract after the orchestrator refactor.

### Acceptance scenarios

#### Scenario: Successful ABET evaluation with stub provider
GIVEN an authenticated Director of the project AND a DOCX version AND a stub AI provider returning valid ABET JSON  
WHEN they POST `/api/director/entregas/{id}/evaluacion-abet`  
THEN the system SHALL persist `type=abet` `status=completed` AND return the structured result.

#### Scenario: Provider not configured
GIVEN an authenticated Director AND the null AI provider  
WHEN they request ABET evaluation  
THEN the response SHALL be HTTP 503 with `ai_unavailable`.

#### Scenario: Non-director forbidden
GIVEN an authenticated user who is not the entrega's Director  
WHEN they request ABET evaluation  
THEN the system SHALL deny access (403/404 per existing domain pattern).

#### Scenario: Student pre-submission still works
GIVEN the existing student evaluation endpoint  
WHEN exercised with a stub provider  
THEN it SHALL still complete successfully (no regression).

#### Scenario: Future metrics swap
GIVEN a new `EvaluationMetricsDefinition` implementation  
WHEN wired into a new or existing strategy  
THEN the AI core classes SHALL remain unmodified.
