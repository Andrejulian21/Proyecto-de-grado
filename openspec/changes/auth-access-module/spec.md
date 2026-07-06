# Spec: Módulo de Autenticación y Acceso

**Change**: `auth-access-module` &nbsp;&nbsp;|&nbsp;&nbsp; **Status**: Full spec (greenfield — no existing specs to delta against)
**Stack**: Laravel 11 + Sanctum (cookie) + React/Vite SPA &nbsp;&nbsp;|&nbsp;&nbsp; **Coverage**: HU01–HU03, RF01–RF05, RNF01, RNF03, RNF04

## Purpose

Establishes the foundational identity, authorization, session, and audit substrate for the entire platform. Every subsequent module depends on this one. Defines what the system SHALL do for institutional Google OAuth, role-based access control, email whitelist, external evaluator credential login, session lifecycle, and the immutable audit trail.

---

## Domain: `auth-oauth` — Google OAuth for @unab.edu.co

### Requirement: Institutional Google Login

The system MUST allow login ONLY through Google OAuth with a verified `unab.edu.co` account. Login attempts that fail any of the three independent validations SHALL be rejected with a clear, non-revealing message.

**Validations (all three MUST pass):**
1. `hd` claim returned by Google equals `unab.edu.co` (when present).
2. Email address ends with `@unab.edu.co` (case-insensitive).
3. Email address exists in the `authorized_emails` whitelist table.

#### Scenario: Successful first-time institutional login
- **GIVEN** `maria@unab.edu.co` is in the whitelist with role `Estudiante` AND Google returns `hd=unab.edu.co` AND email `maria@unab.edu.co`
- **WHEN** Maria completes the Google OAuth flow
- **THEN** the system creates a `User` record with her Google `name`, `email`, `picture`, role from whitelist
- **AND** deletes any prior server-side sessions for that user
- **AND** issues a Sanctum cookie session
- **AND** redirects to `/dashboard/estudiante`
- **AND** writes an `audit_logs` entry: `action=login.success`

#### Scenario: Reject login from non-UNAB domain
- **GIVEN** a user attempts login with `someone@gmail.com` (not in whitelist, wrong domain)
- **WHEN** the OAuth callback is processed
- **THEN** the system rejects with message "Acceso restringido a cuentas UNAB"
- **AND** writes `audit_logs` entry: `action=login.rejected`, `description=domain_mismatch`
- **AND** does NOT create a user record
- **AND** does NOT issue a session

#### Scenario: Reject login when email not whitelisted
- **GIVEN** `john@unab.edu.co` is a valid UNAB account (correct domain) but NOT in the whitelist
- **WHEN** John completes the Google OAuth flow
- **THEN** the system rejects with the same generic message "Acceso restringido a cuentas UNAB"
- **AND** writes `audit_logs` entry: `action=login.rejected`, `description=not_whitelisted`
- **AND** does NOT reveal whether the email is a real UNAB account

#### Scenario: Reject login when `hd` claim is missing
- **GIVEN** a Google Workspace account where `hd` is omitted (personal Google account using UNAB alias)
- **WHEN** OAuth callback is processed
- **THEN** the system rejects because the suffix check OR whitelist check fails
- **AND** writes `audit_logs` entry: `action=login.rejected`, `description=hd_missing`

#### Scenario: Reject login when user denies Google permissions
- **GIVEN** the user clicks Google OAuth then denies the consent screen
- **WHEN** Google redirects back with an error parameter
- **THEN** the system returns to `/login` with message "Inicio de sesión cancelado"
- **AND** writes `audit_logs` entry: `action=login.cancelled`
- **AND** does NOT issue any session

#### Scenario: Reject login on network error during OAuth
- **GIVEN** the Google token exchange fails (network timeout, 5xx, invalid_state)
- **WHEN** the callback handler catches the exception
- **THEN** the system returns to `/login` with a friendly retry message
- **AND** writes `audit_logs` entry: `action=login.error` with the exception class
- **AND** does NOT create partial state

---

## Domain: `rbac` — Role-Based Access Control

### Requirement: Four-Role Authorization Model

