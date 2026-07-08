# Tasks: auth-access-module

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~2,500–3,500 |
| 800-line budget risk | **High** |
| Chained PRs recommended | **Yes** |
| Suggested split | PR 1 (Foundation) → PR 2 (Auth Backend) → PR 3 (Frontend UI) |
| Delivery strategy | ask-always |
| Chain strategy | pending — ask user before apply |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
800-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Project scaffolding + DB migrations + Git | PR 1 | Base branch: main. No auth logic, just infra. Includes all commands, config, and .git. |
| 2 | Auth backend (OAuth, external, RBAC, session, audit) | PR 2 | Depends on PR 1 migrations and models. Controllers, middleware, events, policies, tests. |
| 3 | React frontend (login pages, whitelist CRUD, audit viewer) | PR 3 | Depends on PR 2 API routes. Port wireframes, tailwind config, shadcn/ui setup. |

---

## Sprint 1 (Days 1–5)

### A. Project Scaffolding (Day 1)

#### T-001: Create Laravel 11 project with Composer
- **Description**: Run `laravel new` with Sail preset. Configure PostgreSQL 16 as default DB driver and Redis 7 for session/cache. Verify `php artisan serve` and Sail up work.
- **Files**: `.env`, `docker-compose.yml`, `composer.json`, `phpunit.xml`, `artisan`
- **Acceptance**: `php artisan --version` outputs 11.x. `docker-compose up -d` starts postgres + redis containers. `php artisan migrate` connects to PostgreSQL.
- **Effort**: 2h
- **Depends on**: None

#### T-002: Install and configure Laravel Sanctum (cookie SPA mode)
- **Description**: `composer require laravel/sanctum`. Publish config. Set `stateful` domains in `config/sanctum.php` for the SPA origin (localhost:5173 or app URL). Enable `EXPIRATION` and `prefix` for SPA routes. Configure `.env` with `SESSION_DRIVER=redis` and `SESSION_LIFETIME=60`.
- **Files**: `config/sanctum.php`, `.env`, `app/Http/Kernel.php` (ensure `EnsureFrontendRequestsAreStateful` in api middleware group)
- **Acceptance**: Sanctum cookie auth works with `POST /api/login` and `GET /api/user`. CSRF token endpoint returns 204.
- **Effort**: 1.5h
- **Depends on**: T-001

#### T-003: Install and configure Laravel Socialite (Google OAuth)
- **Description**: `composer require laravel/socialite`. Add Google provider in `config/services.php` with `client_id`, `client_secret`, `redirect` from `.env`. Add `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` to `.env.example`.
- **Files**: `config/services.php`, `.env`, `.env.example`
- **Acceptance**: `Socialite::driver('google')->redirect()` returns a valid Google OAuth URL. `Socialite::driver('google')->user()` parses a mock token response.
- **Effort**: 1h
- **Depends on**: T-001

#### T-004: Install Pest/PHPUnit and Laravel Pint
- **Description**: `composer require pestphp/pest --dev`. Run `php artisan pest:install`. Configure `phpunit.xml` for PostgreSQL test DB. `composer require laravel/pint --dev`. Add `pint.json` with PSR-12 rules.
- **Files**: `phpunit.xml`, `tests/Pest.php`, `pint.json`, `.github/workflows/tests.yml` (optional, basic)
- **Acceptance**: `vendor/bin/pest` runs the example test (green). `vendor/bin/pint --test` passes with default rules.
- **Effort**: 1h
- **Depends on**: T-001

#### T-005: Scaffold React/Vite project inside Laravel
- **Description**: Create `resources/js/` with `npm create vite@latest` (React + TypeScript). Install Tailwind CSS v4 with PostCSS, shadcn/ui with `npx shadcn@latest init`. Configure `vite.config.ts` with `laravel-vite-plugin`. Set up `tsconfig.json`. Verify `npm run dev` compiles without errors.
- **Files**: `package.json`, `vite.config.ts`, `tsconfig.json`, `resources/js/app.tsx`, `resources/js/index.html`, `tailwind.config.ts`, `postcss.config.js`, `resources/css/app.css`, `components.json`
- **Acceptance**: `npm run dev` compiles successfully. `php artisan serve` + `npm run dev` serve a React page at the Laravel URL with Tailwind styles applied. shadcn/ui Button component renders.
- **Effort**: 2h
- **Depends on**: T-001

