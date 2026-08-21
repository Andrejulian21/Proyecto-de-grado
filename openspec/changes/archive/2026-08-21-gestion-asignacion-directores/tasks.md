# Tasks: Gestión de asignación y eliminación de directores

> Change: `gestion-asignacion-directores` | Strict TDD | Runner: `vendor/bin/pest`
> Todas las tareas inician sin checkear. Archivar solo con tests verdes y AC cubiertos.

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~700 (backend + tests + UI diálogo) |
| 400-line budget risk | Medium — un solo change, sin cadena de PRs |
| Chained PRs recommended | No |
| Focused test command | `vendor/bin/pest tests/Feature/Admin/DirectorDeletionTest.php tests/Feature/Admin/ProyectoUpdateAndFilterTest.php tests/Unit/Actions/DeleteDirectorWithReassignmentActionTest.php` |

```text
Decision needed before apply: No
Chained PRs recommended: No
```

## T-001 — OpenSpec

- [x] T-001 Artefactos `proposal.md`, `spec.md`, `design.md`, `tasks.md` en `openspec/changes/gestion-asignacion-directores/`. No modificar `openspec/specs/` ni otros changes.

## T-002 — RED: guard de eliminación y rol

- [x] T-002 `tests/Feature/Admin/DirectorDeletionTest.php`: director sin proyectos → DELETE 200 y usuario ausente; director con proyectos → DELETE 422 `director_has_projects` y usuario/proyectos intactos; PUT rol a Estudiante con proyectos → 422 y rol Director; PUT rol sin proyectos → 200. Tests en rojo.

## T-003 — GREEN: guard backend

- [x] T-003 `DirectorAssignmentException` + `DirectorAssignmentGuard`; `destroyUsuario` / `updateUsuario` / whitelist `update`+`destroy` invocan el guard. Mensajes en español. Tests T-002 verdes.

## T-004 — RED: reasignación, sin receptores, rollback

- [x] T-004 En el mismo feature test + unit de la Action: POST `eliminar-con-reasignacion` reparte proyectos y borra al director; sin otros directores → 422 `no_directors_available`; si `User::deleting` lanza, rollback (director y `director_id` originales). Tests en rojo.

## T-005 — GREEN: Action transaccional + ruta

- [x] T-005 `DeleteDirectorWithReassignmentAction` (`DB::transaction`, `lockForUpdate`, shuffle + round-robin); ruta POST bajo middleware Coordinador; audit `director.projects_reassigned` + `user.deleted`. Tests T-004 verdes.

## T-006 — RED/GREEN: update de proyecto y filtro de grupo

- [x] T-006 `tests/Feature/Admin/ProyectoUpdateAndFilterTest.php`: PUT cambia `director_id` y la respuesta trae el nuevo director; estudiante 403; `director_id` de no-Director 422; GET `?grupo_id=` solo proyectos de ese `semester_id`. `UpdateProyectoRequest` + `ProyectoController::update` + `index` filtra `grupo_id`/`semester_id`; `apiResource` incluye `update`. Tests verdes.

## T-007 — Frontend

- [x] T-007 `DirectorReassignDeleteDialog`: 422 con `can_reassign` ofrece la alternativa; confirmar llama POST; `can_reassign false` o `no_directors_available` muestra `message`. PUT rol muestra error del backend. `GestionProyectos` filtra por `semester_id` real al cambiar de grupo. Sin mocks. `npm run build` sin errores.

## T-008 — Verify y archive

- [x] T-008 Pest de T-002/T-004/T-006 (+ regresión `ProyectoCrudTest`, `HardeningPr3Test`, `WhitelistCrudTest`, `FormRequestValidationTest`) en verde. Marcar tareas. Mover la carpeta a `openspec/changes/archive/2026-08-21-gestion-asignacion-directores/` solo si no queda nada pendiente.

## Notas de ejecución

- **Prohibido**: `git clean`, tocar `database/database.sqlite`, `php artisan migrate*` contra BD real. Tests solo `:memory:`.
- **Prohibido**: modificar specs existentes.
- **Alcance**: no extraer refactors ajenos; `GestionUsuarios` ya excede 500 líneas — extraer solo el diálogo de reasignación.
- **TDD**: no marcar tarea done sin el criterio de aceptación verificado.
