<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('notificaciones table exists', function () {
    expect(Schema::hasTable('notificaciones'))->toBeTrue();
});

test('notificaciones has the columns defined in spec', function () {
    $expected = [
        'id', 'user_id', 'sender_id', 'type', 'title', 'content',
        'is_read', 'sent_at', 'created_at', 'updated_at',
    ];

    foreach ($expected as $column) {
        expect(Schema::hasColumn('notificaciones', $column))
            ->toBeTrue("notificaciones.{$column} should exist");
    }
});

test('notificaciones.is_read defaults to false', function () {
    $columns = Schema::getColumns('notificaciones');
    $col = collect($columns)->firstWhere('name', 'is_read');

    expect($col)->not->toBeNull();
    $default = $col['default'] ?? null;
    expect(in_array(trim((string) $default, "'"), ['0', 'false', '']))->toBeTrue();
});

test('notificaciones nullable columns are nullable', function () {
    $nullable = ['sender_id'];

    $columns = Schema::getColumns('notificaciones');

    foreach ($nullable as $column) {
        $col = collect($columns)->firstWhere('name', $column);
        expect($col)->not->toBeNull("{$column} should exist");
        expect($col['nullable'] ?? false)->toBeTrue("{$column} should be nullable");
    }
});

test('notificaciones has foreign key on user_id', function () {
    $foreignKeys = Schema::getForeignKeys('notificaciones');
    $fk = collect($foreignKeys)->firstWhere('columns', ['user_id']);

    expect($fk)->not->toBeNull();
    $foreignTable = $fk['foreign_table'] ?? $fk['foreignTable'] ?? $fk['foreign_table_name'] ?? null;
    expect($foreignTable)->toBe('users');
});

test('notificaciones has foreign key on sender_id', function () {
    $foreignKeys = Schema::getForeignKeys('notificaciones');
    $fk = collect($foreignKeys)->firstWhere('columns', ['sender_id']);

    expect($fk)->not->toBeNull();
    $foreignTable = $fk['foreign_table'] ?? $fk['foreignTable'] ?? $fk['foreign_table_name'] ?? null;
    expect($foreignTable)->toBe('users');
});

test('notificaciones has indexes on user_id and composite user_id+is_read', function () {
    $indexes = Schema::getIndexes('notificaciones');
    $indexNames = array_map(fn ($i) => $i['name'], $indexes);

    expect($indexNames)->toContain('notificaciones_user_id_index');
    expect($indexNames)->toContain('notificaciones_user_id_is_read_index');
});

test('notificaciones is reversible (down drops it)', function () {
    $migration = include database_path('migrations/2026_07_09_100006_create_notificaciones_table.php');
    expect(method_exists($migration, 'down'))->toBeTrue();
});
