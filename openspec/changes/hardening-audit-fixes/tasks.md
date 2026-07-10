# Tasks: Hardening Audit Fixes

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Low-Medium

## PR 1 — Security Critical (~300)

- [ ] 1.1 RateLimiter::for('login') — AppServiceProvider — H-003
- [ ] 1.2 throttle:login on route — routes — H-003
- [ ] 1.3 loginExterno unified 401 + dummy check — AuthController — H-002
- [ ] 1.4 Remove CSRF exemption — bootstrap/app.php — H-001
- [ ] 1.5 apiFetch in LoginExterno — LoginExterno — H-001
- [ ] 1.6 apiFetch in useAuth logout — useAuth — H-001
- [ ] 1.7 Rm sessionStorage auth_token — LoginExterno — H-001
- [ ] 1.8 Tests: rate 429, unknown/internal 401, timing, CSRF 419 — H-001..003

## PR 2 — Auth Model + Session (~350)

- [ ] 2.1 Rm createToken loginExterno — AuthController — H-004
- [ ] 2.2 Rm createToken GoogleCallback — AuthController — H-004
- [ ] 2.3 Auth::logout ActivityMiddleware — ActivityMiddleware — H-005
- [ ] 2.4 activity+single_session+ensure_pwd admin — routes — H-005
- [ ] 2.5 secure cookie default — config/session.php — H-006
- [ ] 2.6 SESSION_SECURE_COOKIE=true — .env.example — H-006
- [ ] 2.7 Role guard in ProtectedRoute — app.tsx — H-006
- [ ] 2.8 allowedRoles on admin pages — pages — H-006
- [ ] 2.9 clearInterval in logout — useAuth — H-006
- [ ] 2.10 Tests: no token, activity timeout, guards, secure cookie — H-004..006

## PR 3 — Backend Quality (~450)

- [x] 3.1 5 FormRequests: LoginExterno,ChangePassword,StoreWhitelist,UpdateUser,CreateEvaluador — H-010
- [x] 3.2 Wire Forms into AuthController — H-010
- [x] 3.3 Wire Forms into UserController — H-010
- [x] 3.4 name in AuthorizedEmail::$fillable — AuthorizedEmail — H-008
- [x] 3.5 Route-model binding {id}→{user} — routes+UserController — H-010
- [x] 3.6 Role→WHITELIST_ROLES (no EvaluadorExterno) — UpdateUserRequest — H-008
- [x] 3.7 Server-side temp_password — UserController — H-007
- [x] 3.8 crypto.getRandomValues in genPassword — utils — H-007
- [x] 3.9 ShouldQueue WriteAuditLog — WriteAuditLog — H-011
- [x] 3.10 ip_address+user_agent AuditEvent — AuditEvent — H-011
- [x] 3.11 Sync fallback AuditEvent::dispatch — AuditEvent — H-011
- [x] 3.12 Wire/remove dead Gates+Policies — AuthServiceProvider — H-009
- [x] 3.13 Tests: Forms, fillable, role restrict, queue fallback, IP/UA — H-007..011

## PR 4 — DB Schema + Cleanup (~300)

- [ ] 4.1 index on authorized_emails.created_by — H-012
- [ ] 4.2 lower(email) index on users — H-012
- [ ] 4.3 lower(email) index on authorized_emails — H-012
- [ ] 4.4 softDeletes on authorized_emails — H-012
- [ ] 4.5 role CHECK constraint on users — H-013
- [ ] 4.6 Drop redundant action index on audit_logs — H-013
- [ ] 4.7 SoftDeletes on AuthorizedEmail — AuthorizedEmail — H-012
- [ ] 4.8 Delete ExampleTest stubs — tests/ — H-014
- [ ] 4.9 Collapse extractHostedDomain — AuthController — H-014
- [ ] 4.10 like→ilike UserController — UserController — H-014
- [ ] 4.11 sslmode default require — config/database.php — H-014
- [ ] 4.12 Tests: index asserts, soft-delete, case-insensitive, no stubs — H-012..014
