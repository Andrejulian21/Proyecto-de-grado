<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Issue #12: DB-level immutability trigger on audit_logs
|--------------------------------------------------------------------------
|
| These tests verify the PostgreSQL BEFORE UPDATE OR DELETE trigger
| installed by migration `2026_07_10_000001_add_audit_logs_immutable_trigger`
| blocks any attempt to mutate or delete an existing row in
| `audit_logs` at the database level.
|
| IMPORTANT: The trigger is PostgreSQL-only. Tests run against
| SQLite in-memory for speed, so these tests self-skip on SQLite
| (the migration is a no-op on SQLite; the trigger check is
| exercised in CI against the real PostgreSQL service).
|
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    $driver = DB::connection()->getDriverName();

    if ($driver === 'sqlite') {
        $this->markTestSkipped(
            'audit_logs immutability trigger is PostgreSQL-only. '.
            'Run the full test suite against PostgreSQL to exercise this guard.'
        );
    }
});

it('rejects a raw UPDATE on audit_logs at the database level', function () {
    // Insert a real row via Eloquent.
    DB::table('audit_logs')->insert([
        'action' => 'login.success',
        'description' => 'immutable row',
        'created_at' => now(),
    ]);

    // Raw UPDATE must raise an exception (the trigger fires before the row is written).
    expect(function () {
        DB::statement("UPDATE audit_logs SET description = 'tampered' WHERE id = (SELECT id FROM audit_logs LIMIT 1)");
    })->toThrow(Exception::class);
});

it('rejects a raw DELETE on audit_logs at the database level', function () {
    DB::table('audit_logs')->insert([
        'action' => 'login.success',
        'description' => 'immutable row',
        'created_at' => now(),
    ]);

    expect(function () {
        DB::statement("DELETE FROM audit_logs WHERE id = (SELECT id FROM audit_logs LIMIT 1)");
    })->toThrow(Exception::class);
});

it('still allows INSERT and SELECT on audit_logs when the trigger is active', function () {
    // INSERT must succeed.
    $id = DB::table('audit_logs')->insertGetId([
        'action' => 'login.success',
        'description' => 'still allowed',
        'created_at' => now(),
    ]);

    expect($id)->toBeInt();

    // SELECT must succeed and return the row.
    $row = DB::table('audit_logs')->where('id', $id)->first();
    expect($row)->not->toBeNull()
        ->and($row->description)->toBe('still allowed');
});
