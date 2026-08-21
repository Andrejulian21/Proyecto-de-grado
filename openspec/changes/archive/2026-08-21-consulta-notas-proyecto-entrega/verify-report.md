# Verify Report: consulta-notas-proyecto-entrega

**Date**: 2026-08-21
**Verdict**: PASS

## Completeness

| Metric | Value |
|--------|-------|
| Tasks | 5/5 complete |
| Specs existentes modificados | 0 |

## Tests

| Command | Result |
|---------|--------|
| `vendor/bin/pest tests/Feature/Api/ConsultaNotasTest.php` | 11 passed |
| `vendor/bin/pest tests/Feature/Admin/DirectorGradeTest.php` | 13 passed |
| `vendor/bin/pest tests/Feature/Evaluador/EvaluadorAsignacionesTest.php` | 19 passed |
| `npm run build` | exit 0 |

## Acceptance mapping

| # | Criterion | Evidence |
|---|-----------|----------|
| 1 | Coordinador ve proyectos permitidos | `el coordinador consulta las notas de los proyectos del semestre` |
| 2 | Estudiante solo los suyos | `el estudiante solo consulta sus propios proyectos` |
| 3 | Director solo asignados | `el director solo consulta sus proyectos asignados` |
| 4 | Evaluador solo asignados | `el evaluador solo consulta proyectos asignados` |
| 5 | Entregas al proyecto correcto | `las entregas aparecen asociadas al proyecto correcto` |
| 6 | Notas a la entrega correcta | `las notas aparecen asociadas a la entrega correcta y no al template` |
| 7 | Sin nota ≠ 0 | `una entrega sin nota no aparece como cero y el cero real se conserva` |
| 8 | Filtros | `los filtros de estado y busqueda se resuelven en el servidor` |
| 9 | IDOR 403 / 401 | `un usuario no autorizado…` + `exige autenticacion` |
| 10 | Datos de BD | `el evaluador ve su nota propia desde la base de datos…` + source `entrega_proyecto.director_grade` |

## Notes

- Fuente canónica: `entrega_proyecto.director_grade`. No se creó tabla paralela.
- UI: `/notas` en sidebar de los cuatro roles. “Sin calificar” cuando `nota === null`.
- No se ejecutó `migrate` contra la BD local.
