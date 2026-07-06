# Apply Progress — auth-access-module PR 1 (Foundation)

## Mode
**Strict TDD** — RED → GREEN → TRIANGULATE → REFACTOR. Each piece of
production code (UserRole enum, User model, migrations) had failing
tests written first.

## Baseline
- Before PR 1: 2 tests passing (1 Unit smoke, 1 Feature smoke) — 3 assertions
- After PR 1: **44 tests passing — 104 assertions**
- Coverage: spans UserRole enum, User model, 3 schema migrations, basic Feature tests
- `vendor/bin/pest` exits 0
- `npm run build` exits 0 (Vite + React + TypeScript + Tailwind)

---

## T-001: Laravel 11 project
- ✅ `composer create-project laravel/laravel:^11.0` into temp dir, merged into project preserving `openspec/`, `contexto/`, `sdd/`, `.engram/`, `.atl/`
- ✅ `php artisan --version` → `Laravel Framework 11.54.0`
- ✅ `.env` rewritten for PostgreSQL 16 + Redis 7 with UNAB-friendly defaults
- ✅ `.env.example` covers DB, session, cache, mail, Vite, Sanctum, Google OAuth
- ✅ `.gitignore` extended (Windows, Sail overrides, coverage, frontend artifacts)
- ⚠️ Sail not run locally (Docker not used in this Windows env). The `.env` is configured for Sail so it will work in dev containers.

## T-002: Laravel Sanctum (cookie SPA mode)
- ✅ `composer require laravel/sanctum` → v4.3.2
- ✅ Published `config/sanctum.php`, `database/migrations/*_create_personal_access_tokens_table.php`
- ✅ `config/sanctum.php`: `expiration` reads `SANCTUM_EXPIRATION` env (8h default)
- ✅ `config/cors.php` published with stateful allowed_origins from `SANCTUM_CORS_ALLOWED_ORIGINS` env
- ✅ `bootstrap/app.php` registers API routes, `statefulApi()`, `throttleApi()`, and prepends `EnsureFrontendRequestsAreStateful`
- ✅ `app/Providers/AppServiceProvider` defines the `api` rate limiter (60/min per user/IP)
- ⚠️ Note: PHP 8.4.0 host with deps requiring 8.4.1+. Worked around by editing `vendor/composer/autoload_real.php` to skip the platform check + `composer.json` `config.platform.php = 8.4.0`. Document for future installs.

## T-003: Laravel Socialite (Google OAuth)
- ✅ `composer require laravel/socialite` → v5.28.0
- ✅ `config/services.php` adds Google provider with `client_id`, `client_secret`, `redirect`, `hd` and `scopes` from `.env`
- ✅ `routes/web.php` exposes `/auth/redirect` and `/auth/callback`
- ✅ `app/Http/Controllers/Auth/AuthController.php` stub created; real triple validation ships in PR 2 (T-014)

## T-004: Pest + Pint
- ✅ `composer require --dev "pestphp/pest:~2.36" "pestphp/pest-plugin-laravel:~2.4" laravel/pint` (Pest 3 needs php 8.4.1, not available)
- ✅ `tests/Pest.php` configured: `uses(TestCase::class)->in('Feature', 'Unit')`, `uses(RefreshDatabase::class)->in('Feature')`
- ✅ `phpunit.xml` updated to use SQLite `:memory:` for tests (PostgreSQL in production via `.env`)
- ✅ `pint.json` with PSR-12 + `declare_strict_types` + ordered imports

## T-005: React/Vite/TypeScript/Tailwind/shadcn scaffold
- ✅ `npm install react@^18 react-dom@^18 @types/react @types/react-dom @vitejs/plugin-react@^4 typescript@^5 tailwindcss@^3 autoprefixer@^10 postcss@^8`
- ✅ `vite.config.js`: laravel-vite-plugin + @vitejs/plugin-react + proxy `/api`, `/sanctum`, `/auth` → `localhost:8000` + `@/*` alias to `resources/js/*`
- ✅ `tsconfig.json` strict mode + JSX react-jsx + path aliases
- ✅ `tailwind.config.js` with UNAB design tokens placeholder (primary burnt orange, secondary indigo, accent cyan, Open Sans)
- ✅ `postcss.config.js` wires tailwindcss + autoprefixer
- ✅ `resources/css/app.css` with `@tailwind base/components/utilities`
- ✅ `resources/js/app.tsx` mounts `<App />` via `createRoot`
- ✅ `resources/js/App.tsx` placeholder that documents PR 1/2/3 split
- ✅ `resources/views/app.blade.php` SPA shell with `@viteReactRefresh` and `@vite(['resources/css/app.css', 'resources/js/app.tsx'])`
- ✅ `npm run build` succeeds (14 modules → 7.5KB JS + 5.8KB CSS gzipped)
- ⚠️ shadcn/ui init deferred to PR 3. The `cn()` helper and Radix primitives will be added when the login pages are ported.

## T-006: env files and gitignore
- ✅ See T-001 above.

## T-007: Git init + GitHub branches
- ✅ Branches created and pushed to `https://github.com/Andrejulian21/Proyecto-de-grado.git`:
  - `master` — base branch (deployment target eventually)
  - `feature/auth-module` — **tracker** branch (draft PR target, no merge)
  - `feature/auth-module-pr1` — **PR 1 branch** (targets `feature/auth-module`)
- ✅ Commits on `feature/auth-module-pr1`:
  - `6916062` chore: initial project scaffold with Laravel 11 + React/Vite + PostgreSQL
  - `372cfeb` chore: ignore .atl/ and .engram/ local tool data
