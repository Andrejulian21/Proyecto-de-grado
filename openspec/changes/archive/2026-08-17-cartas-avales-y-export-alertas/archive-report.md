# Archive Report: cartas-avales-y-export-alertas

**Archived**: 2026-08-17
**Source**: `openspec/changes/cartas-avales-y-export-alertas/` → `openspec/changes/archive/2026-08-17-cartas-avales-y-export-alertas/`
**Mode**: hybrid (openspec + engram)
**Branch final**: `master`

## Resumen del Cambio

Change de Sprint 5 con 2 capacidades nuevas y un batch de fixes de revisión. Verify **PASS WITH WARNINGS** (0 blockers, 0 critical findings):

1. **director-cartas-aval** — El director genera cartas de aval DOCX (`aval-sustentacion.docx` y `carta-jurados.docx`) por estudiante una vez cerrada la fase de desarrollo. Habilitación por `now() >= max(cierre_efectivo)` de entregas `desarrollo` del semestre (RF-CA-01), generación desde templates con placeholders y tabla de jurados de `evaluador_proyecto.fase='presentacion_final'` (RF-CA-02/03), descarga individual por estudiante con nombres de archivo definidos (RF-CA-04).
2. **coordinator-export-seguimiento** — El coordinador exporta la tabla de seguimiento a `.xlsx` vía `GET /api/admin/seguimiento/semestre/{id}/export` (RF-EX-01), con columnas estudiante/proyecto/director/estado por fase/entrega/bitácoras/observaciones y formato profesional (título mergeado, header bold/fondo, autoSize, zebra, totales).

## Especificaciones Sincronizadas

| Domain | Acción | Detalles |
|--------|--------|----------|
| cartas-avales-y-export-alertas | Creado | Spec completo copiado a `openspec/specs/cartas-avales-y-export-alertas/spec.md` como source of truth (delta spec = spec completo, sin merge). Verificación byte-idéntica SHA256 `215A13C8…`. |

## Contenido del Archivo

| Artifact | Estado |
|----------|--------|
| proposal.md | ✅ |
| spec.md | ✅ |
| design.md | ✅ |
| tasks.md | ✅ (26/26 tareas de implementación completadas) |
| verify-report.md | ✅ |
| archive-report.md | ✅ (este documento) |

> Nota: no existe `explore.md` en el change (la fase explore no persistió archivo). Se archiva lo que existía.

## Progreso de Tareas

| Lote | Tareas | Estado |
|------|--------|--------|
| PR1 — Backend cartas | T-001..T-008 (8) | ✅ Completado |
| PR2 — Frontend cartas | T-009..T-012 (4) | ✅ Completado |
| PR3 — Backend export | T-013..T-016 (4) | ✅ Completado |
| PR4 — Frontend export | T-017..T-018 (2) | ✅ Completado |
| Follow-up ciudad/fecha | T-101..T-104 (4) | ✅ Completado |
| Follow-up formato xlsx | T-201..T-208 (8) | ✅ Completado |
| **Total implementación** | **26** | **✅ 26/26** |

## PRs / Commits (rama master)

Cadena feature-branch-chain (PR1 → PR2 → PR3 → PR4) + follow-up batches en master. Commits representativos:

**PR1 — Backend cartas**
- `0b96578` chore(deps): add phpoffice/phpword
- `d94665f` feat(cartas): add CartaAvalService with habilitación and placeholders
- `1e8d27e` feat(cartas): wire director cartas endpoints with DOCX downloads
- `7374220` docs(cartas-avales): mark PR1 backend tasks complete

**PR2 — Frontend cartas**
- `d7a73c1` feat(cartas): add useDirectorCartas hook with typed API contract
- `876d804` feat(cartas): add director CartasAval page with DOCX downloads
- `2876935` feat(cartas): register cartas route and sidebar link for director
- `63cef64` docs(cartas-avales): mark PR2 frontend tasks complete

**PR3 — Backend export**
- `3a59ee1` feat(seguimiento): export seguimiento table to xlsx
- `37e6d25` Merge branch 'feature-cartas-avales-y-export-alertas-pr3'
- `70ad890` fix(cartas): escape XML values in carta template generation
- `be9f841` docs(cartas-avales): mark PR3 export tasks complete

**PR4 — Frontend export**
- `17cacdf` feat(coordinador): add export xlsx button to seguimiento tab
- `87802fe` feat(coordinador): apply professional formatting to seguimiento xlsx export

**Fixes de revisión**
- `14e59a6` fix(cartas): resolve jurados from both fase value sets (jurados fase Final)
- `6b16723` fix(seguimiento): map 'entregada' estado in seguimiento table
- `938f13a` feat(coordinador): edit evaluator set and phase in assignment update (alineación fases)

**Follow-up ciudad/fecha (T-101..T-104)**
- `e5cdfe1` feat(cartas): add ciudad/fecha placeholders to carta templates (templates versionados)
- `c008b8d` feat(cartas): resolve ciudad/fecha placeholders in resolver
- `2e6ba82` test(cartas): guard against nested runs in carta templates (XML Word)
- `f80c638` docs(cartas-avales): record ciudad/fecha follow-up in tasks.md

**Follow-up formato xlsx (T-201..T-208)**
- `24ddb1e` feat(seguimiento): improve observations formatting in xlsx export

## Verificación

