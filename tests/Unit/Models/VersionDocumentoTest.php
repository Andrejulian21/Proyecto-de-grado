<?php

declare(strict_types=1);

use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\VersionDocumento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);
    $proyecto = Proyecto::create([
        'title' => 'Proyecto Test',
        'semester_id' => $semestre->id,
    ]);
    $this->entrega = Entrega::create([
        'proyecto_id' => $proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega Test',
        'due_date' => '2026-03-01',
    ]);
});

test('VersionDocumento model exists and extends Model', function () {
    $version = new VersionDocumento;
    expect($version)->toBeInstanceOf(Model::class);
});

test('VersionDocumento fillable fields create correctly', function () {
    $version = VersionDocumento::create([
        'entrega_id' => $this->entrega->id,
        'version_number' => 1,
        'file_path' => 'entregas/test.pdf',
        'file_size' => 1024,
        'original_name' => 'documento.pdf',
    ]);

    expect($version->original_name)->toBe('documento.pdf');
    expect($version->version_number)->toBe(1);
    expect($version->file_size)->toBe(1024);
});

test('VersionDocumento belongs to Entrega', function () {
    $version = VersionDocumento::create([
        'entrega_id' => $this->entrega->id,
        'version_number' => 1,
        'file_path' => 'entregas/test.pdf',
        'original_name' => 'doc.pdf',
    ]);

    expect($version->entrega)->toBeInstanceOf(Entrega::class);
    expect($version->entrega->id)->toBe($this->entrega->id);
});

test('VersionDocumento scope ultima orders by version_number desc', function () {
    VersionDocumento::create([
        'entrega_id' => $this->entrega->id,
        'version_number' => 1,
        'file_path' => 'v1.pdf',
        'original_name' => 'v1.pdf',
    ]);
    VersionDocumento::create([
        'entrega_id' => $this->entrega->id,
        'version_number' => 2,
        'file_path' => 'v2.pdf',
        'original_name' => 'v2.pdf',
    ]);

    $latest = VersionDocumento::ultima()->get();
    expect($latest->first()->version_number)->toBe(2);
    expect($latest->last()->version_number)->toBe(1);
});

test('VersionDocumento unique constraint is per document and project', function () {
    $pivot = EntregaProyecto::create([
        'entrega_id' => $this->entrega->id,
        'proyecto_id' => $this->entrega->proyecto_id,
    ]);

    VersionDocumento::create([
        'entrega_id' => $this->entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'archivo_requerido_id' => 'planteamiento',
        'version_number' => 1,
        'file_path' => 'v1.pdf',
        'original_name' => 'v1.pdf',
    ]);

    VersionDocumento::create([
        'entrega_id' => $this->entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'archivo_requerido_id' => 'objetivos',
        'version_number' => 1,
        'file_path' => 'objetivos-v1.pdf',
        'original_name' => 'objetivos-v1.pdf',
    ]);

    expect(VersionDocumento::where('entrega_id', $this->entrega->id)->count())->toBe(2);

    $this->expectException(QueryException::class);
    VersionDocumento::create([
        'entrega_id' => $this->entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'archivo_requerido_id' => 'planteamiento',
        'version_number' => 1,
        'file_path' => 'v1-dupe.pdf',
        'original_name' => 'v1-dupe.pdf',
    ]);
});
