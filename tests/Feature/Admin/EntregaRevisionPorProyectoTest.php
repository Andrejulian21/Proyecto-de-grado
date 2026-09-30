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
 * Resolución de la entrega en el contexto de un proyecto.
 *
 * La entrega es una PLANTILLA compartida a nivel de semestre: todo proyecto
 * nuevo se auto-vincula a las entregas de su semestre. El detalle
 * `/api/admin/entregas/{id}` devuelve hoy una lista plana de versiones de
 * TODOS los proyectos, con un `director_grade` por versión, de modo que la
 * pantalla de revisión del director no sabe qué proyecto está mirando.
 *
 * Estos tests fijan el contrato: cuando se envía `?proyecto=<id>` el backend
 * resuelve la entrega DENTRO de ese proyecto — solo sus versiones, su nota y
 * su análisis IA — y las escrituras (revisar/habilitar) operan sobre el
 * pivote de ese proyecto y ninguno más.
 *
 * Cero llamadas reales a Gemini: los análisis se crean completados en la base
 * con `provider = stub`.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->semestre = Semestre::factory()->create(['is_active' => true]);

    // Un único director supervisa ambos proyectos: el filtro por proyecto no
    // puede confundir "superviso" con "superviso OTRO proyecto".
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

    // Un tercer proyecto del mismo semestre, NO vinculado a la entrega.
    $this->proyectoAjeno = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
        'title' => 'Proyecto ajeno a la entrega',
    ]);

    $this->entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Anteproyecto compartido',
        'description' => 'El estudiante debe presentar el planteamiento del problema.',
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

    $this->versionA = guardarVersionDocxRevision($this->entrega, $this->pivotA, 'avance-a.docx', 'Documento del proyecto A.');
    $this->versionB = guardarVersionDocxRevision($this->entrega, $this->pivotB, 'avance-b.docx', 'Documento del proyecto B.');

    // Calificaciones distintas por proyecto: son la prueba de que el detalle
    // resuelve la nota del proyecto pedido y no una nota compartida.
    $this->pivotA->update(['director_grade' => 3.0]);
    $this->pivotB->update(['director_grade' => 4.5]);

    $this->analisisA = crearAnalisisStub($this->estudianteA, $this->entrega, $this->versionA);
    $this->analisisB = crearAnalisisStub($this->estudianteB, $this->entrega, $this->versionB);
});

function guardarVersionDocxRevision(Entrega $entrega, EntregaProyecto $pivot, string $name, string $text): VersionDocumento
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

function crearAnalisisStub(User $user, Entrega $entrega, ?VersionDocumento $version): AiDocumentEvaluation
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
        'document_hash' => hash('sha256', 'revision-'.$user->id.'-'.($version?->id ?? 'temporal')),
        'prompt_version' => 'v1',
        'processing_ms' => 90,
        'result_json' => ['resumen' => 'Analisis exclusivo del proyecto de '.$user->name],
    ]);
}

/**
 * El detalle con `?proyecto=A` expone únicamente lo del proyecto A: su versión,
 * su nota (3.0) y su análisis IA. Nada del proyecto B puede aparecer.
 */
it('el detalle de la entrega con proyecto solo expone versiones, nota y analisis IA de ese proyecto', function () {
    $response = $this->actingAs($this->director)
        ->getJson("/api/admin/entregas/{$this->entrega->id}?proyecto={$this->proyectoA->id}");

    $response->assertOk();

    $versiones = collect($response->json('data.versiones'));

    expect($versiones->pluck('id')->all())->toBe([$this->versionA->id]);
    expect((float) $versiones->first()['director_grade'])->toEqual(3.0);

    $analisis = $versiones->first()['analisis_ia'];
    expect(collect($analisis)->pluck('id')->all())->toBe([$this->analisisA->id]);
    expect($analisis[0]['resultado']['resumen'])->toBe('Analisis exclusivo del proyecto de '.$this->estudianteA->name);

    // El proyecto B no puede aparecer por ningún lado del payload. Se
    // comprueba con cadenas únicas de B (el id numérico solo no sirve: "1" y
    // "2" aparecen en fechas y contadores de todo el JSON). La lista de
    // proyectos participantes sí es pública de la plantilla compartida, así
    // que lo que no puede filtrarse son versiones, notas y análisis.
    expect($response->getContent())
        ->not->toContain($this->versionB->original_name)
        ->not->toContain($this->versionB->file_path)
        ->not->toContain($this->analisisB->result_json['resumen'])
        ->not->toContain('4.5');
});

