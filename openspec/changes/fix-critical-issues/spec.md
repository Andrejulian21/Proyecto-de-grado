# Spec: Fix Critical Issues

**Change**: `fix-critical-issues` | **Status**: Spec
**Stack**: Laravel 11 + PostgreSQL 16 + Sanctum (SPA cookie) + Pest | **Issues**: #9, #10, #11, #12, #13

---

## Issue #12 — DB-level immutability for audit_logs

### Current State

`AuditLog` model enforces immutability at the application layer: `save()` (when exists), `delete()`, `forceDelete()`, and the custom Eloquent builder all throw `LogicException`. However, **no PostgreSQL trigger exists**. A raw SQL `UPDATE` or `DELETE` directly against the database bypasses all application guards.

### Requirement: PostgreSQL trigger blocks UPDATE and DELETE on audit_logs

A `BEFORE UPDATE OR DELETE` trigger on `audit_logs` SHALL raise an exception, making the table append-only at the database level. The trigger SHALL be installed via a new migration.

#### Scenario: Direct UPDATE is rejected at DB level

- **GIVEN** the trigger migration has been applied (`php artisan migrate`)
- **WHEN** a raw SQL `UPDATE audit_logs SET description = 'tampered' WHERE id = 1` is executed
- **THEN** PostgreSQL SHALL reject the operation with an error
- **AND** the row SHALL remain unchanged

#### Scenario: Direct DELETE is rejected at DB level

- **GIVEN** the trigger migration has been applied
- **WHEN** a raw SQL `DELETE FROM audit_logs WHERE id = 1` is executed
- **THEN** PostgreSQL SHALL reject the operation with an error
- **AND** the row SHALL remain in the table

#### Scenario: INSERT and SELECT are unaffected

- **GIVEN** the trigger is active on `audit_logs`
- **WHEN** a raw SQL `INSERT INTO audit_logs(...)` or `SELECT * FROM audit_logs` is executed
- **THEN** both operations SHALL succeed normally

---

## Issue #11 — AuditArchive command fix and schedule

### Current State

- The `audit_logs_archive` migration **already exists** (`database/migrations/2026_07_08_000001_create_audit_logs_archive_table.php`) ✅
- The `AuditArchive` command **already exists** (`app/Console/Commands/AuditArchive.php`) ✅
- **BUG**: Line 56 calls `$log->delete()` on the `AuditLog` Eloquent model. This throws `LogicException` because `AuditLog::delete()` is blocked (see `app/Models/AuditLog.php` line 167–180). The command crashes on every archive run.
- **MISSING**: No schedule is registered in `routes/console.php`. The schedule file only contains the built-in `inspire` command.
- **Missing dependency**: The PostgreSQL trigger from #12 does not block `DB::table('audit_logs')->delete()`, so the archive command can use a raw query as a privileged bypass.

### Requirement: AuditArchive uses raw DB::table() delete and is scheduled daily

The `audit:archive` command SHALL use `DB::table('audit_logs')->where('id', ...)->delete()` instead of the Eloquent model `$log->delete()` to bypass the append-only guard (which the model intentionally enforces). The command SHALL be registered to run daily in `routes/console.php`.

#### Scenario: Archive command moves old entries without crashing

- **GIVEN** an `audit_logs` row with `created_at < now() - 5 years`
- **WHEN** `php artisan audit:archive` is executed
- **THEN** the row SHALL be copied to `audit_logs_archive` (via `DB::table()->insert()`)
- **AND** the row SHALL be deleted from `audit_logs` via `DB::table('audit_logs')->where('id', ...)->delete()` (bypassing the Eloquent model guard)
- **AND** the command SHALL NOT throw `LogicException`

#### Scenario: Recent entries are NOT archived

- **GIVEN** an `audit_logs` row with `created_at = now()`
- **WHEN** `php artisan audit:archive` is executed
- **THEN** the row SHALL remain in `audit_logs`
- **AND** SHALL NOT appear in `audit_logs_archive`

#### Scenario: Schedule is registered

- **GIVEN** the `routes/console.php` file
- **WHEN** inspecting the schedule registration
- **THEN** `$schedule->command('audit:archive')->daily()` SHALL be present
- **AND** `php artisan schedule:list` SHALL include `audit:archive`

---

## Issue #10 — Single-session with cookie-based session invalidation

### Current State

- `SingleSessionMiddleware` (`app/Http/Middleware/SingleSessionMiddleware.php`) is **intentionally a no-op**. Its docblock (lines 11–29) explains: the single-session enforcement happens at login time in the `AuthController`, and the middleware is a belt-and-suspenders pass-through.
- The current enforcement at login is **token-based only**:
  - `handleGoogleCallback()` line 142: `$user->tokens()->delete()` — deletes Sanctum API tokens
  - `loginExterno()` line 288: `$user->tokens()->delete()` — same
