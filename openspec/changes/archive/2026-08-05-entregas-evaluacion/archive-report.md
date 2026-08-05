# Archive Report: entregas-evaluacion

**Archived**: 2026-08-05
**Source**: `openspec/changes/entregas-evaluacion/` → `openspec/changes/archive/2026-08-05-entregas-evaluacion/`
**Mode**: hybrid (openspec + engram)
**Branch final**: `feature-entregas-evaluacion-pr5` (5 PRs encadenados + bug-fix batch + corrección D3-rev)

## Resumen del Cambio

Change de Sprint 5 con 3 capacidades nuevas (sin colisión con `seguimiento-y-firma`), 14 RFs y 36 escenarios:

1. **entregas-archivos** — Default de archivo principal (`documento-proyecto`) enforceado en backend (RF-ENT-01), campo `analizable_ia` solo para el archivo principal (RF-ENT-02), columna `grade_percentage` informacional 0-100 (RF-ENT-03), regla de pesos 100% por par de fases a nivel de semestre con bloqueo preventivo y validación de completitud (RF-ENT-04), e indicador visual de suma acumulada del par (RF-ENT-05).
2. **evaluacion-evaluador** — Cards de asignaciones del evaluador con aislamiento (RF-EVA-01), detalle con contexto completo + 403 ajeno (RF-EVA-02), envío de nota inmutable con 422/409 (RF-EVA-03), toggle de evaluados con vista solo lectura (RF-EVA-04), persistencia en `evaluador_proyecto.evaluado` + tabla `evaluaciones_evaluador` con UNIQUE (RF-EVA-05).
3. **nota-director** — Columna `director_grade` en `entrega_proyecto` (RF-NOT-01), campo de nota al aprobar observación (RF-NOT-02), edición solo mientras la entrega esté activa (RF-NOT-03), nota visible al evaluador por proyecto (RF-NOT-04). Incluye **enmienda D3-rev**: la nota es POR ENTREGA DEL ESTUDIANTE (por proyecto), no de la entrega general; la columna legacy `entregas.director_grade` queda sin uso.

## Especificaciones Sincronizadas

| Domain | Acción | Detalles |
|--------|--------|----------|
| entregas-evaluacion | Creado | Spec completo copiado a `openspec/specs/entregas-evaluacion/spec.md` como source of truth (delta spec = spec completo, sin merge) |

## Contenido del Archivo

| Artifact | Estado |
|----------|--------|
| explore.md | ✅ |
| proposal.md | ✅ |
| spec.md | ✅ |
| design.md | ✅ |
| tasks.md | ✅ (44/44 tareas de implementación completadas) |
| verify-report.md | ✅ |
| archive-report.md | ✅ (este documento) |

## Progreso de Tareas

| Lote | Tareas | Estado |
|------|--------|--------|
| PR1 — Schema Foundation | T-001..T-007 (7) | ✅ Completado |
| PR2 — Entregas Backend | T-008..T-016 (9) | ✅ Completado |
| PR3 — Evaluador Backend | T-017..T-019 (3) | ✅ Completado |
| PR4 — Nota Director | T-020..T-022 (3) | ✅ Completado |
| PR5 — Frontend | T-023..T-029 (7) | ✅ Completado |
| Bug-fix batch PR5 (r1 + r2) | B-001..B-009 (9) | ✅ Completado |
| Corrección de dominio D3-rev | D-rev-01..D-rev-06 (6) | ✅ Completado |
| **Total implementación** | **44** | **✅ 44/44** |

Nota: el `verify-report.md` rotula "Tasks total 38" pero la suma real de sus propios lotes (29 + 9 + 6) y el conteo de checkboxes del tasks.md archivado es 44. El estado nativo (`gentle-ai sdd-status`) cuenta 50 checkboxes totales (44 implementación + 6 criterios globales), 49 completos.

## PRs / Commits (rama feature-entregas-evaluacion-pr5)

Cadena feature-branch-chain sobre tracker `feature/entregas-evaluacion` (draft, no-merge). Commits finales del head:

- `bda58e1` feat(entregas): store director_grade on the per-project delivery (schema)
- `f6d811f` fix(entregas): persist director grade on the student delivery on review
- `370d8bd` fix(evaluador): expose per-project director_grade in detalle and cards
- `5501245` fix(entregas): show per-project director grade in review and evaluator UI
- `cb5b12e` fix(entregas): include start window fields in create/update closures
- `7a801d8` fix(estudiante): resolve React 310 hook-order crash in entrega detail
- `cf741aa` fix(entregas): resolve semestre_nombre from semester_id on update
- `fd408ac` open spec añadido (artefactos openspec commiteados antes del archive)

## Verificación

