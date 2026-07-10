# Tasks: fix-critical-issues

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~415 (5 atomic commits) |
| 400-line budget risk | Low (per-commit avg ~83 lines) |
| Chained PRs recommended | No (direct commits to master) |
| Suggested split | Not needed — each commit is autonomous |
| Delivery strategy | direct-commits |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

---

**Dependency chain**: #12 → #11 → #10 → #13 → #9
**Strict TDD**: Each commit = code fix + test(s) validating acceptance criteria

---

## Issue #12 — DB-level immutability for audit_logs

- [ ] **12.1** Create migration `$timestamp_add_audit_logs_immutable_trigger.php` with PL/pgSQL function `audit_logs_prevent_mutation()` raising EXCEPTION + `BEFORE UPDATE OR DELETE` trigger on `audit_logs` (`database/migrations/`)
- [ ] **12.2** Write `tests/Feature/Admin/AuditLogImmutabilityTest.php`: insert row, assert raw UPDATE throws, assert raw DELETE throws, assert INSERT/SELECT still work

## Issue #11 — AuditArchive bypass trigger + schedule

- [ ] **11.1** Modify `app/Console/Commands/AuditArchive.php`: replace `$log->delete()` with `DB::table()` raw delete inside `SET LOCAL session_replication_role = replica` transaction (bypasses trigger from #12)
- [ ] **11.2** Add `Schedule::command('audit:archive')->daily();` in `routes/console.php`
- [ ] **11.3** Test: seed old row (>5y) and recent row, run `audit:archive`, assert old row moved to archive, recent row stays, schedule listed

## Issue #10 — Single-session with cookie

- [ ] **10.1** Change `.env` and `.env.example` `SESSION_DRIVER` to `database`; verify/create `sessions` table migration (`php artisan session:table`)
- [ ] **10.2** In `app/Http/Controllers/Auth/AuthController.php`: add `DB::table('sessions')->where('user_id', $id)->delete()` before `Auth::login()` in both `handleGoogleCallback()` and `loginExterno()`
- [ ] **10.3** Test: login user (capture cookie), login again, assert first cookie returns 401

## Issue #13 — Lockout sliding window

- [ ] **13.1** Create migration adding nullable `last_failed_at` timestamp column (with index) to `users` table
- [ ] **13.2** Modify `app/Models/User.php`: add `last_failed_at` cast; update `isLocked()` to check `failed_attempts >= max AND last_failed_at within windowMinutes`; update `registerFailedLogin()` to reset counter if window expired, then set `last_failed_at = now()`
- [ ] **13.3** In `AuthController`: change audit action from `account_locked` to `login.locked`
- [ ] **13.4** Test: 3 fails within window → 423; 2 fails + 15-min gap + 1 fail → NOT locked; verify audit action

## Issue #9 — CI Pipeline

- [x] **9.1** Create `.github/workflows/ci.yml`: triggers on push/PR to master; services: postgres:16; steps: PHP setup → composer install → migrate → pest → pint --test → Node setup → npm ci → npm run build

---

## Summary

| Metric | Value |
|--------|-------|
| Total tasks | 13 |
| Issues covered | 5 (#12, #11, #10, #13, #9) |
| Files created | 3 (2 migrations, 1 workflow) + 4 test files |
| Files modified | 6 (.env, .env.example, AuditArchive.php, routes/console.php, AuthController.php, User.php) |
| Estimated total lines | ~415 |
| Per-commit avg | ~83 lines |
| Strict TDD | Yes (config.yaml) |
| Execution order | #12 → #11 → #10 → #13 → #9 |
