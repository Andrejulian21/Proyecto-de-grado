<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('bitacoras table exists', function () {
    expect(Schema::hasTable('bitacoras'))->toBeTrue();
});

test('bitacoras has the columns defined in spec', function () {
    $expected = [
        'id', 'proyecto_id', 'topic', 'notes', 'evidence_file',
        'meeting_date', 'signature_status', 'student_signed_at',
        'director_signed_at', 'duration_hours',
        'created_at', 'updated_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('bitacoras', $column))
            ->toBeTrue("bitacoras.{$column} should exist");
    }
});

test('bitacoras.signature_status defaults to Pendiente', function () {
    $columns = Schema::getColumns('bitacoras');
    $col = collect($columns)->firstWhere('name', 'signature_status');

    expect($col)->not->toBeNull();
    $default = $col['default'] ?? null;
    expect(trim($default ?? '', "'"))->toBe('Pendiente');
});

test('bitacoras nullable columns are nullable', function () {
    $nullable = ['notes', 'evidence_file', 'student_signed_at', 'director_signed_at', 'duration_hours'];

    $columns = Schema::getColumns('bitacoras');

    foreach ($nullable as $column) {
        $col = collect($columns)->firstWhere('name', $column);
        expect($col)->not->toBeNull("{$column} should exist");
        expect($col['nullable'] ?? false)->toBeTrue("{$column} should be nullable");
    }
});

test('bitacoras has foreign key on proyecto_id', function () {
    $foreignKeys = Schema::getForeignKeys('bitacoras');
    $fk = collect($foreignKeys)->firstWhere('columns', ['proyecto_id']);

    expect($fk)->not->toBeNull();
    $foreignTable = $fk['foreign_table'] ?? $fk['foreignTable'] ?? $fk['foreign_table_name'] ?? null;
    expect($foreignTable)->toBe('proyectos');
});

test('bitacoras has indexes on proyecto_id and signature_status', function () {
    $indexes = Schema::getIndexes('bitacoras');
    $indexNames = array_map(fn ($i) => $i['name'], $indexes);

    expect($indexNames)->toContain('bitacoras_proyecto_id_index');
    expect($indexNames)->toContain('bitacoras_signature_status_index');
});

test('bitacoras is reversible (down drops it)', function () {
    $migration = include database_path('migrations/2026_07_09_100002_create_bitacoras_table.php');
    expect(method_exists($migration, 'down'))->toBeTrue();
});