- **GAP**: Both login flows also call `Auth::login($user)` which creates a Sanctum SPA cookie session. Deleting tokens does NOT invalidate an existing SPA cookie session. A user logged in via cookie on Device A will still be authenticated on Device A after logging in from Device B.

### Requirement: Login invalidates prior sessions (tokens + cookie)

When a user logs in (Google OAuth or external credentials), the system SHALL delete all prior Sanctum API tokens AND all prior session rows for that user from the `sessions` database table. This ensures the SPA cookie session is also invalidated. The `SingleSessionMiddleware` SHALL remain a pass-through (no-op) — enforcement lives in `AuthController`.

#### Scenario: Second login invalidates prior cookie session

- **GIVEN** User is logged in on Device A via Google OAuth (SPA cookie session active, session row exists in `sessions` table)
- **WHEN** User logs in on Device B with the same Google account
- **THEN** `handleGoogleCallback()` SHALL delete all prior `sessions` rows for this user before calling `Auth::login()`
- **AND** Device A's next API request SHALL return 401 (session invalidated)

#### Scenario: External login also invalidates prior session

- **GIVEN** an external evaluator is logged in via cookie (session row exists)
- **WHEN** the same user logs in via `POST /api/auth/externo/login`
- **THEN** `loginExterno()` SHALL delete all prior `sessions` rows for this user before calling `Auth::login()`
- **AND** the previous cookie session SHALL be invalidated

#### Scenario: Middleware remains pass-through

- **GIVEN** the `SingleSessionMiddleware` class
- **WHEN** inspecting its `handle()` method
- **THEN** it SHALL remain a no-op pass-through (return `$next($request)` after token check)
- **AND** the docblock SHALL document that enforcement happens in `AuthController`

---

## Issue #13 — Lockout with 10-minute sliding window

### Current State

- `User::registerFailedLogin()` (line 138–149) increments an integer counter `failed_attempts`. When the counter reaches 3, it sets `locked_until`. **There is no `last_failed_at` timestamp field**. Failed attempts from yesterday count the same as failed attempts from right now.
- `LoginAttemptPolicy::windowMinutes()` (line 30–33) returns 10 but is **never called** from `registerFailedLogin()` — the `windowMinutes` API exists but has no consumer.
- The audit action logged when an account is locked is `account_locked` (AuthController line 266). The canonical name in `AuditEvent` documentation (line 23) is `login.locked`.

### Requirement: Lockout respects a 10-minute sliding window with timestamp

The external login lockout SHALL use a sliding 10-minute window based on a `last_failed_at` timestamp. Failed attempts older than 10 minutes SHALL be discarded (counter reset). The lockout audit action SHALL use the canonical name `login.locked`.

#### Scenario: 3 failures within 10 minutes trigger lockout

- **GIVEN** a valid external evaluator account with `failed_attempts = 0`
- **WHEN** 3 failed login attempts occur within 10 minutes (each updating `last_failed_at`)
- **THEN** `registerFailedLogin()` SHALL set `locked_until = now() + 15 min` and reset `failed_attempts = 0`
- **AND** the next login attempt (within the lockout) SHALL return 423 Locked
- **AND** the audit action SHALL be `login.locked`

#### Scenario: Attempts outside the window do NOT count

- **GIVEN** an external evaluator with 2 failed attempts (`last_failed_at` = 15 minutes ago)
- **WHEN** a 3rd failed attempt occurs
- **THEN** `registerFailedLogin()` SHALL detect that `now() - last_failed_at > 10 min`
- **AND** SHALL reset the counter to 1 (discarding the 2 old attempts)
- **AND** the account SHALL NOT be locked

#### Scenario: Audit action uses canonical name

- **GIVEN** a lockout event triggers an audit log entry
- **WHEN** inspecting the `action` field in `audit_logs`
- **THEN** it SHALL be `login.locked` (matching `AuditEvent` canonical convention)
- **AND** shall NOT be `account_locked`

---

## Issue #9 — CI pipeline setup

### Current State

No `.github/workflows/` directory exists. The project has no CI pipeline. All tests run manually.

### Requirement: GitHub Actions CI pipeline runs tests, lint, and build

The repository SHALL have a CI pipeline (`.github/workflows/ci.yml`) that runs on every push and pull request to `master`. The pipeline SHALL execute the Pest test suite, Pint linting, and frontend build.

#### Scenario: CI runs Pest tests on push

- **GIVEN** `.github/workflows/ci.yml` exists
- **WHEN** a push to `master` occurs
- **THEN** `vendor/bin/pest` SHALL execute in the CI environment
- **AND** the workflow SHALL fail if any test fails

#### Scenario: CI runs Pint linting

- **GIVEN** the CI workflow is triggered
- **WHEN** tests pass
- **THEN** `vendor/bin/pint --test` SHALL execute
- **AND** SHALL fail on style violations

#### Scenario: CI builds frontend

- **GIVEN** the CI workflow is triggered
- **WHEN** linting passes
- **THEN** `npm ci && npm run build` SHALL execute
- **AND** SHALL fail on build errors
