# Archive Report: Director Integration

**Change:** `director-integracion`
**Branch:** `feature/director-integracion`
**Commit:** `ca6bf54` — `feat: integración completa del módulo Director (PRs 1-4)`
**Date:** 2026-07-16
**Verdict:** ✅ ARCHIVED WITH WARNINGS

---

## Summary

Conexión completa del rol Director a APIs reales. Se reemplazaron todos los datos mock del frontend Director por llamadas `apiFetch()` a endpoints reales del backend. El cambio se implementó en 4 PRs encadenados + fixes adicionales, en un solo commit que tocó 50 archivos (+5849/-609 líneas).

## What Was Implemented

### PR 1 — Navbar + Dashboard
- **Sidebar.tsx**: navConfig.Director reducido de 7 a 5 entradas. Active state corregido (Panel con `end`, Supervisión con `isActive` custom).
- **DirectorController.php**: 3 endpoints nuevos (`proyectos`, `kpis`, `entregas`) con scope automático por `director_id` y semestres activos.
- **3 hooks**: `useDirectorProyectos`, `useDirectorKpis`, `useDirectorEntregas` con patrón `useReducer`.
- **DirectorDashboard.tsx**: Eliminados MOCK_KPIS, MOCK_PROGRESS, MOCK_DELIVERIES, PhaseStepper. Carga concurrente con `Promise.all`. 4 StatCards con KPIs reales, carrusel horizontal de proyectos (max 5), DataTable con entregas. Estados loading/error/empty.

### PR 2 — Supervisión + Bitácoras
- **BitacoraController@firmar**: Extendido para firma directa del director desde Pendiente → Completada (sin TOTP, sin pasar por FirmadaEstudiante).
- **Endpoint** `GET /api/director/proyectos/{id}/bitacoras`: bitácoras scoped al director del proyecto.
- **SupervisionProyectoDirector.tsx**: Lista de cards con datos reales + detalle con entregas.
- **BitacorasDirector.tsx**: Conectado a API real, botón Firmar con confirm modal y optimistic update.
- **DetalleEntregaDirector.tsx**: Conectado a endpoints reales, revisión con nota 0.0–5.0.

### PR 3 — Evaluaciones (Calificación 0–5)
- **EvaluacionController@store**: `grade` max:100 → max:5.
- **EntregaController@revisar**: `consolidated_grade` max:100 → max:5.
- **EvaluacionController@consolidado**: Fórmula ajustada con `* 100` para normalización correcta.
- **Endpoint** `GET /api/director/evaluaciones`: proyectos donde el director es evaluador (excluye propios).
- **Endpoint** `GET /api/director/proyectos/{id}/entrega-fase`: entrega aprobada de la fase.
- **EvaluacionesDirector.tsx**: Componente nuevo (723 líneas) con lista + detalle + form calificación 0.0–5.0.

### PR 4 — Recursos (Download Fix + UI)
- **Recursos.tsx**: `file_path`/`link` agregados al interface y `fromApi()`. Botón descarga funcional. Rediseño UI con mejor jerarquía visual, metadata real, search con debounce 300ms.
- **RecursoDetalle.tsx**: Fix botón "Descargar" onClick, metadata real, breadcrumb mejorado.
- **Symlink** `public/storage` verificado.

### Fixes Adicionales
- Entregas por pivot para director (`orWhereHas` para proyectos grupales)
- Detalle de entrega para coordinador (mejoras transversales)

## Spec Compliance

| Spec | Requirements | Scenarios | PASS |
|------|-------------|-----------|------|
| sidebar-navigation | 1 | 3 | 3/3 |
| director-dashboard-api | 4 | 5 | 5/5 |
| director-ui | 6 | 15 | 14/15 (1 UNVERIFIED) |
| supervision-api | 5 | 10 | 10/10 |
| evaluator-grade-api | 4 | 10 | 9/10 (1 UNVERIFIED) |
| **Total** | **20** | **43** | **40/43** |

## Build & Test Status

| Metric | Value |
|--------|-------|
| Build | ✅ 0 errors, 1857 modules, 2.72s |
| Tests passed | 493 ✅ |
| Tests failed (pre-existing) | 2 ⚠️ (unrelated: `HardeningPr2Test`, `SingleSessionOnLoginTest`) |
| Tests skipped | 5 |
| Assertions | 1300 |
| Test files modified | 3 (`EntregaCrudTest`, `EvaluacionCrudTest`, `NotificacionTest`) |

