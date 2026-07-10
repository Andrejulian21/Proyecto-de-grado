# Hardening Audit Fixes — Specification

**Change**: `hardening-audit-fixes` | **Type**: Hardening | **ADRs**: 003, 005, 006, 010

## Purpose

Harden authentication, session management, backend quality, and DB schema per audit findings #14–#28. No new features. No behavioral changes to existing capabilities beyond fixing broken security invariants.

## Requirements

### PR1 — Security Critical

| ID | Requirement | Scenarios |
|----|------------|-----------|
| **H-001** | All auth mutations SHALL use `apiFetch()` with `X-XSRF-TOKEN`. Login and logout SHALL NOT be exempt from CSRF validation. | **Happy**: GIVEN a logged-out session, WHEN `POST /api/auth/externo/login` is called via `apiFetch()`, THEN the request includes `X-XSRF-TOKEN` and CSRF middleware accepts it. **Edge**: GIVEN a raw `fetch()` call without XSRF token, WHEN `POST /api/auth/externo/login` is sent, THEN the server returns 419. |
| **H-002** | Login error responses SHALL be identical (401) regardless of whether the email exists or the password is wrong. `Hash::check()` SHALL run against a dummy hash when the user is not found, ensuring constant-time comparison. | **Happy**: GIVEN a valid email with wrong password, WHEN login is attempted, THEN the response is 401 with an identical body, status, and timing (delta < 50ms) as an invalid email. **Edge**: GIVEN a non-existent email, WHEN login is attempted, THEN `Hash::check()` still executes and the response is 401. |
| **H-003** | `POST /api/auth/externo/login` SHALL be rate-limited to 5 attempts per minute per IP+email combination. | **Happy**: GIVEN 5 failed attempts from the same IP+email, WHEN a 6th attempt is made within the same minute, THEN the server returns 429. **Edge**: GIVEN 5 failed attempts for `a@b.com`, WHEN login is attempted for `c@d.com` from the same IP, THEN it succeeds. |

### PR2 — Auth Model + Session

| ID | Requirement | Scenarios |
|----|------------|-----------|
| **H-004** | Authentication SHALL use Sanctum cookie SPA mode exclusively. Bearer tokens SHALL NOT be created or consumed by `loginExterno`. | **Happy**: GIVEN a successful `loginExterno` call, WHEN the response is received, THEN the session cookie is set and no bearer token is present in the response body. **Edge**: GIVEN a request with an `Authorization: Bearer` header, WHEN hitting a protected endpoint, THEN it fails with 401 (cookies only). |
| **H-005** | `ActivityMiddleware::handle()` SHALL call `Auth::logout()` and invalidate the server session on timeout. All admin routes SHALL include `activity`, `single_session`, and `ensure_password_changed` middleware. | **Happy**: GIVEN an admin session idle for >1h, WHEN the next request is processed, THEN `Auth::logout()` is called, the Redis session is destroyed, and the client receives 401. **Edge**: GIVEN middleware order `single_session` → `activity` → `ensure_password_changed`, WHEN any fails, THEN none of the subsequent middleware runs. |
| **H-006** | `config/session.php` SHALL default `SESSION_SECURE_COOKIE` to `true`. The frontend SHALL NOT store auth tokens in `sessionStorage`. `ProtectedRoute` SHALL enforce role checks. `logout()` SHALL clear the refresh interval. | **Happy**: GIVEN `APP_ENV=production`, WHEN the session config is loaded, THEN `secure` resolves to `true`. **Edge**: GIVEN a logged-in Estudiante user, WHEN navigating to `/admin`, THEN `ProtectedRoute` redirects to `/unauthorized`. |

### PR3 — Backend Quality

| ID | Requirement | Scenarios |
|----|------------|-----------|
| **H-007** | The backend SHALL generate and return the temporary password for external users. `genPassword()` in the frontend SHALL use `crypto.getRandomValues()` for display-only generation. | **Happy**: GIVEN a `POST /api/admin/usuarios` request, WHEN `storeUsuario()` completes, THEN the response includes `temp_password` generated server-side. **Edge**: GIVEN password generation in the browser, WHEN `genPassword()` is called, THEN it uses `crypto.getRandomValues()`, not `Math.random()`. |
| **H-008** | `AuthorizedEmail::$fillable` SHALL include `name`. `updateUsuario()` SHALL restrict the `role` field to `WHITELIST_ROLES` (excluding `EvaluadorExterno`). | **Happy**: GIVEN a `POST /api/admin/usuarios` with `name: "Juan"`, WHEN `AuthorizedEmail::create()` is called, THEN `name` is persisted. **Edge**: GIVEN `updateUsuario()` with `role: EvaluadorExterno`, WHEN validated, THEN it fails. |
| **H-009** | Gates and policies SHALL be either wired into controllers or removed. Unreferenced call-sites SHALL NOT remain. | **Happy**: GIVEN `AuthServiceProvider` registers gates, WHEN grepping for `Gate::allows`, `Gate::authorize`, or `can:` middleware across controllers, THEN every gate has at least one call-site or is removed. |
| **H-010** | Controllers SHALL use `FormRequest` classes for validation and route-model binding for entity resolution. `AuthController` and `UserController` SHALL each be ≤500 lines or split. | **Happy**: GIVEN `POST /api/auth/externo/login`, WHEN the request is handled, THEN a `LoginExternoRequest` validates input before the controller method executes. |
| **H-011** | `WriteAuditLog` SHALL implement `ShouldQueue`. If the queue is unavailable, the listener SHALL fall back to synchronous write. `AuditEvent` SHALL capture `ip_address` and `user_agent` at dispatch time. | **Happy**: GIVEN a running queue worker, WHEN `AuditEvent` is dispatched, THEN `WriteAuditLog` processes asynchronously. **Edge**: GIVEN no queue connection, WHEN `AuditEvent` is dispatched, THEN the audit entry is written synchronously without data loss. |

### PR4 — DB Schema + Cleanup

| ID | Requirement | Scenarios |
|----|------------|-----------|
| **H-012** | `authorized_emails.created_by` SHALL have an index. Email uniqueness SHALL be case-insensitive via `citext` or functional index. `AuthorizedEmail` SHALL use `SoftDeletes`. | **Happy**: GIVEN `john@test.com` exists, WHEN inserting `JOHN@test.com`, THEN it fails with a unique constraint violation. **Edge**: GIVEN a soft-deleted `AuthorizedEmail`, WHEN `->withTrashed()` is called, THEN the record is still retrievable. |
| **H-013** | Timestamp columns SHALL use `timestamptz`. Redundant single-column indexes on `audit_logs` SHALL be removed. `users.role` SHALL have a `CHECK` constraint. | **Happy**: GIVEN a fresh migration, WHEN inspecting column types, THEN `created_at` and `updated_at` are `timestamp with time zone`. |
| **H-014** | `ExampleTest` stubs SHALL be removed. `extractHostedDomain` SHALL collapse redundant branches. `LIKE` SHALL use `ILIKE` for case-insensitive search. `sslmode` SHALL default to `require`. Timeout SHALL be consistently 1h across docs, code, and config. | **Happy**: GIVEN the test suite, WHEN `php artisan test` runs, THEN `ExampleTest.php` does not exist. **Edge**: GIVEN a search for `Juan`, WHEN `ILIKE '%juan%'` is used, THEN it matches `JUAN`, `Juan`, and `juan`. |