The system MUST enforce a strict RBAC model with four roles implemented as a PHP enum on `users.role`: `Estudiante`, `Director`, `Coordinador`, `EvaluadorExterno`. Every protected endpoint MUST validate role server-side (RNF03). Frontend role checks SHALL NOT be trusted for authorization.

#### Scenario: Estudiante cannot access coordinador-only route
- **GIVEN** Maria is authenticated as `Estudiante`
- **WHEN** she sends `GET /api/coordinador/usuarios`
- **THEN** the server returns `403 Forbidden` with body `{error: "unauthorized"}`
- **AND** writes `audit_logs` entry: `action=access.denied`, `description=role_mismatch`

#### Scenario: Director cannot access coordinador dashboard
- **GIVEN** Carlos is authenticated as `Director`
- **WHEN** he sends `GET /api/coordinador/dashboard`
- **THEN** the server returns `403 Forbidden`
- **AND** writes `audit_logs` entry: `action=access.denied`

#### Scenario: Coordinator changes a user's role
- **GIVEN** Ana (Coordinador) opens `/coordinador/usuarios` and changes Luis's role from `Estudiante` to `Director`
- **WHEN** she submits the change
- **THEN** the server updates `users.role`
- **AND** writes `audit_logs` entry: `action=role.changed`, `description=Estudiante→Director`, `actor_id=Ana.id`, `subject_id=Luis.id`
- **AND** Luis sees the new role on his next `/api/user` fetch

#### Scenario: Role change applies on next page load
- **GIVEN** Luis is logged in as `Estudiante` in another browser tab
- **WHEN** Ana changes Luis's role to `Director`
- **THEN** the existing session for Luis remains valid until inactivity timeout
- **AND** Luis's next `GET /api/user` returns the new role
- **AND** Luis's next navigation to a Director-only route succeeds without re-login

#### Scenario: EvaluadorExterno restricted to evaluation endpoints
- **GIVEN** Pedro is authenticated as `EvaluadorExterno`
- **WHEN** he sends `GET /api/proyectos` (project list)
- **THEN** the server returns `403 Forbidden`
- **AND** only his assigned project is accessible via `GET /api/proyectos/{id}`

---

## Domain: `whitelist` — Coordinator-Managed Authorized Emails

### Requirement: Whitelist CRUD by Coordinator

The coordinator MUST be able to create, read, update, and delete entries in the `authorized_emails` table. Each entry contains `email`, `role` (Estudiante|Director|Coordinador), `created_by`, and timestamps. Self-registration SHALL NOT be permitted.

#### Scenario: Coordinator adds a new authorized email
- **GIVEN** Ana (Coordinador) is on `/coordinador/usuarios`
- **WHEN** she submits `{email: "nuevo@unab.edu.co", role: "Estudiante"}`
- **THEN** the server validates email format and UNAB domain
- **AND** inserts the row in `authorized_emails`
- **AND** writes `audit_logs` entry: `action=whitelist.add`
- **AND** the new email becomes eligible to log in via Google OAuth immediately

#### Scenario: Coordinator updates an existing whitelist entry's role
- **GIVEN** `maria@unab.edu.co` exists with role `Estudiante`
- **WHEN** Ana changes her role to `Director`
- **THEN** the server updates `authorized_emails.role` AND the matching `users.role` (if the user has already logged in)
- **AND** writes `audit_logs` entry: `action=whitelist.role_changed`

#### Scenario: Coordinator removes an email from whitelist
- **GIVEN** `juan@unab.edu.co` is in the whitelist and Juan has an active session
- **WHEN** Ana deletes the entry
- **THEN** the row is soft-deleted (or marked inactive)
- **AND** Juan's existing session is invalidated on the next request via middleware
- **AND** Juan cannot log in again via OAuth
- **AND** writes `audit_logs` entry: `action=whitelist.removed`

#### Scenario: Reject duplicate email in whitelist
- **GIVEN** `maria@unab.edu.co` already exists
- **WHEN** Ana attempts to add the same email
- **THEN** the server returns `422 Unprocessable Entity` with field-level error
- **AND** does NOT create a duplicate row

#### Scenario: Reject non-UNAB email in whitelist
- **GIVEN** Ana tries to add `externo@gmail.com`
- **WHEN** she submits
- **THEN** the server returns `422` with error "El correo debe pertenecer al dominio @unab.edu.co"

