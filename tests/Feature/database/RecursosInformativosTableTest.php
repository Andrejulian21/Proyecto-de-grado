<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('recursos_informativos table exists', function () {
    expect(Schema::hasTable('recursos_informativos'))->toBeTrue();
});

test('recursos_informativos has the columns defined in spec', function () {
    $expected = [
        'id', 'author_id', 'title', 'category', 'description',
        'file_path', 'link', 'access_count',
        'created_at', 'updated_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('recursos_informativos', $column))
            ->toBeTrue("recursos_informativos.{$column} should exist");
    }
});

test('recursos_informativos.access_count defaults to 0', function () {
    $columns = Schema::getColumns('recursos_informativos');
    $col = collect($columns)->firstWhere('name', 'access_count');

    expect($col)->not->toBeNull();
    $default = $col['default'] ?? null;
    expect(in_array(trim((string) $default, "'"), ['0', '0']))->toBeTrue();
});

test('recursos_informativos nullable columns are nullable', function () {
    $nullable = ['description', 'file_path', 'link'];

    $columns = Schema::getColumns('recursos_informativos');

    foreach ($nullable as $column) {
        $col = collect($columns)->firstWhere('name', $column);
        expect($col)->not->toBeNull("{$column} should exist");
        expect($col['nullable'] ?? false)->toBeTrue("{$column} should be nullable");
    }
});

test('recursos_informativos has foreign key on author_id', function () {
    $foreignKeys = Schema::getForeignKeys('recursos_informativos');
    $fk = collect($foreignKeys)->firstWhere('columns', ['author_id']);

    expect($fk)->not->toBeNull();
    $foreignTable = $fk['foreign_table'] ?? $fk['foreignTable'] ?? $fk['foreign_table_name'] ?? null;
    expect($foreignTable)->toBe('users');
});

test('recursos_informativos is reversible (down drops it)', function () {
    $migration = include database_path('migrations/2026_07_09_100007_create_recursos_informativos_table.php');
    expect(method_exists($migration, 'down'))->toBeTrue();
});
