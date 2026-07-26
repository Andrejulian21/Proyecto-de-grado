<?php

declare(strict_types=1);

use App\Contracts\Ai\AiProvider;
use App\Enums\AiEvaluationStatus;
use App\Enums\UserRole;
use App\Models\AiDocumentEvaluation;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiProviderRegistry;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;
use App\Services\Ai\Providers\NullAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->otro = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);
    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto IA',
        'semester_id' => $this->semestre->id,
        'code' => 'PG-20261',
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante);

    $this->entrega = Entrega::create([
        'proyecto_id' => $this->proyecto->id,
        'phase' => 'anteproyecto',
        'title' => 'Anteproyecto',
        'due_date' => '2026-03-15',
        'status' => 'pendiente',
        'evaluation_metrics' => 'Claridad de objetivos y coherencia metodológica.',
        'acceptance_criteria' => 'Incluir metodología y cronograma.',
    ]);
});

function storeDocxVersion(Entrega $entrega, string $name = 'avance.docx'): VersionDocumento
{
    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $section->addText('Documento de prueba para evaluacion inteligente.');
    $section->addText('Objetivo: validar el flujo completo.');

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
    ]);
}

function bindStubAiProvider(string $json): void
{
    $stub = new class($json) implements AiProvider
    {
        public function __construct(private readonly string $json) {}

        public function name(): string
        {
            return 'stub';
        }

        public function complete(AiRequest $request): AiResponse
        {
            return new AiResponse(content: $this->json, provider: $this->name(), model: 'stub-model');
        }
    };

    $registry = new AiProviderRegistry(app(), [
        'stub' => $stub,
        'null' => new NullAiProvider,
    ], 'stub');

    app()->instance(AiProviderRegistry::class, $registry);
    app()->instance(AiGateway::class, new AiGateway($registry));
}

it('completa evaluacion inteligente con proveedor stub y persiste resultado', function () {
    $version = storeDocxVersion($this->entrega);

    bindStubAiProvider(json_encode([
        'resumen' => 'Documento con buena base.',
        'fortalezas' => ['Objetivos presentes'],
        'aspectos_mejorar' => ['Ampliar metodología'],
        'errores' => [],
        'recomendaciones' => ['Agregar cronograma detallado'],
        'conclusion' => 'Listo para revisar con el director tras ajustes.',
        'prioridades' => [['item' => 'Metodología', 'criticidad' => 'alta']],
        'confianza' => 0.8,
        'puntaje_orientativo' => 78,
    ], JSON_THROW_ON_ERROR));

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.estado', 'completed')
        ->assertJsonPath('data.resultado.resumen', 'Documento con buena base.')
        ->assertJsonPath('data.resultado.puntaje_orientativo', 78)
        ->assertJsonPath('data.proveedor', 'stub');

    expect(AiDocumentEvaluation::query()->count())->toBe(1);
    $row = AiDocumentEvaluation::query()->first();
    expect($row->status)->toBe(AiEvaluationStatus::Completed)
        ->and($row->result_json['fortalezas'][0])->toBe('Objetivos presentes')
        ->and($row->document_hash)->not->toBeNull();
});

it('responde 503 amigable cuando el proveedor no esta configurado', function () {
    $version = storeDocxVersion($this->entrega);

    // Default app binding uses null provider.
    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ]);

    $response->assertStatus(503)
        ->assertJsonPath('code', 'ai_unavailable')
        ->assertJsonPath(
            'error',
            'No fue posible conectarse al servicio de Inteligencia Artificial. Inténtalo más tarde.',
        );

    expect(AiDocumentEvaluation::query()->first()?->status)->toBe(AiEvaluationStatus::Failed);
});

it('rechaza versiones que no son DOCX', function () {
    $relative = 'entregas/'.$this->entrega->id.'/avance.pdf';
    Storage::disk('public')->put($relative, '%PDF-1.4 fake');

    $version = VersionDocumento::create([
        'entrega_id' => $this->entrega->id,
        'version_number' => 1,
        'file_path' => $relative,
        'original_name' => 'avance.pdf',
        'file_size' => 10,
        'uploaded_at' => now(),
    ]);

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'invalid_document');
});

it('niega acceso a estudiantes de otro proyecto', function () {
    $version = storeDocxVersion($this->entrega);

    $response = $this->actingAs($this->otro)
        ->postJson("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'version_id' => $version->id,
        ]);

    $response->assertStatus(404);
});

it('lista de entregas incluye id de version y metricas', function () {
    $version = storeDocxVersion($this->entrega);

    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/estudiante/entregas');

    $response->assertOk();
    $item = collect($response->json('data'))->firstWhere('id', $this->entrega->id);

    expect($item['metricas_evaluacion'])->toContain('Claridad de objetivos')
        ->and($item['versiones'][0]['id'])->toBe($version->id);
});

it('analiza archivo temporal sin crear version oficial', function () {
    bindStubAiProvider(json_encode([
        'resumen' => 'Borrador temporal evaluado.',
        'fortalezas' => ['Estructura inicial'],
        'aspectos_mejorar' => ['Ampliar marco teórico'],
        'errores' => [],
        'recomendaciones' => ['Revisar objetivos'],
        'conclusion' => 'Útil como ensayo previo.',
        'prioridades' => [],
        'confianza' => 0.7,
        'puntaje_orientativo' => 70,
    ], JSON_THROW_ON_ERROR));

    $phpWord = new PhpWord;
    $phpWord->addSection()->addText('Borrador temporal para IA.');
    $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'borrador-ia-'.uniqid().'.docx';
    IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);

    $upload = new Illuminate\Http\UploadedFile(
        $tmpPath,
        'borrador.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true,
    );

    $versionsBefore = VersionDocumento::query()->count();

    $response = $this->actingAs($this->estudiante)
        ->post("/api/estudiante/entregas/{$this->entrega->id}/evaluacion-inteligente", [
            'file' => $upload,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.temporal', true)
        ->assertJsonPath('data.version_id', null)
        ->assertJsonPath('data.resultado.resumen', 'Borrador temporal evaluado.');

    expect(VersionDocumento::query()->count())->toBe($versionsBefore);
    expect(AiDocumentEvaluation::query()->first()?->version_documento_id)->toBeNull();

    @unlink($tmpPath);
});
