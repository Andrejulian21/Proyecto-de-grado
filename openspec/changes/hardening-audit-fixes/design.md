# Design: hardening-audit-fixes

## Overview

Four stacked PRs (force-chained to `main`) addressing 15 audit findings across security, auth model, backend quality, and database schema. Each PR is independently deployable and testable. Total scope: ~25 files touched, ~8 new files created, 151+ tests maintained.

**PR dependency chain:** PR1 → PR2 → PR3 → PR4 (each builds on the previous).

---

## PR 1 — Security Critical

**Issues addressed:** #15 (CSRF bypass), #16 (user enumeration), #17 (rate limiting)

### Changes

| File | Action | Description |
|------|--------|-------------|
| `bootstrap/app.php` | Modify | Remove CSRF exemptions for `api/auth/logout` and `api/auth/externo/login` (L41-44) |
| `app/Providers/AppServiceProvider.php` | Modify | Register `RateLimiter::for('login', ...)` with 5/min per IP+email |
| `app/Http/Controllers/Auth/AuthController.php` | Modify | Refactor `loginExterno()` for constant-time responses |
| `routes/api.php` | Modify | Add `->middleware('throttle:login')` to the login route |
| `resources/js/pages/auth/LoginExterno.tsx` | Modify | Replace raw `fetch()` with `apiFetch()` from `lib/utils.ts` |
| `resources/js/hooks/useAuth.tsx` | Modify | Replace raw `fetch()` in `logout()` with `apiFetch()` |

### Implementation Detail

#### 1. Rate Limiter (AppServiceProvider)

```php
// In AppServiceProvider::boot(), after the existing 'api' limiter:
RateLimiter::for('login', function (Request $request) {
    $email = strtolower((string) $request->input('email', ''));
    return [
        Limit::perMinute(5)->by($request->ip() . '|' . $email),
    ];
});
```

In `routes/api.php`, add the throttle middleware to the login route:
```php
Route::post('/auth/externo/login', [AuthController::class, 'loginExterno'])
    ->middleware('throttle:login')
    ->name('auth.externo.login');
```

#### 2. Constant-Time Login (AuthController::loginExterno)

The current implementation has three timing-differentiable paths:
- User not found → returns 401/403 immediately (no Hash::check)
- User found but not externo → returns 403
- User found, password wrong → returns 401 (Hash::check ran)

**Refactor:** All three failure cases return identical 401 with `{'error': 'invalid_credentials'}`. A dummy `Hash::check()` runs when no user is found to equalize timing.

```php
public function loginExterno(Request $request): JsonResponse
{
    $payload = $request->validate([
        'email' => ['required', 'string', 'email'],
        'password' => ['required', 'string'],
    ]);

    $user = User::query()->where('email', $payload['email'])->first();

    // Dummy hash check to prevent timing-based user enumeration.
    $dummyHash = '$2y$12$aaaaaaaaaaaaaaaaaaaaaastubHashForTimingProtection1';
    if (! $user) {
        Hash::check($payload['password'], $dummyHash);
    }

    // Unified failure: same response for all auth failures.
    if (! $user || ! $user->es_externo || ! Hash::check($payload['password'], $user->password)) {
        AuditEvent::dispatch($user, 'login.rejected', 'invalid_credentials',
            ['channel' => 'external', 'email' => $payload['email']]);
        return response()->json(['error' => 'invalid_credentials'], 401);
    }

    if ($user->isLocked()) {
        AuditEvent::dispatch($user, 'login.locked', 'account locked',
            ['channel' => 'external', 'locked_until' => $user->locked_until?->toIso8601String()]);
        return response()->json(['error' => 'account_locked'], 423);
    }

    // ... success path unchanged ...
}
```

**Key decision:** The `not_external_evaluator` 403 is REMOVED. An attacker cannot distinguish "email not in system" from "email belongs to internal user" from "wrong password". The audit log still records the specific reason for forensic analysis.

#### 3. CSRF Compliance

**bootstrap/app.php** — Remove the entire `validateCsrfTokens(except: [...])` block (L41-44). The Sanctum SPA flow already handles CSRF via `apiFetch()` which reads the XSRF-TOKEN cookie.

