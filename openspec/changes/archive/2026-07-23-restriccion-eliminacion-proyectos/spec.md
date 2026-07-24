# Spec: Restricción de eliminación de proyectos

**Change**: `2026-07-23-restriccion-eliminacion-proyectos`  
**Type**: Bugfix + business rule  
**ADRs**: ninguno nuevo

## Purpose

Habilitar la eliminación correcta de proyectos desde el módulo de coordinación y garantizar que solo proyectos **sin entregas asociadas** puedan eliminarse, con validación autoritativa en el backend.

## Requirements

| ID | Requirement | Scenarios |
|----|-------------|-----------|
| **EP-001** | THE system SHALL exponer `DELETE /api/admin/proyectos/{proyecto}` autenticado para Coordinador, implementado en `ProyectoController@destroy`. | **Happy**: GIVEN un proyecto sin entregas, WHEN el coordinador envía DELETE, THEN 200 y el proyecto deja de existir. |
| **EP-002** | WHEN el proyecto tiene al menos una entrega por FK (`entregas.proyecto_id`) OR por pivote (`entrega_proyecto`), THE system SHALL rechazar la eliminación con HTTP 422 y mensaje en español indicando que posee entregas registradas. | **Happy**: GIVEN proyecto con 1+ entregas, WHEN DELETE, THEN 422 y el proyecto permanece. **Edge**: GIVEN entrega solo en pivote, WHEN DELETE, THEN 422. |
| **EP-003** | WHEN el proyecto no tiene entregas (ni FK ni pivote), THE system SHALL permitir `delete()` y responder éxito. | **Happy**: GIVEN proyecto solo con estudiantes, WHEN DELETE, THEN éxito; pivote estudiantes se limpia por cascade. |
| **EP-004** | THE frontend SHALL mostrar el mensaje de error del backend al fallar la eliminación, sin depender solo de validación visual. | **Happy**: GIVEN 422 del API, WHEN el usuario confirma eliminar, THEN ve el mensaje claro en la UI. |
| **EP-005** | THE system SHALL NOT alterar crear, editar ni consultar proyectos en este change. | **Happy**: GIVEN suite CRUD existente de list/create/show, WHEN se ejecuta, THEN sigue en verde. |

## Acceptance criteria (EARS)

- WHEN a coordinator deletes a project with no associated deliveries, THE system SHALL remove the project and update the list.
- WHEN a coordinator deletes a project with one or more associated deliveries, THE system SHALL reject the request with 422 and a clear Spanish message.
- WHERE deliveries are linked via FK or pivot, THE system SHALL treat either association as blocking.
- THE validation SHALL be enforced in the backend; the frontend SHALL only reflect the result.

## Non-goals

- Soft delete / archive workflow.
- Blocking deletion due to bitácoras alone.
- Cascading deletion of entregas.
