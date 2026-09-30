<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

/**
 * States a delivery can be in for ONE project, derived from what is already
 * on disk (no schema change, no new columns):
 *
 *   no_iniciada            -> deadline passed, delivery never enabled
 *   pendiente              -> deadline still open, nothing uploaded
 *   no_entregada           -> deadline passed, nothing uploaded  => scores 0
 *   entregada_sin_calificar-> uploaded, director has not ruled
 *   calificada             -> director_grade is not null
 *
 * The defect under test: skipping a NULL nota REMOVED it from the
 * denominator too, so not delivering a task RAISED the weighted average.
 */
uses(RefreshDatabase::class);

/**
 * Build a delivery of the anteproyecto phase for one project.
 *
 * @param  array{
 *     peso: ?float,
 *     nota: ?float,
 *     due_date?: string,
 *     status?: string,
 *     versiones?: int
 * }  $spec
 * @return array{entrega: Entrega, pivot: EntregaProyecto}
 */
function crearEntregaParaNota(Proyecto $proyecto, string $titulo, array $spec): array
{
    $entrega = Entrega::create([
        'semester_id' => $proyecto->semester_id,
        'phase' => 'anteproyecto',
        'title' => $titulo,
        'description' => 'Descripción de la entrega',
        'due_date' => $spec['due_date'] ?? now()->addMonth()->toDateString(),
        'status' => $spec['status'] ?? 'pendiente',
        'grade_percentage' => $spec['peso'],
    ]);

    $pivot = EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $proyecto->id,
        'estado' => 'pendiente',
        'director_grade' => $spec['nota'],
    ]);

    $total = $spec['versiones'] ?? 0;

    for ($i = 1; $i <= $total; $i++) {
        VersionDocumento::create([
            'entrega_id' => $entrega->id,
            'entrega_proyecto_id' => $pivot->id,
            'version_number' => $i,
            'file_path' => "documentos/e{$entrega->id}/v{$i}.pdf",
            'original_name' => "version-{$i}.pdf",
            'uploaded_at' => now(),
        ]);
    }

    return ['entrega' => $entrega, 'pivot' => $pivot];
}

/**
 * @return array{entrega: Entrega, pivot: EntregaProyecto}
 */
function crearProyectoParaNota(User $director, User $estudiante, Semestre $semestre, string $titulo): array
{
    $proyecto = Proyecto::factory()->create([
        'title' => $titulo,
        'semester_id' => $semestre->id,
        'director_id' => $director->id,
    ]);

    $proyecto->estudiantes()->attach($estudiante);

    return ['proyecto' => $proyecto];
}

function leerProyectoNota(array $payload, int $proyectoId): array
{
    foreach ($payload['data']['proyectos'] ?? [] as $proyecto) {
        if ((int) $proyecto['id'] === $proyectoId) {
            return $proyecto;
        }
    }

    throw new RuntimeException("El proyecto {$proyectoId} no aparece en el payload.");
}

/**
 * @return array<string, array<string, mixed>>
 */
function mapearEntregasNota(array $proyecto): array
{
    $mapa = [];

    foreach ($proyecto['notas_entregas_anteproyecto'] ?? [] as $entrega) {
        $mapa[$entrega['titulo']] = $entrega;
    }

    return $mapa;
}

beforeEach(function () {
    $this->semestre = Semestre::factory()->create([
        'name' => '2026-2',
        'is_active' => true,
    ]);

    $this->director = User::factory()->director()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->proyecto = crearProyectoParaNota($this->director, $this->estudiante, $this->semestre, 'Sistema de inventario')['proyecto'];

    $this->consultarComoDirector = function (): array {
        /** @var TestResponse $response */
        $response = $this->actingAs($this->director)
            ->getJson('/api/notas?semestre_id='.$this->semestre->id.'&tipo=pg1')
            ->assertOk();

        return $response->json();
    };
});

