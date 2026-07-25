# Tasks: Integración módulo Evaluador

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~900 |
| 400-line budget risk | Medium (single change, focused module) |
| Chained PRs recommended | No (unless review asks) |
| Delivery strategy | apply-in-order |

---

## Phase A — Backend API

- [x] T-001 **Crear `EvaluadorController`** con `evaluaciones()`, `kpis()`, `entregaFase()` reutilizando el patrón de `DirectorController` (sin excluir `director_id`; incluir director, `assigned_at`, `evaluation_status`, `rating`). *Archivos: `app/Http/Controllers/Api/EvaluadorController.php`*
- [x] T-002 **Registrar rutas `/api/evaluador/*`** en el grupo `auth:sanctum`. *Archivos: `routes/api.php`*
- [x] T-003 **Tests Pest** `EvaluadorDashboardTest.php`: con asignaciones, vacío, aislamiento, kpis, entrega-fase 403/404. *Archivos: `tests/Feature/Api/EvaluadorDashboardTest.php`*

## Phase B — Frontend wiring

- [x] T-004 **Hook `useEvaluadorEvaluaciones`** (patrón `useDirectorEvaluaciones`). *Archivos: `resources/js/hooks/useEvaluadorEvaluaciones.ts`*
- [x] T-005 **Helper `datoNoEncontrado`** (módulo util del evaluador o `lib`). *Archivos: `resources/js/lib/datoNoEncontrado.ts`*
- [x] T-006 **Wire `EvaluadorDashboard`**: eliminar mocks; KPIs + cards reales; navegar a detalle. *Archivos: `resources/js/pages/dashboard/EvaluadorDashboard.tsx`*
- [x] T-007 **Wire `EvaluarProyecto`**: `useParams`, carga real, documento, submit `POST /api/evaluaciones`, missing messages. *Archivos: `resources/js/pages/evaluador/EvaluarProyecto.tsx`*
- [x] T-008 **Wire `EvaluadorCalificar`**: mismo contrato de datos/navegación/submit. *Archivos: `resources/js/pages/evaluador/EvaluadorCalificar.tsx`*
- [x] T-009 **Sidebar EvaluadorExterno**: Panel/Evaluaciones a rutas del módulo. *Archivos: `resources/js/components/layout/Sidebar.tsx`*

## Phase C — Seed + verify

- [x] T-010 **Seed**: asignar evaluador Angel al proyecto demo (`invitation_status` Aceptada, fase). *Archivos: `database/seeders/TestUsersSeeder.php`*
- [x] T-011 **Verify**: `vendor/bin/pest tests/Feature/Api/EvaluadorDashboardTest.php` + `npm run build`; checklist manual Casos 1–4.
- [x] T-012 **Archive** change a `openspec/changes/archive/2026-07-25-integracion-modulo-evaluador` tras verify OK.
