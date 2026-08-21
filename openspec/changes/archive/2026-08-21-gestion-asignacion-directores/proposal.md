# Proposal: Gestión de asignación y eliminación de directores

## Intent

Garantizar la integridad de la relación `proyectos.director_id` cuando el coordinador elimina un director, le cambia el rol o reasigna el director de un proyecto. Hoy `DELETE /api/admin/usuarios/{user}` y `PUT` de rol no comprueban proyectos asignados (el FK `proyectos.director_id` es RESTRICT), el `apiResource` de proyectos no expone `update` (el frontend hace PUT y falla), y `GET /api/admin/proyectos` ignora `grupo_id` pese a que Gestión de Proyectos lo envía.

## Scope

### In Scope

- Bloqueo backend de eliminación directa y de cambio de rol de un director con uno o más proyectos asignados (`proyectos.director_id`), con mensaje claro en español.
- Flujo alternativo: eliminar director y distribuir aleatoriamente sus proyectos entre los demás directores existentes, en una transacción.
- Rechazo si no hay otros directores disponibles.
- Implementar `PUT /api/admin/proyectos/{proyecto}` para cambiar director (y título/estudiantes ya enviados por el frontend) sin asignaciones duplicadas.
- Filtrar `GET /api/admin/proyectos` por el semestre/grupo real (`semester_id` = `grupo_id`) y alinear el frontend.
- UI de Gestión de Usuarios: informar el bloqueo y ofrecer la reasignación aleatoria.
- Tests Pest de los 10 escenarios de aceptación.

### Out of Scope

- Cambiar el esquema de `proyectos` o introducir una tabla pivote director–proyecto.
- Soft-delete de usuarios.
- Recalcular o validar cupos (`max_capacity`) al reasignar.
- Eliminación masiva o reasignación manual proyecto-a-proyecto (el coordinador ya puede editar el director de un proyecto).
- Modificar specs existentes en `openspec/specs/` u otros changes.

## Capabilities

### New Capabilities

- `director-deletion-guard`: Un director con proyectos asignados no puede eliminarse ni cambiar de rol; el API responde 422 con motivo.
- `director-delete-with-reassignment`: Operación atómica que reparte los proyectos al azar entre otros directores y luego elimina al usuario.

### Modified Capabilities

- `gestion-proyectos-update`: El coordinador puede persistir el cambio de director de un proyecto.
- `gestion-proyectos-group-filter`: El listado de proyectos del coordinador respeta el grupo/semestre seleccionado.

## Approach

Reutilizar `User::proyectosDirigidos()` y `Proyecto::director()`. Extraer un guard de dominio y una Action transaccional (`DB::transaction` + `lockForUpdate`). Añadir `update` al `apiResource` de proyectos y el filtro `grupo_id` → `semester_id` (mismo alias que entregas). El frontend interpreta el 422 y, si hay otros directores, confirma la reasignación.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Http/Controllers/Admin/UserController.php` | Modified | Guard en delete/rol; endpoint de eliminación con reasignación; mismo guard en whitelist. |
| `app/Http/Controllers/Admin/ProyectoController.php` | Modified | `update()` + filtro `grupo_id`/`semester_id`. |
| `app/Actions/Directors/` | New | Action transaccional de reasignación + borrado. |
| `app/Services/Directors/` | New | Guard de proyectos asignados. |
| `app/Http/Requests/UpdateProyectoRequest.php` | New | Validación de título, director (rol Director) y estudiantes. |
| `routes/api.php` | Modified | Ruta POST de reasignación; `update` en proyectos. |
| `resources/js/pages/coordinador/GestionUsuarios.tsx` | Modified | Diálogo de bloqueo + reasignación. |
| `resources/js/pages/coordinador/GestionProyectos.tsx` | Modified | Filtro cliente por `semester_id` del proyecto. |
| `tests/Feature/Admin/` | New/Modified | Cobertura de los 10 criterios. |

## Assumptions

1. “Proyectos asignados” = filas en `proyectos` con `director_id` igual al usuario, cualquier estado (incluido completado).
2. “Directores disponibles” = usuarios con `role = Director` distintos del que se elimina (no se exige cupo libre).
3. “Grupo” en Gestión de Proyectos es un `Semestre` (`/api/admin/semestres`); `grupo_id` es `semester_id`.
4. La distribución aleatoria es round-robin sobre la lista de destinatarios barajada (aleatoria y equilibrada).
5. El coordinador es el único actor; el middleware `role:Coordinador` se mantiene.

## Non-Goals

- No migraciones contra la BD local real.
- No mocks ni datos estáticos en las páginas afectadas.
- No cambiar RBAC ni el modelo de usuarios.

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Condición de carrera: dos deletes simultáneos dejan un proyecto sin director | Low | Transacción + `lockForUpdate` sobre el director y sus proyectos |
| Un solo director restante: el coordinador no entiende por qué no puede borrar | Med | 422 `no_directors_available` con mensaje accionable |
| PUT de proyecto rompe contratos de students ya asignados a otro proyecto | Med | Validar unicidad excluyendo el proyecto actual; `sync()` del pivote |

## Rollback Plan

Revertir el commit del change. Los endpoints nuevos dejan de existir; el delete/rol vuelven al comportamiento previo (sin guard). No hay migraciones.

## Success Criteria

- [ ] Director sin proyectos se elimina con DELETE directo.
- [ ] Director con proyectos: DELETE y cambio de rol responden 422 con mensaje en español.
- [ ] Con otros directores, el coordinador puede confirmar reasignación aleatoria; el usuario desaparece y ningún proyecto queda sin director.
- [ ] Sin otros directores, la reasignación se rechaza.
- [ ] Si falla cualquier paso, la transacción revierte.
- [ ] PUT de proyecto actualiza `director_id` y la UI muestra el nuevo director.
- [ ] El filtro por grupo devuelve solo proyectos de ese `semester_id`.
- [ ] Suite Pest del change en verde; no se tocan specs existentes.
