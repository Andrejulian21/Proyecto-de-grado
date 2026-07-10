# Design: Fix Critical Issues

**Change**: `fix-critical-issues` | **Status**: Design (v2 — gaps fixed)
**Stack**: Laravel 11 + PostgreSQL 16 + Pest PHP + GitHub Actions

---

## Technical Approach

Five independent security/reliability fixes applied as atomic commits to `master`. Each fix addresses a GitHub issue with defined acceptance criteria. The fixes touch DB-level enforcement (PostgreSQL triggers), session management (driver switch + middleware), credential lockout (sliding window), and CI infrastructure (GitHub Actions).

Key dependency chain: #12 (trigger) must land before #11 (archive), because the archive command must be updated to bypass the new trigger.

---

## Architecture Decisions

| # | Decision | Alternatives Considered | Rationale |
|---|----------|------------------------|-----------|
| D1 | PostgreSQL `BEFORE UPDATE OR DELETE` trigger for `audit_logs` immutability | `REVOKE` privileges on app role; application-only guard (current) | Trigger catches raw SQL from any source (compromised creds, migration mistakes). `REVOKE` requires separate DB roles which adds deployment complexity. Current app-level guard (`AuditLog::delete()` throws) is trivially bypassed by `DB::table()`. |
| D2 | `SET session_replication_role = replica` to bypass trigger for archive | `DB::table()` raw delete; disable trigger per-statement; drop+recreate trigger | `DB::table()` bypasses the **model** guard but NOT the PostgreSQL trigger — the trigger fires at DB level regardless of query origin. `session_replication_role = replica` disables all triggers for the current session only, is transaction-scoped, and is the standard PostgreSQL mechanism for privileged maintenance operations. Requires `superuser` or `REPLICATION` attribute on the DB role. |
| D3 | Switch `SESSION_DRIVER` to `database` for single-session enforcement | Keep `file` driver; keep `redis` (from `.env.example`) | File driver stores sessions as filesystem files — cannot query by `user_id` to delete specific user sessions. Redis driver could work but adds infrastructure dependency for this feature. `database` driver stores sessions in a `sessions` table with `user_id` column, enabling `DB::table('sessions')->where('user_id', $id)->delete()`. Laravel's `config/session.php` already defaults to `database`. |
| D4 | `last_failed_at` timestamp + `failed_attempts` counter on `users` for sliding window | Separate `login_attempts` table; Redis-based rate limiting | Existing schema already has `failed_attempts` and `locked_until` columns (migration `2026_07_06_000004`). Adding one nullable timestamp column (`last_failed_at`) is minimal. If `now() - last_failed_at > windowMinutes`, reset counter before checking threshold. No extra table, no Redis dependency. |
| D5 | Modify `AuthController::loginExterno()` for lockout audit action name | Only change `User::isLocked()` | The spec requires audit action `login.locked` (not `account_locked`). The controller hardcodes the action string at line 266. Both `isLocked()` logic AND the controller's audit dispatch must change. |

---

## Data Flow

### #12 + #11 — Audit immutability + archive bypass

```
Normal app flow:
  INSERT INTO audit_logs → succeeds (trigger allows INSERT)
  UPDATE audit_logs SET ... → TRIGGER raises EXCEPTION, operation rejected
  DELETE FROM audit_logs WHERE ... → TRIGGER raises EXCEPTION, operation rejected

Archive flow (privileged):
  php artisan audit:archive
    → BEGIN transaction
    → SET LOCAL session_replication_role = replica  ← bypasses trigger
    → INSERT INTO audit_logs_archive SELECT ... WHERE created_at < cutoff
    → DELETE FROM audit_logs WHERE id IN (archived ids)
    → SET LOCAL session_replication_role = DEFAULT  ← restore
    → COMMIT
```

### #10 — Single-session with database driver

```
Login (Google OAuth or external credentials):
  AuthController::handleGoogleCallback() / loginExterno()
    → DB::table('sessions')->where('user_id', $user->id)->delete()  ← kill prior sessions
    → Auth::login($user)  ← creates new session row
    → AuditEvent: 'logout.session_invalidated'

Subsequent request from old device:
  Cookie carries old session ID
    → SessionMiddleware loads session from DB → not found
    → Sanctum returns 401
```

