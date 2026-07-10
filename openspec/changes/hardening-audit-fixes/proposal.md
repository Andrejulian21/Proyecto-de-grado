# Proposal: Hardening Audit Fixes

## Intent

Address 14 security and quality audit findings by hardening authentication, session management, backend controllers, and database schema. Unify auth to Sanctum cookie-only SPA mode, eliminate bearer token leakage, and enforce existing ADRs (003, 005, 006, 010).

## Scope

### In Scope
- **PR1 — Security Critical**: Fix CSRF bypass in LoginExterno, eliminate user enumeration via timing, add per-endpoint rate limiting
- **PR2 — Auth Model + Session**: Cookie-only Sanctum SPA, fix ActivityMiddleware logout, enforce single_session on admin routes, SPA cleanup (secure cookies, role guard, logout polling)
- **PR3 — Backend Quality**: Server-side secure password generation, fix AuthorizedEmail fillable, restrict role updates in updateUsuario, wire Gates/call-sites, extract FormRequests, implement ShouldQueue with sync fallback for audit
- **PR4 — DB Schema + Cleanup**: Add FK index, case-insensitive email unique, soft deletes on AuthorizedEmail, timestamptz consistency, remove stubs and dead code

### Out of Scope
- New features or UI redesign
- OAuth provider changes beyond token cleanup
- Full test suite rewrite (maintain 151+ baseline)

## Capabilities

### New Capabilities
- None

### Modified Capabilities
- None

> This change is pure hardening/refactoring. No spec-level behavioral changes; existing capabilities remain intact.

## Approach

TDD-first per AGENTS.md. Stack PRs to `main`. Extract FormRequests to slim controllers under 500 lines (Art VII). Unify auth to Sanctum cookie SPA per constitution Art I and ADR-003. Add `ShouldQueue` to `WriteAuditLog` with synchronous fallback (ADR-006). Migrations are additive-only.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `AuthController.php` | Modified | FormRequests, constant-time login, rate limiters |
| `UserController.php` | Modified | FormRequests, route-model binding, role gates |
| `ActivityMiddleware` | Modified | Call `Auth::logout()` on timeout |
| `LoginExterno.tsx` | Modified | Use `apiFetch()`, remove bearer token |
| `AuthorizedEmail` | Modified | SoftDeletes, fix `$fillable` |
| `database/migrations` | Modified | Indexes, CITEXT, timestamptz, `deleted_at` |
| `bootstrap/app.php` | Modified | Remove CSRF exemption, register limiters |
| `config/session.php` | Modified | `secure=true`, `same_site=strict` |
| `tests/` | Modified | Remove ExampleTest stubs |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| PR2 auth regression blocks users | Med | Staged rollout; keep revert script ready |
| Migration conflict on existing data | Low | Additive migrations; backup before PR4 |
| Queue fallback hides infra issue | Low | Alert if fallback rate exceeds 5% |

## Rollback Plan

Revert each PR by reversing its commits in stack order:
- **PR1**: Revert `fix/csrf-enumeration-rate-limit` → restore CSRF exemption, raw fetch
- **PR2**: Revert `fix/auth-model-session` → restore bearer paths, session defaults
- **PR3**: Revert `fix/backend-quality` → restore controller monoliths, inline audit
- **PR4**: Revert `fix/db-schema-cleanup` → rollback migrations in reverse, restore stubs

## Dependencies

- Redis/queue worker available for PR3 (sync fallback handles absence)

## Success Criteria

- [ ] **PR1**: CSRF token on every auth request; login response delta <50ms valid vs invalid; 429 after 5 attempts
- [ ] **PR2**: Zero bearer token references in frontend; `Auth::logout()` in `ActivityMiddleware`; admin routes under `activity` + `single_session`; logout cancels poll
- [ ] **PR3**: Server-side password generation; `name` preserved in `AuthorizedEmail`; `updateUsuario` restricts role; all controllers <500 lines; `WriteAuditLog` implements `ShouldQueue` with sync fallback
- [ ] **PR4**: `created_by` indexed; email unique case-insensitive; `AuthorizedEmail` has `deleted_at`; no `ExampleTest` stubs; 151+ tests passing
