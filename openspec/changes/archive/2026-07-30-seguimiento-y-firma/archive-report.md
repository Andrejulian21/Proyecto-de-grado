# Archive Report: seguimiento-y-firma

**Archived**: 2026-07-30
**Source**: `openspec/changes/seguimiento-y-firma/` → `openspec/changes/archive/2026-07-30-seguimiento-y-firma/`
**Mode**: hybrid (openspec + engram)

## Resumen del Cambio

Implementación de 3 capacidades nuevas en el sistema de proyectos de grado:

1. **logbook-signature** (PR 1) — Firma de bitácoras con clave dinámica de 6 dígitos, expiración en 2 min, 5 intentos vía RateLimiter, re-solicitud única.
2. **coordinator-tracking** (PR 2 + PR 3) — Tablero de seguimiento por semestre para el coordinador con columnas dinámicas de entregas, grupos de fase colapsables y observaciones inline.
3. **logbook-weekly** (PR 4) — Campo semana (1-32) único por proyecto, backfill automático para existentes, ventana de edición de 15 min, renombre "descripción detallada" → "contenido".

## Especificaciones Sincronizadas

| Domain | Acción | Detalles |
|--------|--------|----------|
| seguimiento-y-firma | Creado | Spec completo copiado a `openspec/specs/seguimiento-y-firma/spec.md` como source of truth |

## Contenido del Archivo

| Artifact | Estado |
|----------|--------|
| proposal.md | ✅ |
| spec.md | ✅ |
| design.md | ✅ |
| tasks.md | ✅ (26/26 tareas completadas) |
| verify-report.md | ✅ |
| archive-report.md | ✅ (este documento) |

## Progreso de Tareas

| PR | Tareas | Estado |
|----|--------|--------|
| PR 1 — Firma por clave dinámica | 9 | ✅ Completado |
| PR 2 — Backend de seguimiento | 5 | ✅ Completado |
| PR 3 — Frontend de seguimiento | 5 | ✅ Completado |
| PR 4 — Semana, edición 15 min, rename | 7 | ✅ Completado |
| **Total** | **26** | **✅ 26/26** |

## Verificación

- **Tests**: 69 passed (300 assertions)
  - `BitacoraFirmaTest` (15 tests) — firma, intentos, expiración, re-solicitud
  - `BitacoraSemanaTest` (12 tests) — semana rango/duplicado, ventana edición
  - `SeguimientoTest` (3 tests) — auth, coordinador data, non-coordinador rejection
  - `BitacoraCrudTest` (13 tests) — CRUD completo
  - Additional unit + DB tests
- **Build**: Vite build exit 0 (1862 modules)
- **Coverage**: No code coverage driver configured

## Hallazgos del Verify Report (resueltos)

| Finding | Estado | Resolución |
|---------|--------|------------|
| Tasks PR 1/2/3 sin marcar (CRITICAL) | ✅ Resuelto | Todas las 26 tareas marcadas `[x]` |
| RF-TRK sin tests (CRITICAL) | ✅ Resuelto | `SeguimientoTest.php` creado con 3 tests pasando |
| Componentes inline vs separados (WARNING) | ⚠️ Aceptado | `ColumnaEntrega` y `ObservacionField` inline en `SeguimientoSemestre.tsx`. Funcionalidad correcta. |
| RF-WK-05 solo parcial verificado (WARNING) | ⚠️ Aceptado | Confirmado label "Contenido" en formulario creación |

## Decisiones de Arquitectura (del diseño)

| Decisión | Seguida |
|----------|---------|
| `signature_retries` en BD | ✅ |
| Upsert observaciones vía UNIQUE constraint | ✅ |
| Backfill semana con window function en migración | ✅ |
| Edición 15 min guardada en backend | ✅ |
| RateLimiter para 5 intentos de firma | ✅ |

## Observaciones Engram

| Artifact | Observation ID |
|----------|---------------|
| sdd/seguimiento-y-firma/proposal | #355 |
| sdd/seguimiento-y-firma/spec | #356 |
| sdd/seguimiento-y-firma/design | #357 |
| sdd/seguimiento-y-firma/tasks | #358, #359 |
| sdd/seguimiento-y-firma/verify-report | #369 |
| sdd/seguimiento-y-firma/archive-report | (este documento) |

## Source of Truth Actualizado

El spec ahora refleja el comportamiento implementado:
- `openspec/specs/seguimiento-y-firma/spec.md`

## SDD Cycle Complete

El cambio ha sido completamente planificado, implementado, verificado y archivado.
