<?php

declare(strict_types=1);

use App\Enums\AiEvaluationStatus;
use App\Enums\AiEvaluationType;
use App\Enums\UserRole;
use App\Models\AiDocumentEvaluation;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

uses(RefreshDatabase::class);

/**
 * Aislamiento de datos privados en una entrega compartida.
 *
 * Una ENTREGA es una plantilla compartida por todos los proyectos del
 * semestre: título, fase, descripción, fecha límite, archivos_requeridos y
 * grade_percentage son públicos para todos. En cambio, lo que cada proyecto
 * SUBE vive en el pivote `entrega_proyecto`: versiones, file_path,
 * original_name, uploaded_at, director_notes, director_grade y los análisis
 * de IA son privados del proyecto dueño.
 *
 * Este archivo arma dos proyectos sobre la MISMA entrega (mismo director, dos
 * estudiantes, una versión por proyecto) y verifica que ningún endpoint
 * exponga al estudiante A los datos privados del proyecto B — y que el
 * director, que sí supervisa ambos, los siga viendo.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->semestre = Semestre::factory()->create(['is_active' => true]);

    // Un único director supervisa ambos proyectos: si la corrección filtrara
    // por rol sin distinguir, el director perdería visibilidad legítima.
    $this->director = User::factory()->director()->create();

    $this->estudianteA = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->estudianteB = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->proyectoA = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
        'title' => 'Proyecto A',
    ]);
    $this->proyectoA->estudiantes()->attach($this->estudianteA);

    $this->proyectoB = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
        'title' => 'Proyecto B',
    ]);
    $this->proyectoB->estudiantes()->attach($this->estudianteB);

    $this->entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Anteproyecto compartido',
        'description' => 'El estudiante debe presentar el planteamiento del problema con contexto y justificación.',
        'due_date' => now()->addMonth()->toDateString(),
        'status' => 'enviada',
        'grade_percentage' => 20,
        'archivos_requeridos' => [
            [
                'slug' => 'documento-proyecto',
                'nombre' => 'Documento del proyecto',
                'versionamiento' => true,
                'analizable_ia' => true,
            ],
        ],
    ]);

    // El boot hook de Proyecto ya auto-vincula al semestre; el attach
    // explícito hace el escenario determinista (unique entrega+proyecto).
    foreach ([$this->proyectoA, $this->proyectoB] as $proyecto) {
        if (! $this->entrega->proyectos()->where('proyecto_id', $proyecto->id)->exists()) {
            $this->entrega->proyectos()->attach($proyecto->id);
        }
    }

    $this->pivotA = EntregaProyecto::where('entrega_id', $this->entrega->id)
        ->where('proyecto_id', $this->proyectoA->id)
        ->firstOrFail();
    $this->pivotB = EntregaProyecto::where('entrega_id', $this->entrega->id)
        ->where('proyecto_id', $this->proyectoB->id)
        ->firstOrFail();

    $this->versionA = guardarVersionDocx($this->entrega, $this->pivotA, 'avance-a.docx', 'Documento submitted por el proyecto A para analisis.');
    $this->versionB = guardarVersionDocx($this->entrega, $this->pivotB, 'avance-b.docx', 'Documento submitted por el proyecto B para analisis.');

    // Notas del director por proyecto: son privadas de cada entrega_proyecto.
    $this->pivotA->update(['director_grade' => 3.0]);
    $this->pivotB->update(['director_grade' => 4.5]);

    $this->analisisA = crearAnalisisCompletado($this->estudianteA, $this->entrega, $this->versionA);
    $this->analisisB = crearAnalisisCompletado($this->estudianteB, $this->entrega, $this->versionB);

    // Análisis temporal (subida previa, nunca persistida como VersionDocumento):
    // se filtra por autor, no por pivote.
    $this->analisisTemporalB = crearAnalisisCompletado($this->estudianteB, $this->entrega, null);
});

/**
 * Escribe un DOCX real en el disco fake y devuelve la versión creada.
 */
function guardarVersionDocx(Entrega $entrega, EntregaProyecto $pivot, string $name, string $text): VersionDocumento
{
    $phpWord = new PhpWord;
    $phpWord->addSection()->addText($text);

    $relative = "entregas/{$entrega->id}/{$name}";
    $absolute = Storage::disk('public')->path($relative);

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0777, true);
    }
    IOFactory::createWriter($phpWord, 'Word2007')->save($absolute);

    return VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'archivo_requerido_id' => 'documento-proyecto',
        'version_number' => 1,
        'file_path' => $relative,
        'file_size' => filesize($absolute) ?: 0,
        'original_name' => $name,
        'uploaded_at' => now(),
    ]);
}

function crearAnalisisCompletado(User $user, Entrega $entrega, ?VersionDocumento $version): AiDocumentEvaluation
{
    return AiDocumentEvaluation::create([
        'user_id' => $user->id,
        'entrega_id' => $entrega->id,
        'version_documento_id' => $version?->id,
        'archivo_requerido_id' => 'documento-proyecto',
        'type' => AiEvaluationType::PreSubmission,
        'status' => AiEvaluationStatus::Completed,
        'provider' => 'stub',
        'model' => 'stub-model',
        'document_hash' => $version !== null
            ? hash_file('sha256', Storage::disk('public')->path($version->file_path))
            : hash('sha256', 'temporal-'.$user->id),
        'prompt_version' => 'v1',
        'processing_ms' => 120,
        'result_json' => ['resumen' => 'Analisis de '.$user->name],
    ]);
}