/**
 * Bloque `entrega_proyecto`: el estado del proyecto pedido dentro de la
 * entrega compartida, con sus versiones anidadas.
 */
it('el detalle con proyecto expone el bloque entrega_proyecto de ese proyecto', function () {
    $response = $this->actingAs($this->director)
        ->getJson("/api/admin/entregas/{$this->entrega->id}?proyecto={$this->proyectoA->id}");

    $response->assertOk();

    $pivot = $response->json('data.entrega_proyecto');

    expect($pivot)->not->toBeNull()
        ->and($pivot['id'])->toBe($this->pivotA->id)
        ->and($pivot['proyecto_id'])->toBe($this->proyectoA->id)
        ->and((float) $pivot['director_grade'])->toEqual(3.0)
        ->and($pivot)->toHaveKey('director_notes');

    expect(collect($pivot['versiones'])->pluck('id')->all())->toBe([$this->versionA->id]);
});

/**
 * Espejo del caso anterior para el proyecto B.
 */
it('el detalle con el otro proyecto devuelve su version, su nota y su analisis IA', function () {
    $response = $this->actingAs($this->director)
        ->getJson("/api/admin/entregas/{$this->entrega->id}?proyecto={$this->proyectoB->id}");

    $response->assertOk();

    $versiones = collect($response->json('data.versiones'));

    expect($versiones->pluck('id')->all())->toBe([$this->versionB->id]);
    expect((float) $versiones->first()['director_grade'])->toEqual(4.5);
    expect(collect($versiones->first()['analisis_ia'])->pluck('id')->all())->toBe([$this->analisisB->id]);

    expect($response->json('data.entrega_proyecto.id'))->toBe($this->pivotB->id);

    expect($response->getContent())
        ->not->toContain($this->versionA->original_name)
        ->not->toContain($this->versionA->file_path)
        ->not->toContain($this->analisisA->result_json['resumen'])
        ->not->toContain('3.0');
});

/**
 * Compatibilidad: sin `proyecto` el detalle sigue siendo la supervisión global
 * del semestre. Otros llamadores (coordinador, reportes) no se rompen.
 */
it('sin el filtro proyecto el detalle sigue devolviendo el conjunto completo', function () {
    $response = $this->actingAs($this->director)
        ->getJson("/api/admin/entregas/{$this->entrega->id}");

    $response->assertOk();

    $ids = collect($response->json('data.versiones'))->pluck('id')->all();

    expect($ids)->toContain($this->versionA->id)
        ->and($ids)->toContain($this->versionB->id);

    // El bloque por proyecto es opcional: sin filtro no se inventa uno.
    expect($response->json('data'))->not->toHaveKey('entrega_proyecto');
});

/**
 * Un proyecto que no está en el pivote de esa entrega no se puede resolver:
 * 404, sin confirmar nada del resto de la entrega.
 */
it('rechaza un proyecto que no pertenece a la entrega (404)', function () {
    $response = $this->actingAs($this->director)
        ->getJson("/api/admin/entregas/{$this->entrega->id}?proyecto={$this->proyectoAjeno->id}");

    $response->assertStatus(404);

    expect($response->getContent())->not->toContain($this->versionA->original_name);
});

/**
 * Ser director de ALGÚN proyecto de la entrega no habilita a ver los demás.
 */
it('un director que no supervisa el proyecto solicitado recibe 403', function () {
    $otroDirector = User::factory()->director()->create();

    // Para que la policy `view` pase igual, el director ajeno debe tener
    // relación con alguna entrega; se la damos vía el proyecto B.
    $this->proyectoB->update(['director_id' => $otroDirector->id]);

    $response = $this->actingAs($this->director)
        ->getJson("/api/admin/entregas/{$this->entrega->id}?proyecto={$this->proyectoB->id}");

    $response->assertStatus(403);
});

/**
 * Un estudiante tampoco puede usar el filtro para leer el proyecto ajeno.
 */
it('un estudiante no puede resolver el detalle de otro proyecto (403)', function () {
    $response = $this->actingAs($this->estudianteA)
        ->getJson("/api/admin/entregas/{$this->entrega->id}?proyecto={$this->proyectoB->id}");

    $response->assertStatus(403);
});

/**
 * TEST CRÍTICO — calificar con `proyecto` solo toca ese pivote.
 *
 * Antes del fix, aprobar el proyecto A alcanzaba para pisar la nota/estado
 * compartidos de la entrega y `habilitar` descongelaba TODOS los pivotes
 * calificados, borrando la nota del proyecto B.
 */