#### T-006: Configure Tailwind with UNAB design tokens
- **Description**: Map all UNAB design tokens from `shared/tokens.css` into `tailwind.config.ts` — colors (primary `#c2410c`, secondary `#4f46e5`, accent `#0891b2`, semantic), font family (`Open Sans`), spacing scale (4px base), border radii, shadows. Configure `@tailwind base` + Open Sans font import in `app.css`.
- **Files**: `tailwind.config.ts`, `resources/css/app.css`, `resources/js/index.html` (add Open Sans font)
- **Acceptance**: `text-primary` renders burnt orange. `font-sans` uses Open Sans. `rounded-lg` = 12px. `shadow-md` = warm shadow.
- **Effort**: 1.5h
- **Depends on**: T-005

---

### B. Git + GitHub (Day 1)

#### T-007: Initialize git repo and create .gitignore
- **Description**: `git init` in `C:\Users\Owner\Proyecto de grado`. Create `.gitignore` with Laravel defaults (`vendor/`, `.env`, `node_modules/`, `storage/framework/cache/*`, `public/hot`, `public/build/`, `*.log`, `.idea/`, `Thumbs.db`, `/docker-compose.yml` local overrides).
- **Files**: `.gitignore`
- **Acceptance**: `git status` shows only source files, not vendor/node_modules/.env.
- **Effort**: 0.5h
- **Depends on**: T-001, T-005

#### T-008: Initial commit and push to GitHub
- **Description**: `git add . && git commit -m "chore: initial project scaffold"`. Wait for user to provide repo URL, then `git remote add origin <url> && git push -u origin main`.
- **Files**: N/A (git operations)
- **Acceptance**: Repo is on GitHub with all scaffolded files. CI (if configured) starts.
- **Effort**: 0.5h
- **Depends on**: T-007, user provides repo URL

---

### C. Database Migrations (Day 1–2)

#### T-009: Create `users` table migration
- **Description**: Migration `create_users_table`. Columns: `id`, `name`, `email` (unique), `password` (nullable), `role` (string/varchar backing the enum — `Estudiante|Director|Coordinador|EvaluadorExterno`), `es_externo` (bool, default false), `google_id` (nullable, unique), `avatar` (nullable), `last_activity_at` (timestamp, nullable), `totp_secret` (nullable, string), `remember_token`, `timestamps`. Indexes on `email`, `role`, `es_externo`, `google_id`. Must be reversible (`down()`).
- **Files**: `database/migrations/xxxx_01_create_users_table.php`
- **Acceptance**: `php artisan migrate` creates the table with all columns. `php artisan migrate:rollback` drops it cleanly.
- **Effort**: 1h
- **Depends on**: T-001

#### T-010: Create `authorized_emails` table migration
- **Description**: Migration `create_authorized_emails_table`. Columns: `id`, `email` (unique string), `role` (string), `created_by` (FK → users.id, nullable), `created_at`. No `updated_at`. Index on `email` (unique), `role`.
- **Files**: `database/migrations/xxxx_02_create_authorized_emails_table.php`
- **Acceptance**: `php artisan migrate` creates the whitelist table. FK constraint works. `php artisan migrate:rollback` drops it.
- **Effort**: 0.5h
- **Depends on**: T-009

#### T-011: Create `audit_logs` table migration
- **Description**: Migration `create_audit_logs_table`. Columns: `id`, `user_id` (FK → users.id, nullable, onDelete set null), `action` (string 64), `description` (text, nullable), `ip_address` (string 45, nullable), `user_agent` (text, nullable), `metadata` (json, nullable), `created_at`. NO `updated_at`. NO soft deletes. Indexes on `user_id`, `action`, `created_at`. The application DB user MUST NOT have UPDATE/DELETE privileges on this table (configured via migration raw SQL or separate grant).
- **Files**: `database/migrations/xxxx_03_create_audit_logs_table.php`
- **Acceptance**: `php artisan migrate` creates table. Direct SQL `UPDATE audit_logs` from the app user fails with permissions error.
- **Effort**: 1h
- **Depends on**: T-009

