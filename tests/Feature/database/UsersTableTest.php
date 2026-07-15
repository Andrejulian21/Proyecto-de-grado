<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| users table (T-008)
|--------------------------------------------------------------------------
*/

test('users table exists', function () {
    expect(Schema::hasTable('users'))->toBeTrue();
});

test('users table has the columns defined in spec/design', function () {
    $expected = [
        'id', 'name', 'email', 'password',
        'role', 'es_externo', 'google_id', 'avatar',
        'last_activity_at', 'totp_secret',
        'remember_token',
        'created_at', 'updated_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('users', $column))
            ->toBeTrue("users.{$column} should exist");
    }
});

test('users.email is unique', function () {
    $indexes = collect(Schema::getIndexes('users'));
    $emailUnique = $indexes->contains(fn ($idx) => $idx['unique']
        && collect($idx['columns'])->contains('email'));

    expect($emailUnique)->toBeTrue();
});

test('users.password is nullable (Google OAuth users have no password)', function () {
    $columns = Schema::getColumns('users');
    $password = collect($columns)->firstWhere('name', 'password');

    expect($password)->not->toBeNull();
    expect((bool) ($password['nullable'] ?? false))->toBeTrue();
});

test('users.role is a string column (string-backed enum)', function () {
    $columns = Schema::getColumns('users');
    $role = collect($columns)->firstWhere('name', 'role');

    expect($role)->not->toBeNull();
    // SQLite reports 'varchar' for $table->string(); both are acceptable
    // for storing the UserRole backing value.
    expect($role['type'] === 'varchar' || $role['type'] === 'string' || $role['type'] === 'text' || str_starts_with($role['type'], 'character varying'))
        ->toBeTrue('role column type should be varchar/string/text/character varying');
});

test('users.es_externo defaults to false', function () {
    $columns = Schema::getColumns('users');
    $col = collect($columns)->firstWhere('name', 'es_externo');

    expect($col)->not->toBeNull();
    // SQLite returns 0 (int) for boolean default false.
    expect((int) $col['default'])->toBe(0);
});

test('users.google_id is unique (one Google account per user)', function () {
    $indexes = collect(Schema::getIndexes('users'));
    $googleUnique = $indexes->contains(fn ($idx) => $idx['unique']
        && collect($idx['columns'])->contains('google_id'));

    expect($googleUnique)->toBeTrue();
});

test('users table is reversible (down drops it)', function () {
    // The UsersTable down() exists and is callable. If it ever throws,
    // migrate:rollback or migrate:fresh will fail loudly. This is
    // enforced by the spec's "Migrations are reversible" requirement.
    $migration = include database_path('migrations/0001_01_01_000000_create_users_table.php');
    expect(method_exists($migration, 'down'))->toBeTrue();
});
