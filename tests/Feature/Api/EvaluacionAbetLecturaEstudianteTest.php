<?php

declare(strict_types=1);

use App\Enums\AiEvaluationStatus;
use App\Enums\AiEvaluationType;
use App\Enums\UserRole;
use App\Models\AiDocumentEvaluation;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->director = User::factory()->create(['role' => UserRole::Director->value]);
    $this->otroDirector = User::factory()->create(['role' => UserRole::Director->value]);
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);

    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto lectura IA',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);

    $this->entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Anteproyecto lectura',
        'description' => 'Planteamiento del problema.',
        'due_date' => '2026-03-15',
        'status' => 'enviada',
        'archivos_requeridos' => [
            [
                'slug' => 'documento-proyecto',
                'nombre' => 'Documento del proyecto',
                'versionamiento' => true,
                'analizable_ia' => true,
            ],
        ],
    ]);
    $this->entrega->proyectos()->attach($this->proyecto->id);
});

function storeLecturaDocxVersion(Entrega $entrega, string $name = 'avance.docx'): VersionDocumento
{
    $phpWord = new PhpWord;
    $phpWord->addSection()->addText('Documento oficial para lectura del director.');

    $relative = 'entregas/'.$entrega->id.'/'.$name;
    $absolute = Storage::disk('public')->path($relative);

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0777, true);
    }
    IOFactory::createWriter($phpWord, 'Word2007')->save($absolute);

    return VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'version_number' => 1,
        'file_path' => $relative,
        'original_name' => $name,
        'file_size' => filesize($absolute) ?: 0,
        'uploaded_at' => now(),
        'archivo_requerido_id' => 'documento-proyecto',
    ]);
}

function makeStudentEvaluation(
    Entrega $entrega,
    User $estudiante,
    array $overrides = []
): AiDocumentEvaluation {
    return AiDocumentEvaluation::create(array_merge([
        'user_id' => $estudiante->id,
        'entrega_id' => $entrega->id,
        'version_documento_id' => null,
        'archivo_requerido_id' => 'documento-proyecto',
        'type' => AiEvaluationType::PreSubmission,
        'status' => AiEvaluationStatus::Completed,
        'provider' => 'gemini',
        'model' => 'gemini-2.0-flash',
        'document_hash' => 'hash-temporal-'.Str::uuid()->toString(),
        'prompt_version' => 'v1',
        'result_json' => [
            'resumen' => 'Resumen del estudiante.',
            'conclusion' => 'Conclusión del estudiante.',
        ],
    ], $overrides));
}

it('muestra al director el ultimo analisis pedido por el estudiante', function () {
    $viejo = makeStudentEvaluation($this->entrega, $this->estudiante, [
        'document_hash' => 'hash-viejo',
        'result_json' => ['resumen' => 'Análisis viejo.', 'conclusion' => 'Vieja.'],
    ]);
    $latest = makeStudentEvaluation($this->entrega, $this->estudiante, [
        'document_hash' => 'hash-nuevo',
        'result_json' => ['resumen' => 'Análisis nuevo.', 'conclusion' => 'Nueva.'],
    ]);
    AiDocumentEvaluation::query()->whereKey($viejo->id)->update(['created_at' => now()->subHour()]);
    AiDocumentEvaluation::query()->whereKey($latest->id)->update(['created_at' => now()]);

    $response = $this->actingAs($this->director)
        ->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet");

    $response->assertOk()
        ->assertJsonPath('data.id', $latest->id)
        ->assertJsonPath('data.tipo', 'pre_submission')
        ->assertJsonPath('data.resultado.resumen', 'Análisis nuevo.')
        ->assertJsonCount(2, 'historial')
        ->assertJsonPath('historial.0.id', $latest->id);
});

it('muestra el ultimo analisis del grupo aunque el borrador difiera del oficial', function () {
    $version = storeLecturaDocxVersion($this->entrega);

    $temporal = makeStudentEvaluation($this->entrega, $this->estudiante, [
        'version_documento_id' => null,
        'document_hash' => 'hash-borrador-distinto',
        'result_json' => ['resumen' => 'Análisis del borrador.', 'conclusion' => 'Borrador.'],
    ]);

    $response = $this->actingAs($this->director)
        ->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet?version_id={$version->id}&proyecto_id={$this->proyecto->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $temporal->id)
        ->assertJsonPath('data.resultado.resumen', 'Análisis del borrador.');
});

