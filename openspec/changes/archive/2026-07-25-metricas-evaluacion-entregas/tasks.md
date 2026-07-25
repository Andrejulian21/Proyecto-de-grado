# Tasks: Métricas de evaluación en entregas

- [x] T-001 **OpenSpec docs** — proposal / design / spec / tasks (este change).
- [x] T-002 **Migración** — agregar `evaluation_metrics` (text, nullable) a `entregas` con `down()`.
- [x] T-003 **Modelo** — incluir `evaluation_metrics` en `$fillable` de `Entrega`.
- [x] T-004 **API** — validar y persistir en `EntregaController@store` (`metricas_evaluacion`) y `@update` (`evaluation_metrics`).
- [x] T-005 **Frontend types/hook** — extender `Entrega`, `CreateEntregaPayload`, `UpdateEntregaPayload` en `useEntregas`.
- [x] T-006 **UI campo + guía** — componente `MetricasEvaluacionField` (textarea, tooltip, modal guía) e integrar en create/edit de `CoordinadorEntregas`.
- [x] T-007 **Tests Pest** — create y update persisten/recuperan métricas; omitir campo sigue OK.
- [x] T-008 **Verify** — Pest relevante + `npm run build`.
- [x] T-009 **Archive** → `openspec/changes/archive/2026-07-25-metricas-evaluacion-entregas` tras verify OK.
