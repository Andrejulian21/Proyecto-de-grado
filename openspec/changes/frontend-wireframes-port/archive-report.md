# Archive Report: Frontend Wireframes Port

**Change**: `frontend-wireframes-port` | **Status**: ARCHIVED
**Date**: 2026-07-10 | **Sprint**: 4

---

## Resumen

Porteo completo de ~29 wireframes de Open Design a React 18 + TypeScript + Tailwind v4 + shadcn/ui. Sprint 4 — frontend puro con mock data, sin integración backend. 14 PRs encadenados vía feature-branch-chain hacia `feature/frontend-wireframes-port`.

## Stats

| Métrica | Valor |
|---------|-------|
| Tareas completadas | 31 (T-001 → T-031) |
| Componentes compartidos creados | 7 |
| Páginas nuevas/mejoradas | 29 |
| PRs encadenados | 14 |
| Archivos tocados | ~45 |
| Tests backend | 495 passed (sin regresiones) |
| Build frontend | 1843 modules, 0 errors, 4.21s |
| Chunks lazy generados | 33 (~3-7 kB cada uno) |

## Lo completado

### Componentes Compartidos (7)
StatusBadge (7 variantes), StatCard (KPI con trend), PageHeader (eyebrow+h2+actions), DataTable (genérica con paginación), EmptyState, ConfirmDialog (danger variant), TOTPInput (6 dígitos autofocus)

### Batch 1 — Dashboards + Shared Pages (PRs 1-3)
- EstudianteDashboard: hero card, stepper 4 fases, upload zone, accordion entregas, version table
- CoordinadorDashboard: 4 KPIs, proyectos table, alertas cards
- DirectorDashboard: bezel header, progress cards, entregas table
- EvaluadorDashboard: KPIs, evaluation cards con star rating
- AnunciosPublica, AnuncioDetalle, Recursos, RecursoDetalle
- Routing + Sidebar navConfig + ROUTE_TITLES

### Batch 2 — Landing + Estudiante (PRs 4-5)
- LandingPage: pública, hero + 5 role cards, sin AppShell, auth-aware redirect
- BitacorasEstudiante: DataTable con 3 estados firma
- NuevaBitacora: formulario 2-col + TOTP mock
- DetalleEntregaEstudiante: split-screen documento + review

### Batch 3 — Director Flow (PRs 6-8)
- SupervisionProyectoDirector: bezel + stepper + info cards + entregas expandibles
- SeleccionProyectosBitacoras: search + grid proyectos
- BitacorasDirector: stat cards + filtros + DataTable
- DetalleFirmaBitacora: TOTP signature panel
- RevisionEntregaDirector: split-screen + 3 botones decisión

### Batch 4 — Coordinador Gestión (PRs 9-10)
- GestionProyectos: semester bar + DataTable + crear grupo (1-3 estudiantes) + cupos
- AnunciosAdmin: cards + CRUD form + ConfirmDialog
- AsignacionEvaluadores: register form + tabla + agenda

### Batch 5 — Coordinador Resto + Evaluador (PRs 11-13)
- CoordinadorEntregas, CoordinadorBitacoras, GestionAlertas
- ReportesConsolidados, RecursosAdmin
- EvaluarProyecto (rúbrica 4 criterios), EvaluadorCalificar

### Batch 6 — IA Mock (PR 14)
- AnalisisAutomaticoEntregas: coherence score + checklist
- AsistenteOrientacion: chatbot con sugerencias director

## Skills aplicadas

react-patterns, shadcn-ui, tailwind-patterns, typescript-expert, accessibility, impeccable, make-interfaces-feel-better, css-animations, chained-pr, work-unit-commits

## Lecciones aprendidas

1. **Skills en sub-agentes**: Pasar solo la ruta de la skill no garantiza que la lean. Hay que inyectar las reglas clave directamente en el prompt.
2. **Branch naming**: Usar guiones (`feature/fw-port-batch-1`) en vez de slashes para evitar colisión con el tracker branch.
3. **Lazy loading**: 33 chunks generados automáticamente. El bundle principal se mantiene en ~300 KB.
4. **Responsive mobile-first**: Los wireframes eran desktop-first. La adaptación a mobile-first con Tailwind grids funcionó bien.

## Riesgos abiertos

- Iconos de lucide-react con nombres distintos a los de Material Symbols de los wireframes → mapeo manual necesario
- TOTPInput es mock, no valida contra backend real
- Páginas IA son mock, sin conexión a FastAPI

## Próximo paso recomendado

**Sprint 5: Integración backend** — Reemplazar todos los `MOCK_DATA` con llamadas `apiFetch()` a los ~40 endpoints del Sprint 2. Registrar rutas en api.php si es necesario.
