# Proposal: Módulo de Autenticación y Acceso

## Intent

Foundation of the entire system — without auth, nothing else works. Implements institutional Google OAuth login, RBAC, audit logging, and external evaluator access for HU01-HU03 and RF01-RF05. Every subsequent module depends on this one.

## Scope

### In Scope
- Google OAuth login restricted to @unab.edu.co (coordinator whitelist + `hd` claim + email suffix)
- RBAC: 4 roles (Estudiante, Director, Coordinador, EvaluadorExterno) via enum on User model + Laravel Gates/Policies
- Whitelist CRUD managed by coordinador (authorized emails, manual role assignment, no default role)
- Separate credential-based login for external evaluators (`es_externo = true`)
- Single active session per user (server-side enforcement)
- 8h inactivity timeout (session config + middleware)
- Immutable audit log (Events + Listeners, 5-year retention)
- Sanctum cookie-based auth for React SPA
- First-login profile auto-creation (RF03)

### Out of Scope
- TOTP implementation (deferred to bitácora module — architecture must support it)
- Notifications module (HU17-HU20)
- Chat module
- AI microservice integration
- Data export / Habeas Data (RNF06 — separate change)

## Capabilities

### New Capabilities
- `auth-oauth`: Google OAuth flow with Socialite, triple validation (hd claim + email suffix + whitelist), session management, single-session enforcement, 8h timeout
- `rbac`: Role enum, Gates/Policies, middleware, role-based routing (frontend + backend)
- `whitelist`: Coordinator CRUD for authorized emails, manual role assignment
- `audit-log`: Immutable event logging via Events/Listeners, coordinator query interface, 5-year retention policy
- `auth-external`: Separate credential login for external evaluators, coordinator-managed accounts

### Modified Capabilities
None — greenfield project.

## Approach

**Auth flow (institutional):**
1. User clicks "Iniciar con Google" → Socialite redirects to Google with `hd=unab.edu.co` hint
2. Callback handler validates: (a) `hd` claim == `unab.edu.co`, (b) email ends with `@unab.edu.co`, (c) email exists in whitelist table
3. If all pass → find or create User, assign role from whitelist entry, issue Sanctum cookie
4. If any fail → reject with clear error message
5. On login: delete all existing sessions for this user (single-session), record audit event

**Auth flow (external evaluators):**
- Separate `/login/externo` page with email + password form
- Coordinator creates account manually (name, email, password, role=EvaluadorExterno, `es_externo=true`)
- Same Sanctum cookie auth, same session rules

**RBAC:** `role` column as PHP enum on `users` table. Laravel Gates for simple checks, Policies for model-level authorization. Frontend reads role from `/api/user` and conditionally renders routes/menus.

**Whitelist:** `authorized_emails` table (email, role, created_by, created_at). Coordinator CRUD at `/coordinador/usuarios`. No self-registration — coordinator manually adds each email.

**Audit log:** `audit_logs` table (user_id, action, description, ip_address, user_agent, created_at). Written via Laravel Events + Listeners — never directly. No UPDATE/DELETE routes exposed. Coordinator can filter by user, date range, action type. Retention: 5 years, then archived or purged via scheduled command.

**Single session:** On login, `Session::where('user_id', $user->id)->delete()` before creating new session. Middleware validates session_id on each request.

**1h timeout:** `SESSION_LIFETIME=60` in `.env` + custom middleware that checks `last_activity` timestamp and force-logout if gap > 1h.

**Sanctum:** Cookie-based SPA authentication. `sanctum.php` config with `stateful` domains. CSRF via Sanctum's built-in token.

## Wireframes / UI Mapping

| Screen | Open Design File |
|--------|-----------------|
| Login institucional (Google OAuth) | `login-institucional.html` |
| Login evaluadores externos | `login-evaluadores-externos.html` |
| Panel del coordinador | `panel-coordinador.html` |
| Gestión de usuarios (whitelist) | `gestion-usuarios.html` |

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Models/User.php` | New | User model with role enum, relationships |
| `app/Models/AuditLog.php` | New | Immutable audit log model |
| `app/Models/AuthorizedEmail.php` | New | Whitelist model |
| `app/Http/Controllers/Auth/` | New | OAuth + external login controllers |
| `app/Http/Middleware/` | New | RoleGuard, SessionTimeout, SingleSession |
| `app/Policies/` | New | Authorization policies per role |
| `app/Events/` + `app/Listeners/` | New | Audit event system |
| `database/migrations/` | New | users, authorized_emails, audit_logs, sessions tables |
| `routes/api.php` | New | Auth + user management routes |
| `resources/js/pages/auth/` | New | Login pages (institutional + external) |
| `resources/js/pages/coordinador/` | New | User management + audit log views |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Google `hd` claim missing or spoofed | Med | Triple validation: hd + email suffix + whitelist. Never trust hd alone. |
| Single-session bypass via stale cookies | Low | Server-side session validation on every request middleware |
| Whitelist is manual — onboarding friction | High | Accepted tradeoff for security. Coordinator UX must be smooth. |
| Audit log grows unbounded | Med | 5-year retention + scheduled archival command |
| Sanctum CORS misconfiguration | Med | Test with actual Vite dev server + production domain early |

## Rollback Plan

Auth module is foundational — rollback means reverting to `main` branch before this change. All migrations are reversible (`down()` methods). No data migration in this change. Feature flag `AUTH_MODULE_ENABLED` wraps the new routes during initial rollout.

## Dependencies

- Google Cloud Console OAuth 2.0 credentials (client_id + client_secret)
- PostgreSQL 16 running
- Redis 7 for session storage
- HTTPS configured (RNF02)

## Success Criteria

- [ ] Google OAuth login works for whitelisted @unab.edu.co accounts
- [ ] Non-whitelisted and non-UNAB accounts are rejected with clear messages
- [ ] External evaluator login works with coordinator-created credentials
- [ ] Each role sees only their authorized routes and UI elements
- [ ] Audit log captures all auth events and is queryable by coordinator
- [ ] Single-session enforcement: login on device B kills device A session
- [ ] 8h inactivity timeout logs user out
- [ ] All Pest tests pass (unit + integration), coverage ≥ 80% on auth module
