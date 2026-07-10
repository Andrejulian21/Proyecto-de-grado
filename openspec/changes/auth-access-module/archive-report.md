# Archive Report: auth-access-module

**Change**: `auth-access-module` | **Status**: ARCHIVED
**Date**: 2026-07-10 | **Sprint**: 1

---

## Resumen

Sprint 1 completado — Fundación de identidad, autorización, sesión y auditoría para toda la plataforma.

| Métrica | Valor |
|---------|-------|
| Tareas completadas | 18 (T-014 → T-031) |
| Tests (passed) | 151 |
| Assertions | 441 |
| Migraciones nuevas | 5 |
| PRs mergeados | 3 (chained: PR1 infra → PR2 backend → PR3 frontend) |
| Branch tracker | `feature/auth-module` → `main` |

---

## Artefactos entregados

### PR 1 — Infraestructura
- Scaffolding del proyecto, configuraciones base, migraciones iniciales

### PR 2 — Auth Backend
- Google OAuth callback con triple validación (hd + @unab.edu.co + whitelist)
- Login evaluador externo con lockout por 3 intentos
- Forced password change + EnsurePasswordChanged middleware
- RoleMiddleware (roles separados por coma)
- Gates + UserPolicy (manage-users, view-admin)
- Whitelist CRUD (index, store, update, destroy)
- SingleSessionMiddleware (sesión única)
- ActivityMiddleware (timeout 1h inactividad)
- Logout + sessionCheck endpoints
- AuditLogController (paginated, filterable)
- Audit archive command (`audit:archive`, 5-year retention)

### PR 3 — Frontend UI
- LoginInstitucional.tsx (Google OAuth con branding UNAB)
- LoginExterno.tsx (credenciales para evaluadores externos)
- useAuth hook + AppShell layout (sidebar + header + user chip)
- GestionUsuarios.tsx (whitelist CRUD UI)
- AuditLog.tsx (visor con filtros)
- DashboardRouter.tsx (redirección por rol + placeholders)

---

## Verificación

- **Tests**: 151 passed, 0 failures, 441 assertions
- **Frontend**: `npm run build` ✅ (262 KB JS / 19 KB CSS gzipped)
- **Spec compliance**: 100% — HU01–HU03, RF01–RF05, RNF01, RNF03, RNF04

---

## Próximo paso recomendado

Sprint 2: Backend completo — migraciones, modelos, enums, controladores, ~40 endpoints.
