# Archive Report: coordinador-integracion

**Status**: ✅ ARCHIVADO
**Fecha**: 2026-07-15
**Tipo**: Integración Frontend-Backend + Reformulación Coordinador

---

## Resumen

Conexión completa de todas las páginas del rol Coordinador desde datos mock a endpoints reales del backend. Reformulación de 10 módulos, nuevas páginas, y fixes de UI/UX.

## PRs Implementados

| PR | Enfoque | Archivos |
|----|---------|----------|
| PR1 | Dashboard KPIs + SupervisionReadOnly + Sidebar fixes | CoordinadorDashboard, SupervisionReadOnly, Sidebar, useKpis |
| PR2 | GestionProyectos reform (grupos, cupos, crear proyecto, autocomplete) | GestionProyectos, useProyectos, useGrupos, useCupos, useStudentSearch, StudentAutocomplete, GroupSelector |
| PR3 | Directores page (nueva) | DirectoresPage, useDirectores, app.tsx |
| PR4 | AsignacionEvaluadores reform (calendario, resultados, CRUD) | AsignacionEvaluadores, useEvaluadorProyecto, useEvaluaciones, CalendarGrid, ResultsTable |
| PR5 | GestionUsuarios tabla unificada | GestionUsuarios (tabla fusionada whitelist+roles) |
| PR6 | Entregas por grupo + Alertas + RecursosAdmin real | CoordinadorEntregas, GestionAlertas, RecursosAdmin, useEntregas, useAlertas, useRecursos |

## Métricas

| Métrica | Valor |
|---------|-------|
| Archivos tocados | ~40 |
| Líneas agregadas | ~4000 |
| Tests | 492 passed, 5 skipped |
| Commits | ~30+ |
| Hooks creados | 12 |
| Componentes creados | 6 |
| Migraciones | 5 |
| Endpoints backend creados/modificados | ~10 |

## Sidebar final Coordinador

Panel, Proyectos, Directores, Evaluadores, Usuarios, Anuncios, Alertas, Entregas, Recursos Admin

## Issues conocidos

- 3 tests fallan pre-existentes (no relacionados a este cambio)
- GestionUsuarios no usa el hook dedicado (usa apiFetch directo)
- Algunas páginas exceden 500 líneas

## Pendientes

- Hook useUnifiedUsers sin usar en GestionUsuarios
- Migrar ilike a like en PostgreSQL para producción
- Tests de frontend faltantes
