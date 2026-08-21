# Design: Gestión de asignación y eliminación de directores

## Enfoque técnico

No hay tabla pivote director–proyecto: la asignación es `proyectos.director_id` (FK a `users`, sin `onDelete`). El frontend de Gestión de Proyectos ya envía `PUT /api/admin/proyectos/{id}` y `GET ...?grupo_id=`, pero el `apiResource` no incluye `update` y `index()` no filtra por semestre.

Se añade un **guard de dominio** reutilizado en delete/rol (usuarios y whitelist) y una **Action transaccional** para el flujo de reasignación. El filtro de grupo reutiliza el alias `grupo_id` → `semester_id` ya usado en entregas.

## Decisiones de arquitectura

### D1 — Dónde vive la regla “director con proyectos”

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| Ifs en `UserController` | Rápido; duplicado en whitelist; controller >500 líneas | ❌ |
| **`DirectorAssignmentGuard` + excepción de dominio** | Un solo sitio; testeable; controller delgado | ✅ |

**Racional**: `UserController` ya ronda 440 líneas (límite constitucional 500). Whitelist `destroy`/`update` también borran/sincronizan usuarios.

### D2 — Endpoint de reasignación

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| `DELETE` con body `{ reasignar: true }` | DELETE con body es frágil en clientes | ❌ |
| **`POST /api/admin/usuarios/{user}/eliminar-con-reasignacion`** | Operación no idempotente explícita, fácil de auditar | ✅ |

### D3 — Capa de reasignación

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| Lógica en el controller | Difícil de testear el rollback | ❌ |
| **`DeleteDirectorWithReassignmentAction`** | Encaja con `app/Actions/*`; `DB::transaction` + `lockForUpdate` | ✅ |

Algoritmo (D3 spec): `$recipients->shuffle()` y `director_id = $recipients[$i % count]`. Invariantes testeables sin fijar semilla: ningún proyecto queda con el id eliminado; todos los ids ∈ receptores.

### D4 — Contrato de error 422

Misma forma que otros admin endpoints (`error` + `message`), más campos para la UI:

| Campo | Uso |
|-------|-----|
| `error` | `director_has_projects` \| `no_directors_available` |
| `message` | Texto en español para toast/diálogo |
| `proyectos_count` | Solo en `director_has_projects` |
| `can_reassign` | `true` si hay ≥1 otro Director |

`DirectorHasProjectsException` y un 422 plano para `no_directors_available` (puede ser la misma excepción con `errorCode` distinto, o un código en la Action).

### D5 — Update de proyecto

`UpdateProyectoRequest` (authorize: Coordinador):

- `title`: sometimes, string, max 500
- `director_id`: sometimes, exists:users,id, y el usuario MUST tener rol Director
- `student_ids`: sometimes, array, max 3, exists:users; unicidad en `proyecto_estudiante` **excluyendo** el proyecto actual
- `status`: sometimes (el hook ya lo tipa; si no se envía, no se toca)

Controller: asigna campos, `save()`, `estudiantes()->sync()` si viene `student_ids`, `load` de `director` y `estudiantes`.

Añadir `'update'` a `Route::apiResource('proyectos', ...)->only([...])`.

### D6 — Filtro de grupo

En `ProyectoController::index`:

```php
if ($request->filled('grupo_id') || $request->filled('semester_id')) {
    $query->where('semester_id', $request->integer('grupo_id') ?: $request->integer('semester_id'));
}
```

Frontend: `useProyectos` ya pone `grupo_id`. En `GestionProyectos`, filtrar además `p.semester_id === selectedGroupId` para no mostrar filas de otro grupo si la respuesta viniera incompleta. Sin mocks.

### D7 — UI de eliminación

`GestionUsuarios` ya supera 500 líneas: extraer `DirectorReassignDeleteDialog`. Flujo:

1. DELETE habitual.
2. Si 422 `director_has_projects` y `can_reassign`: abrir diálogo de alternativa.
3. Confirmar → POST eliminar-con-reasignacion.
4. Si `can_reassign === false` o 422 `no_directors_available`: banner con `message`.
5. PUT de rol: mostrar `message` del 422 (sin ofrecer reasignación).

## Data flow

```
ELIMINAR DIRECTOR
  DELETE /api/admin/usuarios/{id}
    → DirectorAssignmentGuard::assertCanDelete(user)
       ├─ 0 proyectos → UserController borra user + whitelist + AuditEvent user.deleted
       └─ n>0 → 422 director_has_projects { can_reassign }

  POST /api/admin/usuarios/{id}/eliminar-con-reasignacion
    → DeleteDirectorWithReassignmentAction
       DB::transaction
         lock director + proyectosDirigidos
         lock/list other directors
         empty? throw no_directors_available
         round-robin shuffle assign
         delete user + whitelist
         AuditEvent director.projects_reassigned + user.deleted

CAMBIO DE DIRECTOR
  PUT /api/admin/proyectos/{id} { director_id }
    → UpdateProyectoRequest
    → Proyecto.director_id = nuevo; save; return with director

FILTRO GRUPO
  GET /api/admin/proyectos?grupo_id=S
    → where semester_id = S
```

## Integridad y transacción

Orden dentro de la transacción (nunca borrar primero):

1. Cargar y bloquear proyectos del director.
2. Resolver receptores; abortar si vacío.
3. Escribir todos los `director_id` nuevos.
4. Borrar whitelist + usuario.

Si el `delete()` lanza (FK, observer de test, etc.), Laravel hace rollback: los `director_id` vuelven al original.

SQLite `:memory:` (Pest) soporta transacciones; `lockForUpdate` es no-op benigno.

## Archivos

| File | Action |
|------|--------|
| `app/Exceptions/DirectorAssignmentException.php` | Create |
| `app/Services/Directors/DirectorAssignmentGuard.php` | Create |
| `app/Actions/Directors/DeleteDirectorWithReassignmentAction.php` | Create |
| `app/Http/Requests/UpdateProyectoRequest.php` | Create |
| `app/Http/Controllers/Admin/UserController.php` | Modify |
| `app/Http/Controllers/Admin/ProyectoController.php` | Modify |
| `routes/api.php` | Modify |
| `resources/js/components/coordinador/DirectorReassignDeleteDialog.tsx` | Create |
| `resources/js/pages/coordinador/GestionUsuarios.tsx` | Modify |
| `resources/js/pages/coordinador/GestionProyectos.tsx` | Modify |
| `tests/Feature/Admin/DirectorDeletionTest.php` | Create |
| `tests/Feature/Admin/ProyectoUpdateAndFilterTest.php` | Create |
| `tests/Unit/Actions/DeleteDirectorWithReassignmentActionTest.php` | Create |

## Permisos

Sin cambios de policy: rutas admin ya usan `role:Coordinador`. `UpdateProyectoRequest::authorize()` replica Coordinador. No-coordinador → 403.

## Open questions (resueltos en spec)

- ¿Cupo máximo al reasignar? No (D2 spec).
- ¿Reasignar solo proyectos activos? No: todos (D1 spec).