**LoginExterno.tsx L41-46** — Replace:
```tsx
const res = await fetch('/api/auth/externo/login', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ email: email.trim(), password }),
});
```
With:
```tsx
import { apiFetch } from '@/lib/utils';
// ...
const res = await apiFetch('/api/auth/externo/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: email.trim(), password }),
});
```

**useAuth.tsx L132-138** — Same pattern for logout:
```tsx
import { apiFetch } from '@/lib/utils';
// ...
await apiFetch('/api/auth/logout', { method: 'POST' });
```

**LoginExterno.tsx L50** — Remove `sessionStorage.setItem('auth_token', data.token)` (the token response field will be removed in PR2, but we stop storing it now).

### Tests Required

| Test | Type | Description |
|------|------|-------------|
| `test('login rate limit returns 429 after 5 attempts')` | Feature | POST 6 times in 1 minute, assert 429 on 6th |
| `test('login with non-existent email returns 401')` | Feature | Assert 401 (not 403/404) |
| `test('login with internal user email returns 401')` | Feature | Assert 401 (not 403) — no user enumeration |
| `test('login response time is consistent')` | Performance | Measure delta < 50ms between valid/invalid |
| `test('CSRF token required on login')` | Feature | POST without XSRF-TOKEN → 419 |

---

## PR 2 — Auth Model + Session

**Issues addressed:** #20 (hybrid auth), #25 (timeout incomplete), #26 (SPA leaks)

### Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Http/Controllers/Auth/AuthController.php` | Modify | Remove `createToken()` from `loginExterno()`, remove `createToken()` from `handleGoogleCallback()` |
| `app/Http/Middleware/ActivityMiddleware.php` | Modify | Add `Auth::logout()` + session invalidation on timeout |
| `routes/api.php` | Modify | Add `activity`, `single_session`, `ensure_password_changed` to admin group |
| `config/session.php` | Modify | `'secure' => env('SESSION_SECURE_COOKIE', true)` |
| `.env.example` | Modify | Add `SESSION_SECURE_COOKIE=true` |
| `resources/js/pages/auth/LoginExterno.tsx` | Modify | Remove `sessionStorage.setItem('auth_token', ...)`, use cookie-only flow |
| `resources/js/hooks/useAuth.tsx` | Modify | Add `clearInterval(refreshRef.current)` in `logout()` |
| `resources/js/app.tsx` | Modify | Add role check to `ProtectedRoute` |

### Implementation Detail

#### 1. Cookie-Only loginExterno

Remove `createToken()` call (L316) and the `token` field from the JSON response. The response becomes:

```php
return response()->json([
    'user' => [
        'id' => $user->id,
        'email' => $user->email,
        'name' => $user->name,
        'role' => $user->role->value,
        'es_externo' => $user->es_externo,
    ],
    'must_change_password' => $user->mustChangePassword(),
]);
```

Same cleanup in `handleGoogleCallback()` (L152): remove `$accessToken = $user->createToken('google-oauth')`. The `Auth::login()` on L155 already establishes the Sanctum SPA session.

#### 2. ActivityMiddleware Fix

Current code (L48) only deletes the access token. Must also invalidate the session:

```php
if ($lastActivity !== null
    && $lastActivity->lt(now()->subMinutes(self::INACTIVITY_MINUTES))) {
    $user->currentAccessToken()?->delete();

    // Invalidate the Sanctum SPA session too.
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    AuditEvent::dispatch($user, 'logout.timeout', '1-hour inactivity timeout');

    return response()->json(['error' => 'session.timeout'], 401);
}
```

#### 3. Admin Middleware Stack

Current admin group (L129): `['auth:sanctum', 'role:Coordinador']`

Change to: `['auth:sanctum', 'single_session', 'activity', 'ensure_password_changed', 'role:Coordinador']`

This ensures admin routes enforce the same session/activity guarantees as user routes.

#### 4. Secure Cookie Default

`config/session.php` L175: `'secure' => env('SESSION_SECURE_COOKIE', true)`

Add to `.env.example`:
```
SESSION_SECURE_COOKIE=true
```

#### 5. ProtectedRoute Role Guard