### #13 — Sliding window lockout

```
POST /api/login/externo
  → User::where('email', $email)->first()
  → $policy = app(LoginAttemptPolicy::class)  // maxAttempts=3, windowMinutes=10
  → Check lockout:
      if $user->failed_attempts >= maxAttempts
         AND $user->last_failed_at !== null
         AND now()->diffInMinutes($user->last_failed_at) < windowMinutes
        → 423 Locked, audit action: 'login.locked'
      else if $user->last_failed_at is expired (> windowMinutes ago)
        → Reset: failed_attempts = 0, last_failed_at = null
  → Validate credentials:
      fail → registerFailedLogin(): increment failed_attempts, set last_failed_at = now()
             if failed_attempts >= maxAttempts → set locked_until = now() + lockMinutes
      success → clearFailedLogin(): reset failed_attempts = 0, locked_until = null
```

---

## File Changes

| Issue | File | Action | Description |
|-------|------|--------|-------------|
| #12 | `database/migrations/2026_07_10_000001_add_audit_logs_immutable_trigger.php` | Create | PL/pgSQL function + `BEFORE UPDATE OR DELETE` trigger on `audit_logs` that raises exception |
| #11 | `database/migrations/2026_07_08_000001_create_audit_logs_archive_table.php` | **Exists** | Archive table already created by this migration — no action needed |
| #11 | `app/Console/Commands/AuditArchive.php` | Modify | Replace `$log->delete()` (model, throws LogicException) with transaction-scoped `SET LOCAL session_replication_role = replica` + raw `DB::table('audit_logs')->whereIn('id', $ids)->delete()` |
| #11 | `routes/console.php` | Modify | Add `Schedule::command('audit:archive')->daily();` alongside existing `inspire` command |
| #10 | `.env` | Modify | Change `SESSION_DRIVER=file` → `SESSION_DRIVER=database` |
| #10 | `.env.example` | Modify | Change `SESSION_DRIVER=redis` → `SESSION_DRIVER=database` |
| #10 | `database/migrations/2026_07_10_000002_create_sessions_table.php` | Create | Laravel `sessions` table (run `php artisan session:table` output) — required by `database` driver |
| #10 | `app/Http/Middleware/SingleSessionMiddleware.php` | Modify | Replace no-op: check if current session ID exists in `sessions` table; return 401 if not found (for cookie-authenticated requests) |
| #10 | `app/Http/Controllers/Auth/AuthController.php` | Modify | Add `DB::table('sessions')->where('user_id', $user->id)->delete()` before `Auth::login()` in both `handleGoogleCallback()` (line ~142) and `loginExterno()` (line ~288) |
| #10 | `tests/Feature/Auth/SingleSessionOnLoginTest.php` | Modify | Test cookie session invalidation: login twice, verify first session returns 401 |
| #13 | `database/migrations/2026_07_10_000003_add_last_failed_at_to_users.php` | Create | Add `last_failed_at` nullable timestamp column to `users` table |
| #13 | `app/Models/User.php` | Modify | Add `last_failed_at` to `$fillable` and `casts` (datetime). Update `isLocked()` to incorporate sliding window: check `failed_attempts >= max AND last_failed_at within windowMinutes`. Update `registerFailedLogin()` to set `last_failed_at = now()`. |
| #13 | `app/Http/Controllers/Auth/AuthController.php` | Modify | Change audit action from `'account_locked'` → `'login.locked'` at line 266. Change HTTP response key from `'account_locked'` to `'locked'` (or keep error code 423 with correct audit). |
| #13 | `app/Auth/LoginAttemptPolicy.php` | No change | Already exposes `windowMinutes()`, `maxAttempts()`, `lockMinutes()` — the policy is correct, the consumer (`User::isLocked()` / `registerFailedLogin()`) was not using the window. |
| #13 | `tests/Unit/Models/UserExternalAuthTest.php` | Modify | Test: 3 fails within 10 min → locked; 2 fails + 15 min gap + 1 fail → not locked (counter reset) |
| #9 | `.github/workflows/ci.yml` | Create | Jobs: pest (`vendor/bin/pest`), pint (`vendor/bin/pint --test`), frontend (`npm ci && npm run build`). Trigger on push/PR to `master`. |

---

## Interfaces / Contracts

