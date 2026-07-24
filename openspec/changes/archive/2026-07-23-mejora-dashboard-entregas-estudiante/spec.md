# Spec: Mejora Dashboard entregas estudiante

**Change**: `2026-07-23-mejora-dashboard-entregas-estudiante`  
**Type**: UX enhancement  
**ADRs**: ninguno nuevo

## Purpose

Mostrar en el Dashboard del Estudiante el título real de cada entrega y un historial resumido de versiones dentro del accordion, sin alterar el flujo hacia el detalle.

## Requirements

| ID | Requirement | Scenarios |
|----|-------------|-----------|
| **DE-001** | THE card header SHALL display the delivery `title` from the API/database (not a phase-only hardcoded label). Phase labels MAY be used only as fallback if title is missing. | **Happy**: GIVEN entrega title "Documento técnico v1", WHEN Dashboard loads, THEN the card shows that title. |
| **DE-002** | `GET /api/estudiante/entregas` SHALL include a `versiones` array per delivery with version number, file name, upload datetime, optional observation text, and derived status — using the existing eager-load (no N+1). | **Happy**: GIVEN 2 versiones, WHEN endpoint is called, THEN `versiones` has length 2 with required fields. **Edge**: GIVEN 0 versiones, THEN `versiones` is `[]`. |
| **DE-003** | WHEN the accordion is expanded, THE UI SHALL list all versions with number, file name, date+time, status badge, and truncated observation preview. | **Happy**: GIVEN multiple versions, WHEN expand, THEN all rows render. |
| **DE-004** | WHEN observation length exceeds the preview limit (~80 chars), THE UI SHALL truncate with an ellipsis. Empty observations SHALL show a short empty hint or blank without error. | **Happy**: GIVEN notes > 80 chars, THEN preview ends with `…`. |
| **DE-005** | THE "Ver detalle" navigation and PhaseStepper filtering SHALL remain unchanged. | **Happy**: GIVEN click Ver detalle, THEN navigates to `/estudiante/entregas/:id`. |

## Acceptance criteria (EARS)

- WHEN the student dashboard loads deliveries, THE system SHALL show each delivery’s database title on the card.
- WHEN a delivery accordion is expanded, THE system SHALL show all associated document versions with file name, datetime, status, and observation preview.
- IF a delivery has no versions, THE accordion SHALL show the existing empty state without errors.
- THE system SHALL NOT require navigating to the detail page to see the version summary.

## Non-goals

- Editing notes, uploading from dashboard, full observation text in accordion.
- Redesign of dashboard layout beyond enriching the existing card.
