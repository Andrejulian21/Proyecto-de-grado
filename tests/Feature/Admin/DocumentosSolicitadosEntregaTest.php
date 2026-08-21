<?php

declare(strict_types=1);

use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->semestre = Semestre::factory()->create(['is_active' => true]);
    Proyecto::factory()->create(['semester_id' => $this->semestre->id]);
});

/**
 * @param  array<int, array<string, mixed>>  $documentos
 * @return array<string, mixed>
 */
function payloadDocumentos(int $semestreId, array $documentos): array
{
    return [
        'grupo_id' => $semestreId,
        'fase' => 'anteproyecto',
        'titulo' => 'Avance de planteamiento',
        'descripcion' => 'El estudiante entrega los documentos del avance.',
        'fecha_limite' => now()->addMonths(2)->toDateString(),
        'archivos_requeridos' => $documentos,
    ];
}

it('crea una entrega con varios documentos solicitados cada uno con su titulo', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', payloadDocumentos($this->semestre->id, [
            ['id' => 'planteamiento_del_problema', 'nombre' => 'Planteamiento del problema', 'versionamiento' => true],
            ['id' => 'objetivos', 'nombre' => 'Objetivos', 'versionamiento' => true],
            ['id' => 'justificacion', 'nombre' => 'Justificación', 'versionamiento' => true],
        ]));

    $response->assertCreated();

    $entrega = Entrega::first();
    $documentos = collect($entrega->archivos_requeridos);

    expect($documentos)->toHaveCount(3)
        ->and($documentos->pluck('nombre')->all())->toBe([
            'Planteamiento del problema',
            'Objetivos',
            'Justificación',
        ])
        ->and($documentos->pluck('slug')->all())->toBe([
            'planteamiento_del_problema',
            'objetivos',
            'justificacion',
        ]);
});

it('no exige el slug documento-proyecto para crear una entrega', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', payloadDocumentos($this->semestre->id, [
            ['id' => 'marco_teorico', 'nombre' => 'Marco teórico', 'versionamiento' => true],
        ]));

    $response->assertCreated();
    expect(Entrega::first()->archivos_requeridos[0]['slug'])->toBe('marco_teorico');
});

it('persiste analizable_ia en un documento que no es documento-proyecto', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', payloadDocumentos($this->semestre->id, [
            ['id' => 'objetivos', 'nombre' => 'Objetivos', 'versionamiento' => true, 'analizable_ia' => true],
            ['id' => 'anexo', 'nombre' => 'Anexo', 'versionamiento' => false],
        ]));

    $response->assertCreated();

    $objetivos = collect($response->json('data.archivos_requeridos'))->firstWhere('slug', 'objetivos');
    $anexo = collect($response->json('data.archivos_requeridos'))->firstWhere('slug', 'anexo');

    expect($objetivos['analizable_ia'])->toBeTrue()
        ->and($anexo['analizable_ia'])->toBeFalse();
});

it('rechaza una entrega con dos documentos analizable_ia', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', payloadDocumentos($this->semestre->id, [
            ['id' => 'planteamiento', 'nombre' => 'Planteamiento', 'versionamiento' => true, 'analizable_ia' => true],
            ['id' => 'objetivos', 'nombre' => 'Objetivos', 'versionamiento' => true, 'analizable_ia' => true],
        ]));

    $response->assertStatus(422);
    expect($response->json('errors.archivos_requeridos.0'))
        ->toContain('Solo un documento de la entrega puede analizarse con IA');
    expect(Entrega::count())->toBe(0);
});

it('el listado de estudiante identifica el documento analizable de forma inequívoca', function () {
    $this->actingAs($this->coordinador)
        ->postJson('/api/admin/entregas', payloadDocumentos($this->semestre->id, [
            ['id' => 'planteamiento', 'nombre' => 'Planteamiento', 'versionamiento' => true],
            ['id' => 'objetivos', 'nombre' => 'Objetivos', 'versionamiento' => true, 'analizable_ia' => true],
        ]))
        ->assertCreated();

    $estudiante = User::factory()->create();
    $proyecto = Proyecto::where('semester_id', $this->semestre->id)->first();
    $proyecto->estudiantes()->attach($estudiante);

    $response = $this->actingAs($estudiante)->getJson('/api/estudiante/entregas');

    $response->assertOk();
    $item = $response->json('data.0');

    expect($item['documento_analizable_ia'])->toBe('objetivos');
    $ia = collect($item['archivos_requeridos'])->firstWhere('slug', 'objetivos');
    expect($ia['analizable_ia'])->toBeTrue();
});