### New migration: audit_logs trigger

```sql
CREATE OR REPLACE FUNCTION audit_logs_prevent_mutation()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'audit_logs is append-only; UPDATE/DELETE is not allowed'
        USING ERRCODE = 'P0001';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_audit_logs_immutable
    BEFORE UPDATE OR DELETE ON audit_logs
    FOR EACH ROW EXECUTE FUNCTION audit_logs_prevent_mutation();
```

### Archive bypass pattern (AuditArchive command)

```php
DB::transaction(function () use ($ids) {
    DB::statement("SET LOCAL session_replication_role = replica");
    DB::table('audit_logs_archive')->insert(
        DB::table('audit_logs')->whereIn('id', $ids)->get()->toArray()
    );
    DB::table('audit_logs')->whereIn('id', $ids)->delete();
    DB::statement("SET LOCAL session_replication_role = DEFAULT");
});
```

### User model sliding window contract

```php
// isLocked() — now window-aware
public function isLocked(): bool
{
    $policy = app(LoginAttemptPolicy::class);

    // Window expired → not locked regardless of counter
    if ($this->last_failed_at === null
        || now()->diffInMinutes($this->last_failed_at) >= $policy->windowMinutes()
    ) {
        return false;
    }

    // Within window: locked if attempts exhausted OR locked_until is future
    return $this->failed_attempts >= $policy->maxAttempts()
        || ($this->locked_until !== null && $this->locked_until->isFuture());
}
```

---

## Testing Strategy

| Issue | Layer | What to Test | Approach |
|-------|-------|-------------|----------|
| #12 | Feature | Raw `UPDATE`/`DELETE` on `audit_logs` throws PDOException | `DB::statement('UPDATE audit_logs SET ...')` wrapped in `expectException(\PDO\Exception::class)` |
| #12 | Feature | `INSERT` and `SELECT` still work | Normal Eloquent create + query |
| #11 | Feature | Archive moves old entries, preserves recent | Factory-create old + recent `AuditLog`, run `audit:archive`, assert counts in both tables |
| #11 | Feature | Schedule registration | `Artisan::call('schedule:list')` contains `audit:archive` |
| #10 | Feature | Second login invalidates first cookie session | Login user (session A), login same user again (session B), request with session A cookie → 401 |
| #10 | Feature | External login invalidates prior cookie session | Same as above via `POST /api/auth/externo/login` |
| #13 | Unit | 3 fails within 10 min → `isLocked()` returns true | Freeze time with `Carbon::setTestNow()`, call `registerFailedLogin()` 3x |
| #13 | Unit | 2 fails + 15 min gap + 1 fail → `isLocked()` returns false | Advance time between attempts, verify counter resets |
| #13 | Unit | Audit action is `login.locked` on lockout | Assert `AuditLog::latest()->action === 'login.locked'` |
| #9 | CI | Pipeline runs on push to master | Verify `.github/workflows/ci.yml` syntax + local `act` dry-run |

---

## Migration / Rollout

**Execution order** (respects dependencies):

1. `fix(#12): enforce audit_logs immutability at DB level` — trigger migration
2. `fix(#11): make AuditArchive bypass trigger + register schedule` — command fix + schedule
3. `fix(#10): switch to database session driver + enforce single-session on login` — `.env` + sessions migration + controller + middleware
4. `fix(#13): implement sliding window for login lockout` — migration + User model + controller audit action
5. `ci(#9): add GitHub Actions pipeline` — workflow file

Each commit is independently revertable. No feature flags needed — all fixes are correctness/security fixes, not behavioral changes behind toggles.

**Prerequisite**: The PostgreSQL role used by the app must have `REPLICATION` attribute (or be superuser) for the archive bypass to work. Verify with `SELECT rolname, rolreplication FROM pg_roles WHERE rolname = current_user;`.

---

## Open Questions

- [ ] Does the production PostgreSQL role have `REPLICATION` attribute? If not, the DBA must grant it before deploying #11.
- [ ] Should `session_replication_role` bypass be scoped to only the `trg_audit_logs_immutable` trigger (using `ENABLE ALWAYS TRIGGER` / `ENABLE REPLICA TRIGGER`) instead of all triggers? Currently the archive command disables ALL triggers in the session — acceptable since it runs in an isolated artisan command.
