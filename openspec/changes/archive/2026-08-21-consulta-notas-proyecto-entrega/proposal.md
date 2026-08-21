# Proposal: Sistema general de consulta de notas por proyecto y entrega

## Intent

Ofrecer un apartado único de **consulta de notas** (no de registro) organizado por proyecto y entrega. Coordinador, estudiante, director y evaluador ven los mismos tipos de dato, acotados a su ámbito. Los datos salen de la BD existente (`entrega_proyecto.director_grade` y, para el evaluador, `evaluaciones_evaluador.nota`).

## Scope

### In Scope

- Sección de UI `/notas` para los cuatro roles, con sidebar.
- API de consulta scoped por rol (backend).
- Árbol Proyecto → entregas → nota (o “Sin calificar”).
- Distinguir nota `0` de ausencia de nota.
- Filtros: semestre, proyecto, entrega, estado de nota; búsqueda por código/título.
- Tests Pest de autorización, asociación y filtros.

### Out of Scope

- Modificar specs existentes.
- Registrar o recalcular notas (review del director, rúbrica del evaluador).
- Nueva tabla de calificaciones.
- Exportación PDF/Excel (el seguimiento ya exporta estados, no este change).
- Mocks o datos estáticos.

## Capabilities

### New Capabilities

- `consulta-notas-por-rol`: listado de proyectos y entregas con nota, filtrable, autorizado en servidor.

### Modified Capabilities

Ninguna capacidad de escritura. El sidebar gana un ítem “Notas”.

## Approach

Reutilizar `entrega_proyecto.director_grade` (D3-rev: nota del director **por proyecto**, no la del template `entregas.consolidated_grade`). Para el evaluador, además su `evaluaciones_evaluador.nota` de la asignación. Consultas Eloquent con scope por rol. UI inspirada en el seguimiento de semestre (selector, tabla expandible), sin copiar observaciones ni bitácoras.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Services/ConsultaNotasService.php` | New | Query scoped + filtros en BD |
| `app/Http/Controllers/Api/ConsultaNotasController.php` | New | GET `/api/notas` |
| `routes/api.php` | Modified | Ruta autenticada, cuatro roles |
| `app/Models/Proyecto.php` | Modified | Relación `entregaProyectos` si falta |
| `resources/js/pages/shared/ConsultaNotas.tsx` | New | UI |
| Sidebar, App.tsx, AppShell | Modified | Navegación |
| `tests/Feature/Api/ConsultaNotasTest.php` | New | 10 criterios |

## Assumptions

1. Nota canónica de una entrega para un proyecto = `entrega_proyecto.director_grade` (0.00–5.00 o NULL).
2. `entregas.consolidated_grade` / `entregas.director_grade` no se usan como fuente de esta consulta (son del template).
3. Evaluador: proyectos de `evaluador_proyecto`; nota propia = `evaluaciones_evaluador`.
4. Coordinador: todos los proyectos del semestre (como seguimiento).
5. “Sin calificar” = no hay fila de pivot o `director_grade` es NULL.

## Non-Goals

- No `php artisan migrate` contra BD real (no hace falta migración).
- No cambiar ReviewEntregaAction ni el flujo de calificar del evaluador.
- No modificar `openspec/specs/`.

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Mostrar 0 cuando la nota es NULL | Med | Comparar estrictamente con null; tests con nota 0 vs sin nota |
| Usar nota del template en vez del pivot | Med | Leer solo `entrega_proyecto` |
| SemestreSelector admin-only | Med | Semestres vienen en la propia API de notas |

## Rollback Plan

Revertir el commit. No hay migración. Las notas existentes no se tocan.

## Success Criteria

- [ ] Cada rol ve solo sus proyectos.
- [ ] Entrega sin nota muestra “Sin calificar”, no `0`.
- [ ] Nota `0.00` se muestra como `0.00`.
- [ ] Filtros se resuelven en el servidor.
