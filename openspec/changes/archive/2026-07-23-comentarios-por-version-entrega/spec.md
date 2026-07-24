# Spec: Comentarios del Director por versión de entrega

**Change**: `2026-07-23-comentarios-por-version-entrega`  
**Type**: Bugfix  
**ADRs**: ninguno nuevo (reutiliza modelo `VersionDocumento`)

## Purpose

Garantizar que las observaciones del Director en la revisión de entregas sean **específicas de cada versión de documento**, sin alterar el flujo general de revisión ni requerir cambios de esquema.

## Requirements

| ID | Requirement | Scenarios |
|----|-------------|-----------|
| **CV-001** | WHEN el Director guarda una revisión con `director_notes` y `version_id` válido, THE system SHALL persistir esas notas únicamente en la `VersionDocumento` indicada. | **Happy**: GIVEN entrega con v1,v2,v3, WHEN `PUT .../revisar` con `version_id=v1` y notes "Corregir introducción.", THEN solo v1.director_notes cambia. **Edge**: GIVEN `version_id` de otra entrega, WHEN se llama `revisar`, THEN 422 y ninguna versión se modifica. |
| **CV-002** | WHEN el Director guarda una revisión con `director_notes` sin `version_id`, THE system SHALL aplicar el comportamiento compatible: guardar en la versión de mayor `version_number` (si existe). | **Happy**: GIVEN v1 y v2, WHEN `revisar` sin `version_id`, THEN v2.director_notes se actualiza y v1 permanece. |
| **CV-003** | WHEN el Director cambia la versión seleccionada en la UI de revisión, THE system SHALL mostrar en el campo de observaciones las notas de esa versión (o vacío si no tiene). | **Happy**: GIVEN v1="A", v2="B", WHEN selecciona v1 luego v2, THEN el textarea muestra "A" luego "B". |
| **CV-004** | WHEN el Director edita y guarda observaciones con una versión seleccionada, THE system SHALL NO sobrescribir `director_notes` de las demás versiones. | **Happy**: GIVEN v1="Corregir introducción.", v2="Corregir metodología.", WHEN edita v3 a "Aprobada.", THEN v1 y v2 conservan su texto. |
| **CV-005** | THE system SHALL NOT alter status transitions, consolidated grade, notifications, phase auto-advance, nor require a new migration/model/endpoint for this change. | **Happy**: GIVEN aprobación con notas en v2, WHEN `status=aprobada`, THEN evaluation_complete, notificación y auto-avance siguen igual. |
| **CV-006** | Lectura existente (estudiante/coordinador) SHALL continuar mostrando `director_notes` por versión sin cambios de contrato. | **Happy**: GIVEN versiones con notas distintas, WHEN `GET /api/admin/entregas/{id}`, THEN cada ítem en `versiones` incluye su propio `director_notes`. |

## Acceptance criteria (EARS)

- WHEN a director reviews a delivery version, THE system SHALL store `director_notes` on that `VersionDocumento` row.
- WHEN a director switches selected version, THE UI SHALL display that version's notes.
- WHEN a director saves notes for version N, THE system SHALL leave notes of versions ≠ N unchanged.
- IF `version_id` is omitted, THE system SHALL fall back to the latest version for notes persistence.
- IF `version_id` does not belong to the delivery, THE system SHALL reject the request with 422.
- THE system SHALL NOT introduce a new migration for `director_notes`.

## Non-goals

- Immutable historical audit of note edits (last write per version still wins for that version).
- Separate "save notes only" endpoint without decision status.
- UI redesign beyond binding/selector wiring.
