# Tasks: Mejora Dashboard entregas estudiante

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~120 |
| 400-line budget risk | Low |

---

## Phase 1 — Backend

- [x] T-001 **Test Pest payload entregas**. Assert `titulo` + `versiones[]` con campos esperados (número, archivo, fecha, observación). Caso sin versiones → array vacío. *Archivos: `tests/Feature/Api/EstudianteEntregasDashboardTest.php`*
- [x] T-002 **EstudianteController@entregas — incluir versiones**. Eager-load ordenado; mapear `versiones` con `numero_version`, `nombre_archivo`, `subido_en`, `observacion`, `estado` derivado. Mantener campos actuales. *Archivos: `app/Http/Controllers/Api/EstudianteController.php`*

## Phase 2 — Frontend

- [x] T-003 **types/estudiante.ts**. Extender `VersionData` con `observationPreview`. *Archivos: `resources/js/types/estudiante.ts`*
- [x] T-004 **EstudianteDashboard mapping**. Preferir `titulo`/`title` sobre `LABELS`; mapear versiones con fecha+hora y preview. *Archivos: `resources/js/pages/dashboard/EstudianteDashboard.tsx`*
- [x] T-005 **DeliveryAccordion**. Mostrar preview de observación truncada; vacío sin versiones sin romper. *Archivos: `resources/js/components/DeliveryAccordion.tsx`*

## Phase 3 — Verify

- [x] T-006 Pest en verde. → 2/2 PASSED (14 assertions).
- [x] T-007 `npm run build` OK.
- [x] T-008 Casos: 0/1/N versiones, notes largas, título = BD — cubiertos por test + truncado FE (80 chars).

## Out of scope

- DeliveryVersionHistory (huérfano), mocks, detalle entrega, PhaseStepper.
