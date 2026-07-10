# Archive Report: fix-critical-issues

**Change**: fix-critical-issues
**Tipo**: Bug fixes + infraestructura
**Estado**: ✅ ARCHIVADO
**Fecha**: 2026-07-09

## Resumen

5 issues críticos resueltos en GitHub, todos mergeados a master. Los fixes cubren seguridad (inmutabilidad DB, single-session), backend (AuditArchive, lockout) e infraestructura (CI pipeline).

## Stats

| Métrica | Valor |
|---------|-------|
| Issues cerrados | 5 (#9, #10, #11, #12, #13) |
| Tests totales | **452** (antes: 439 — baseline +13) |
| Archivos nuevos | 6 (CI pipeline, SDD artifacts, test) |
| Commits | 1 (`b6c13eb` — fix(#9): add CI pipeline and close critical issues) |
| CI pipeline | `.github/workflows/ci.yml` — Pest + Pint + npm build sobre PostgreSQL 16 |

## Issues resueltos

| # | Título | Fix |
|---|--------|-----|
| #12 | Inmutabilidad DB de audit_logs | Trigger PostgreSQL `BEFORE UPDATE OR DELETE` con migración |
| #11 | AuditArchive no funcional | `DB::table()` raw delete + `SET session_replication_role = replica` bypass + schedule |
| #10 | Single-session sin cookie | `SESSION_DRIVER=database` + `purgePriorSessions()` en AuthController |
| #13 | Lockout sin ventana temporal | Sliding window en `User::isLocked()` + `registerFailedLogin()` |
| #9 | Sin CI pipeline | `.github/workflows/ci.yml` con 13 steps, servicio PostgreSQL |

## Lecciones aprendidas

1. **Validación cruzada**: Los sub-agentes (`sdd-spec-prueba`, `sdd-design-prueba`) encontraron gaps que el orquestador inline no detectó — validación independiente es valiosa incluso cuando el contenido está correcto.
2. **Skills del registry**: Cargar las skills específicas (`gh-actions`, `database-migrations`, `strict-tdd`) desde `.atl/skill-registry.md` antes de implementar evita errores de patrón.
3. **Código ya implementado**: Varios fixes ya existían de sesiones anteriores sin issues cerrados — importante verificar estado real antes de planificar.

## Próximo paso recomendado

Sprint 4: **Frontend completo** — portear los 32 wireframes de Open Design a React.
