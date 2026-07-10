# Archive Report: hardening-audit-fixes

**Status**: ✅ ARCHIVADO
**Fecha**: 2026-07-10
**Tipo**: Hardening / Fixes de auditoría

---

## Resumen

15 hallazgos de auditoría de código del módulo `auth-access` fijados en 4 stacked PRs.

| PR | Enfoque | Issues |
|----|---------|--------|
| PR 1 — Security Critical | CSRF, rate limiting, constant-time login | #15, #16, #17 |
| PR 2 — Auth Model + Session | Cookie-only Sanctum, fix timeout, SPA cleanup | #20, #25, #26 |
| PR 3 — Backend Quality | FormRequests, queue, fillable, gates, password gen | #14, #18, #19, #22, #23, #24 |
| PR 4 — DB Schema + Cleanup | Índices, SoftDeletes, CHECK, stubs, ilike, sslmode | #21, #27, #28 |

## Impacto

| Métrica | Antes | Después |
|---------|-------|---------|
| Tests | 452 pasando | **495 pasando** (+44) |
| FormRequests | 0 | **5** |
| Rate limiting login | ❌ Genérico 60/min | ✅ 5/min per IP+email |
| CSRF | Exento en login/logout | ✅ Cerrado |
| Auth | Híbrido cookie+token | ✅ Cookie-only (ADR-003) |
| Secure cookie | Sin default (`null`) | ✅ `true` |
| ProtectedRoute role guard | ❌ No existía | ✅ Implementado |
| AuthorizedEmail fillable | Sin `name` | ✅ Con `name` |
| WriteAuditLog | Síncrono | ✅ ShouldQueue + sync fallback |
| Gates/Policies | Código muerto | ✅ Removido |
| Índices | `created_by` sin índice | ✅ Indexado |
| Email | Case-sensitive | ✅ lower() functional index |
| Soft-delete whitelist | Hard-delete | ✅ SoftDeletes |
| ExampleTest stubs | Existían | ✅ Removidos |
| sslmode | `prefer` | ✅ `require` por defecto |

## Verificación

- **Tests**: 495 passed, 5 skipped (PG-only), 0 failures
- **Frontend**: `npm run build` ✅
- **Spec compliance**: 14/14 requerimientos (H-001 a H-014) COMPLIANT

## Issues cerrados

#14, #15, #16, #17, #18, #19, #20, #21, #22, #23, #24, #25, #26, #27, #28

## Pendientes / Deferidos

- **timestamptz**: Documentado como known limitation — migrar `timestamp()` → `timestamptz()` requiere ALTER COLUMN por tabla, alto riesgo vs. poco valor para este alcance.
- **tests/Feature/ExampleTest.php**: No es stub (tiene health-check real). Sugerencia: renombrar en futuro PR.
- **Queue worker health check**: Deferido a futuro PR.

## Cambios en el repositorio

- 4 branches pusheados a GitHub
- 44 archivos tocados: +1425 / -691 líneas
- 6 migraciones nuevas
- 5 FormRequests nuevos
- 4 archivos de test nuevos