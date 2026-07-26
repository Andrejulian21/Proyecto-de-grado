# Spec: Perfil académico de Directores

## Capability: Coordinator-managed director academic profiles

### Requirements

#### REQ-PAD-001 — Reuse existing profile entity
The system SHALL store structured academic data in `director_academic_profiles` (1:1 with Director users). It SHALL NOT create a parallel academic profile table.

#### REQ-PAD-002 — Required academic attributes
A Director academic profile SHALL support at least: specialization areas (`users.areas`), research lines, years of experience, technologies, methodologies, and professional description (`academic_experience`).

#### REQ-PAD-003 — Persist real data only
All academic profile values SHALL be stored and loaded from the database. The UI SHALL NOT use mock academic profiles.

#### REQ-PAD-004 — Admin API
The system SHALL expose Coordinador-only endpoints to retrieve and update a Director's academic profile.

#### REQ-PAD-005 — Create path sync
WHEN a Director is added via whitelist with academic fields, the system SHALL create/update the corresponding `director_academic_profiles` row and `users.areas`.

#### REQ-PAD-006 — Authorization
Only users with role Coordinador SHALL manage academic profiles. The target user SHALL have role Director.

#### REQ-PAD-007 — Assistant readiness
`DirectorCatalogBuilder` SHALL expose the persisted fields (including years of experience and specialization areas) so the existing Academic Assistant can consume them without further schema changes.

#### REQ-PAD-008 — UI integration
`GestionUsuarios` SHALL allow registering and editing these fields for Directors while preserving current visual identity and non-related user-management flows.

#### REQ-PAD-009 — No AI logic
This change SHALL NOT implement recommendation, prompting, or provider calls.

### Acceptance scenarios

#### Scenario: Create director with academic profile
GIVEN a Coordinador  
WHEN they POST whitelist role=Director with areas, research lines, technologies, methodologies, years, and description  
THEN a User and DirectorAcademicProfile SHALL exist with those values in the database.

#### Scenario: Update academic profile
GIVEN an existing Director user  
WHEN the Coordinador PUTs `/api/admin/directores/{id}/perfil-academico`  
THEN the profile and areas SHALL be updated and returned.

#### Scenario: Non-director rejected
GIVEN a user who is not a Director  
WHEN a Coordinador attempts to update their academic profile  
THEN the API SHALL reject the request (422/404).

#### Scenario: Non-coordinator forbidden
GIVEN a non-Coordinador authenticated user  
WHEN they call the profile endpoints  
THEN access SHALL be denied by role middleware.

#### Scenario: Existing user management still works
GIVEN student/evaluator whitelist flows  
WHEN exercised  
THEN they SHALL continue to work without requiring academic profile fields.