---

### D. Auth: Google OAuth (Days 1–2)

#### T-012: Create UserRole enum and User model
- **Description**: PHP enum `UserRole` with cases `Estudiante`, `Director`, `Coordinador`, `EvaluadorExterno` — backed by string. Create `App\Models\User` with `HasApiTokens` (Sanctum), `HasFactory`, fillable fields, casts for `role` (UserRole), `es_externo` (bool), `last_activity_at` (datetime). Relations: `auditLogs()`, `authorizedEmails()` (created by).
- **Files**: `app/Enums/UserRole.php`, `app/Models/User.php`
- **Acceptance**: `UserRole::Estudiante->value === 'Estudiante'`. `User::factory()->create()` works with role cast. `$user->role instanceof UserRole`.
- **Effort**: 1h
- **Depends on**: T-009

#### T-013: Create AuthorizedEmail and AuditLog models
- **Description**: `App\Models\AuthorizedEmail` — fillable `email`, `role`, `created_by`, guarded id/timestamps. Relation `creator()` → User. `App\Models\AuditLog` — guarded `id`, no `updated_at` column in model, `$timestamps = false`. Only expose `create()` and query scopes — no `update()` or `delete()`.
- **Files**: `app/Models/AuthorizedEmail.php`, `app/Models/AuditLog.php`
- **Acceptance**: `AuthorizedEmail::create([...])` works. `AuditLog::find(1)->delete()` throws a logic error or is not callable.
- **Effort**: 1h
- **Depends on**: T-010, T-011

#### T-014: Implement Google OAuth redirect and callback with triple validation
- **Description**: `AuthController@redirectToGoogle` — return Socialite redirect. `AuthController@handleGoogleCallback` — implement triple validation: (1) check `hd === unab.edu.co`, (2) check email suffix `@unab.edu.co` case-insensitive, (3) check `authorized_emails` whitelist. On failure: write AuditEvent `login.rejected` with description (domain_mismatch / not_whitelisted / hd_missing). On success: `findOrCreate` User, delete prior sessions, create Sanctum token, update `last_activity_at`, write AuditEvent `login.success`, redirect to role-based dashboard. Handle all edge cases: Google error param → `login.cancelled`, exception → `login.error`.
- **Files**: `app/Http/Controllers/Auth/AuthController.php`, `routes/web.php` (GET `/auth/redirect`, GET `/auth/callback`)
- **Acceptance**: Pest test: mock Socialite with `hd=unab.edu.co` + whitelisted email creates user and returns redirect. Mock with gmail.com → 403. Mock with valid domain but unlisted → 403. Mock with missing hd → 403. Google returns error → redirects to /login.
- **Effort**: 3h
- **Depends on**: T-012, T-013, T-003, T-015

#### T-015: Create AuditEvent and WriteAuditLog listener
- **Description**: `App\Events\AuditEvent` — constructor with `?User $user`, `string $action`, `string $description`, `array $meta = []`. `App\Listeners\WriteAuditLog` — handles AuditEvent, persists to `audit_logs` table (dispatch via ShouldQueue for non-blocking writes). Register in `EventServiceProvider`.
- **Files**: `app/Events/AuditEvent.php`, `app/Listeners/WriteAuditLog.php`, `app/Providers/EventServiceProvider.php`
- **Acceptance**: `AuditEvent::dispatch(user: $user, action: 'login.success', ...)` creates a row in `audit_logs` asynchronously.
- **Effort**: 1.5h
- **Depends on**: T-013

---

### E. Auth: External Evaluator Login (Day 2)