```tsx
function ProtectedRoute({ children, allowedRoles }: {
    children: React.ReactNode;
    allowedRoles?: string[];
}) {
    const { isAuthenticated, isLoading, role } = useAuth();

    if (isLoading) return <Loader />;
    if (!isAuthenticated) return <Navigate to="/login" replace />;
    if (allowedRoles && role && !allowedRoles.includes(role)) {
        return <Navigate to="/" replace />;
    }

    return <>{children}</>;
}
```

Apply to admin routes: `<ProtectedRoute allowedRoles={['Coordinador']}>`.

#### 6. Logout Poll Cleanup

In `useAuth.tsx`, the `logout()` function must clear the polling interval:

```tsx
async function logout() {
    if (refreshRef.current) {
        clearInterval(refreshRef.current);
        refreshRef.current = null;
    }
    try {
        await apiFetch('/api/auth/logout', { method: 'POST' });
    } catch { /* best-effort */ }
    finally {
        setUser(null);
        localStorage.removeItem('user_role');
        sessionStorage.removeItem('auth_user');
        navigate('/login', { replace: true });
    }
}
```

### Tests Required

| Test | Type | Description |
|------|------|-------------|
| `test('loginExterno does not return bearer token')` | Feature | Assert response has no `token` key |
| `test('ActivityMiddleware calls Auth::logout on timeout')` | Feature | Mock expired user, assert session invalidated |
| `test('admin routes require activity middleware')` | Feature | Inactive user gets 401 on admin endpoint |
| `test('admin routes require single_session')` | Feature | Second login invalidates first session |
| `test('session cookie is secure in production')` | Unit | Assert config when SESSION_SECURE_COOKIE=true |

---

## PR 3 — Backend Quality

**Issues addressed:** #14 (Math.random password), #18 (fillable), #19 (role restriction), #22 (dead gates), #23 (FormRequests), #24 (sync listener)

### Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Http/Requests/LoginExternoRequest.php` | Create | FormRequest for login validation |
| `app/Http/Requests/ChangePasswordRequest.php` | Create | FormRequest for password change |
| `app/Http/Requests/StoreWhitelistRequest.php` | Create | FormRequest for whitelist creation |
| `app/Http/Requests/UpdateUserRequest.php` | Create | FormRequest for user role update |
| `app/Http/Requests/CreateEvaluadorRequest.php` | Create | FormRequest for external evaluator creation |
| `app/Models/AuthorizedEmail.php` | Modify | Add `'name'` to `$fillable` |
| `app/Http/Controllers/Auth/AuthController.php` | Modify | Use FormRequests, remove dead code |
| `app/Http/Controllers/Admin/UserController.php` | Modify | Use FormRequests, route-model binding, fix role validation |
| `app/Events/AuditEvent.php` | Modify | Capture `ip_address` and `user_agent` in constructor |
| `app/Listeners/WriteAuditLog.php` | Modify | Implement `ShouldQueue`, add sync fallback |
| `app/Providers/AuthServiceProvider.php` | Modify | Wire Gates to actual controller call-sites OR remove |
| `routes/api.php` | Modify | Route-model binding: `{user}` instead of `{id}` |

### Implementation Detail

#### 1. FormRequests

Create `app/Http/Requests/` directory with 5 classes. Example:

