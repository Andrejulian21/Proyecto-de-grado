# Exploration: Módulo de Autenticación y Acceso (HU01-HU03, RF01-RF05, RNF01-RNF05)

## Current State

Greenfield — no code exists. Project directory at `C:\Users\Owner\Proyecto de grado\` contains only context documents and SDD config. Stack is Laravel 11 + React/Vite + PostgreSQL + Redis. Team of 2 developers with Laravel experience.

## Affected Areas

- `app/Models/User.php` — User model with role enum, domain validation
- `app/Models/AuditLog.php` — Immutable audit log model
- `app/Http/Middleware/` — RBAC middleware, domain validation middleware
- `app/Http/Controllers/Auth/` — Socialite callback, login, logout controllers
- `app/Listeners/` — Authenticated event listener for domain restriction + auto-profile creation
- `app/Policies/` — Role-based policies for resource access
- `database/migrations/` — users, roles (or enum), audit_logs tables
- `routes/api.php` + `routes/web.php` — Auth routes with middleware groups
- `config/services.php` — Socialite Google OAuth config
- `resources/js/` — React login page, role-based dashboards, audit log viewer

## Analysis by Requirement

---

### HU01 / RF01 / RNF01 — Login institucional con Google OAuth (@unab.edu.co)

**Complexity:** Low-Medium
**Effort:** 1-1.5 days

**Technical approach:**
1. Install `laravel/socialite` with Google provider
2. Register Google OAuth app in Google Cloud Console (scoped to UNAB tenant if possible)
3. In the Socialite callback handler, validate `hd` (hosted domain) claim from Google's ID token — this is the **server-side** domain check required by RNF01
4. If domain != `@unab.edu.co`, reject with 403 and clear session
5. On first login, auto-create user profile (RF03) with role defaulting to `estudiante`
6. Redirect to role-specific dashboard

**Edge cases:**
- Google's `hd` claim can be spoofed if not verified — must verify the ID token signature using Google's public keys (Socialite does this automatically, but worth confirming)
- Users with multiple Google accounts may accidentally select personal account — need clear UX messaging
- External evaluadores (RF mentions them) need traditional email/password login as fallback — the docs say "login tradicional para evaluadores externos"

**Risks:**
- ⚠️ Google OAuth `hd` claim is NOT guaranteed to be present for all Google Workspace accounts. Must also check the `email` field suffix as a fallback.
- ⚠️ RNF01 says "sin posibilidad de elusión por parámetros URL" — Socialite's state parameter prevents CSRF, but we need to ensure no redirect URL manipulation bypasses the domain check.

**Dependencies:** Google Cloud Console project, OAuth credentials.

---

### HU02 / RF02 / RF03 / RNF03 — Role assignment (RBAC)

**Complexity:** Medium
**Effort:** 2 days

**Technical approach:**
1. Use a simple `role` enum column on `users` table: `estudiante | director | coordinador | evaluador_externo`
2. Laravel Gates + Policies for authorization:
   - Define gates in `AuthServiceProvider`: `can-access-coordinator-panel`, `can-assign-roles`, etc.
   - Policies for each resource (Project, Delivery, Bitacora, etc.)
3. Middleware `role:coordinador` on coordinator-only routes
4. Role change endpoint: `PUT /api/users/{user}/role` — only accessible by coordinador
5. On role change, the change takes effect on next page load because Laravel Sanctum reads the role from the database on each authenticated request (not from a cached token claim)

**Edge cases:**
- What if the only coordinador changes their own role? Need at least one coordinador always.
- Role change audit: must log who changed whose role and when (ties into HU03)
- External evaluadores have a different auth path (traditional login) but still need roles

**Risks:**
- ⚠️ Using a simple enum column works for 3-4 roles but becomes unwieldy if permissions get granular later. For this project scope, enum is fine. If permissions needed per-action granularity, Spatie Laravel-Permission would be better — but that's overkill here.
- ⚠️ RNF03 requires "RBAC validated on every backend endpoint" — easy to forget on new endpoints. Must enforce via route middleware groups, not just controller checks.

---

### HU03 / RF05 — Audit log

**Complexity:** Medium
**Effort:** 1.5-2 days

**Technical approach:**
1. `audit_logs` table: `id, user_id (FK), action (string), description (text), ip_address, user_agent, occurred_at (timestamp)`
2. Use Laravel Events + Listeners for audit entries:
   - `UserLoggedIn`, `UserRoleChanged`, `DocumentUploaded`, `BitacoraSigned`, `GradeAssigned`, etc.
   - Each event fires a listener that writes to `audit_logs`
3. **Immutability:** No `update` or `delete` methods on the AuditLog model. Use a database trigger or Laravel model observer to block updates/deletes at the ORM level. Also, no UI routes for edit/delete.
4. Coordinator view: `GET /api/audit-logs` with query params `?user_id=&date_from=&date_to=&action_type=` — paginated, filtered
5. For RNF05 (TOTP integrity later), the architecture should support adding TOTP-related audit events without schema changes

**Edge cases:**
- What counts as a "significant action"? Need a clear list. Minimum: login, logout, role change, document upload, bitacora signature, grade assignment, project status change.
- High-volume events (e.g., every page view) should NOT be logged — only meaningful business events.
- IP address and user agent capture for forensic value.

**Risks:**
- ⚠️ Event/listener approach is clean but if an event fails to fire, the audit entry is lost. Consider a fallback: also log critical actions directly in the controller/action for the most sensitive operations (role changes, grade modifications).
- ⚠️ "Log cannot be edited or deleted from UI" — this is a UI constraint, but the real requirement is immutability at the data layer. A database-level constraint (no UPDATE/DELETE grants on the table for the app user) would be stronger, but may be overkill for a student project. At minimum, the Eloquent model should have no update/delete methods.

---

### RF04 — Secure logout

**Complexity:** Low
**Effort:** 0.5 days

**Technical approach:**
1. Sanctum: `POST /api/logout` — calls `auth()->user()->tokens()->delete()` to invalidate all tokens for that session
2. Web session: `auth()->logout()` + `session()->invalidate()` + `session()->regenerateToken()`
3. 8-hour inactivity timeout: configure `session.lifetime` to 480 minutes in `config/session.php`, using Redis as the session driver
4. For SPA + Sanctum: the frontend should also clear any stored tokens/cookies on logout

**Edge cases:**
- Multiple active sessions (user logged in on phone + laptop) — should logout invalidate ALL tokens or just the current one? RF04 says "invalidating the token immediately" (singular), suggesting per-session logout. But for security, invalidate all is safer.
- Token rotation: if using Sanctum's token ability feature, need to handle ability-specific revocation.

---

### RNF02 — HTTPS/TLS 1.2+

**Complexity:** Low (infrastructure, not code)
**Effort:** 0.5 days (Nginx config)

Nginx handles TLS termination. Laravel's `AppServiceProvider` should enforce HTTPS via `\Illuminate\Support\Facades\URL::forceScheme('https')` in production. Not a code-heavy requirement but must be verified.

---

### RNF04 — OWASP Top 10 protection

**Complexity:** Low-Medium (Laravel handles most of this)
**Effort:** 0.5-1 day (configuration + verification)

Laravel 11 provides out-of-the-box:
- CSRF protection (VerifyCsrfToken middleware)
- XSS protection (Blade auto-escapes; React auto-escapes JSX)
- SQL injection protection (Eloquent parameterized queries)
- Password hashing (bcrypt/argon2)
- Rate limiting (`ThrottleRequests` middleware)

Additional steps needed:
- Configure `laravel/helmet` or custom middleware for security headers (X-Frame-Options, X-Content-Type-Options, CSP, HSTS)
- Rate limit auth endpoints specifically (5 attempts per 15 minutes)
- Configure `APP_DEBUG=false` in production
- Validate all API inputs with Form Requests

---

### RNF05 — TOTP architecture support

**Complexity:** Low (architecture only, implementation later)
**Effort:** 0.5 days (planning)

The docs specify `pragmarx/google2fa-laravel` for TOTP. The auth module should:
- Add `totp_secret` (nullable) and `totp_enabled` (boolean) columns to users table
- Add TOTP enrolment flow (QR code generation, secret storage)
- Architecture should allow TOTP verification to be required for specific actions (bitacora signing) without requiring it for every login

---

## Approaches

### 1. Simple enum + events (Recommended)
- **Pros:** Minimal dependencies, easy to understand, fits team's Laravel experience, sufficient for 3-4 roles
- **Cons:** Harder to extend if permissions become granular later
- **Effort:** Low

### 2. Spatie Laravel-Permission package
- **Pros:** Industry-standard, supports roles + granular permissions, well-tested
- **Cons:** Overkill for 3 roles, adds dependency complexity, learning curve
- **Effort:** Medium

### 3. Custom RBAC with pivot table (roles + permissions)
- **Pros:** Full flexibility, no external dependency
- **Cons:** Reinventing the wheel, more code to maintain, error-prone
- **Effort:** High

## Recommendation

**Approach 1 (Simple enum + events)** for this project. The scope is well-defined with 3 main roles + external evaluators. Adding Spatie or building a custom RBAC system would be premature optimization. Use Laravel Gates for authorization checks and Policies for resource-level access control. If the project expands beyond the current scope, migration to Spatie is straightforward.

## Risks

1. **Google OAuth domain validation bypass** — Must verify both `hd` claim AND email suffix server-side. Client-side checks are insufficient (RNF01).
2. **Audit log gaps** — Event-driven logging is elegant but fragile. Critical actions (role changes, grade modifications) should have dual logging (event + direct).
3. **Session fixation** — Laravel handles this, but must ensure `session.regenerate_on_login` is enabled.
4. **Token leakage in SPA** — Sanctum's cookie-based auth is safer than localStorage tokens. Must use cookie-based Sanctum for the React SPA.
5. **Scope creep on RBAC** — If stakeholders start requesting fine-grained permissions ("director can view but not edit X"), the enum approach will need refactoring. Set expectations early.

## Open Questions

1. **External evaluators auth:** The docs mention "login tradicional para evaluadores externos." Do they get a separate login page, or the same Google OAuth page with a "login with email" fallback? How are they created — by coordinador manually?
2. **8-hour timeout:** Is this 8 hours of inactivity (idle timeout) or 8 hours from login (absolute timeout)? RNF04 says "inactividad" suggesting idle, but needs confirmation.
3. **Audit log retention:** How long should audit logs be kept? Forever? Archived after X months? This affects database size.
4. **Role default:** When a new user logs in for the first time, what role do they get? `estudiante` by default? Or `null` until a coordinador assigns one?
5. **Multi-session logout:** When a user clicks logout, should it invalidate ALL their sessions or just the current one?
6. **TOTP enrolment:** Who enrols in TOTP? Only directors and students for bitacora signing? Or all users?

## Recommended First Change

**Smallest testable slice:** Implement Google OAuth login with domain validation + auto-profile creation (HU01/RF01/RF03).

This gives you:
- A working login flow
- Server-side domain validation (the most critical security gate)
- Auto-created user records in the database
- Something to demo and test immediately

Steps:
1. `composer require laravel/socialite`
2. Configure Google OAuth in `config/services.php`
3. Create `AuthController` with `redirectToGoogle()` and `handleGoogleCallback()`
4. In callback: verify domain, create user if new, log them in
5. Write a Pest test that mocks Google's response and verifies domain rejection for non-@unab.edu.co emails
6. Create the React login page with a "Sign in with Google UNAB" button

This is 1-1.5 days of work, fully testable, and unblocks everything else.

## Ready for Proposal

**Yes.** The exploration is complete. The orchestrator should present this analysis to the user, highlight the open questions (especially about external evaluators and role defaults), and get clarification before proceeding to the proposal phase. The recommended first change (Google OAuth + domain validation) is small enough to implement and verify within a single SDD cycle.