#### T-016: Implement external evaluator credential login
- **Description**: `AuthController@loginExterno` — validate `request->email` + `request->password` via `Auth::attempt()`. Check `$user->es_externo === true`. Single-session enforcement (delete old sessions). Create Sanctum token. Check if `must_change_password` flag (set when password matches a "temporary" marker) — redirect to password change. Write AuditEvent `login.success` with `channel=external`. On failure: increment failed attempts counter → lockout after 3 attempts in 10 min → return 423. On 5th failed attempt within lockout window, extend lockout 15 min.
- **Files**: `app/Http/Controllers/Auth/AuthController.php` (add `loginExterno`), `routes/api.php` (POST `/api/login/externo`)
- **Acceptance**: Pest test: valid credentials return Sanctum cookie + 200. Wrong password → 401. 3 wrong attempts → 423 Locked. External user without `es_externo=true` → 403.
- **Effort**: 3h
- **Depends on**: T-012, T-015

#### T-017: Implement forced password change for external evaluators
- **Description**: Add `password_changed_at` (nullable timestamp) to `users` table via new migration. `AuthController@loginExterno` checks if `password_changed_at === null` → set session flag `must_change_password=true`. `AuthController@changePassword` — validates current password + new password + confirmation. Updates `password` hash + `password_changed_at`. Create `routes/api.php` POST `/api/auth/change-password`. Middleware `EnsurePasswordChanged` — redirects all routes except `/api/auth/change-password` to password change page when flag is set.
- **Files**: `database/migrations/xxxx_04_add_password_changed_at_to_users.php`, `app/Http/Middleware/EnsurePasswordChanged.php`, `app/Http/Kernel.php`, `routes/api.php`
- **Acceptance**: New external user logs in → gets redirect to `/cuenta/cambiar-contrasena`. All other API routes return 403 with `password_change_required`. After password change, all routes work.
- **Effort**: 2h
- **Depends on**: T-016

---

### F. RBAC (Days 2–3)

#### T-018: Create RoleMiddleware
- **Description**: `RoleMiddleware` — accepts a comma-separated list of allowed roles. On every request: fetch authenticated user, check `$user->role->value` is in allowed list. On mismatch: abort 403 with `{error: "unauthorized"}`. Write AuditEvent `access.denied` with `role_mismatch` description. Register in `app/Http/Kernel.php` as `role` alias.
- **Files**: `app/Http/Middleware/RoleMiddleware.php`, `app/Http/Kernel.php`
- **Acceptance**: `Route::middleware('role:Coordinador')` blocks Estudiante with 403. Audit log entry written on denial. `Route::middleware('role:Coordinador,Director')` allows both roles.
- **Effort**: 1.5h
- **Depends on**: T-012, T-015

#### T-019: Create Gates and UserPolicy
- **Description**: `UserPolicy` — `viewAny`, `create`, `update`, `delete` all gated to `Coordinador`. `AuthServiceProvider` — define `manage-users` gate (Coordinador only) and `view-admin` gate (Coordinador + Director). Register policy in `AuthServiceProvider`.
- **Files**: `app/Policies/UserPolicy.php`, `app/Providers/AuthServiceProvider.php`
- **Acceptance**: `Gate::allows('manage-users', auth_user)` returns true for Coordinador, false for Estudiante. Pest test with `actingAs(Coordinador)` passes policy checks.
- **Effort**: 1h
- **Depends on**: T-012

---

### G. Whitelist Management (Day 3)

#### T-020: Implement whitelist CRUD (backend)
- **Description**: `UserController` — `index()` returns paginated authorized emails list. `store()` validates email format + `@unab.edu.co` domain + no duplicates, creates AuthorizedEmail entry. `update()` changes role, syncs `users.role` if the user has already logged in. `destroy()` soft-deletes/marks inactive. All actions gated by `role:Coordinador` middleware. Each action writes AuditEvent (`whitelist.add`, `whitelist.role_changed`, `whitelist.removed`).
- **Files**: `app/Http/Controllers/UserController.php`, `routes/api.php`
- **Acceptance**: Pest: Coordinador POSTs valid email → 201 + DB row. Duplicate → 422. Non-UNAB email → 422. Estudiante POSTs → 403. Delete → 200 + entry deactivated.
- **Effort**: 2.5h
- **Depends on**: T-015, T-018, T-019