/**
 * (a) La lista de entregas del estudiante A no debe incluir la versión del
 * proyecto B ni exponer `ruta_archivo` (file_path) en ninguna versión.
 */
it('la lista de entregas del estudiante solo muestra sus propias versiones y sin ruta de archivo', function () {
    $response = $this->actingAs($this->estudianteA)
        ->getJson('/api/estudiante/entregas');

    $response->assertOk();

    $entrega = collect($response->json('data'))->firstWhere('id', $this->entrega->id);

    expect($entrega)->not->toBeNull();

    $versiones = $entrega['versiones'];

    expect(collect($versiones)->pluck('id')->all())->toBe([$this->versionA->id]);

    foreach ($versiones as $version) {
        expect($version)->not->toHaveKey('ruta_archivo');
    }

    // El payload completo nunca debe mencionar el archivo ajeno.
    expect($response->getContent())->not->toContain($this->versionB->file_path);
});

/**
 * (b) El detalle de la entrega visto por el estudiante A debe quedar limitado
 * a su proyecto: sin la versión de B, sin file_path y sin el director_grade
 * del pivote de B.
 */
it('el detalle de la entrega visto por el estudiante oculta versiones y notas de otro proyecto', function () {
    $response = $this->actingAs($this->estudianteA)
        ->getJson("/api/admin/entregas/{$this->entrega->id}");

    $response->assertOk();

    $versiones = $response->json('data.versiones');

    expect(collect($versiones)->pluck('id')->all())->toBe([$this->versionA->id]);

    foreach ($versiones as $version) {
        expect($version)->not->toHaveKey('file_path');
    }

    expect($versiones[0]['director_grade'])->toEqual(3.0);

    // Ni el archivo ajeno ni la nota privada del pivote ajeno.
    expect($response->getContent())->not->toContain($this->versionB->file_path);
    expect($response->getContent())->not->toContain('4.5');
});

/**
 * (c) GET /api/entregas/{id}/versiones del estudiante A solo devuelve su
 * versión, nunca la del proyecto B.
 */
it('el historial de versiones del estudiante solo incluye su propia version', function () {
    $response = $this->actingAs($this->estudianteA)
        ->getJson("/api/entregas/{$this->entrega->id}/versiones");

    $response->assertOk();

    $versiones = $response->json('data');

    expect(collect($versiones)->pluck('id')->all())->toBe([$this->versionA->id]);

    foreach ($versiones as $version) {
        expect($version)->not->toHaveKey('file_path');
    }
});

/**
 * (d) Sin `version_id`, el historial de IA del estudiante A no puede devolver
 * los análisis del proyecto B — ni los anclados a la versión de B ni los
 * temporales creados por el estudiante B.
 */
it('el historial de analisis IA del estudiante excluye los analisis de otro proyecto', function () {
    $response = $this->actingAs($this->estudianteA)
        ->getJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente");

    $response->assertOk();

    $historial = collect($response->json('historial'));

    expect($historial->pluck('id')->all())->toBe([$this->analisisA->id]);
    expect($response->json('data.id'))->toBe($this->analisisA->id);
});

/**
 * (e) Un estudiante no puede lanzar el análisis de IA sobre la versión de otro
 * proyecto. 404 (no 403) para no confirmar la existencia del documento ajeno.
 */
it('el estudiante no puede analizar una version de otro proyecto (404)', function () {
    $response = $this->actingAs($this->estudianteA)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $this->versionB->id,
        ]);

    $response->assertStatus(404)
        ->assertJsonPath('error', 'No se encontró la versión del documento.');

    expect(AiDocumentEvaluation::query()->count())->toBe(3);
});

/**
 * (f) Anti-regresión: el director supervisa AMBOS proyectos sobre la misma
 * entrega, así que debe seguir viendo las dos versiones con sus respectivas
 * notas. El aislamiento aplica al estudiante, no al supervisor.
 */
it('el director sigue viendo las versiones de ambos proyectos', function () {
    $detalle = $this->actingAs($this->director)
        ->getJson("/api/admin/entregas/{$this->entrega->id}");

    $detalle->assertOk();

    $versiones = collect($detalle->json('data.versiones'))->keyBy('id');

    expect($versiones->keys()->all())
        ->toContain($this->versionA->id)
        ->toContain($this->versionB->id);

    expect((float) $versiones[$this->versionA->id]['director_grade'])->toEqual(3.0)
        ->and((float) $versiones[$this->versionB->id]['director_grade'])->toEqual(4.5);

    $historial = $this->actingAs($this->director)
        ->getJson("/api/entregas/{$this->entrega->id}/versiones");

    $historial->assertOk();

    expect(collect($historial->json('data'))->pluck('id')->all())
        ->toContain($this->versionA->id)
        ->toContain($this->versionB->id);
});

/**
 * El coordinador tampoco se filtra: ve la entrega completa del semestre.
 */
it('el coordinador ve las versiones de todos los proyectos de la entrega', function () {
    $coordinador = User::factory()->coordinador()->create();

    $response = $this->actingAs($coordinador)
        ->getJson("/api/admin/entregas/{$this->entrega->id}");

    $response->assertOk();

    expect(collect($response->json('data.versiones'))->pluck('id')->all())
        ->toContain($this->versionA->id)
        ->toContain($this->versionB->id);
});
