# Tasks: Comentarios del Director por versión de entrega

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~120 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Delivery strategy | single-unit |

---

## Phase 1 — Backend (TDD)

- [x] T-001 **Test Pest multi-versión**. Añadir en `EntregaCrudTest.php`: crear entrega con 3 versiones; `PUT .../revisar` con `version_id` de v1, luego v2, luego v3 con notas distintas; assert cada fila conserva su `director_notes`. Caso `version_id` inválido → 422. *Archivos: `tests/Feature/Admin/EntregaCrudTest.php`*
- [x] T-002 **EntregaController@revisar — version_id**. Validar `version_id` nullable|integer; si presente, resolver versión de esa entrega o 422; actualizar `director_notes` ahí. Si ausente, fallback a última versión (comportamiento actual). No cambiar status/grade/notificaciones/auto-avance. *Archivos: `app/Http/Controllers/Admin/EntregaController.php`*

## Phase 2 — Frontend Director

- [x] T-003 **RevisionEntregaDirector — binding por versión**. Al cambiar `selectedVersion`, cargar `director_notes` en el textarea. En `handleSubmitReview`, enviar `version_id: selectedVersion.id`. No alterar layout/estilos. *Archivos: `resources/js/pages/director/RevisionEntregaDirector.tsx`*
- [x] T-004 **DetalleEntregaDirector — binding por versión**. Introducir selección de versión para el formulario de notas (default: última); sincronizar textarea; enviar `version_id` en `PUT .../revisar`. Mantener listado de versiones con sus comentarios. *Archivos: `resources/js/pages/director/DetalleEntregaDirector.tsx`*

## Phase 3 — Verify

- [x] T-005 **Pest**. Ejecutar tests de `EntregaCrudTest` (y suite relevante) en verde. → 3 tests nuevos PASSED (10 assertions).
- [x] T-006 **Build frontend**. `npm run build` sin errores TypeScript. → OK (vite build exit 0).
- [x] T-007 **Validación funcional documentada**. Confirmado vía Pest: v1/v2/v3 con comentarios independientes; editar una no altera las demás; fallback sin `version_id`; `version_id` ajeno → 422.

## Out of scope (no tasks)

- Migraciones, nuevos modelos/endpoints, cambios estudiante/coordinador de lectura, estilos no relacionados.
