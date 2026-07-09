<?php

declare(strict_types=1);

use App\Enums\EstadoEntrega;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);
    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto Test',
        'semester_id' => $semestre->id,
    ]);
});

test('Entrega model exists and extends Model', function () {
    $entrega = new Entrega();
    expect($entrega)->toBeInstanceOf(Illuminate\Database\Eloquent\Model::class);
});

test('Entrega fillable fields are guarded correctly', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega inicial',
        'due_date' => '2026-03-01',
    ]);

    expect($entrega->title)->toBe('Entrega inicial');
    expect($entrega->phase)->toBe('anteproyecto');
});

test('Entrega casts status to EstadoEntrega enum', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega con status',
        'due_date' => '2026-03-01',
        'status' => 'pendiente',
    ]);

    expect($entrega->fresh()->status)->toBeInstanceOf(EstadoEntrega::class);
    expect($entrega->fresh()->status)->toBe(EstadoEntrega::Pendiente);
    expect($entrega->fresh()->status->value)->toBe('pendiente');

    $entrega->update(['status' => 'aprobada']);
    expect($entrega->fresh()->status)->toBe(EstadoEntrega::Aprobada);
});

test('Entrega casts due_date to date', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Fecha test',
        'due_date' => '2026-03-15',
    ]);

    expect($entrega->due_date)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    expect($entrega->due_date->format('Y-m-d'))->toBe('2026-03-15');
});

test('Entrega casts consolidated_grade to decimal', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Nota test',
        'due_date' => '2026-03-01',
        'consolidated_grade' => 85.5,
    ]);

    expect((float) $entrega->consolidated_grade)->toEqual(85.5);
});

test('Entrega belongs to Proyecto', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Relación test',
        'due_date' => '2026-03-01',
    ]);

    expect($entrega->proyecto)->toBeInstanceOf(Proyecto::class);
    expect($entrega->proyecto->id)->toBe($this->proyecto->id);
});

test('Entrega has many VersionDocumento', function () {
    $entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Versiones test',
        'due_date' => '2026-03-01',
    ]);

    $version = VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 1,
        'file_path' => '/tmp/test.pdf',
        'original_name' => 'test.pdf',
    ]);

    expect($entrega->versiones)->toHaveCount(1);
    expect($entrega->versiones->first())->toBeInstanceOf(VersionDocumento::class);
});

test('Entrega scope porFase filters by phase', function () {
    Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'anteproyecto', 'title' => 'A', 'due_date' => '2026-03-01']);
    Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'desarrollo', 'title' => 'B', 'due_date' => '2026-04-01']);

    expect(Entrega::porFase('anteproyecto')->count())->toBe(1);
    expect(Entrega::porFase('desarrollo')->count())->toBe(1);
    expect(Entrega::porFase('presentacion_final')->count())->toBe(0);
});

test('Entrega scope porEstado filters by status', function () {
    Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'anteproyecto', 'title' => 'A', 'due_date' => '2026-03-01', 'status' => 'pendiente']);
    Entrega::create(['proyecto_id' => $this->proyecto->id, 'phase' => 'anteproyecto', 'title' => 'B', 'due_date' => '2026-03-01', 'status' => 'aprobada']);

    expect(Entrega::porEstado('pendiente')->count())->toBe(1);
    expect(Entrega::porEstado('aprobada')->count())->toBe(1);
    expect(Entrega::porEstado('rechazada')->count())->toBe(0);
});
