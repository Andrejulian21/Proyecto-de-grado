# Proposal: Comentarios del Director por versión de entrega

## Intent

Corregir el guardado de observaciones del Director durante la revisión de entregas para que cada `VersionDocumento` conserve su propio `director_notes`, en lugar de asociar siempre el comentario a la última versión.

## Problem

Hoy el esquema ya modela observaciones por versión (`versiones_documento.director_notes`), y el frontend lee/muestra notas por versión. Sin embargo, `PUT /api/admin/entregas/{id}/revisar` escribe `director_notes` únicamente en la versión con mayor `version_number`. Además, las pantallas de revisión no envían el `version_id` seleccionado ni sincronizan el textarea al cambiar de versión. Resultado: al comentar sobre una versión histórica se sobrescribe (o solo se actualiza) la última.

## Scope

### In Scope
- Extender `EntregaController@revisar` para aceptar `version_id` opcional y persistir notas en esa versión.
- Mantener fallback a la última versión si no se envía `version_id` (compatibilidad).
- Sincronizar UI del Director: selector de versión ↔ observación editable ↔ payload de guardado.
- Tests Pest del caso multi-versión.
- Compatibilidad con entregas/versiones existentes (sin migración).

### Out of Scope
- Nuevas tablas, modelos o endpoints.
- Cambios al flujo de decisión (aprobada / necesita ajustes / nota consolidada).
- Cambios de estilo visual no necesarios.
- TOTP, IA, notificaciones, reportes.
- Refactor amplio de páginas no relacionadas.

## Capabilities

### Modified Capabilities
- `entrega-revision-director`: observaciones ligadas a la versión seleccionada.

### Unchanged Capabilities
- Subida de versiones, eliminación condicionada, habilitación, avance de fase, lectura estudiante/coordinador de notas por versión.

## Approach

Reutilizar el campo existente `VersionDocumento.director_notes` y el endpoint `revisar`. Impacto mínimo: validar `version_id` (pertenece a la entrega) y actualizar esa fila; en frontend enviar `version_id` de la versión seleccionada y cargar sus notas al cambiar de versión.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `EntregaController@revisar` | Modified | Persistir notes por `version_id` |
| `RevisionEntregaDirector.tsx` | Modified | Sync + enviar `version_id` (ruta activa) |
| `DetalleEntregaDirector.tsx` | Modified | Sync/selección + enviar `version_id` |
| `EntregaCrudTest.php` | Modified | Casos multi-versión |
| Migraciones / modelos nuevos | None | Campo ya existe |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Clientes antiguos sin `version_id` | Low | Fallback a última versión |
| `version_id` de otra entrega | Low | Validar pertenencia; 422 |
| Sobrescribir notas históricas por error de UI | Med | Textarea sincronizado a versión seleccionada |

## Rollback Plan

Revertir el change: restaurar bloque que actualiza solo la última versión y quitar `version_id` del payload frontend. Sin migraciones que revertir.

## Dependencies

- Tabla `versiones_documento` con `director_notes` (ya en producción del esquema).
- Endpoint `GET /api/admin/entregas/{id}` que ya incluye `versiones`.

## Success Criteria

- [ ] Cada versión conserva su propio comentario tras guardar.
- [ ] Cambiar de versión actualiza el comentario mostrado/editable.
- [ ] Guardar modifica solo la versión seleccionada.
- [ ] Entregas existentes siguen funcionando sin migración.
- [ ] Tests Pest del escenario multi-versión pasan.
- [ ] Build frontend sin errores TypeScript.
