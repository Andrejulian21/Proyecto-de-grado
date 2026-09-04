<?php

declare(strict_types=1);

use App\Enums\EstadoFirma;
use App\Models\Bitacora;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

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

test('Bitacora model exists and extends Model', function () {
    $bitacora = new Bitacora;
    expect($bitacora)->toBeInstanceOf(Model::class);
});

test('Bitacora fillable fields work correctly', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Revisión de avances',
        'notes' => 'Se revisaron los módulos',
        'meeting_date' => '2026-04-01',
    ]);

    expect($bitacora->topic)->toBe('Revisión de avances');
    expect($bitacora->notes)->toBe('Se revisaron los módulos');
    expect($bitacora->proyecto_id)->toBe($this->proyecto->id);
});

test('Bitacora casts signature_status to EstadoFirma enum', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Test enum',
        'meeting_date' => '2026-04-01',
    ]);

    expect($bitacora->fresh()->signature_status)->toBeInstanceOf(EstadoFirma::class);
    expect($bitacora->fresh()->signature_status)->toBe(EstadoFirma::Pendiente);
    expect($bitacora->fresh()->signature_status->value)->toBe('Pendiente');

    $bitacora->update(['signature_status' => 'FirmadaEstudiante']);
    expect($bitacora->fresh()->signature_status)->toBe(EstadoFirma::FirmadaEstudiante);
});

test('Bitacora casts meeting_date to date', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Fecha test',
        'meeting_date' => '2026-04-15',
    ]);

    expect($bitacora->meeting_date)->toBeInstanceOf(Carbon::class);
    expect($bitacora->meeting_date->format('Y-m-d'))->toBe('2026-04-15');
});

test('Bitacora casts signed timestamps to datetime', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Timestamps test',
        'meeting_date' => '2026-04-01',
        'student_signed_at' => '2026-04-10 10:00:00',
        'director_signed_at' => '2026-04-11 14:30:00',
    ]);

    expect($bitacora->student_signed_at)->toBeInstanceOf(Carbon::class);
    expect($bitacora->director_signed_at)->toBeInstanceOf(Carbon::class);
    expect($bitacora->student_signed_at->format('Y-m-d H:i'))->toBe('2026-04-10 10:00');
    expect($bitacora->director_signed_at->format('Y-m-d H:i'))->toBe('2026-04-11 14:30');
});

test('Bitacora belongs to Proyecto', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Relación test',
        'meeting_date' => '2026-04-01',
    ]);

    expect($bitacora->proyecto)->toBeInstanceOf(Proyecto::class);
    expect($bitacora->proyecto->id)->toBe($this->proyecto->id);
});

test('Bitacora scope porEstado filters by signature status', function () {
    Bitacora::create(['proyecto_id' => $this->proyecto->id, 'topic' => 'A', 'meeting_date' => '2026-04-01', 'signature_status' => 'Pendiente']);
    Bitacora::create(['proyecto_id' => $this->proyecto->id, 'topic' => 'B', 'meeting_date' => '2026-04-02', 'signature_status' => 'FirmadaEstudiante']);
    Bitacora::create(['proyecto_id' => $this->proyecto->id, 'topic' => 'C', 'meeting_date' => '2026-04-03', 'signature_status' => 'Completada']);

    expect(Bitacora::porEstado('Pendiente')->count())->toBe(1);
    expect(Bitacora::porEstado('FirmadaEstudiante')->count())->toBe(1);
    expect(Bitacora::porEstado('Completada')->count())->toBe(1);
    expect(Bitacora::porEstado('Sospechosa')->count())->toBe(0);
});

test('Bitacora can be created with all fields', function () {
    $director = User::factory()->director()->create();

    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Sprint Review',
        'notes' => 'Se completaron los módulos planificados.',
        'evidence_file' => 'evidencias/reunion_001.pdf',
        'meeting_date' => '2026-05-01',
        'signature_status' => 'Pendiente',
        'student_signed_at' => null,
        'director_signed_at' => null,
        'duration_hours' => 1.5,
    ]);

    expect($bitacora->topic)->toBe('Sprint Review');
    expect($bitacora->evidence_file)->toBe('evidencias/reunion_001.pdf');
    expect($bitacora->duration_hours)->toEqual(1.5);
});