```php
// app/Http/Requests/LoginExternoRequest.php
class LoginExternoRequest extends FormRequest
{
    public function authorize(): bool { return true; } // guest route

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

```php
// app/Http/Requests/UpdateUserRequest.php
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Coordinador;
    }

    public function rules(): array
    {
        // Exclude EvaluadorExterno — those accounts are managed via storeExternal/destroyUsuario.
        $allowedRoles = [
            UserRole::Estudiante->value,
            UserRole::Director->value,
            UserRole::Coordinador->value,
        ];
        return [
            'role' => ['required', 'string', Rule::in($allowedRoles)],
        ];
    }
}
```

#### 2. AuthorizedEmail fillable

```php
protected $fillable = ['email', 'name', 'role', 'created_by'];
```

#### 3. ShouldQueue + Sync Fallback (WriteAuditLog)

```php
class WriteAuditLog implements ShouldQueue
{
    public function handle(AuditEvent $event): void
    {
        AuditLog::create([
            'user_id' => $event->user?->id,
            'action' => $event->action,
            'description' => $event->description,
            'ip_address' => $event->ip_address,
            'user_agent' => $event->user_agent,
            'metadata' => $event->meta,
        ]);
    }
}
```

**AuditEvent refactor** — capture request context at dispatch time (not at listener time), because queued jobs lose the HTTP request:

```php
class AuditEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ?User $user,
        public readonly string $action,
        public readonly string $description,
        public readonly array $meta = [],
        public readonly ?string $ip_address = null,
        public readonly ?string $user_agent = null,
    ) {}

    // Static factory that auto-captures request context.
    public static function dispatch(?User $user, string $action, string $description, array $meta = []): static
    {
        $request = app()->bound('request') ? app('request') : null;
        $event = new static(
            $user, $action, $description, $meta,
            ip_address: $request?->ip(),
            user_agent: $request?->userAgent(),
        );
        event($event);
        return $event;
    }
}
```

**Sync fallback** — In `EventServiceProvider`, the listener remains registered. If the queue connection fails, Laravel's `failed_jobs` table catches it. For additional safety, wrap the dispatch in a try/catch at the `AuditEvent::dispatch()` factory level:

```php
public static function dispatch(...): static
{
    // ... build event ...
    try {
        event($event);
    } catch (\Throwable $e) {
        // Queue down — write synchronously as fallback.
        (new WriteAuditLog())->handle($event);
        report($e);
    }
    return $event;
}
```

#### 4. Route-Model Binding

```php
// routes/api.php
Route::put('/usuarios/{user}', [UserController::class, 'updateUsuario'])->name('usuarios.update');
Route::delete('/usuarios/{user}', [UserController::class, 'destroyUsuario'])->name('usuarios.destroy');
```

```php
// UserController.php
public function updateUsuario(UpdateUserRequest $request, User $user): JsonResponse
public function destroyUsuario(Request $request, User $user): JsonResponse
```

#### 5. Dead Gates/Policies

**Decision:** Remove `AuthServiceProvider` Gates and `UserPolicy`. The `role:Coordinador` middleware already enforces access at the route level. The Gates are registered but never called in production code (confirmed via grep). Removing them eliminates confusion.

If the team wants to keep them for future use, wire them as middleware on the admin routes: `->middleware('can:manage-users')`. But the simpler path is removal.

#### 6. Dead Code Cleanup

- `extractHostedDomain()` L231-237: The second branch (L235-237) is dead code — it checks the same key that was already checked on L231. Collapse to a single lookup.
- `like` → `ilike` in `UserController::usuarios()` L44-47 for case-insensitive search on PostgreSQL.

### Tests Required

| Test | Type | Description |
|------|------|-------------|
| `test('FormRequest validates email format')` | Unit | Each FormRequest with valid/invalid data |
| `test('AuthorizedEmail stores name when fillable')` | Feature | Create with name, assert it persists |
| `test('updateUsuario rejects EvaluadorExterno role')` | Feature | PUT with EvaluadorExterno → 422 |
| `test('WriteAuditLog writes when queue is down')` | Feature | Mock queue failure, assert audit row exists |
| `test('AuditEvent captures IP and user_agent')` | Unit | Dispatch event, assert fields populated |
| `test('route-model binding resolves User')` | Feature | PUT /admin/usuarios/1 → resolved via binding |

---

## PR 4 — DB Schema + Cleanup

**Issues addressed:** #21 (FK index + email case), #27 (schema), #28 (cleanup)

### Changes

| File | Action | Description |
|------|--------|-------------|
| `database/migrations/2026_07_XX_000001_add_created_by_index_to_authorized_emails.php` | Create | Index on `created_by` |
| `database/migrations/2026_07_XX_000002_add_email_lower_index_to_users.php` | Create | Functional index `lower(email)` |
| `database/migrations/2026_07_XX_000003_add_email_lower_index_to_authorized_emails.php` | Create | Functional index `lower(email)` |
| `database/migrations/2026_07_XX_000004_add_soft_deletes_to_authorized_emails.php` | Create | `deleted_at` column |
| `database/migrations/2026_07_XX_000005_add_role_check_constraint_to_users.php` | Create | CHECK constraint on `role` |
| `database/migrations/2026_07_XX_000006_drop_redundant_action_index_from_audit_logs.php` | Create | Drop single-column `action` index |
| `app/Models/AuthorizedEmail.php` | Modify | Add `SoftDeletes` trait |
| `app/Http/Controllers/Admin/UserController.php` | Modify | `destroy()` uses soft-delete |
| `tests/Unit/ExampleTest.php` | Delete | Remove stub |
| `tests/Feature/ExampleTest.php` | Delete | Remove stub (if exists) |

### Implementation Detail

#### 1. Migrations (all additive, no modifications to existing migrations)

```php
// 2026_07_XX_000001_add_created_by_index_to_authorized_emails.php
Schema::table('authorized_emails', function (Blueprint $table) {
    $table->index('created_by');
});
```

```php
// 2026_07_XX_000002_add_email_lower_index_to_users.php
// PostgreSQL functional index for case-insensitive email lookup.
DB::statement('CREATE INDEX users_email_lower_index ON users (lower(email))');
// down(): DB::statement('DROP INDEX users_email_lower_index');
```

```php
// 2026_07_XX_000003_add_email_lower_index_to_authorized_emails.php
DB::statement('CREATE INDEX authorized_emails_email_lower_index ON authorized_emails (lower(email))');
```

```php
// 2026_07_XX_000004_add_soft_deletes_to_authorized_emails.php
Schema::table('authorized_emails', function (Blueprint $table) {
    $table->softDeletes();
});
```

```php
// 2026_07_XX_000005_add_role_check_constraint_to_users.php
$validRoles = implode("', '", UserRole::values());
DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('{$validRoles}'))");
// down(): DB::statement('ALTER TABLE users DROP CONSTRAINT users_role_check');
```

```php
// 2026_07_XX_000006_drop_redundant_action_index_from_audit_logs.php
// The composite index (action, created_at) from migration 000003 supersedes
// the single-column action index from the original table creation.
Schema::table('audit_logs', function (Blueprint $table) {
    $table->dropIndex('audit_logs_action_index');
});
```

#### 2. timestamptz Decision

**Decision: Do NOT migrate `timestamp()` → `timestamptz()`.**

Rationale:
- All existing migrations use `timestamp()`. Changing them would require either modifying existing migrations (forbidden — breaks deployed databases) or creating ALTER COLUMN migrations for every table (high risk, low value for this project scope).
- PostgreSQL `timestamp` without time zone works correctly when the application consistently uses UTC (which Laravel does by default with `APP_TIMEZONE=America/Bogota` but all DB operations in UTC).
- Document this as a known limitation in `DECISIONES.md` for future reference.

#### 3. AuthorizedEmail SoftDeletes

```php
class AuthorizedEmail extends Model
{
    use HasFactory, SoftDeletes;
    // ...
}
```

Update `UserController::destroy()` to use soft-delete:
```php
$entry->delete(); // Now a soft-delete thanks to SoftDeletes trait
```

#### 4. Cleanup

- Delete `tests/Unit/ExampleTest.php` (stub)
- Delete `tests/Feature/ExampleTest.php` (stub, if exists)
- Collapse `extractHostedDomain()` in AuthController (dead branch removal)
- Change `like` to `ilike` in `UserController::usuarios()` search
- Add `SESSION_SECURE_COOKIE=true` to `.env.example` (done in PR2, verify here)

### Tests Required

| Test | Type | Description |
|------|------|-------------|
| `test('authorized_emails.created_by is indexed')` | Migration | Assert index exists |
| `test('email lookup is case-insensitive')` | Feature | Find user by mixed-case email |
| `test('authorized_email soft-deletes')` | Feature | Delete, assert `deleted_at` set, not found in default query |
| `test('users.role CHECK constraint rejects invalid')` | Migration | Insert invalid role → exception |
| `test('redundant action index dropped')` | Migration | Assert index no longer exists |
| `test('ExampleTest stubs removed')` | Smoke | Assert files don't exist |

---

## Architecture Decisions

### Decision: Cookie-only auth (no bearer tokens)

**Choice:** Remove all `createToken()` calls from AuthController.
**Alternatives considered:** Keep dual cookie+bearer for future mobile clients.
**Rationale:** Constitution Art I mandates Sanctum cookie SPA. Bearer tokens in sessionStorage are a XSS vector. No mobile client exists. Adding one later is trivial with Sanctum's `createToken()`.

### Decision: Constant-time login with unified 401

**Choice:** All auth failures return 401 `{'error': 'invalid_credentials'}`.
**Alternatives considered:** Keep 403 for non-externo users (more informative).
**Rationale:** OWASP recommends against differentiated auth errors. The audit log preserves forensic detail.

### Decision: ShouldQueue with sync fallback for audit

**Choice:** WriteAuditLog implements ShouldQueue. AuditEvent::dispatch() catches dispatch failures and writes synchronously.
**Alternatives considered:** Always synchronous (current), fire-and-forget queue (risk data loss).
**Rationale:** ADR-006 specifies queued listeners. The sync fallback prevents audit data loss when the queue is unavailable.

### Decision: Functional index over citext for email

**Choice:** `CREATE INDEX ... ON users (lower(email))` instead of changing column type to citext.
**Alternatives considered:** `ALTER COLUMN ... TYPE citext USING email::citext`.
**Rationale:** citext changes the column type on existing data (risky on production). A functional index achieves the same query performance for `WHERE lower(email) = ?` without altering the schema. Less invasive.

### Decision: Additive-only migrations

**Choice:** New migration files for all schema changes. Never modify existing migrations.
**Rationale:** Existing migrations have already run on deployed databases. Modifying them would cause migration conflicts. This is standard Laravel practice.

---

## Data Flow

### Login Flow (after PR1+PR2)

```
Browser                    Laravel
  │                           │
  ├─ GET /sanctum/csrf-cookie ─→ Set XSRF-TOKEN cookie
  │                           │
  ├─ POST /api/auth/externo/login ─→
  │  {email, password}        │ RateLimiter check (5/min)
  │  X-XSRF-TOKEN: <token>    │ CSRF verify
  │                           │ Hash::check (constant-time)
  │                           │ Auth::login() → session cookie
  │                           │ AuditEvent::dispatch()
  │  ← 200 {user, must_change_password}
  │                           │
  ├─ GET /api/auth/user ──────→
  │  Cookie: laravel_session  │ Sanctum resolves user from session
  │  ← 200 {id, name, role}  │