it('no_entregada cuenta como cero y no sube la nota', function () {
    // 50/50 split. Only one delivery is graded (5.0); the other missed its
    // deadline with nothing uploaded.
    //
    // 5.0 * 0.50 + 0.0 * 0.50 = 2.50
    //
    // The previous implementation dropped the missing delivery from BOTH the
    // numerator and the denominator, renormalised the remainder and returned
    // 5.0 — not delivering a task raised the grade.
    crearEntregaParaNota($this->proyecto, 'Anteproyecto documental', [
        'peso' => 50,
        'nota' => 5.0,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    crearEntregaParaNota($this->proyecto, 'Informe de avance', [
        'peso' => 50,
        'nota' => null,
        'due_date' => now()->subDays(5)->toDateString(),
        'versiones' => 0,
    ]);

    $proyecto = leerProyectoNota(($this->consultarComoDirector)(), $this->proyecto->id);

    expect((float) $proyecto['nota_entregas_ponderada'])->toBe(2.5);
});

it('el denominador incluye el peso de las entregas no entregadas', function () {
    // 40/30/30. Two deliveries graded, one missed.
    //
    // 4.0 * 0.40 + 5.0 * 0.30 + 0.0 * 0.30 = 1.6 + 1.5 + 0 = 3.10
    crearEntregaParaNota($this->proyecto, 'Anteproyecto documental', [
        'peso' => 40,
        'nota' => 4.0,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    crearEntregaParaNota($this->proyecto, 'Informe de avance', [
        'peso' => 30,
        'nota' => 5.0,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    crearEntregaParaNota($this->proyecto, 'Corrección menor', [
        'peso' => 30,
        'nota' => null,
        'due_date' => now()->subDays(5)->toDateString(),
        'versiones' => 0,
    ]);

    $proyecto = leerProyectoNota(($this->consultarComoDirector)(), $this->proyecto->id);

    expect((float) $proyecto['nota_entregas_ponderada'])->toBe(3.1);
});

it('pendiente no penaliza ni computa', function () {
    // The second delivery still has an open deadline, so its grade is not
    // knowable yet: it must neither score 0 nor let a partial average leak
    // out as the phase grade.
    crearEntregaParaNota($this->proyecto, 'Anteproyecto documental', [
        'peso' => 50,
        'nota' => 5.0,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    crearEntregaParaNota($this->proyecto, 'Informe de avance', [
        'peso' => 50,
        'nota' => null,
        'due_date' => now()->addDays(10)->toDateString(),
        'versiones' => 0,
    ]);

    $proyecto = leerProyectoNota(($this->consultarComoDirector)(), $this->proyecto->id);
    $entregas = mapearEntregasNota($proyecto);

    expect($proyecto['nota_entregas_ponderada'])->toBeNull()
        ->and($entregas['Informe de avance']['estado'])->toBe('pendiente')
        ->and($entregas['Informe de avance']['es_no_entregada'])->toBeFalse();
});

it('entregada sin calificar bloquea la nota final', function () {
    // Uploaded, but the director has not ruled yet: still unknown.
    crearEntregaParaNota($this->proyecto, 'Anteproyecto documental', [
        'peso' => 50,
        'nota' => 5.0,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    crearEntregaParaNota($this->proyecto, 'Informe de avance', [
        'peso' => 50,
        'nota' => null,
        'due_date' => now()->subDays(5)->toDateString(),
        'versiones' => 1,
    ]);

    $proyecto = leerProyectoNota(($this->consultarComoDirector)(), $this->proyecto->id);
    $entregas = mapearEntregasNota($proyecto);

    expect($proyecto['nota_entregas_ponderada'])->toBeNull()
        ->and($entregas['Informe de avance']['estado'])->toBe('entregada_sin_calificar')
        ->and($entregas['Informe de avance']['es_no_entregada'])->toBeFalse();
});

it('no_iniciada no penaliza', function () {
    // Deadline passed and the delivery was never enabled: `solicitada` is the
    // pre-habilitación state, and HabilitarEntregaAction is what moves it to
    // `pendiente`. So a still-`solicitada` delivery never reached any student
    // and must not be scored as missed.
    //
    // NOTE: habilitación is recorded on the SEMESTER-WIDE template, not per
    // project — there is no per-project "was this requested?" column. That is
    // why this state is derived from `entregas.status` and why it can only be
    // reported for deliveries no project ever uploaded to.
    crearEntregaParaNota($this->proyecto, 'Anteproyecto documental', [
        'peso' => 50,
        'nota' => 5.0,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    crearEntregaParaNota($this->proyecto, 'Anexo nunca habilitado', [
        'peso' => 50,
        'nota' => null,
        'due_date' => now()->subDays(5)->toDateString(),
        'status' => 'solicitada',
        'versiones' => 0,
    ]);

    $proyecto = leerProyectoNota(($this->consultarComoDirector)(), $this->proyecto->id);
    $entregas = mapearEntregasNota($proyecto);

    expect($entregas['Anexo nunca habilitado']['estado'])->toBe('no_iniciada')
        ->and($entregas['Anexo nunca habilitado']['es_no_entregada'])->toBeFalse()
        ->and($entregas['Anexo nunca habilitado']['nota'])->toBeNull()
        // Unknown state: the phase does not compute, but nothing is penalised.
        ->and($proyecto['nota_entregas_ponderada'])->toBeNull();
});

it('los cinco estados se distinguen en la respuesta', function () {
    crearEntregaParaNota($this->proyecto, 'Estado calificada', [
        'peso' => 20,
        'nota' => 4.2,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    crearEntregaParaNota($this->proyecto, 'Estado no_entregada', [
        'peso' => 20,
        'nota' => null,
        'due_date' => now()->subDays(5)->toDateString(),
        'versiones' => 0,
    ]);

    crearEntregaParaNota($this->proyecto, 'Estado entregada_sin_calificar', [
        'peso' => 20,
        'nota' => null,
        'due_date' => now()->subDays(5)->toDateString(),
        'versiones' => 2,
    ]);

    crearEntregaParaNota($this->proyecto, 'Estado pendiente', [
        'peso' => 20,
        'nota' => null,
        'due_date' => now()->addDays(10)->toDateString(),
        'versiones' => 0,
    ]);

    crearEntregaParaNota($this->proyecto, 'Estado no_iniciada', [
        'peso' => 20,
        'nota' => null,
        'due_date' => now()->subDays(5)->toDateString(),
        'status' => 'solicitada',
        'versiones' => 0,
    ]);

    $proyecto = leerProyectoNota(($this->consultarComoDirector)(), $this->proyecto->id);
    $entregas = mapearEntregasNota($proyecto);

    $estados = collect($entregas)->pluck('estado');

    expect($estados->unique()->sort()->values()->all())
        ->toBe([
            'calificada',
            'entregada_sin_calificar',
            'no_entregada',
            'no_iniciada',
            'pendiente',
        ]);

    // The zero of a missed delivery must be explicit, not a bare NULL.
    expect($entregas['Estado no_entregada']['es_no_entregada'])->toBeTrue();

    foreach (['Estado calificada', 'Estado entregada_sin_calificar', 'Estado pendiente', 'Estado no_iniciada'] as $titulo) {
        expect($entregas[$titulo]['es_no_entregada'])->toBeFalse();
    }

    // Isolation: a second project of the same semester with its own grades
    // must not leak into this student's view.
    $otroDirector = User::factory()->director()->create();
    $otroEstudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $otroProyecto = crearProyectoParaNota($otroDirector, $otroEstudiante, $this->semestre, 'Plataforma de alertas')['proyecto'];

    crearEntregaParaNota($otroProyecto, 'Otro proyecto calificada', [
        'peso' => 100,
        'nota' => 1.0,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    /** @var TestResponse $response */
    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/notas?semestre_id='.$this->semestre->id.'&tipo=pg1')
        ->assertOk();

    $payload = $response->json();
    $ids = collect($payload['data']['proyectos'])->pluck('id')->all();

    expect($ids)->toBe([$this->proyecto->id]);

    $propio = leerProyectoNota($payload, $this->proyecto->id);
    $propias = mapearEntregasNota($propio);

    expect(array_keys($propias))->toHaveCount(5)
        ->and($propias)->not->toHaveKey('Otro proyecto calificada')
        // This project's own grade, not the other project's 1.0.
        ->and((float) $propias['Estado calificada']['nota'])->toBe(4.2);
});

it('el caso donde todo está calificado se comporta como antes', function () {
    // Regression guard: with every delivery graded the weights already add up
    // to 100%, so removing the renormalisation must not change the number.
    //
    // 4.0 * 0.50 + 5.0 * 0.50 = 4.50
    crearEntregaParaNota($this->proyecto, 'Anteproyecto documental', [
        'peso' => 50,
        'nota' => 4.0,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    crearEntregaParaNota($this->proyecto, 'Informe de avance', [
        'peso' => 50,
        'nota' => 5.0,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    $proyecto = leerProyectoNota(($this->consultarComoDirector)(), $this->proyecto->id);

    expect((float) $proyecto['nota_entregas_ponderada'])->toBe(4.5);
});
