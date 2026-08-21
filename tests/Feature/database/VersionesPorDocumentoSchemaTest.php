<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('versiones_documento unique is per document and project not per entrega', function () {
    $indexes = Schema::getIndexes('versiones_documento');
    $indexNames = array_map(fn (array $i): string => $i['name'], $indexes);

    expect($indexNames)->not->toContain('versiones_documento_entrega_id_version_number_unique');
    expect($indexNames)->toContain('versiones_documento_ep_archivo_version_unique');
});

test('versiones_documento keeps uploaded_at and director_notes', function () {
    expect(Schema::hasColumn('versiones_documento', 'uploaded_at'))->toBeTrue();
    expect(Schema::hasColumn('versiones_documento', 'director_notes'))->toBeTrue();
    expect(Schema::hasColumn('versiones_documento', 'archivo_requerido_id'))->toBeTrue();
});