---

## Domain: `auth-external` — External Evaluator Credential Login

### Requirement: Separate Login Page for External Evaluators

The system MUST provide a separate `/login/externo` page with email + password authentication, exclusively for users with `users.es_externo = true` and role `EvaluadorExterno`. The coordinator MUST create these accounts manually. The same session, audit, and timeout rules apply.

#### Scenario: External evaluator logs in with valid credentials
- **GIVEN** Ana created `pedro@evaluador.com` with password `TempPass!2026` and role `EvaluadorExterno`
- **WHEN** Pedro POSTs `{email, password}` to `/login/externo`
- **THEN** the system validates credentials using Laravel Hash
- **AND** creates a session (deletes any prior session for Pedro)
- **AND** writes `audit_logs` entry: `action=login.success`, `channel=external`
- **AND** redirects to `/dashboard/evaluador-externo`

#### Scenario: Reject external login with wrong password
- **GIVEN** Pedro's account exists
- **WHEN** he POSTs the correct email but wrong password 3 times within 10 minutes
- **THEN** the system locks the account for 15 minutes
- **AND** writes `audit_logs` entry: `action=login.locked` on each failed attempt
- **AND** returns `423 Locked` with message "Cuenta bloqueada por intentos fallidos"

#### Scenario: Coordinator creates external evaluator account
- **GIVEN** Ana (Coordinador) is on `/coordinador/evaluadores`
- **WHEN** she submits `{name, email, temporary_password, assigned_project_id}`
- **THEN** the server hashes the password, creates the user with `es_externo=true`, role `EvaluadorExterno`
- **AND** writes `audit_logs` entry: `action=user.created_external`
- **AND** the external user receives an invitation email with a password-reset link

#### Scenario: External evaluator forced password change on first login
- **GIVEN** Pedro's account was just created with a temporary password
- **WHEN** he logs in successfully for the first time
- **THEN** the system flags the session with `must_change_password=true`
- **AND** redirects to `/cuenta/cambiar-contrasena` instead of the dashboard
- **AND** blocks access to all other routes until the password is changed

#### Scenario: External evaluator cannot use Google OAuth login
- **GIVEN** Pedro has `es_externo=true` and no Google account
- **WHEN** he navigates to `/login` (institutional) and clicks Google
- **THEN** the OAuth flow returns 403 because his email is not in the UNAB whitelist
- **AND** he sees a message directing him to `/login/externo`

---

## Domain: `session` — Session Lifecycle

### Requirement: Single Active Session with 8-Hour Inactivity Timeout

The system MUST enforce a single active session per user (new login invalidates previous server-side session) and MUST log out the user after 8 hours of inactivity. The session SHALL be stored server-side in the database (Redis-backed).

#### Scenario: New login invalidates previous session
- **GIVEN** Luis is logged in on Device A (Sanctum cookie present)
- **WHEN** Luis logs in again on Device B with valid credentials
- **THEN** the server deletes all `sessions` rows for `Luis.id`
- **AND** Device A's next API call returns `401 Unauthorized`
- **AND** Device A is redirected to `/login` with message "Su sesión fue cerrada desde otro dispositivo"

#### Scenario: 8-hour inactivity timeout
- **GIVEN** Maria logged in at 09:00
- **AND** no API request has been made for 8 hours (timestamp `last_activity`)
- **WHEN** she sends any API request at 17:01
- **THEN** the middleware detects `now() - last_activity > 8h`
- **AND** invalidates the session
- **AND** returns `401 Unauthorized` with code `session.timeout`
- **AND** writes `audit_logs` entry: `action=logout.timeout`

#### Scenario: Activity resets the 8-hour clock
- **GIVEN** Maria logged in at 09:00
- **WHEN** she makes an API request at 14:00 (5 hours later)
- **THEN** `last_activity` is updated to 14:00
- **AND** the timeout is now 22:00 (8h after 14:00)

#### Scenario: Explicit logout
- **GIVEN** Luis is authenticated
- **WHEN** he clicks "Cerrar sesión" → POST `/api/logout`
- **THEN** the server deletes the current session row
- **AND** invalidates the Sanctum cookie
- **AND** writes `audit_logs` entry: `action=logout.user_initiated`
- **AND** subsequent API calls return `401`

