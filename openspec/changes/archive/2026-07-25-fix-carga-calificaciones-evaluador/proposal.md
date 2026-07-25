# Proposal: Fix carga calificaciones evaluador

## Intent

Al reabrir una evaluación ya enviada, mostrar exactamente los puntajes por criterio persistidos en BD (hoy el comentario sí aparece, los scores quedan en 0).

## Root cause

1. El envío desde `EvaluarProyecto` guarda criterios: *Cumplimiento de Objetivos*, *Calidad Técnica…*, etc.
2. “Ver evaluación” navega a `EvaluadorCalificar`, que hidrata contra otra lista fija (*Contenido y Estructura*, …).
3. El match `saved.criterio === c.name` falla → scores quedan en 0.
4. El comentario se toma con `saved.find(s => s.comment)` → sí se muestra.
5. Además, `EvaluarProyecto` **no** vuelve a cargar grades al abrir.

Persistencia en BD es correcta (verificado: grades 5/4/3/5 + comment).

## Scope

- Helper compartido de hidratación desde `GET /api/evaluaciones?entrega_id=`.
- Rúbrica por defecto unificada (la usada al guardar).
- Wire `EvaluadorCalificar` + `EvaluarProyecto` para reconstruir criterios desde filas guardadas.
- OpenSpec + verify + archive.

## Out of Scope

- Cambiar schema / API de store.
- Rediseño visual.
- Módulos ajenos.

## Success Criteria

- [ ] Reabrir evaluación muestra cada grade almacenado.
- [ ] Total = suma de grades.
- [ ] Observación intacta.
- [ ] Build TS OK.