```

### Audit Flow (after PR3)

```
Controller                  AuditEvent              WriteAuditLog           audit_logs
  │                            │                         │                      │
  ├─ AuditEvent::dispatch() ──→│                         │                      │
  │                            ├─ Capture IP/UA ─────────→│                      │
  │                            │   from request()         │                      │
  │                            ├─ event($event) ─────────→│                      │
  │                            │                          ├─ Queue push ─────────→│
  │                            │                          │   (or sync fallback)  │
  │                            │                          │                      ├─ INSERT row
```

---

## Migration / Rollout

Each PR merges to `main` independently. No feature flags needed — changes are backward-compatible within each PR's scope.

**Rollback per PR:**
- PR1: Revert CSRF exemption removal, rate limiter, constant-time refactor
- PR2: Revert cookie-only change (re-add `createToken()`), middleware stack
- PR3: Revert FormRequests (back to inline validation), ShouldQueue
- PR4: Rollback migrations in reverse order (`php artisan migrate:rollback --step=N`)

**Post-PR4 verification:**
```bash
php artisan migrate:status   # All new migrations show as ran
php artisan test             # 151+ tests passing
npm run build                # Frontend compiles
```

---

## Open Questions

- [ ] **Queue worker availability:** PR3's ShouldQueue assumes Redis queue worker is running. The sync fallback handles absence, but should we add a health check endpoint for the queue? → Deferred to future PR.
- [ ] **Google OAuth bearer token:** `handleGoogleCallback()` L152 creates a token that's never used. Remove it in PR2. Confirm no external consumer depends on it. → Confirmed: no external consumer exists.
- [ ] **timestamptz migration scope:** Documented as deferred. If the team wants it later, create ALTER COLUMN migrations per table. Not blocking this change.
