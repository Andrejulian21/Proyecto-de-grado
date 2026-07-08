# Design: Módulo de Autenticación y Acceso

## Technical Approach

Laravel 11 backend with Sanctum cookie-based SPA auth. Two login paths: Google OAuth (institutional) and credentials (external evaluators). RBAC via PHP enum + Gates/Policies. Audit log via Events/Listeners, immutable. Frontend: React/Vite pages that port the existing Open Design wireframes using Tailwind + shadcn/ui. Redis for session storage. PostgreSQL for persistence.

## Architecture Decisions

| Decision | Options | Tradeoffs | Choice |
|----------|---------|-----------|--------|
| Auth driver | Sanctum cookie vs JWT | Cookie = CSRF protection built-in, simpler SPA; JWT = stateless but more client complexity | Sanctum cookie SPA mode |
| Role storage | Enum column vs pivot table | Enum = fast queries, simple; pivot = flexible but overkill for 4 roles | Enum on `users.role` |
| Session store | Redis vs DB file | Redis = fast expiry, single-session enforcement; DB = simpler infra | Redis (matches stack) |
| External evaluator auth | Same User table vs separate | Same table = unified session/audit; separate = cleaner isolation | Same table with `es_externo` flag |
| Audit log writes | Events+Listeners vs direct DB | Events = decoupled, testable; direct = simpler | Events+Listeners |
| Inactivity timeout | Middleware timestamp vs Laravel `SESSION_LIFETIME` | Middleware = precise per-request check; env = coarse but simple | Both: `SESSION_LIFETIME=60` + middleware |
| Whitelist enforcement | DB table vs env array | DB = coordinator CRUD, auditable; env = restart required | `authorized_emails` table |

## Data Flow