## What Was NOT Implemented (Deferred)

| Item | Reason | Impact |
|------|--------|--------|
| **4 dedicated test files** | No se crearon `DirectorDashboardTest.php`, `DirectorBitacoraTest.php`, `EvaluacionEscalaTest.php`, `RecursosTest.php` | No hay verificación runtime de flujos Director; 3 escenarios quedaron UNVERIFIED |
| **Playwright smoke tests per PR** | Especificados en proposal risks pero no ejecutados | Riesgo de mock-to-real switch breaks sin cobertura E2E |
| **TOTP bitácora signing** | Fuera de scope (state-only) | Flujo de firma directa sin segundo factor |
| **AI, deployment, reports** | Fuera de scope (Sprint 5-7) | Pendiente para próximos sprints |

## Documented Warnings (del verify-report)

| ID | Issue | Severity |
|----|-------|----------|
| W1 | **Tasks.md stale**: 22/31 tasks unchecked a pesar de código completamente implementado. Solo Phase 3 (T-015–T-023) marcada como hecha. | ⚠️ WARNING |
| W2 | **Missing dedicated tests**: 4 archivos de test especificados en el design no fueron creados. | ⚠️ WARNING |
| W3 | **Supervision active state incomplete**: `isActive` solo triggerea en `/bitacoras`, no en todos los subroutes de `/supervision`. | ⚠️ WARNING |
| W4 | **KPI response wrapped in `data`**: Design spec muestra flat response, pero implementación devuelve `{data: {...}}`. Frontend funciona correctamente pero difiere del design. | ⚠️ WARNING |

## Suggestions (from verify-report)

| ID | Suggestion |
|----|-----------|
| S1 | Run `php artisan storage:link` verification test |
| S2 | Add Playwright smoke tests per PR |
| S3 | Add phase-consistency verification in `EvaluacionController@store` |
| S4 | Update `tasks.md` to reflect actual completion state |
| S5 | Create the 4 missing dedicated test files |

## Lessons Learned

1. **Stale task tracking**: `tasks.md` no se actualizó durante la implementación. El verify-report pudo detectar que el código estaba completo vs los checkboxes. Para futuros cambios, actualizar tasks.md como parte del Definition of Done en cada PR.
2. **Missing tests infrastructure**: El design especificaba 4 test files dedicados que no se crearon. Los test files deberían ser parte del PR review gate.
3. **Response shape alignment**: La discrepancia entre la respuesta plana del design y la respuesta envuelta en `data` de la implementación (`W4`) muestra la importancia de alinear contracts API antes de codificar.
4. **Active state edge cases**: El partial match de Supervisión (`W3`) muestra que las rutas anidadas necesitan verificación explícita de todos los subroutes.

## Design Coherence

| Design Decisions | Status |
|-----------------|--------|
| DirectorController with 3+ methods | ✅ Superset (6 methods) |
| Routes `/api/director/*` in auth:sanctum | ✅ MATCH |
| Sidebar Director: 5 entries | ✅ MATCH |
| BitacoraController@firmar — director direct sign | ✅ MATCH |
| Grade max:5 validation | ✅ MATCH |
| Consolidado formula | ✅ MATCH (with `* 100`) |
| Recursos: file_path/link + download | ✅ MATCH |
| Director dashboard concurrent loading | ✅ MATCH |
| Horizontal project carousel (max 5) | ✅ MATCH |
| PhaseStepper removed | ✅ MATCH |

## Next Steps

1. **Create missing test files**: `DirectorDashboardTest.php`, `DirectorBitacoraTest.php`, `EvaluacionEscalaTest.php`, `RecursosTest.php` (Sprint 5 candidate)
2. **Update tasks.md** to reflect actual completion (clerical cleanup)
3. **Fix supervision active state** to cover all `/supervision` subroutes
4. **Proceed to Sprint 5**: Integración backend — reemplazar mock data restante con `apiFetch()` en otros roles

---

*Archived by SDD archive workflow. Verify evidence: commit `ca6bf54`, 50 files changed, build 0 errors, 493 tests passed.*