---

### H. Session Management (Days 3–4)

#### T-021: Implement single-session enforcement
- **Description**: `SingleSessionMiddleware` — after authentication, delete all Sanctum `personal_access_tokens` for the user except the current token. On subsequent requests: if the token was deleted, return 401. Hook into login flow (both OAuth and external) to purge prior sessions before creating new ones.
- **Files**: `app/Http/Middleware/SingleSessionMiddleware.php`, `app/Http/Kernel.php`
- **Acceptance**: User logs in on device A → token active. User logs in on device B → device A token invalidated. Device A next request → 401.
- **Effort**: 1.5h
- **Depends on**: T-002

#### T-022: Implement inactivity timeout middleware
- **Description**: `ActivityMiddleware` — on every authenticated request: check `now() - last_activity_at > 1 hour`. If timed out: delete token, return 401 with code `session.timeout`, write AuditEvent `logout.timeout`. If active: update `last_activity_at` to now(). Register as global middleware in `api` group.
- **Files**: `app/Http/Middleware/ActivityMiddleware.php`, `app/Http/Kernel.php`
- **Acceptance**: User logged in at 09:00. Request at 10:01 → 401 timeout. User makes request at 09:45 → 200, `last_activity_at` updated. Next timeout at 10:45.
- **Effort**: 1.5h
- **Depends on**: T-012, T-015

#### T-023: Implement secure logout
- **Description**: `AuthController@logout` — delete current Sanctum token, delete `sessions` row, write AuditEvent `logout.user_initiated`, return 204 with cookie cleared. `AuthController@sessionCheck` — return current user with role for SPA state.
- **Files**: `app/Http/Controllers/Auth/AuthController.php` (add `logout`, `sessionCheck`), `routes/api.php` (POST `/api/logout`, GET `/api/user`)
- **Acceptance**: POST `/api/logout` → 204 + cookie cleared. Next API call → 401. GET `/api/user` returns `{id, name, email, role, avatar}`.
- **Effort**: 1h
- **Depends on**: T-002

---

### I. Audit Log (Day 4)

#### T-024: Implement audit log viewer (backend)
- **Description**: `AuditLogController@index` — returns paginated audit logs (50 default, max 200). Filters: `user_id`, `action` (enum of action types), `date_from`, `date_to`, `ip_address`. Results ordered `created_at DESC`. Gated by `role:Coordinador`. Index on `(action, created_at)` for filter performance. Ensure query < 500ms with 100k rows.
- **Files**: `app/Http/Controllers/AuditLogController.php`, `routes/api.php`
- **Acceptance**: Coordinador GET `/api/audit-logs?action=login.success&date_from=2026-06-01` → paginated results. Non-coordinador → 403. Performance test with 100k seeded rows < 500ms.
- **Effort**: 2h
- **Depends on**: T-013, T-018

#### T-025: Create audit archive command
- **Description**: `php artisan make:command AuditArchive` — scheduled `audit:archive`. Moves entries older than 5 years from `audit_logs` to `audit_logs_archive` table (same schema, created in a separate migration). Runs daily. Verifies archive table exists before INSERT.
- **Files**: `app/Console/Commands/AuditArchive.php`, `database/migrations/xxxx_05_create_audit_logs_archive_table.php`, `app/Console/Kernel.php`
- **Acceptance**: `php artisan audit:archive` moves entries older than 5 years. Newer entries remain in `audit_logs`. Archive table is queryable.
- **Effort**: 1.5h
- **Depends on**: T-011

---

### J. React UI: Auth Pages (Days 4–5)

