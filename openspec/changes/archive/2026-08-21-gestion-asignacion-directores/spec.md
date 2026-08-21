# Spec: Gestión de asignación y eliminación de directores

> Change: `gestion-asignacion-directores` | Relación canónica: `proyectos.director_id` → `users.id`

## Decisiones

| # | Decisión | Resolución |
|---|----------|-----------|
| D1 | Criterio de “proyectos asignados” | Todas las filas de `proyectos` con `director_id` = usuario, cualquier `status`. |
| D2 | Directores receptores | `users.role = Director` excluyendo al eliminado. No se valida cupo. |
| D3 | Algoritmo de distribución | Barajar destinatarios y asignar proyectos en round-robin. Cada proyecto queda con exactamente un `director_id`. |
| D4 | Atomicidad | `DB::transaction` + `lockForUpdate`. Si cualquier paso falla, rollback completo. |
| D5 | Alias de grupo | Query `grupo_id` (y `semester_id`) filtra `proyectos.semester_id`. |
| D6 | Cambio de rol bloqueado | Solo si el rol actual es Director, el nuevo rol es distinto, y hay proyectos asignados. |
| D7 | Mensajes | Español, accionables; código de error estable para la UI (`director_has_projects`, `no_directors_available`). |

---

## Capacidad: director-deletion-guard

### RF-DIR-01: Eliminación directa de director sin proyectos

WHEN a Coordinador envía `DELETE /api/admin/usuarios/{id}` AND the user es Director AND no tiene proyectos con `director_id` igual a su id, the system SHALL eliminar el usuario y su entrada de whitelist, AND responder 200.

#### Escenario: Director sin proyectos se elimina

- GIVEN un director sin filas en `proyectos.director_id`
- WHEN el coordinador llama DELETE
- THEN el usuario no existe AND se emite `user.deleted`

### RF-DIR-02: Eliminación directa bloqueada si hay proyectos

WHEN a Coordinador envía `DELETE /api/admin/usuarios/{id}` (o `DELETE /api/admin/whitelist/{id}` que borra el usuario asociado) AND the user es Director AND tiene uno o más proyectos asignados, the system SHALL rechazar con 422 AND SHALL NOT eliminar al usuario ni alterar `director_id`.

El cuerpo MUST incluir:

```json
{
  "error": "director_has_projects",
  "message": "<texto en español explicando que no se puede eliminar mientras tenga proyectos>",
  "proyectos_count": <n>,
  "can_reassign": <true si existe al menos otro Director>
}
```

#### Escenario: Director con proyectos — DELETE directo

- GIVEN un director con 2 proyectos
- WHEN DELETE sin reasignación
- THEN 422, `error = director_has_projects`, `proyectos_count = 2`
- AND el director sigue existiendo
- AND los proyectos siguen con el mismo `director_id`

#### Escenario: Mensaje claro

- GIVEN el 422 de RF-DIR-02
- THEN `message` está en español AND menciona que tiene proyectos asignados

### RF-DIR-03: Cambio de rol bloqueado si hay proyectos

WHEN a Coordinador envía `PUT /api/admin/usuarios/{id}` o `PUT /api/admin/whitelist/{id}` para cambiar el rol AND the user es Director AND el nuevo rol es distinto de Director AND tiene proyectos asignados, the system SHALL rechazar con 422 (`error = director_has_projects`) AND SHALL NOT cambiar el rol.

WHEN el nuevo rol es igual al actual, the system SHALL permitir la operación (no-op de rol).

WHEN the user es Director sin proyectos, the system SHALL permitir el cambio de rol.

#### Escenario: Director con proyectos no cambia a Estudiante

- GIVEN director con 1 proyecto
- WHEN PUT `{ "role": "Estudiante" }`
- THEN 422 AND `role` sigue siendo Director

#### Escenario: Director sin proyectos sí cambia de rol

- GIVEN director sin proyectos
- WHEN PUT `{ "role": "Estudiante" }`
- THEN 200 AND `role` es Estudiante

---

## Capacidad: director-delete-with-reassignment

### RF-DIR-04: Inicio del flujo de eliminación con reasignación

WHEN a Coordinador confirma la alternativa “Eliminar director y distribuir aleatoriamente sus proyectos entre los directores existentes”, the system SHALL invocar `POST /api/admin/usuarios/{id}/eliminar-con-reasignacion`.

El endpoint MUST estar protegido por `role:Coordinador`.

### RF-DIR-05: Distribución y borrado atómicos

WHEN the POST de RF-DIR-04 se ejecuta AND existe al menos un Director distinto AND the user es Director con proyectos, the system SHALL, dentro de una transacción:

1. Obtener los proyectos con `director_id` del usuario (lock).
2. Obtener los demás Directores.
3. Asignar cada proyecto a un receptor según D3 (un solo `director_id` por proyecto; sin filas duplicadas).
4. Eliminar al director (y whitelist asociada) solo después de que todos los proyectos tengan nuevo director.