it('revisar con proyecto solo califica el pivote de ese proyecto y deja intacto el otro', function () {
    $response = $this->actingAs($this->director)->putJson(
        "/api/admin/entregas/{$this->entrega->id}/revisar",
        [
            'proyecto' => $this->proyectoA->id,
            'status' => 'aprobada',
            'director_grade' => 3.0,
            'director_notes' => 'Observaciones privadas del proyecto A',
            'version_id' => $this->versionA->id,
        ],
    );

    $response->assertOk();

    $this->pivotA->refresh();
    $this->pivotB->refresh();
    $this->versionA->refresh();
    $this->versionB->refresh();

    expect((float) $this->pivotA->director_grade)->toEqual(3.0)
        ->and($this->pivotA->observaciones_director)->toBe('Observaciones privadas del proyecto A')
        ->and($this->versionA->director_notes)->toBe('Observaciones privadas del proyecto A');

    // El proyecto B no se mueve: ni nota, ni observaciones, ni estado.
    expect((float) $this->pivotB->director_grade)->toEqual(4.5)
        ->and($this->pivotB->observaciones_director)->toBeNull()
        ->and($this->versionB->director_notes)->toBeNull();

    // La plantilla compartida tampoco se marca como revisada: el proyecto B
    // todavía puede revisarse.
    expect($this->entrega->fresh()->status->value)->toBe('enviada');
});

/**
 * El estado de la revisión vive en el pivote del proyecto, no en la entrega.
 */
it('el estado de la revision se persiste en el pivote del proyecto', function () {
    $this->actingAs($this->director)->putJson(
        "/api/admin/entregas/{$this->entrega->id}/revisar",
        [
            'proyecto' => $this->proyectoA->id,
            'status' => 'rechazada',
            'director_notes' => 'Debe ampliar la Justificacion',
            'version_id' => $this->versionA->id,
        ],
    )->assertOk();

    expect($this->pivotA->fresh()->estado)->toBe('rechazada')
        ->and($this->pivotB->fresh()->estado)->toBeNull()
        ->and($this->entrega->fresh()->status->value)->toBe('enviada');
});

/**
 * Cross-proyecto: pedir el proyecto A y enviar la versión de B no puede
 * calificar a B. Es el escenario "el director causalizó el proyecto
 * equivocado" que motiva el fix.
 */
it('no se puede calificar la version de otro proyecto usando su propio filtro (404)', function () {
    $response = $this->actingAs($this->director)->putJson(
        "/api/admin/entregas/{$this->entrega->id}/revisar",
        [
            'proyecto' => $this->proyectoA->id,
            'status' => 'aprobada',
            'director_grade' => 1.0,
            'version_id' => $this->versionB->id,
        ],
    );

    $response->assertStatus(404);

    expect((float) $this->pivotB->fresh()->director_grade)->toEqual(4.5)
        ->and($this->pivotB->fresh()->estado)->toBeNull()
        ->and($this->pivotB->fresh()->observaciones_director)->toBeNull()
        ->and((float) $this->pivotA->fresh()->director_grade)->toEqual(3.0);
});

/**
 * `habilitar` con proyecto descongela únicamente ese pivote calificado.
 */
it('habilitar con proyecto solo descongela el pivote de ese proyecto', function () {
    $this->pivotA->update(['estado' => 'aprobada']);
    $this->pivotB->update(['estado' => 'aprobada']);

    $response = $this->actingAs($this->director)->putJson(
        "/api/admin/entregas/{$this->entrega->id}/habilitar",
        ['proyecto' => $this->proyectoA->id],
    );

    $response->assertOk();

    expect($this->pivotA->fresh()->director_grade)->toBeNull()
        ->and((float) $this->pivotB->fresh()->director_grade)->toEqual(4.5)
        ->and($this->pivotB->fresh()->estado)->toBe('aprobada');
});

/**
 * `habilitar` con un proyecto ajeno a la entrega no procede.
 */
it('habilitar con proyecto ajeno a la entrega devuelve 404', function () {
    $response = $this->actingAs($this->director)->putJson(
        "/api/admin/entregas/{$this->entrega->id}/habilitar",
        ['proyecto' => $this->proyectoAjeno->id],
    );

    $response->assertStatus(404);

    expect((float) $this->pivotA->fresh()->director_grade)->toEqual(3.0)
        ->and((float) $this->pivotB->fresh()->director_grade)->toEqual(4.5);
});
