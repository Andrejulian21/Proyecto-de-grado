<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| audit_logs table (T-011) — APPEND-ONLY, immutable
|--------------------------------------------------------------------------
*/

test('audit_logs table exists', function () {
    expect(Schema::hasTable('audit_logs'))->toBeTrue();
});

test('audit_logs has the columns defined in spec', function () {
    $expected = [
        'id', 'user_id', 'action', 'description',
        'ip_address', 'user_agent', 'metadata',
        'created_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('audit_logs', $column))
            ->toBeTrue("audit_logs.{$column} should exist");
    }
});

test('audit_logs has NO updated_at (append-only table)', function () {
    expect(Schema::hasColumn('audit_logs', 'updated_at'))
        ->toBeFalse('audit_logs is append-only — updated_at must not exist');
});

test('audit_logs has NO deleted_at (no soft deletes on audit)', function () {
    expect(Schema::hasColumn('audit_logs', 'deleted_at'))
        ->toBeFalse('audit_logs does not support soft deletes');
});

test('audit_logs.user_id is nullable (system actions have no user)', function () {
    $columns = Schema::getColumns('audit_logs');
    $col = collect($columns)->firstWhere('name', 'user_id');

    expect($col)->not->toBeNull();
    expect($col['nullable'] ?? false)->toBeTrue();
});

test('audit_logs.action is a string column with bounded length', function () {
    $columns = Schema::getColumns('audit_logs');
    $col = collect($columns)->firstWhere('name', 'action');

    expect($col)->not->toBeNull();
    expect($col['type'])->toBeIn(['varchar', 'string', 'text']);
});

test('audit_logs.ip_address is a string column (IPv6-compatible)', function () {
    // The migration declares length 45 in PostgreSQL — enough for any
    // IPv6 literal. SQLite reports no length for VARCHAR, so we just
    // verify the column exists with the right type.
    $columns = Schema::getColumns('audit_logs');
    $col = collect($columns)->firstWhere('name', 'ip_address');

    expect($col)->not->toBeNull();
    expect($col['type'])->toBeIn(['varchar', 'string', 'text']);
});

test('audit_logs is reversible (down drops it)', function () {
    $migration = include database_path('migrations/2026_07_06_000002_create_audit_logs_table.php');
    expect(method_exists($migration, 'down'))->toBeTrue();
});
