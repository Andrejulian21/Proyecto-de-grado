## Exploration: hardening-audit-fixes

### Current State

All 15 issues are **CONFIRMED PRESENT** in the current codebase. Zero have been fixed.

> **Path correction**: Issues referenced `AuthController.php` and `UserController.php` without full paths. Actual locations:
> - `app/Http/Controllers/Auth/AuthController.php` (418 lines)
> - `app/Http/Controllers/Admin/UserController.php` (331 lines)

---

#### Grupo A — Seguridad (ALTA)

| Issue | Status | Evidence |
|-------|--------|----------|
| **#15 CSRF** | **PRESENT** | `LoginExterno.tsx:41-46` raw fetch sin XSRF-TOKEN. `useAuth.tsx:132-138` logout raw fetch. `bootstrap/app.php:41-44` exenta logout Y externo/login de CSRF. |
| **#16 Enumeración** | **PRESENT** | `AuthController.php:267-280` — 403 `not_external_evaluator` vs 401 `invalid_credentials` revelan existencia. `Hash::check()` solo corre si `$user` existe (L293). |
| **#17 Rate limiting** | **PRESENT** | `AppServiceProvider.php:30-34` — solo limiter genérico 60/min. Login externo sin limiter dedicado. |
| **#19 Rol EvaluadorExterno** | **PRESENT** | `UserController.php:60-66` — `updateUsuario()` valida contra `UserRole::cases()` completo (incluye EvaluadorExterno). `store()` usa WHITELIST_ROLES pero update no. |
| **#20 Auth híbrido** | **PRESENT** | `AuthController.php:152` crea token `google-oauth` que se descarta. `loginExterno` L313-316 hace `Auth::login()` + `createToken()` — dual cookie/bearer incoherente. |
| **#25 Timeout incompleto** | **PRESENT** | `ActivityMiddleware.php:48` borra token pero NO llama `Auth::logout()` ni invalida sesión cookie. `routes/api.php:129` grupo admin omite `activity`, `single_session`, `ensure_password_changed`. |
| **#26 Fugas SPA** | **PRESENT** | `config/session.php:175` — `secure` sin default (null si env no set). `LoginExterno.tsx:50` guarda bearer en sessionStorage. `app.tsx:16-32` ProtectedRoute sin role guard. `useAuth.tsx` logout no cancela poll interval. |

#### Grupo B — Backend (ALTA/MEDIA)

| Issue | Status | Evidence |
|-------|--------|----------|
| **#14 Password Math.random** | **PRESENT** | `GestionUsuarios.tsx:50-57` — `genPassword()` usa `Math.random()`. L246-253 POST body solo lleva email+name; el backend genera password en `storeExternal()`. El password del frontend es cosmético. |
| **#18 AuthorizedEmail.name** | **PRESENT** | `AuthorizedEmail.php:33-37` — `$fillable = ['email', 'role', 'created_by']`. La columna `name` existe (migration `2026_07_08_193544`) pero no está en fillable → se descarta silenciosamente. `UserController.php:222` pasa name pero se pierde. |
| **#22 Gates sin uso** | **PRESENT** | `AuthServiceProvider.php:29,36-45` — `UserPolicy` y Gates `manage-users`/`view-admin` registrados. Grep confirma: **cero call-sites en producción** (solo en tests). Middleware `role:` hace el trabajo. |
| **#23 Sin FormRequests** | **PRESENT** | Directorio `app/Http/Requests/` no existe. Validación inline en controllers. AuthController 418 líneas, UserController 331 líneas. `findOrFail(int $id)` manual en vez de route-model binding. |
| **#24 Listener síncrono** | **PRESENT** | `WriteAuditLog.php` no implementa `ShouldQueue`. Resuelve `request` del contenedor en `handle()`. Cada audit event bloquea el request cycle. |

#### Grupo C — Base de datos (ALTA/MEDIA)

| Issue | Status | Evidence |
|-------|--------|----------|
| **#21 FK sin índice + email case** | **PRESENT** | `create_authorized_emails_table.php:28-31` — `foreignId('created_by')->constrained()` sin `->index()`. `create_users_table.php:31` — `string('email')->unique()` es case-sensitive en PostgreSQL. |
| **#27 Esquema** | **PRESENT** | Migrations usan `timestamp()` no `timestamptz()`. `create_audit_logs_table.php:55` índice single en `action` redundante con compuesto `(action, created_at)` de migration posterior. `users.role` sin CHECK constraint. `destroyUsuario()` hard-delete. |

#### Grupo D — Docs/Cleanup (BAJA)

| Issue | Status | Evidence |
|-------|--------|----------|
| **#28 Limpieza** | **PRESENT** | `tests/Unit/ExampleTest.php` stub. `extractHostedDomain()` L231-237 tiene rama muerta (redundante). `like` vs `ilike` en searches. `sslmode=prefer`. |

---

### Affected Areas

**Backend (PHP):**
- `app/Http/Controllers/Auth/AuthController.php` — #15, #16, #20, #25, #26, #28
- `app/Http/Controllers/Admin/UserController.php` — #18, #19, #23, #27
- `app/Http/Middleware/ActivityMiddleware.php` — #25
- `app/Models/AuthorizedEmail.php` — #18
- `app/Providers/AppServiceProvider.php` — #17
- `app/Providers/AuthServiceProvider.php` — #22
- `app/Listeners/WriteAuditLog.php` — #24
- `bootstrap/app.php` — #15
- `config/session.php` — #26
- `routes/api.php` — #17, #25

