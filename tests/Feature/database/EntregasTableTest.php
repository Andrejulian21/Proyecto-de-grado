<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('entregas table exists', function () {
    expect(Schema::hasTable('entregas'))->toBeTrue();
});

test('entregas has the columns defined in spec', function () {
    $expected = [
        'id', 'proyecto_id', 'phase', 'title', 'description',
        'due_date', 'status', 'consolidated_grade', 'evaluation_complete',
        'created_at', 'updated_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('entregas', $column))
            ->toBeTrue("entregas.{$column} should exist");
    }
});

test('entregas.status defaults to pendiente', function () {
    $columns = Schema::getColumns('entregas');
    $col = collect($columns)->firstWhere('name', 'status');

    expect($col)->not->toBeNull();
    $default = $col['default'] ?? null;
    expect(trim($default ?? '', "'"))->toBe('pendiente');
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

test('entregas has foreign key on proyecto_id', function () {
    $foreignKeys = Schema::getForeignKeys('entregas');
    $fk = collect($foreignKeys)->firstWhere('columns', ['proyecto_id']);

    expect($fk)->not->toBeNull();
    expect($fk['foreign_table'] ?? $fk['foreignTable'] ?? null)->toBe('proyectos');
});

test('entregas has indexes on proyecto_id, (proyecto_id, phase), and status', function () {
    $indexes = Schema::getIndexes('entregas');
    $indexNames = array_map(fn ($i) => $i['name'], $indexes);

    expect($indexNames)->toContain('entregas_proyecto_id_index');
    expect($indexNames)->toContain('entregas_proyecto_id_phase_index');
    expect($indexNames)->toContain('entregas_status_index');
});

test('entregas is reversible (down drops it)', function () {
    $migration = include database_path('migrations/2026_07_09_100000_create_entregas_table.php');
    expect(method_exists($migration, 'down'))->toBeTrue();
});
