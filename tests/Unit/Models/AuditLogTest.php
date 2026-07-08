<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 1: AuditLog model immutability + scopes.
 *
 * RED → GREEN → TRIANGULATE → REFACTOR
 *
 * The audit_logs table is append-only by domain definition. The model
 * MUST make `update()` and `delete()` fail loudly so any code path
 * that tries to mutate a log row surfaces immediately in dev/test
 * (the DB role also denies UPDATE/DELETE, see T-010 deployment note).
 *
 * The query scopes (`forUser`, `forAction`, `betweenDates`) power the
 * AuditLogController@index filters (T-024) and the audit:archive
 * command (T-025).
 */
it('creates an audit log row via the factory/model', function () {
    $user = User::factory()->create();
    $row = AuditLog::create([
        'user_id' => $user->id,
        'action' => 'login.success',
        'description' => 'first login',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'metadata' => ['channel' => 'test'],
    ]);

    expect($row)
        ->toBeInstanceOf(AuditLog::class)
        ->and($row->user_id)->toBe($user->id)
        ->and($row->action)->toBe('login.success')
        ->and($row->description)->toBe('first login')
        ->and($row->ip_address)->toBe('127.0.0.1')
        ->and($row->metadata)->toBe(['channel' => 'test'])
        ->and($row->created_at)->not->toBeNull();
});

it('refuses to update an existing audit log row', function () {
    $row = AuditLog::create([
        'action' => 'login.success',
        'description' => 'first',
    ]);

    $row->description = 'tampered';

    expect(fn () => $row->save())
        ->toThrow(LogicException::class, 'audit_logs is append-only');
});

it('refuses to delete an existing audit log row', function () {
    $row = AuditLog::create(['action' => 'login.success']);

    expect(fn () => $row->delete())
        ->toThrow(LogicException::class, 'audit_logs is append-only');
});

it('refuses mass update via the query builder', function () {
    AuditLog::create(['action' => 'login.success']);

    expect(fn () => AuditLog::query()->update(['description' => 'x']))
        ->toThrow(LogicException::class, 'audit_logs is append-only');
});

it('refuses mass delete via the query builder', function () {
    AuditLog::create(['action' => 'login.success']);

    expect(fn () => AuditLog::query()->delete())
        ->toThrow(LogicException::class, 'audit_logs is append-only');
});

it('scopes logs by user_id', function () {
    $alice = User::factory()->create(['role' => UserRole::Coordinador->value]);
    $bob = User::factory()->create(['role' => UserRole::Estudiante->value]);

    AuditLog::create(['user_id' => $alice->id, 'action' => 'login.success']);
    AuditLog::create(['user_id' => $bob->id, 'action' => 'login.success']);
    AuditLog::create(['user_id' => null, 'action' => 'access.denied']);

    expect(AuditLog::forUser($alice->id)->count())->toBe(1)
        ->and(AuditLog::forUser($bob->id)->count())->toBe(1)
        ->and(AuditLog::forUser(null)->count())->toBe(1);
});

it('scopes logs by action', function () {
    AuditLog::create(['action' => 'login.success']);
    AuditLog::create(['action' => 'login.rejected']);
    AuditLog::create(['action' => 'logout.user_initiated']);

    expect(AuditLog::forAction('login.success')->count())->toBe(1)
        ->and(AuditLog::forAction('login.rejected')->count())->toBe(1)
        ->and(AuditLog::forAction('logout.user_initiated')->count())->toBe(1);
});

it('scopes logs by date range', function () {
    // Use raw inserts to control created_at exactly — Eloquent's
    // timestamp hook would otherwise overwrite any value passed in
    // create() with `now()`.
    $old = insertAuditLogWithTimestamp('login.success', now()->subYears(6));
    $mid = insertAuditLogWithTimestamp('login.success', now()->subDays(10));
    $new = insertAuditLogWithTimestamp('login.success', now());

    $between = AuditLog::betweenDates(now()->subDays(30), now()->addDay())->get();

    expect($between)->toHaveCount(2)
        ->and($between->pluck('id')->all())
        ->toEqual([$mid->id, $new->id]);
});

it('orders logs by created_at descending by default', function () {
    $first = insertAuditLogWithTimestamp('login.success', now()->subHours(2));
    $second = insertAuditLogWithTimestamp('login.success', now()->subHour());
    $third = insertAuditLogWithTimestamp('login.success', now());

    $ids = AuditLog::query()->ordered()->pluck('id')->all();

    expect($ids)->toEqual([$third->id, $second->id, $first->id]);
});

/**
 * Insert an audit log row with an explicit created_at value, bypassing
 * Eloquent's automatic timestamp handling so the test can pin the
 * row to a specific point in time.
 */
function insertAuditLogWithTimestamp(string $action, Carbon $createdAt): AuditLog
{
    $row = new AuditLog;
    $row->setRawAttributes([
        'action' => $action,
        'created_at' => $createdAt,
    ]);
    $row->save();

    return $row;
}