**Frontend (React/TS):**
- `resources/js/pages/auth/LoginExterno.tsx` — #15, #26
- `resources/js/hooks/useAuth.tsx` — #15, #26
- `resources/js/app.tsx` — #26
- `resources/js/pages/coordinador/GestionUsuarios.tsx` — #14

**Database (Migrations):**
- `database/migrations/0001_01_01_000000_create_users_table.php` — #21, #27
- `database/migrations/2026_07_06_000001_create_authorized_emails_table.php` — #21
- `database/migrations/2026_07_06_000002_create_audit_logs_table.php` — #27
- `database/migrations/2026_07_06_000004_add_external_auth_columns_to_users.php` — #27

**New files needed:**
- `app/Http/Requests/` directory — #23
- `app/Jobs/WriteAuditLogJob.php` — #24

---

### Dependencies Between Issues

```
#15 (CSRF) ←→ #26 (SPA leaks)     — ambos tocan fetch/cookie auth flow
#20 (auth híbrido) → #25 (timeout) — timeout debe entender el dual cookie/token
#21 (FK/index) → #27 (schema)      — ambos tocan migrations; hacer juntos
#14 (password) → #23 (FormRequests) — si creamos FormRequests, mover validación password ahí
#19 (role whitelist) → #22 (gates)  — si activamos Gates, el role check podría usarlos
#23 (FormRequests) es transversal   — afecta AuthController + UserController
```

**Cluster dependencies:**
- Cluster 1 (Security): #15, #16, #17, #26 — todos tocan auth flow
- Cluster 2 (Auth model): #20, #25 — inseparables, mismo flujo
- Cluster 3 (Backend quality): #14, #18, #19, #22, #23, #24 — refactoring
- Cluster 4 (DB schema): #21, #27 — migrations

---

### Approach Recommendation: 4 Stacked PRs (stacked-to-main)

**PR 1 — Security Critical** (issues #15, #16, #17)
- Add dedicated `login` rate limiter (5/min per IP+email)
- Unify login error responses (constant-time: same 401 for all failures, always run Hash::check)
- Switch frontend to axios with CSRF interceptor OR add XSRF-TOKEN header to fetch calls
- Remove CSRF exemption for `api/auth/externo/login` (keep for logout if needed)
- Risk: HIGH — touches login flow, must not break existing sessions
- Tests: add timing-attack test, rate-limit test

**PR 2 — Auth Model + Session** (issues #20, #25, #26)
- Decide: cookie-only SPA auth OR bearer-only for external. Remove the unused one.
- Fix ActivityMiddleware: call `Auth::logout()` + invalidate session on timeout
- Add `activity`, `single_session`, `ensure_password_changed` to admin route group
- Set `SESSION_SECURE_COOKIE` default to `true` in production
- Remove sessionStorage token storage; use cookie-only
- Cancel poll interval on logout
- Add role guard to ProtectedRoute
- Risk: HIGH — core auth flow, needs careful testing
- Tests: session invalidation, timeout, admin middleware chain

**PR 3 — Backend Quality** (issues #14, #18, #19, #22, #23, #24)
- Create `app/Http/Requests/` with FormRequest classes for auth + user controllers
- Fix `AuthorizedEmail` fillable (add `name`)
- Restrict `updateUsuario` role validation to `WHITELIST_ROLES` (or remove EvaluadorExterno)
- Remove or activate Gates/Policies (recommend: remove, middleware handles it)
- Make `WriteAuditLog` implement `ShouldQueue`
- Remove frontend `genPassword()` (backend generates; frontend just displays)
- Risk: MEDIUM — isolated refactoring, low regression risk
- Tests: FormRequest validation, queued listener

**PR 4 — Database Schema** (issues #21, #27, #28)
- New migration: add index on `authorized_emails.created_by`
- New migration: add `citext` extension + change email columns to case-insensitive
- New migration: change `timestamp()` → `timestampTz()` (or document why not)
- New migration: drop redundant single-column indexes on audit_logs
- New migration: add CHECK constraint on `users.role`
- New migration: add `deleted_at` to users table for soft-delete
- Change `destroyUsuario` to soft-delete
- Cleanup: remove ExampleTest stub, dead code in extractHostedDomain, fix like→ilike
- Risk: MEDIUM — migrations are additive, but citext change needs care
- Tests: migration up/down, soft-delete behavior

---

### Risks

1. **PR 1 regression**: Changing login error responses could break frontend error handling. Must update `LoginExterno.tsx` error parsing in same PR.
2. **PR 2 auth model**: Removing bearer token from loginExterno could break mobile/future clients. Need to confirm no other consumers exist.
3. **PR 3 queued listener**: Moving audit to queue requires queue worker running. If queue fails, audit logs are lost. Need `failed_jobs` monitoring.
4. **PR 4 citext migration**: Changing email column type on existing data needs `USING email::citext`. Must test with production data volume.
5. **Soft-delete cascade**: Adding soft-delete to users affects `authorized_emails.created_by` FK and all queries that `findOrFail` users. Must audit all User queries.
6. **Test coverage**: 151 baseline tests. Each PR must maintain or increase coverage. Auth changes need integration tests.

---

### Ready for Proposal

**Yes.** The exploration confirms all 15 issues are present and the dependency graph is clear. The orchestrator can proceed to the proposal phase with the 4-PR grouping above. The user should be told:

> "Los 15 issues están confirmados presentes. Recomiendo 4 PRs apilados: (1) seguridad crítica, (2) modelo auth + sesiones, (3) calidad backend, (4) esquema DB. El orden importa: seguridad primero porque es lo más riesgoso, schema al final porque es aditivo y no rompe nada."
