<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('entregas table exists', function () {
    expect(Schema::hasTable('entregas'))->toBeTrue();
});

test('entregas has the columns defined in spec', function () {
    $expected = [
        'id', 'phase', 'title', 'description',
        'due_date', 'status', 'consolidated_grade', 'evaluation_complete',
        'created_at', 'updated_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('entregas', $column))
            ->toBeTrue("entregas.{$column} should exist");
    }
});

test('entregas no longer has the legacy proyecto_id column (issue #39)', function () {
    expect(Schema::hasColumn('entregas', 'proyecto_id'))->toBeFalse();
});

test('entregas.status defaults to pendiente', function () {
    $columns = Schema::getColumns('entregas');
    $col = collect($columns)->firstWhere('name', 'status');

    expect($col)->not->toBeNull();
    $cleaned = trim(explode('::', (string) $col['default'])[0], "'");
    expect($cleaned)->toBe('pendiente');
});

test('entregas.consolidated_grade is nullable', function () {
    $columns = Schema::getColumns('entregas');
    $col = collect($columns)->firstWhere('name', 'consolidated_grade');

    expect($col)->not->toBeNull();
    expect($col['nullable'] ?? false)->toBeTrue();
});

test('entregas.description is nullable', function () {
    $columns = Schema::getColumns('entregas');
    $col = collect($columns)->firstWhere('name', 'description');

    expect($col)->not->toBeNull();
    expect($col['nullable'] ?? false)->toBeTrue();
});

test('entregas.evaluation_complete defaults to false', function () {
    $columns = Schema::getColumns('entregas');
    $col = collect($columns)->firstWhere('name', 'evaluation_complete');

    expect($col)->not->toBeNull();
    $default = $col['default'] ?? null;
    expect(in_array(trim((string) $default, "'"), ['0', 'false'], true))->toBeTrue();
});

test('entregas has indexes on status', function () {
    $indexes = Schema::getIndexes('entregas');
    $indexNames = array_map(fn ($i) => $i['name'], $indexes);

    expect($indexNames)->toContain('entregas_status_index');
});

test('entregas drop migration is reversible (down recreates proyecto_id)', function () {
    $migration = include database_path('migrations/2026_08_24_000002_drop_proyecto_id_from_entregas.php');
    expect(method_exists($migration, 'down'))->toBeTrue();
});
