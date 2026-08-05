<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * Business rule (confirmed by the owner): before `start_date + start_time`
 * the student cannot view/upload; after `due_date + hora_maxima` the student
 * can view but cannot upload. The backend enforces the upload cutoff in
 * EntregaEstudianteController::verificarVentanaTiempo().
 */
beforeEach(function () {
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->semestre = Semestre::factory()->create(['is_active' => true]);
    $this->proyecto = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);

    $this->intentarSubir = function (Entrega $entrega): TestResponse {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        return $this->actingAs($this->estudiante)
            ->postJson("/api/entregas/{$entrega->id}/archivos/documento-proyecto", ['file' => $file]);
    };
});

function seedEntregaConVentana(array $window): Entrega
{
    $entrega = Entrega::create(array_merge([
        'semester_id' => $window['semestre_id'],
        'phase' => 'anteproyecto',
        'title' => 'Entrega con ventana',
        'description' => 'Desc',
        'status' => 'pendiente',
        'archivos_requeridos' => [
            ['slug' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
        ],
    ], [
        'start_date' => $window['start_date'] ?? null,
        'start_time' => $window['start_time'] ?? null,
        'due_date' => $window['due_date'],
        'hora_maxima' => $window['hora_maxima'] ?? null,
    ]));

    // Link to the student's project via pivot (StoreEntregaAction shape).
    $entrega->proyectos()->attach($window['proyecto_id']);

    return $entrega;
}

it('bloquea la subida antes de la fecha de inicio', function () {
    $entrega = seedEntregaConVentana([
        'semestre_id' => $this->semestre->id,
        'proyecto_id' => $this->proyecto->id,
        'start_date' => now()->addDay()->toDateString(),
        'due_date' => now()->addMonths(1)->toDateString(),
    ]);

    $response = ($this->intentarSubir)($entrega);

    $response->assertStatus(422);
    expect($response->json('error'))->toContain('aún no está disponible');
});

it('bloquea la subida el mismo día antes de la hora de inicio', function () {
    // +1h garantiza hora futura; si cruza medianoche, la rama de fecha
    // también rechaza con el mismo mensaje (robusto ante el borde 00:00).
    $inicio = now()->copy()->addHour();

    $entrega = seedEntregaConVentana([
        'semestre_id' => $this->semestre->id,
        'proyecto_id' => $this->proyecto->id,
        'start_date' => $inicio->toDateString(),
        'start_time' => $inicio->format('H:i'),
        'due_date' => now()->addMonths(1)->toDateString(),
    ]);

    $response = ($this->intentarSubir)($entrega);

    $response->assertStatus(422);
    expect($response->json('error'))->toContain('aún no está disponible');
});

it('bloquea la subida después de la fecha límite pero permite ver', function () {
    $entrega = seedEntregaConVentana([
        'semestre_id' => $this->semestre->id,
        'proyecto_id' => $this->proyecto->id,
        'start_date' => now()->subMonth()->toDateString(),
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $response = ($this->intentarSubir)($entrega);

    $response->assertStatus(422);
    expect($response->json('error'))->toContain('fecha límite');

    // View access stays open after the deadline (GET detail, student scope).
    $this->actingAs($this->estudiante)
        ->getJson("/api/admin/entregas/{$entrega->id}")
        ->assertOk();
});

it('bloquea la subida el día de la fecha límite después de la hora máxima', function () {
    $entrega = seedEntregaConVentana([
        'semestre_id' => $this->semestre->id,
        'proyecto_id' => $this->proyecto->id,
        'start_date' => now()->subMonth()->toDateString(),
        'due_date' => now()->toDateString(),
        'hora_maxima' => '00:00',
    ]);

    $response = ($this->intentarSubir)($entrega);

    $response->assertStatus(422);
    expect($response->json('error'))->toContain('hora máxima');
});

it('permite subir dentro de la ventana de tiempo', function () {
    $entrega = seedEntregaConVentana([
        'semestre_id' => $this->semestre->id,
        'proyecto_id' => $this->proyecto->id,
        'start_date' => now()->subDay()->toDateString(),
        'due_date' => now()->addMonths(1)->toDateString(),
    ]);

    $response = ($this->intentarSubir)($entrega);

    $response->assertStatus(201);
});