#### Scenario: Browser crash or tab close
- **GIVEN** Maria closed her browser without logging out
- **WHEN** she returns and opens the app
- **THEN** if `last_activity` < 8h ago, the Sanctum cookie is still valid
- **AND** the SPA resumes her session without re-login

#### Scenario: Multiple browser tabs share one session
- **GIVEN** Luis has the app open in 3 browser tabs
- **WHEN** any tab makes an API call
- **THEN** all 3 tabs continue to work (single server-side session)
- **AND** logging out from any tab logs out all 3 tabs

---

## Domain: `audit-log` — Immutable Action Trail

### Requirement: Append-Only Audit Logging

The system MUST log every significant action through Laravel Events + Listeners (never directly from controllers). The `audit_logs` table SHALL be append-only: no UPDATE or DELETE route is exposed, and database permissions deny those operations for the application user.

#### Scenario: Login success creates audit entry
- **GIVEN** Pedro successfully logs in
- **WHEN** the login flow completes
- **THEN** an `AuditLog` row is created with: `user_id`, `action='login.success'`, `ip_address`, `user_agent`, `created_at=now()`

#### Scenario: Role change is logged with actor and subject
- **GIVEN** Ana (Coordinador) changes Luis's role
- **WHEN** the change is committed
- **THEN** an `AuditLog` row records `action='role.changed'`, `actor_id=Ana.id`, `subject_id=Luis.id`, `description='Estudiante→Director'`, `metadata={reason?: "..."}`

#### Scenario: No endpoint allows editing or deleting audit entries
- **GIVEN** the API exposes audit query endpoints
- **WHEN** any client attempts `PUT/PATCH/DELETE /api/audit-logs/{id}`
- **THEN** the server returns `405 Method Not Allowed`
- **AND** the database role used by Laravel has no UPDATE/DELETE privilege on `audit_logs`

#### Scenario: Coordinator filters audit log
- **GIVEN** Ana is on `/coordinador/auditoria`
- **WHEN** she applies filters: `user_id=Luis.id`, `date_from=2026-06-01`, `date_to=2026-07-01`, `action=login.success`
- **THEN** the server returns paginated matching entries (default 50 per page, max 200)
- **AND** results are ordered by `created_at DESC`
- **AND** query takes < 500ms with 100k rows

#### Scenario: High-volume logging does not block user requests
- **GIVEN** 1000 audit entries are written per minute during peak hours
- **WHEN** users perform normal actions
- **THEN** audit writes are dispatched via Laravel's queue (async listener)
- **AND** the user-facing request completes in < 200ms p95

#### Scenario: 5-year retention policy
- **GIVEN** an `audit_logs` entry is older than 5 years
- **WHEN** the daily scheduled command `audit:archive` runs
- **THEN** entries older than 5 years are moved to `audit_logs_archive` (cold storage table)
- **AND** the active `audit_logs` table stays under 2M rows
- **AND** archived entries remain queryable but are excluded from the default coordinator view

#### Scenario: Unauthorized access attempt is logged
- **GIVEN** an unauthenticated request hits a protected route
- **WHEN** the auth middleware rejects it
- **THEN** an `AuditLog` entry is written: `action='access.denied'`, `user_id=null`, `ip_address`, `path`, `method`

---

## Cross-Cutting Non-Functional Requirements

| ID    | Requirement                                                                                                                              |
|-------|------------------------------------------------------------------------------------------------------------------------------------------|
| RNF01 | Server-side domain validation. No URL parameter, header, or client-side check may bypass the `hd` + suffix + whitelist triple check.       |
| RNF02 | All auth endpoints MUST serve over HTTPS only. The system MUST redirect any HTTP request to HTTPS.                                       |
| RNF03 | Every protected endpoint MUST re-validate the role on the server, regardless of frontend state. Tampering with frontend MUST NOT escalate privileges. |
| RNF04 | Sanctum CSRF token MUST be validated on every state-changing request. All user inputs MUST be validated server-side.                     |
| RNF05 | Architecture MUST support a future TOTP module (signature flows). The `users` table SHALL include a `totp_secret` nullable column from day one (no logic yet). |
