# Proposal: Fix validación envío evaluación

## Intent

Corregir el rechazo falso de `POST /api/evaluaciones` cuando el evaluador sí está asignado, y hacer que cada condición de error devuelva un mensaje específico (nunca un mensaje de “no asignado” por otra causa).

## Root cause

`EvaluacionController@store` valida la asignación así:

```php
EvaluadorProyecto::where('proyecto_id', $entrega->proyecto_id)
    ->where('evaluador_id', $user->id)
    ->exists();
```

En producción/dev muchas entregas tienen `proyecto_id = null` y se vinculan vía pivot `entrega_proyecto`.  
`GET /api/evaluador/proyectos/{id}/entrega-fase` ya resuelve esas entregas por pivot, por eso el UI carga bien; al enviar, `proyecto_id` nulo hace fallar la consulta y responde 403 con mensaje de no asignación — **engañoso**.

## Scope

### In Scope
- Resolver IDs de proyecto de la entrega (FK directa + pivot).
- Validar asignación contra esos IDs.
- Mensajes de error específicos por condición.
- Tests Pest (pivot OK, no asignado, sin evaluadores, sin proyecto vinculado).

### Out of Scope
- Cambios de UI/wireframe.
- Migraciones de schema.
- Módulos ajenos al envío de evaluación.

## Success Criteria

- [ ] Evaluador asignado + entrega solo-por-pivot → 201.
- [ ] Mensajes distintos para: sin vínculo proyecto, sin evaluadores, usuario no asignado, porcentajes, estado no evaluable.
- [ ] Tests existentes de calificación siguen pasando (ajustados si el copy del 403 cambia).
