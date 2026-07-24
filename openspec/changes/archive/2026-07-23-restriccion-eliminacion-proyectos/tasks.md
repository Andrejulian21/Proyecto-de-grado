# Tasks: Restricción de eliminación de proyectos

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~100 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |

---

## Phase 1 — Backend (TDD)

- [x] T-001 **Tests Pest eliminación**. En `ProyectoCrudTest.php`: (1) proyecto sin entregas → DELETE 200 y ausente en BD; (2) proyecto con entrega FK → 422 y permanece; (3) proyecto con entrega solo pivote → 422. *Archivos: `tests/Feature/Admin/ProyectoCrudTest.php`*
- [x] T-002 **ProyectoController@destroy**. Gate con `entregas()->exists() \|\| entregasPivot()->exists()` → 422 con mensaje claro; si no, `delete()` y 200. *Archivos: `app/Http/Controllers/Admin/ProyectoController.php`*
- [x] T-003 **Registrar ruta destroy**. Ampliar `apiResource(...)->only([...])` con `destroy`. *Archivos: `routes/api.php`*

## Phase 2 — Frontend

- [x] T-004 **useProyectos.eliminar — parsear error**. Leer `body.error` / `body.message` en respuestas no OK. *Archivos: `resources/js/hooks/useProyectos.ts`*
- [x] T-005 **GestionProyectos — feedback**. Estado `deleteError` + `ErrorBanner` existente; cerrar diálogo solo en éxito; no alterar crear/editar. *Archivos: `resources/js/pages/coordinador/GestionProyectos.tsx`*

## Phase 3 — Verify

- [x] T-006 **Pest** en verde para casos de eliminación. → 3/3 PASSED; suite completa ProyectoCrudTest en verde.
- [x] T-007 **Build frontend** (`npm run build`) OK.
- [x] T-008 **Casos funcionales** 1 (sin entregas), 2 (con entregas), 3 (CRUD restante intacto).

## Out of scope

- Migraciones, soft deletes, restricción por bitácoras, cambios a update de proyectos.