it('version_id solo valida existencia y devuelve el ultimo del grupo', function () {
    $version = storeLecturaDocxVersion($this->entrega);
    $otra = storeLecturaDocxVersion($this->entrega, 'otro.docx');
    $otra->update(['version_number' => 2]);

    makeStudentEvaluation($this->entrega, $this->estudiante, [
        'version_documento_id' => $otra->id,
        'document_hash' => 'hash-otra',
    ]);
    $ultimo = makeStudentEvaluation($this->entrega, $this->estudiante, [
        'version_documento_id' => null,
        'document_hash' => 'hash-temporal-ultimo',
        'result_json' => ['resumen' => 'Análisis más reciente.', 'conclusion' => 'Reciente.'],
    ]);

    $response = $this->actingAs($this->director)
        ->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet?version_id={$version->id}&proyecto_id={$this->proyecto->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $ultimo->id)
        ->assertJsonCount(2, 'historial');
});

it('con proyecto_id solo muestra analisis de ese grupo', function () {
    $otroEstudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $otroProyecto = Proyecto::create([
        'title' => 'Otro proyecto mismo entrega',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);
    $otroProyecto->estudiantes()->attach($otroEstudiante);
    // Nota: ProyectoObserver ya vincula la entrega al proyecto recién creado.

    $propio = makeStudentEvaluation($this->entrega, $this->estudiante, [
        'document_hash' => 'hash-propio',
        'result_json' => ['resumen' => 'Análisis propio.', 'conclusion' => 'Propio.'],
    ]);
    makeStudentEvaluation($this->entrega, $otroEstudiante, [
        'document_hash' => 'hash-otro',
        'result_json' => ['resumen' => 'Análisis otro grupo.', 'conclusion' => 'Otro.'],
    ]);

    $this->actingAs($this->director)
        ->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet?proyecto_id={$this->proyecto->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $propio->id)
        ->assertJsonCount(1, 'historial');
});

it('no expone analisis heredados tipo abet del director', function () {
    $student = makeStudentEvaluation($this->entrega, $this->estudiante, [
        'document_hash' => 'hash-student',
    ]);
    AiDocumentEvaluation::create([
        'user_id' => $this->director->id,
        'entrega_id' => $this->entrega->id,
        'version_documento_id' => null,
        'archivo_requerido_id' => 'documento-proyecto',
        'type' => AiEvaluationType::Abet,
        'status' => AiEvaluationStatus::Completed,
        'provider' => 'gemini',
        'model' => 'gemini-2.0-flash',
        'document_hash' => 'hash-abet',
        'prompt_version' => 'v1',
        'result_json' => ['resumen' => 'Viejo re-análisis del director.'],
    ]);

    $response = $this->actingAs($this->director)
        ->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet");

    $response->assertOk()
        ->assertJsonPath('data.id', $student->id)
        ->assertJsonCount(1, 'historial');
});

it('responde data null cuando el estudiante aun no pidio analisis', function () {
    $this->actingAs($this->director)
        ->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet")
        ->assertOk()
        ->assertJsonPath('data', null)
        ->assertJsonCount(0, 'historial');
});

it('rechaza versiones de otra entrega y directores sin vinculo', function () {
    $version = storeLecturaDocxVersion($this->entrega);

    $this->actingAs($this->director)
        ->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet?version_id=999999")
        ->assertNotFound();

    $this->actingAs($this->otroDirector)
        ->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet")
        ->assertForbidden();

    $this->getJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet")
        ->assertForbidden();

    expect($version->id)->toBeGreaterThan(0);
});

it('ya no permite re-analizar desde el lado director', function () {
    $version = storeLecturaDocxVersion($this->entrega);

    $this->actingAs($this->director)
        ->postJson("/api/director/entregas/{$this->entrega->id}/evaluacion-abet", [
            'version_id' => $version->id,
        ])
        ->assertStatus(405);
});