#### T-026: Port login-institucional.html to React component
- **Description**: Create `LoginInstitucional.tsx` that replicates the wireframe exactly: UNAB brand (school icon, "UNAB" + "Ingeniería de Sistemas"), divider, title "Sistema Centralizado de Proyectos de Grado", Google OAuth button with SVG icon, error alert slot ("Acceso restringido a cuentas UNAB"), link to external evaluator login, footer. Use `surface-bezel` + `surface-bezel__inner` double-card pattern. Map all CSS tokens to Tailwind classes configured in T-006. Implement `useAuth` hook to call `/auth/redirect`.
- **Files**: `resources/js/pages/auth/LoginInstitucional.tsx`, `resources/js/hooks/useAuth.ts`
- **Acceptance**: Renders pixel-match with wireframe. Google button redirects to `/auth/redirect`. Error alert displays on OAuth failure. Link navigates to `/login/externo`.
- **Effort**: 3h
- **Depends on**: T-006, T-014

#### T-027: Port login-evaluadores-externos.html to React component
- **Description**: Create `LoginExterno.tsx` — form with email (icon `person`) and password (icon `lock`) fields, submit button, validation errors, link back to institutional login. Wire to `POST /api/login/externo`. Display error states (invalid credentials, locked account). Use UNAB design tokens (secondary indigo for badges, burnt orange for primary action).
- **Files**: `resources/js/pages/auth/LoginExterno.tsx`
- **Acceptance**: Form validates required fields. Submit sends POST. Error messages display on 401/423. Success redirects to role dashboard.
- **Effort**: 2.5h
- **Depends on**: T-006, T-016

#### T-028: Create useAuth hook and AppShell layout
- **Description**: `useAuth.ts` — manages Sanctum auth state: `user`, `isAuthenticated`, `role`, `login()`, `logout()`, `sessionCheck()`. Calls `GET /api/user` on mount to restore session. `AppShell.tsx` — layout component with sidebar + header structure from `layout.css`. Sidebar nav links for each role, user chip, logout button.
- **Files**: `resources/js/hooks/useAuth.ts`, `resources/js/components/layout/AppShell.tsx`, `resources/js/components/layout/Sidebar.tsx`, `resources/js/components/layout/Header.tsx`
- **Acceptance**: `useAuth()` returns current user. Logout clears state. Sidebar shows role-appropriate nav links. User chip shows name + role.
- **Effort**: 3h
- **Depends on**: T-023, T-006

#### T-029: Create whitelist management UI (Coordinador)
- **Description**: `GestionUsuarios.tsx` — paginated table of authorized emails. CRUD actions: Add (modal form with email + role select), Edit (change role), Delete (confirmation dialog). Search/filter by email. Uses shadcn/ui Dialog, Table, Button mapped to UNAB design tokens.
- **Files**: `resources/js/pages/coordinador/GestionUsuarios.tsx`
- **Acceptance**: List renders. Add creates entry via POST `/api/usuarios`. Delete shows confirmation, then removes. Non-coordinador sees 403.
- **Effort**: 3h
- **Depends on**: T-020, T-028

#### T-030: Create audit log viewer UI (Coordinador)
- **Description**: `AuditLog.tsx` — table with filters: user select, action type dropdown, date range picker. Results paginated. Columns: timestamp, user, action, description, IP, user-agent. No edit/delete actions.
- **Files**: `resources/js/pages/coordinador/AuditLog.tsx`
- **Acceptance**: Filters work. Pagination works. No edit/delete buttons visible. Non-coordinador → 403.
- **Effort**: 2.5h
- **Depends on**: T-024, T-028

#### T-031: Create role-based dashboard redirect page
- **Description**: `DashboardRouter.tsx` — after login, reads user role from `useAuth()` and redirects: `Estudiante → /dashboard/estudiante`, `Director → /dashboard/director`, `Coordinador → /dashboard/coordinador`, `EvaluadorExterno → /dashboard/evaluador-externo`. Simple placeholder pages for each dashboard with role name and "coming soon" content.
- **Files**: `resources/js/pages/DashboardRouter.tsx`, `resources/js/pages/dashboard/EstudianteDashboard.tsx`, `resources/js/pages/dashboard/DirectorDashboard.tsx`, `resources/js/pages/dashboard/CoordinadorDashboard.tsx`, `resources/js/pages/dashboard/EvaluadorDashboard.tsx`
- **Acceptance**: After login, user arrives at correct dashboard URL based on role. Placeholder renders role name.
- **Effort**: 2h
- **Depends on**: T-028
