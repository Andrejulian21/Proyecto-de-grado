# Spec: Asistente Académico Inteligente

## Capability: Academic assistant (student orientation)

### Requirements

#### REQ-AA-001 — Reuse AI infrastructure
The system SHALL route every assistant completion exclusively through the existing `AiGateway` / `AiProvider` infrastructure. Controllers and domain services SHALL NOT call AI vendors directly.

#### REQ-AA-002 — No mock conversations
The system SHALL NOT return simulated or hard-coded AI replies as if they came from a provider. Static UI welcome text (non-persisted, clearly local) MAY be shown when the conversation has no messages.

#### REQ-AA-003 — Controlled failure without provider
WHEN no AI provider is configured (`NullAiProvider` / `ProviderNotConfigured`), the system SHALL complete the orchestration up to the gateway, persist a failed turn metadata when applicable, and respond with HTTP 503 and a Spanish friendly message indicating the AI service is temporarily unavailable (`code: ai_unavailable`).

#### REQ-AA-004 — Real academic context
The system SHALL build the assistant context from real database data available to the student (identity, optional project, conversation history, director catalog with academic profiles and capacity).

#### REQ-AA-005 — Director academic profile
The system SHALL store structured director academic data in a related entity (`director_academic_profiles`) so the profile can grow without modifying the assistant orchestrator.

#### REQ-AA-006 — Founded director recommendations
The system SHALL recommend directors only from the catalog supplied in context. Recommendations SHALL include a justification. IDs not present in the catalog SHALL be discarded. Recommendations SHALL NOT be random selections in application code.

#### REQ-AA-007 — Extensible recommendation criteria
The system SHALL expose a catalog enrichment extension point so future recommendation criteria can be added without changing `AcademicAssistantService` core logic.

#### REQ-AA-008 — Structured response
WHEN the provider returns a successful completion, the system SHALL parse a structured JSON payload containing at least: conversational `mensaje`, `resumen_conversacion`, `idea_refinada`, `lineas_investigacion`, `tecnologias_recomendadas`, `metodologias_sugeridas`, `directores_recomendados` (with justification), `riesgos`, and `proximos_pasos`.

#### REQ-AA-009 — Persistence
The system SHALL persist conversations and messages in generic AI assistant tables with a `type` discriminator reusable by future specialized assistants.

#### REQ-AA-010 — Student API
The system SHALL expose authenticated student endpoints to retrieve the active conversation and to send a new message that triggers a full assistant turn.

#### REQ-AA-011 — Frontend integration
The existing `AsistenteOrientacion` page SHALL consume the real API, remove mock dialogue, and display either the structured orientation result or a friendly AI-unavailable state. Visual identity SHALL be preserved (no redesign).

#### REQ-AA-012 — Role extensibility
The assistant architecture SHALL NOT be hard-wired exclusively to the student role at the service/strategy layer: new roles SHALL be addable via new `AssistantPromptStrategy` implementations.

### Acceptance scenarios (EARS)

#### Scenario: Successful turn with stub provider
GIVEN an authenticated student AND a configured stub AI provider that returns valid structured JSON  
WHEN the student posts a message to `/api/estudiante/asistente/mensajes`  
THEN the system SHALL persist the user and assistant messages AND return HTTP 200 with structured `resultado` AND chat `mensaje`.

#### Scenario: Provider not configured
GIVEN an authenticated student AND default `null` AI provider  
WHEN the student posts a message  
THEN the system SHALL return HTTP 503 with `code: ai_unavailable` AND a Spanish friendly error AND SHALL NOT return a fabricated assistant answer.

#### Scenario: Director ID hallucination discarded
GIVEN a stub provider that recommends a director id absent from the catalog  
WHEN the turn completes  
THEN the structured `directores_recomendados` SHALL omit that id.

#### Scenario: Conversation continuity
GIVEN an authenticated student with a prior assistant conversation  
WHEN the student requests GET `/api/estudiante/asistente/conversacion`  
THEN the system SHALL return the existing conversation messages and last `result_json` when present.

#### Scenario: RBAC
GIVEN an authenticated non-student user  
WHEN they call the student assistant endpoints  
THEN the system SHALL deny access (403/unauthorized per existing role middleware).

#### Scenario: UI unavailable state
GIVEN the API returns 503 `ai_unavailable`  
WHEN the student uses the Asistente page  
THEN the UI SHALL show a clear Spanish message that the AI service is temporarily unavailable AND SHALL NOT show technical stack traces.
