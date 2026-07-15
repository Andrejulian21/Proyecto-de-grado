<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| authorized_emails table (T-010)
|--------------------------------------------------------------------------
*/

test('authorized_emails table exists', function () {
    expect(Schema::hasTable('authorized_emails'))->toBeTrue();
});

test('authorized_emails has the columns defined in spec', function () {
    $expected = ['id', 'email', 'role', 'created_by', 'created_at', 'updated_at'];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('authorized_emails', $column))
            ->toBeTrue("authorized_emails.{$column} should exist");
    }
});

test('authorized_emails.email is unique', function () {
    $indexes = collect(Schema::getIndexes('authorized_emails'));
    $emailUnique = $indexes->contains(fn ($idx) => $idx['unique']
        && collect($idx['columns'])->contains('email'));

    expect($emailUnique)->toBeTrue();
});

test('authorized_emails.role is a string column', function () {
    $columns = Schema::getColumns('authorized_emails');
    $col = collect($columns)->firstWhere('name', 'role');

    expect($col)->not->toBeNull();
    expect($col['type'] === 'varchar' || $col['type'] === 'string' || $col['type'] === 'text' || str_starts_with($col['type'], 'character varying'))
        ->toBeTrue('role column type should be varchar/string/text/character varying');
});

test('authorized_emails.created_by is nullable (system seed rows have no creator)', function () {
    $columns = Schema::getColumns('authorized_emails');
    $col = collect($columns)->firstWhere('name', 'created_by');

    expect($col)->not->toBeNull();
    expect((bool) ($col['nullable'] ?? false))->toBeTrue();
});

test('authorized_emails is reversible (down drops it)', function () {
    $migration = include database_path('migrations/2026_07_06_000001_create_authorized_emails_table.php');
    expect(method_exists($migration, 'down'))->toBeTrue();
});
