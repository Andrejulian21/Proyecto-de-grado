<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('versiones_documento table exists', function () {
    expect(Schema::hasTable('versiones_documento'))->toBeTrue();
});

test('versiones_documento has the columns defined in spec', function () {
    $expected = [
        'id', 'entrega_id', 'version_number', 'file_path',
        'file_size', 'original_name', 'director_notes', 'uploaded_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('versiones_documento', $column))
            ->toBeTrue("versiones_documento.{$column} should exist");
    }
});

test('versiones_documento.file_size is nullable', function () {
    $columns = Schema::getColumns('versiones_documento');
    $col = collect($columns)->firstWhere('name', 'file_size');

    expect($col)->not->toBeNull();
    expect($col['nullable'] ?? false)->toBeTrue();
});

test('versiones_documento.director_notes is nullable', function () {
    $columns = Schema::getColumns('versiones_documento');
    $col = collect($columns)->firstWhere('name', 'director_notes');

    expect($col)->not->toBeNull();
    expect($col['nullable'] ?? false)->toBeTrue();
});

test('versiones_documento has foreign key on entrega_id', function () {
    $foreignKeys = Schema::getForeignKeys('versiones_documento');
    $fk = collect($foreignKeys)->firstWhere('columns', ['entrega_id']);

    expect($fk)->not->toBeNull();
    expect($fk['foreign_table'] ?? $fk['foreignTable'] ?? null)->toBe('entregas');
});

test('versiones_documento has unique on (entrega_id, version_number)', function () {
    $indexes = Schema::getIndexes('versiones_documento');
    $uniqueIndexes = array_filter($indexes, fn ($i) => ($i['unique'] ?? false));

    $match = collect($uniqueIndexes)->first(function ($idx) {
        return $idx['columns'] === ['entrega_id', 'version_number'];
    });

    expect($match)->not->toBeNull('missing unique index on (entrega_id, version_number)');
});

test('versiones_documento has indexes on entrega_id and (entrega_id, version_number)', function () {
    $indexes = Schema::getIndexes('versiones_documento');
    $indexNames = array_map(fn ($i) => $i['name'], $indexes);

    expect($indexNames)->toContain('versiones_documento_entrega_id_index');
    expect($indexNames)->toContain('versiones_documento_entrega_id_version_number_index');
});

test('versiones_documento is reversible (down drops it)', function () {
    $migration = include database_path('migrations/2026_07_09_100001_create_versiones_documento_table.php');
    expect(method_exists($migration, 'down'))->toBeTrue();
});