```
Browser ──→ Vite/React SPA
              │
              ├── /login (institucional) ──→ Google OAuth ──→ /auth/callback
              │                                                    │
              │                                            Socialite handler
              │                                                    │
              │                              Triple validation (hd + suffix + whitelist)
              │                                                    │
              │                              findOrCreate User ──→ Sanctum cookie
              │                                                    │
              │                              SingleSessionMiddleware
              │                              Audit event (UserLoggedIn)
              │
              ├── /login/externo ──→ credentials POST ──→ AuthController@loginExterno
              │                                          Validate + same session rules
              │
              └── Authenticated routes ──→ RoleMiddleware + ActivityMiddleware
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `routes/web.php` | Create | Auth web routes (OAuth redirect/callback, logout) |
| `routes/api.php` | Create | API routes: user CRUD, audit log list, session check |
| `app/Http/Controllers/Auth/AuthController.php` | Create | OAuth callback, loginExterno, logout, sessionCheck |
| `app/Http/Controllers/UserController.php` | Create | Whitelist CRUD, role assignment, profile |
| `app/Http/Controllers/AuditLogController.php` | Create | Index + filter (no CUD) |
| `app/Http/Middleware/RoleMiddleware.php` | Create | Validate role on route access |
| `app/Http/Middleware/SingleSessionMiddleware.php` | Create | Enforce one active session per user |
| `app/Http/Middleware/ActivityMiddleware.php` | Create | Update `last_activity_at`; force logout if >8h |
| `app/Models/User.php` | Create | Enum role, `es_externo`, `last_activity_at`, relations |
| `app/Models/AuditLog.php` | Create | Immutable, guarded, no soft deletes |
| `app/Models/AuthorizedEmail.php` | Create | Whitelist entry: email, role, created_by |
| `app/Enums/UserRole.php` | Create | Estudiante, Director, Coordinador, EvaluadorExterno |
| `app/Events/AuditEvent.php` | Create | Generic audit event: action, description, metadata |
| `app/Listeners/WriteAuditLog.php` | Create | Persists AuditEvent to `audit_logs` table |
| `app/Policies/UserPolicy.php` | Create | Coordinator-only whitelist/role mutations |
| `app/Providers/AuthServiceProvider.php` | Create | Gates: `view-admin`, `manage-users` |
| `database/migrations/xxxx_01_create_users_table.php` | Create | With role enum, es_externo, password nullable |
| `database/migrations/xxxx_02_create_authorized_emails_table.php` | Create | Whitelist |
| `database/migrations/xxxx_03_create_audit_logs_table.php` | Create | Immutable log |
| `config/sanctum.php` | Modify | Stateful domains, cookie settings |
| `.env.example` | Modify | `SESSION_LIFETIME=60`, `SESSION_DRIVER=redis` |
| `resources/js/pages/auth/LoginInstitucional.tsx` | Create | Port `login-institucional.html` wireframe |
| `resources/js/pages/auth/LoginExterno.tsx` | Create | Port `login-evaluadores-externos.html` wireframe |
| `resources/js/pages/coordinador/GestionUsuarios.tsx` | Create | Whitelist CRUD UI |
| `resources/js/pages/coordinador/AuditLog.tsx` | Create | Audit log viewer |
| `resources/js/hooks/useAuth.ts` | Create | Sanctum auth state, user, role, logout |
| `resources/js/components/layout/AppShell.tsx` | Create | Sidebar + header from wireframe CSS |
| `resources/css/app.css` | Modify | Import Tailwind; map design tokens to Tailwind config |
| `tailwind.config.ts` | Create | Extend with UNAB tokens (burnt orange, indigo, Open Sans) |

## Interfaces / Contracts

### Routes

| Method | URI | Controller | Middleware | Role |
|--------|-----|------------|------------|------|
| GET | `/login` | React page | guest | — |
| GET | `/login/externo` | React page | guest | — |
| GET | `/auth/redirect` | AuthController@redirectToGoogle | guest | — |
| GET | `/auth/callback` | AuthController@handleGoogleCallback | guest | — |
| POST | `/api/login/externo` | AuthController@loginExterno | guest | — |
| POST | `/api/logout` | AuthController@logout | auth | — |
| GET | `/api/user` | AuthController@sessionCheck | auth | — |
| GET | `/api/audit-logs` | AuditLogController@index | auth | Coordinador |
| GET | `/api/usuarios` | UserController@index | auth | Coordinador |
| POST | `/api/usuarios` | UserController@store | auth | Coordinador |
| PUT | `/api/usuarios/{id}` | UserController@update | auth | Coordinador |
| DELETE | `/api/usuarios/{id}` | UserController@destroy | auth | Coordinador |

### Models

**User**
- `id`, `name`, `email`, `password` (nullable), `role` (enum), `es_externo` (bool), `google_id` (nullable), `avatar` (nullable), `last_activity_at` (timestamp), `remember_token`, `timestamps`
- Index on `email` (unique), `role`, `es_externo`

**AuthorizedEmail**
- `id`, `email`, `role` (enum), `created_by` (FK → users), `created_at`
- Unique on `email`

**AuditLog**
- `id`, `user_id` (nullable), `action` (string 64), `description` (text), `ip_address`, `user_agent`, `created_at`
- Index on `user_id`, `action`, `created_at`
- No `updated_at`, no soft deletes

### Events

```php
class AuditEvent {
    public function __construct(
        public ?User $user,
        public string $action,
        public string $description,
        public array $meta = []
    ) {}
}
```

## Testing Strategy

| Layer | What | Approach |
|-------|------|----------|
| Unit | Enum, Gates, Policy methods | Pest with ` actingAs() ` |
| Integration | OAuth callback triple validation, loginExterno, single-session enforcement, middleware timeout | Pest with `RefreshDatabase`, mock Socialite |
| E2E | Login flows, role-based UI visibility, audit log viewer | Playwright: institutional login (mocked), external login, coordinator CRUD |

## Migration / Rollout

1. Feature flag `AUTH_MODULE_ENABLED` wraps new routes in `Route::middleware(['auth.module'])` during first deploy.
2. Migrations are reversible (`down()` present).
3. Initial coordinator account seeded via `DatabaseSeeder` (manual insert with hashed password).
4. Google OAuth credentials must be configured in `.env` before enabling flag.
5. Redis session driver must be running before rollout.

## Open Questions

- [ ] TOTP schema extension: should `users` get `totp_secret`/`totp_enabled` now (nullable) or migrate later? **Decision needed.**
- [ ] Should `audit_logs` include a `subject_id` + `subject_type` polymorphic reference for actions on other models? **Defer until bitácora module.**
- [ ] Google OAuth test strategy: use Socialite mock or real sandbox credentials? **Recommend mock in CI, real in staging.**

## Sequence Diagrams

### Institutional Login (Google OAuth)

```
User    Browser    Laravel          Google    DB/Redis    Audit
 |        |          |                |          |          |
 |──click Iniciar──→|                |          |          |
 |        |──redirect──→|            |          |          |
 |        |          |────OAuth redirect──────→|          |
 |        |          |                |          |          |
 |        |←────callback─────────────|          |          |
 |        |          | validate hd + email + whitelist    |
 |        |          |────query────────────────→|          |
 |        |          |←──whitelist entry───────|          |
 |        |          |                |          |          |
 |        |          | findOrCreate User        |          |
 |        |          |────write───────────────→|          |
 |        |          |                |          |          |
 |        |          | delete old sessions      |          |
 |        |          |────del───────────────→|  |          |
 |        |          | create new session       |          |
 |        |          |────write───────────────→|  |          |
 |        |          |                |          |          |
 |        |          | dispatch AuditEvent      |          |
 |        |          |──────────────────────────────→|      |
 |        |          |                |          |          |
 |        |←──Sanctum cookie + redirect────────|          |
 |        |          |                |          |          |
```

### External Evaluator Login

```
User    Browser    Laravel          DB/Redis    Audit
 |        |          |                |          |
 |──form submit────→|                |          |
 |        |──POST /api/login/externo──→|         |
 |        |          | validate credentials      |
 |        |          |────query──────→|         |
 |        |          |←──User─────────|         |
 |        |          | check es_externo=true     |
 |        |          |                |          |
 |        |          | delete old sessions       |
 |        |          |────del───────→|          |
 |        |          | create new session        |
 |        |          |────write─────→|          |
 |        |          | dispatch AuditEvent     |
 |        |          |──────────────────────────→|
 |        |←──cookie + redirect─────|          |
```

### Role Change (Coordinator)

```
Coord   Browser    Laravel          DB         Audit
 |        |          |                |          |
 |──PUT /api/usuarios/{id}────────→|            |
 |        |          | authorize via UserPolicy  |
 |        |          |                |          |
 |        |          | update role enum          |
 |        |          |────write──────→|         |
 |        |          |                |          |
 |        |          | dispatch AuditEvent       |
 |        |          |──────────────────────────→|
 |        |←──204 No Content────────|            |
```
