# Archive Report: Backend Completo — Capa de Persistencia y API

**Change**: `backend-completo` | **Status**: ARCHIVED
**Date**: 2026-07-09 | **Sprint**: 2

---

## Resumen

Sprint 2 completado. Se materializó el modelo de datos de la Fase 2 como migraciones reversibles, modelos Eloquent, enums PHP nativos, controladores REST y ~40 endpoints protegidos por Sanctum + RBAC.

| Métrica | Valor |
|---------|-------|
| Tareas completadas | 22 (T-001 → T-022) |
| Tests (passed) | 373 |
| Assertions | 1,028 |
| Tests baseline (Sprint 1) | 151 |
| Tests agregados (Sprint 2) | +222 |
| Migraciones nuevas | 13 |
| Modelos nuevos | 12 |
| Enums nuevos | 5 |
| Controladores nuevos | 7 |
| Form Requests | 12+ |
| Endpoints | ~40 |
| PRs mergeados | 5 (PR 1–5) |

---

## Artefactos creados

### Migraciones (13)
`semestres`, `proyectos`, `proyecto_estudiante`, `evaluador_proyecto`, `entregas`, `versiones_documento`, `bitacoras`, `evaluaciones`, `notificaciones`, `anuncios`, `recursos_informativos`, `analisis_ia`, `sugerencia_director`

### Modelos (12 nuevos)
`Semestre`, `Proyecto`, `Entrega`, `VersionDocumento`, `Bitacora`, `Evaluacion`, `Notificacion`, `Anuncio`, `RecursoInformativo`, `AnalisisIa`, `SugerenciaDirector`, `EvaluadorProyecto`

### Enums (5 nuevos)
`FaseProyecto`, `EstadoEntrega` (con state machine), `EstadoFirma`, `EstadoInvitacionEvaluador`, `EstadoProyecto`, `CategoriaRecurso`

### Capacidades entregadas
| Capacidad | Descripción |
|-----------|-------------|
| `academic-semester` | CRUD semestres, máx 2 activos, seeders |
| `project-management` | CRUD proyectos, código auto-generado, asignación estudiantes/evaluadores |
| `deliverable-management` | Entregas por fase, versionado (máx 4), state machine (5 estados) |
| `meeting-log` | Bitácoras, firmas, detección sospechosa, horas mínimas |
| `evaluation` | Evaluación por criterio con pesos, notas consolidadas, reportes |
| `notification` | Notificaciones in-app + email queue (Redis) |
| `announcement-resource` | Anuncios vigentes, recursos por categoría con contador |
| `admin-dashboard` | KPIs: proyectos activos, en riesgo, alertas, tasa cumplimiento |

---

## Lecciones aprendidas

1. **State machine en modelo**: `canTransitionTo()` en `Entrega` y lógica de auto-avance de fase en observer funcionaron como diseño. Centralizar en el modelo evitó lógica dispersa en controladores.
2. **Pest + RefreshDatabase**: El enfoque TDD con tests de feature que refrescan DB por clase fue efectivo. 222 tests nuevos sin falso positivos.
3. **Auto-código con row-level lock**: El contador atómico en `semestres.next_proyecto_seq` con `lockForUpdate()` fue suficiente — sin deadlocks en tests de concurrencia.
4. **Bitácoras sospechosas**: La detección de firmas masivas (>3 en 5 min) requirió ajuste fino del window. Tests parametrizados ayudaron a calibrar.
5. **Notificaciones duales (in-app + email)**: Separar canal síncrono (in-app) de asíncrono (email queue) fue correcto. In-app aparece inmediato; email con retry 3x.

---

## Next steps

1. **Sprint 3: Frontend (React)** — Wireframes, pantallas de lista/detalle/formulario para cada dominio.
2. **Sprint 4: Directores + Evaluadores** — Flujo completo de asignación, aceptación/rechazo, dashboard de director.
3. **Sprint 5: Anuncios + Recursos + Notificaciones UI** — Vista de usuario para anuncios, recursos, notificaciones.
4. **Sprint 6: IA (FastAPI)** — Módulo de embeddings, análisis de coherencia, sugerencia de director.
5. **Sprint 7: QA + Deploy** — CI/CD, Docker refinado, despliegue Azure, E2E con Playwright.
6. **Pendiente**: TOTP para firmas de bitácoras (postergado a Sprint 4).
