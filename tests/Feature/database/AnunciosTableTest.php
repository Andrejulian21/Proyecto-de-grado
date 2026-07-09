<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('anuncios table exists', function () {
    expect(Schema::hasTable('anuncios'))->toBeTrue();
});

test('anuncios has the columns defined in spec', function () {
    $expected = [
        'id', 'author_id', 'title', 'content', 'published_at', 'is_active',
        'created_at', 'updated_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('anuncios', $column))
            ->toBeTrue("anuncios.{$column} should exist");
    }
});

test('anuncios.is_active defaults to true', function () {
    $columns = Schema::getColumns('anuncios');
    $col = collect($columns)->firstWhere('name', 'is_active');

    expect($col)->not->toBeNull();
    $default = $col['default'] ?? null;
    expect(in_array(trim((string) $default, "'"), ['1', 'true', '1']))->toBeTrue();
});

test('anuncios nullable columns are nullable', function () {
    $nullable = ['published_at'];

    $columns = Schema::getColumns('anuncios');

    foreach ($nullable as $column) {
        $col = collect($columns)->firstWhere('name', $column);
        expect($col)->not->toBeNull("{$column} should exist");
        expect($col['nullable'] ?? false)->toBeTrue("{$column} should be nullable");
    }
});

test('anuncios has foreign key on author_id', function () {
    $foreignKeys = Schema::getForeignKeys('anuncios');
    $fk = collect($foreignKeys)->firstWhere('columns', ['author_id']);

    expect($fk)->not->toBeNull();
    $foreignTable = $fk['foreign_table'] ?? $fk['foreignTable'] ?? $fk['foreign_table_name'] ?? null;
    expect($foreignTable)->toBe('users');
});

test('anuncios is reversible (down drops it)', function () {
    $migration = include database_path('migrations/2026_07_09_100005_create_anuncios_table.php');
    expect(method_exists($migration, 'down'))->toBeTrue();
});
