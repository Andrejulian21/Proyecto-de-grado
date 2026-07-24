# Proposal: Restricción de eliminación de proyectos

## Intent

Corregir la eliminación de proyectos desde `GestionProyectos` e imponer la regla de negocio: un proyecto solo puede eliminarse si **nunca ha tenido entregas asociadas** (FK directa ni pivote `entrega_proyecto`).

## Problem

1. `DELETE /api/admin/proyectos/{id}` no está registrado: `apiResource` solo expone `index`, `store`, `show`.
2. `ProyectoController` no implementa `destroy`.
3. El frontend llama a DELETE, recibe error (404/405), no parsea el cuerpo y cierra el diálogo sin feedback útil.

## Scope

### In Scope
- Registrar e implementar `destroy` en el endpoint admin de proyectos.
- Validación backend: bloquear si existen entregas vía `entregas` o `entregasPivot`.
- Mensaje claro en español (422).
- Frontend: propagar y mostrar el error; éxito remueve del listado.
- Tests Pest de ambos casos.

### Out of Scope
- Soft-delete de proyectos, archivado, cascada de entregas.
- Cambios a crear/editar/consultar proyectos.
- Restricciones adicionales por bitácoras (solo entregas, según requisito).
- Nuevos modelos o migraciones.

## Approach

Reutilizar relaciones Eloquent existentes (`entregas()`, `entregasPivot()`). Extender el `apiResource` con `destroy`. Sin migraciones: las FK `cascadeOnDelete` no se invocan cuando la validación bloquea proyectos con entregas.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `ProyectoController` | Modified | Nuevo `destroy` + regla de entregas |
| `routes/api.php` | Modified | Incluir `destroy` en `only` |
| `useProyectos.ts` | Modified | Parsear mensaje de error en `eliminar` |
| `GestionProyectos.tsx` | Modified | Mostrar error de eliminación |
| `ProyectoCrudTest.php` | Modified | Casos sin/con entregas |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Entrega solo en pivote no detectada | Med | Chequear ambas relaciones |
| Cascade borraría entregas si se omitiera la validación | High | Gate duro en controller antes de `delete()` |

## Success Criteria

- [ ] Proyecto sin entregas se elimina y desaparece del listado.
- [ ] Proyecto con entregas (FK o pivote) → 422 con mensaje claro; permanece en BD.
- [ ] Crear/editar/listar no se afectan.
- [ ] Tests Pest y build frontend OK.