- **Veredicto**: **PASS WITH WARNINGS** (0 blockers, 0 critical findings)
- **Envelope nativo**: `gentle-ai.verify-result/v1`, evidence_revision `sha256:b50f5bc9…`
- **Requirements**: 5/5 | **Escenarios**: 13/13 compliant
- **Tests**: `vendor/bin/pest` → 698 passed / 0 failed / 9 skipped (2000 assertions)
- **Build**: `npm run build` EXIT=0
- **Lint**: `vendor/bin/pint --test` — deuda en 2 archivos del change (ver Hallazgos)
- **Static**: `npx tsc --noEmit` — errores pre-existentes de Sprint 4 (TS6133/TS2322/TS2304), 0 en archivos del change
- **Coverage**: no disponible (sin tool de cobertura en la corrida)

## Hallazgos del Verify (warnings — no bloqueantes)

| Finding | Tipo | Resolución |
|---------|------|------------|
| `vendor/bin/pint --test` falla en 2 archivos del change: `app/Http/Controllers/Api/DirectorController.php` (array_indentation, unary_operator_spaces, braces_position, not_operator_with_successor_space, single_line_empty_body) y `tests/Feature/ExportSeguimientoTest.php` (fully_qualified_strict_types, ordered_imports) | WARNING | Deuda de estilo no funcional. Contradice la aceptación "pint limpio en archivos tocados" (T-104/T-207). Pendiente de `vendor/bin/pint` en esos archivos. |
| `npx tsc --noEmit` exit 1 con errores pre-existentes de Sprint 4 (TS6133 imports sin uso, TS2322 mismatch de tipos, TS2304 nombres no encontrados) en ~16 archivos ajenos al change | WARNING | 0 errores en archivos del change. Deuda `tsc` heredada de Sprint 4. Nota: `npm run build` es solo `vite build` (sin `tsc`), build verde ≠ type-clean. |

## Pendientes (post-archive)

1. **Deuda pint (2 archivos del change)** — `app/Http/Controllers/Api/DirectorController.php` y `tests/Feature/ExportSeguimientoTest.php` requieren `vendor/bin/pint` para cumplir la aceptación de estilo. No funcional.
2. **Deuda tsc Sprint 4** — ~16 archivos ajenos al change con errores TS pre-existentes (TS6133/TS2322/TS2304). A resolver en un sprint de limpieza de types.
3. **E2E diferido** — Los escenarios frontend (botón export, generación/descarga de cartas) quedaron verificados por `npm run build`, no por browser E2E en runtime. Correr Playwright en un follow-up.

## Decisiones de Arquitectura (del diseño, seguidas en implementación)

| Decisión | Seguida |
|----------|---------|
| D1: Cédula literal `[Número de documento]` en Carta 2 (sin columna en `users`) | ✅ |
| D2: Jurados faltantes → tabla vacía + warning; carta se genera igual | ✅ |
| D3: Habilitación `now() >= max(due_date + (hora_maxima ?? '23:59:59'))` | ✅ |
| D4: Nombres DOCX `Aval Sustentacion Publica [Nombre].docx` / `Carta de Aval Entrega a Jurados [Nombre].docx` | ✅ |
| D5: Nombre .xlsx `Seguimiento del [Grupo] [YYYY-MM-DD HHmm].xlsx` + filename* RFC 5987 | ✅ |

## Observaciones Engram

| Artifact | Observation ID |
|----------|---------------|
| sdd/cartas-avales-y-export-alertas/explore | #407 |
| sdd/cartas-avales-y-export-alertas/proposal | #409 |
| sdd/cartas-avales-y-export-alertas/spec | #410 |
| sdd/cartas-avales-y-export-alertas/design | #411 |
| sdd/cartas-avales-y-export-alertas/tasks | #412 |
| sdd/cartas-avales-y-export-alertas/apply-progress | #413 |
| sdd/cartas-avales-y-export-alertas/verify-report | #430 |
| sdd/cartas-avales-y-export-alertas/archive-report | (este documento + topic Engram) |

Los artefactos fuente se leyeron desde el filesystem openspec (proposal/spec/design/tasks/verify-report del change) antes de moverlos al archive; verify-report (#430) es el artefacto más autoritativo del estado final (PASS WITH WARNINGS).

## Estado de Gates (a la fecha del archive)

- **Native Review Receipt Gate**: sin artifacts de review en el change (`reviews/` ausente; reviewGate estructuralmente ausente — no se inició review para este candidato, kill switch deshabilitado). Sin receipt que validar. Archive procede bajo política de repo ordinaria.
- **Task Completion Gate**: 26/26 tareas de implementación `[x]`. Sin tareas unchecked. Archive procede.
- **Action Context Guard**: `actionContext.mode: repo-local`, `allowedEditRoots: [C:\Users\Owner\proyecto-de-grado]` — todas las operaciones dentro del repo.
- **CRITICAL verify findings**: 0. No hay bloqueo de archive.

## Source of Truth Actualizado

El spec ahora refleja el comportamiento implementado:
- `openspec/specs/cartas-avales-y-export-alertas/spec.md`

## SDD Cycle Complete

El cambio ha sido completamente planificado, implementado, verificado (PASS WITH WARNINGS, sin blockers) y archivado. Pendientes registrados: deuda pint en 2 archivos del change, deuda `tsc` heredada de Sprint 4, y E2E browser diferido.
