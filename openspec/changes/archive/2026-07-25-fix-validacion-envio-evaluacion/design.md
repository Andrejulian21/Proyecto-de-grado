# Design: Fix validación envío evaluación

## Flow today

1. UI `EvaluarProyecto` / `EvaluadorCalificar` → loop `POST /api/evaluaciones`.
2. `EvaluacionController@store` valida payload → carga `Entrega` → check `EvaluadorProyecto` con `$entrega->proyecto_id` → suma porcentajes → create.

## Failure point

`$entrega->proyecto_id === null` (vínculo solo en `entrega_proyecto`) ⇒ `where('proyecto_id', null)` no encuentra asignación ⇒ 403 “No estás asignado…”.

Evidence (dev DB): entrega `#5` `proyecto_id=null`, pivot `[1,2]`; evaluador `#20` asignado a proyecto `1`.

## Fix (minimal)

```php
$entrega->loadMissing('proyectos:id');
$proyectoIds = collect([$entrega->proyecto_id])
    ->merge($entrega->proyectos->pluck('id'))
    ->filter()
    ->unique()
    ->values();
```

Then:

| Condition | HTTP | Message |
|-----------|------|---------|
| `$proyectoIds` vacío | 422 | La entrega no está vinculada a ningún proyecto. |
| Hay proyectos pero ningún `evaluador_proyecto` | 403 | El proyecto no tiene un evaluador asignado. |
| Hay asignaciones pero no al user | 403 | El usuario autenticado no corresponde al evaluador asignado. |
| Suma % > 100 | 422 | (mensaje existente de percentage) |
| Criterio ya existe (unique) | 422 | La evaluación para este criterio ya fue enviada. |

Nota: no se añadió gate nuevo de `status === aprobada` para no romper el contrato API existente; el UI ya solo obtiene entregas aprobadas vía `entrega-fase`.

Reuse `Entrega::scopeParaProyecto` pattern / pivot relation already on model — no new relations.

## Impact

- Solo `EvaluacionController@store` (+ tests).
- Frontend: ya muestra `body.error` / `errors.percentage` — sin cambios obligatorios.