- **Veredicto**: **PASS WITH WARNINGS** (0 blockers, 0 critical findings)
- **Envelope nativo**: `gentle-ai.verify-result/v1`, evidence_revision `sha256:8dc8ee58…`
- **Requirements**: 14/14 | **Escenarios**: 36/36 (31 con test pasando; 5 frontend-only verificados por build)
- **Tests**: `vendor/bin/pest` → 632 passed / 9 skipped / 0 failed (1781 assertions)
- **Build**: `npm run build` EXIT=0 (vite, 1865 modules)
- **Lint**: `vendor/bin/pint --test` limpio en archivos backend tocados
- **Static**: `npx tsc --noEmit` — 65 errores pre-existentes (Sprint 4), 0 nuevos
- **Coverage**: no disponible (sin driver pcov/Xdebug en el entorno)

## Hallazgos del Verify (warnings — no bloqueantes)

| Finding | Tipo | Resolución |
|---------|------|------------|
| Criterio global "Migraciones rollback sin pérdida (migrate:rollback --step=3)" unchecked | WARNING | Untestable por REGLAS DURAS (prohibido correr migraciones contra la DB real). Schema tests confirman reversibilidad de la migración D3-rev vía `:memory:`. Sin riesgo real. |
| 5 escenarios frontend-only (RF-ENT-05 ×3, RF-EVA-04 ×2) sin evidencia E2E browser | WARNING | Verificados por `npm run build` EXIT=0; QA manual recomendada. E2E diferido (ver Pendientes). |

## Pendientes (post-archive)

1. **E2E diferido** — Los 5 escenarios frontend-only (indicador suma del par RF-ENT-05 y toggle/ver evaluados RF-EVA-04) quedaron verificados por build, no por browser E2E. El spec `e2e/entregas-evaluacion.spec.ts` existe pero no se ejecutó a nivel runtime en verify. Correr Playwright en un follow-up.
2. **HardeningPr2 — decisión de dueño** — Deuda de auditoría de hardening pendiente de decisión del dueño del producto (decisión de negocio, fuera del alcance del SDD). Ver `hardening-audit-fixes` archivado.
3. **Deuda legacy** — Columna `entregas.director_grade` queda SIN USO tras la enmienda D3-rev (la nota vive en `entrega_proyecto.director_grade`). No se elimina: una migración destructiva no está justificada en este punto. Backfill/limpieza a decisión futura.

## Decisiones de Arquitectura (del diseño, seguidas en implementación)

| Decisión | Seguida |
|----------|---------|
| D1: Una o más entregas por fase (sin UNIQUE) | ✅ |
| D2: Par incompleto sin bloqueo, advertencia visual | ✅ |
| D3-rev: `director_grade` en `entrega_proyecto` (por proyecto) | ✅ |
| D4: Misma lógica Store/Update (EntregaPesoService) | ✅ |
| D5: Nota editable solo con entrega activa | ✅ |
| D6: Re-envío de evaluación bloqueado con 409 | ✅ |
| D7: Notas 0-5 con 2 decimales; `grade_percentage` 0-100 | ✅ |

## Observaciones Engram

| Artifact | Observation ID |
|----------|---------------|
| sdd/entregas-evaluacion/explore | #375 |
| sdd/entregas-evaluacion/proposal | #376 |
| sdd/entregas-evaluacion/spec | #378 |
| sdd/entregas-evaluacion/design | #380 |
| sdd/entregas-evaluacion/tasks | #381 |
| sdd/entregas-evaluacion/apply-progress | #382 |
| sdd/entregas-evaluacion/verify-report | #402 |
| sdd/entregas-evaluacion/archive-report | (este documento + topic Engram) |

## Estado de Gates (a la fecha del archive)

- **Native Review Receipt Gate**: sin artifacts de review en el change (`reviews/` ausente; reviewPolicy/ledger/receipt/state missing en status nativo); el orquestador deshabilitó review para este ciclo (`disabled/unmanaged`). Sin receipt que validar.
- **Task Completion Gate**: 44/44 tareas de implementación `[x]`. El único checkbox unchecked es el criterio global de aceptación "Migraciones rollback sin pérdida", untestable por REGLAS DURAS y documentado como WARNING en verify-report (ver Hallazgos). Archive procede como **intentional-with-warnings** con aprobación explícita del orquestador (no hay CRITICALs).
- **Action Context Guard**: `actionContext.mode: repo-local`, `allowedEditRoots: [C:\Users\Owner\proyecto-de-grado]` — todas las operaciones dentro del repo.

## Source of Truth Actualizado

El spec ahora refleja el comportamiento implementado:
- `openspec/specs/entregas-evaluacion/spec.md`

## SDD Cycle Complete

El cambio ha sido completamente planificado, implementado, verificado (PASS WITH WARNINGS, sin blockers) y archivado. Pendientes registrados: E2E browser diferido, decisión de dueño HardeningPr2, y deuda legacy `entregas.director_grade`.