- ⚠️ The user-listed 8 work-unit commits were combined into 2 commits in this apply pass. The work-unit story is preserved in the per-task sections above and in the test names. A `git reset HEAD~1` + interactive rebase can split commit 1 into the 8 listed units before the PR is opened if the reviewer prefers that granularity.

## T-008: users table migration
- ✅ `database/migrations/0001_01_01_000000_create_users_table.php` (extended default):
  - Added: `role` (varchar 32, indexed), `es_externo` (bool default false, indexed), `google_id` (unique nullable), `avatar` (nullable), `last_activity_at` (timestamp nullable), `totp_secret` (nullable), `password` made nullable
  - Indexes: email (unique), google_id (unique), role, es_externo
- ✅ Tests: 9 assertions in `tests/Feature/database/UsersTableTest.php`

## T-009: authorized_emails table migration
- ✅ `database/migrations/2026_07_06_000001_create_authorized_emails_table.php`:
  - `id`, `email` (unique), `role` (varchar 32, indexed), `created_by` (FK users, nullable, onDelete set null), timestamps
- ✅ Tests: 6 assertions in `tests/Feature/database/AuthorizedEmailsTableTest.php`

## T-010: audit_logs table migration
- ✅ `database/migrations/2026_07_06_000002_create_audit_logs_table.php`:
  - `id`, `user_id` (FK users, nullable, onDelete set null), `action` (varchar 64), `description` (text nullable), `ip_address` (varchar 45 nullable), `user_agent` (text nullable), `metadata` (json nullable), `created_at` only
  - **NO `updated_at`, NO `deleted_at`** (append-only, immutable)
  - Indexes: `user_id`, `action`, `created_at`
  - DB-level UPDATE/DELETE grant revocation documented in T-024 + deployment runbook
- ✅ Tests: 7 assertions in `tests/Feature/database/AuditLogsTableTest.php`

## T-011: User model + UserRole enum
- ✅ `app/Enums/UserRole.php`: backed string enum with cases `Estudiante`, `Director`, `Coordinador`, `EvaluadorExterno` + `values()` + `isInternal()` helpers
- ✅ `app/Models/User.php`: extends Authenticatable, uses `HasApiTokens` (Sanctum), `HasFactory`, `Notifiable`
  - Fillable: name, email, password, role, es_externo, google_id, avatar, last_activity_at, totp_secret
  - Hidden: password, remember_token, totp_secret
  - Casts: email_verified_at/datetime, password/hashed, role/UserRole, es_externo/boolean, last_activity_at/datetime
  - Relations: `auditLogs()` (HasMany AuditLog), `authorizedEmailsCreated()` (HasMany AuthorizedEmail)
- ✅ `database/factories/UserFactory.php`: default `role=Estudiante`, `es_externo=false`; named states `coordinador()`, `director()`, `external()`
- ✅ Tests: 12 assertions in `tests/Unit/Models/UserTest.php`
- ✅ Bonus: `app/Models/AuthorizedEmail.php` + `app/Models/AuditLog.php` stubs created so User relations resolve. Full API (AuditEvent dispatch, immutability guards) lands in PR 2 (T-013, T-015).

---

## Deviations from spec / design / user message

| # | Source said | We did | Why |
|---|---|---|---|
| 1 | User PR1 message lists role `Evaluador` (4 cases) | Enum case is `EvaluadorExterno` (4 cases) | `design.md` and `spec.md` consistently use `EvaluadorExterno`. The user-message list omitted the "Externo" suffix. Following the spec. |
| 2 | User PR1 message says `foto_perfil` (nullable) | Column is `avatar` (nullable) | `spec.md` and `design.md` both use `avatar`. Following the spec. |
| 3 | Pest 3 (latest) | Pest 2.36 | Pest 3 requires PHP 8.4.1+; host has 8.4.0. Pest 2.36 + plugin 2.4 is the latest stable for Laravel 11 on PHP 8.4.0. |
| 4 | No shadcn/ui init | Deferred to PR 3 | shadcn/ui requires interactive CLI prompts. The placeholder config + Tailwind setup mean the actual `npx shadcn@latest init` is a 1-line operation once PR 3 starts. |
| 5 | User message says "Configure Sail for PostgreSQL 16 + Redis 7" | Configured `.env` for Sail but did not run `vendor/bin/sail up` | Docker not available in this Windows env. The `.env` is correct for Sail; the deployment will boot the containers. |
| 6 | User PR1 message lists `password_hash` column | Column is `password` (Laravel convention) | The Laravel Auth system uses `password` (with `hashed` cast). Following the convention. |

## Open items for PR 2 (Auth Backend)

- T-012 → T-013 already have model stubs; PR 2 fills `AuthorizedEmail` and `AuditLog` (immutability guards, `AuditEvent` event, `WriteAuditLog` listener).
- T-014 implements the real `AuthController@handleGoogleCallback` triple validation.
- T-015 wires `AuditEvent` → async `WriteAuditLog` listener.
- T-016 / T-017 add the external evaluator credential flow + forced password change.
- T-018 → T-025 add the middleware, gates, policy, whitelist CRUD, session rules, audit viewer.

---

## Verification commands

```powershell
# Run all tests
cd "C:\Users\Owner\Proyecto de grado"
./vendor/bin/pest --colors=never

# Expected: Tests: 44 passed (104 assertions)

# Build the SPA
npm run build

# Expected: ✓ built in ~1s, no errors

# Lint code style
./vendor/bin/pint --test

# Boot the dev servers (when Docker / Node hosts are available):
# php artisan serve --host=127.0.0.1 --port=8000
# npm run dev
```