THEN 200 con `message` en español AND opcionalmente `reasignaciones`.
AND the usuario ya no existe.
AND ningún proyecto queda con `director_id` nulo ni con el id del eliminado.
AND todos los `director_id` resultantes pertenecen al conjunto de receptores.

#### Escenario: Un receptor — todos los proyectos pasan a él

- GIVEN director A con 3 proyectos AND director B (único otro)
- WHEN POST eliminar-con-reasignacion sobre A
- THEN los 3 proyectos tienen `director_id = B.id`
- AND A no existe

#### Escenario: Varios receptores — se reparte entre ellos

- GIVEN director A con 4 proyectos AND directores B y C
- WHEN POST eliminar-con-reasignacion sobre A
- THEN cada proyecto tiene `director_id` ∈ {B, C}
- AND A no existe
- AND no hay dos filas de proyecto con el mismo id

### RF-DIR-06: Sin otros directores — rechazo

WHEN no existe ningún otro usuario con rol Director, the system SHALL rechazar el POST con 422:

```json
{
  "error": "no_directors_available",
  "message": "<texto en español: no hay otros directores para reasignar>"
}
```

AND SHALL NOT eliminar al director ni cambiar asignaciones.

#### Escenario: Único director en el sistema

- GIVEN solo un Director, con proyectos
- WHEN POST eliminar-con-reasignacion
- THEN 422 `no_directors_available`
- AND el director y sus `director_id` no cambian

### RF-DIR-07: Fallo parcial — rollback

WHEN cualquier paso de la transacción lanza una excepción (p. ej. el delete del usuario falla tras reasignar), the system SHALL revertir todos los cambios.

#### Escenario: Delete falla después de reasignar

- GIVEN director A con proyectos AND otro director B
- WHEN la transacción aborta en el delete
- THEN A sigue existiendo
- AND los proyectos siguen con `director_id = A.id`

### RF-DIR-08: Usuario que no es director

WHEN the POST se llama sobre un usuario que no es Director, the system SHALL rechazar con 422 AND SHALL NOT reasignar ni borrar (salvo que se documente como delete simple; este change MUST no borrar no-directores por este endpoint).

---

## Capacidad: gestion-proyectos-update

### RF-PRY-01: Cambiar director de un proyecto

WHEN a Coordinador envía `PUT /api/admin/proyectos/{id}` con `director_id` de un usuario con rol Director, the system SHALL persistir `proyectos.director_id` al nuevo valor AND devolver el proyecto con la relación `director` cargada.

The system SHALL NOT crear una segunda asignación: un proyecto tiene un único `director_id`.

The system SHALL mantener el middleware `role:Coordinador`.

Estudiante u otro rol MUST recibir 403.

#### Escenario: Cambio de director persiste

- GIVEN proyecto P con director A AND director B existente
- WHEN PUT `{ "director_id": B.id }`
- THEN `P.director_id = B.id`
- AND la respuesta incluye `director.id = B.id` y `director.name`

#### Escenario: director_id inválido (no es Director)

- GIVEN `director_id` de un Estudiante
- WHEN PUT
- THEN 422 AND `director_id` no cambia

#### Escenario: sync de estudiantes sin duplicar

- GIVEN proyecto P con estudiantes [E1]
- WHEN PUT `{ "director_id": B.id, "student_ids": [E1, E2] }` AND E2 no está en otro proyecto
- THEN pivote `proyecto_estudiante` queda exactamente {E1, E2} para P

---

## Capacidad: gestion-proyectos-group-filter

### RF-GRP-01: Filtro por grupo real

WHEN a Coordinador llama `GET /api/admin/proyectos?grupo_id={id}` (o `semester_id={id}`), the system SHALL devolver únicamente proyectos cuyo `semester_id` es ese id.

WHEN el query no trae grupo, the system MAY devolver todos (el frontend no muestra tabla hasta seleccionar grupo).

En Gestión de Proyectos, WHEN el coordinador cambia el grupo seleccionado, the lista SHALL actualizarse a los proyectos de ese semestre (hook `useProyectos(grupoId)` + filtro de `semester_id` en UI).

The system SHALL NOT usar datos estáticos ni un campo distinto de `semester_id` para el filtro.

#### Escenario: Dos grupos, filtro correcto

- GIVEN semestre S1 con proyecto P1 AND semestre S2 con proyecto P2
- WHEN GET `?grupo_id=S1.id`
- THEN `data` contiene P1 AND no contiene P2

#### Escenario: Cambio de grupo en UI

- GIVEN el coordinador tenía S1 seleccionado
- WHEN selecciona S2
- THEN la tabla muestra solo proyectos con `semester_id = S2.id`
