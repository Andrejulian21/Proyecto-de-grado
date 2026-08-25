<?php

declare(strict_types=1);

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Issue #11: AuditArchive bypass + schedule (T-025)
|--------------------------------------------------------------------------
|
| The `audit:archive` command moves audit log entries older than
| 5 years from `audit_logs` to `audit_logs_archive`. The previous
| implementation called `$log->delete()` on the AuditLog Eloquent
| model, which is blocked at the application level (LogicException)
| AND (after #12) at the database level by a PostgreSQL trigger.
|
| The fix uses `DB::table('audit_logs')->delete()` inside a
| transaction that issues `SET LOCAL session_replication_role =
| replica` — the standard PostgreSQL mechanism for privileged
| maintenance. On SQLite the `SET LOCAL` is a no-op but the raw
| delete bypasses the Eloquent guard so the test works on either
| driver.
|
| The schedule registration is verified through `Schedule::command`
| introspection and `Artisan::call('schedule:list')`.
*/

it('moves audit entries older than 5 years to audit_logs_archive', function () {
    Carbon::setTestNow('2026-07-10 12:00:00');

    // Use raw insert because `created_at` is intentionally NOT in
    // AuditLog's $fillable (the model is append-only via mass
    // assignment). The column has DEFAULT CURRENT_TIMESTAMP, so an
    // Eloquent create() with `created_at` in the payload would
    // silently drop the timestamp and use the test's "now" — which
    // would put both rows at the same instant and break the test's
    // "old vs recent" distinction.
    $old = DB::table('audit_logs')->insertGetId([
        'action' => 'login.success',
        'description' => 'ancient login',
        'created_at' => now()->subYears(6),
    ]);

    $recent = DB::table('audit_logs')->insertGetId([
        'action' => 'login.success',
        'description' => 'recent login',
        'created_at' => now()->subDays(7),
    ]);

    $exitCode = Artisan::call('audit:archive');

    expect($exitCode)->toBe(0);

    // Old row no longer in audit_logs, present in audit_logs_archive.
    expect(AuditLog::query()->where('id', $old)->exists())->toBeFalse();
    expect(DB::table('audit_logs_archive')->where('id', $old)->exists())->toBeTrue();

    // Recent row still in audit_logs, not in archive.
    expect(AuditLog::query()->where('id', $recent)->exists())->toBeTrue();
    expect(DB::table('audit_logs_archive')->where('id', $recent)->exists())->toBeFalse();

    Carbon::setTestNow();
});

it('reports no entries to archive when nothing is old enough', function () {
    Carbon::setTestNow('2026-07-10 12:00:00');

    DB::table('audit_logs')->insert([
        'action' => 'login.success',
        'description' => 'only recent',
        'created_at' => now()->subDays(2),
    ]);

    expect(Artisan::call('audit:archive'))->toBe(0)
        ->and(AuditLog::query()->count())->toBe(1)
        ->and(DB::table('audit_logs_archive')->count())->toBe(0);

    Carbon::setTestNow();
});

it('does not crash when AuditLog model delete guard is in effect', function () {
    // Sanity check: the archive command must use the query builder
    // (`DB::table`), NOT the Eloquent model — otherwise the
    // AuditLog::delete() guard throws LogicException and breaks the
    // batch (see issue #11 motivation).
    Carbon::setTestNow('2026-07-10 12:00:00');

    DB::table('audit_logs')->insert([
        'action' => 'login.success',
        'description' => 'old entry',
        'created_at' => now()->subYears(10),
    ]);

    // Should NOT throw LogicException("audit_logs is append-only...").
    expect(Artisan::call('audit:archive'))->toBe(0);

    Carbon::setTestNow();
});

it('registers audit:archive on the daily schedule', function () {
    $events = Schedule::events();

    $found = collect($events)->first(function ($event) {
        return str_contains($event->command, 'audit:archive');
    });

    expect($found)->not->toBeNull();
    // The expression for ->daily() in Laravel 11 is `0 0 * * *`.
    expect($found->expression)->toBe('0 0 * * *');
});

it('archives all eligible rows when they exceed the batch size', function () {
    // Issue #50 regression: chunk() + delete inside the callback shifts
    // the OFFSET and skips ~1/3 of rows (1500 rows / 500 batch = 3 pages,
    // but the third page's offset lands beyond the shrunken result set).
    // chunkById() pages with `WHERE id > ?` and must archive every row.
    Carbon::setTestNow('2026-07-10 12:00:00');

    DB::table('audit_logs')->insert(
        collect(range(1, 1500))->map(fn () => [
            'action' => 'login.success',
            'description' => 'bulk old entry',
            'created_at' => now()->subYears(6),
        ])->all()
    );

    $exitCode = Artisan::call('audit:archive');

    expect($exitCode)->toBe(0);

    // Acceptance criteria #1: no eligible rows remain.
    expect(DB::table('audit_logs')->count())->toBe(0);
    // Acceptance criteria #3: total rows before and after is identical.
    expect(DB::table('audit_logs_archive')->count())->toBe(1500);
    // Acceptance criteria #2: reported count matches reality.
    expect(Artisan::output())->toContain('Done. 1500 entries archived.');

    Carbon::setTestNow();
});
