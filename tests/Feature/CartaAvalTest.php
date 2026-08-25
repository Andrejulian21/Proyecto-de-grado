<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->semestre = Semestre::factory()->create(['is_active' => true]);
    $this->director = User::factory()->director()->create();
    $this->estudiante = User::factory()->create([
        'role' => UserRole::Estudiante->value,
        'name' => 'Juan Perez',
        'codigo_estudiante' => 'U0012345',
    ]);
    $this->proyecto = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante->id);
});

/**
 * Crea un DOCX mínimo (zip válido con word/document.xml) en el storage
 * fake, con los placeholders dados, para ejercitar el TemplateProcessor.
 *
 * @param  list<string>  $placeholders
 */
function crearTemplateCarta(string $archivo, array $placeholders): void
{
    $xml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        .'<w:body><w:p><w:r><w:t xml:space="preserve">'
        .implode('', array_map(fn (string $k): string => '${'.$k.'}', $placeholders))
        .'</w:t></w:r></w:p></w:body></w:document>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        .'</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        .'</Relationships>';

    $path = Storage::disk('local')->path('templates/'.$archivo);

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $xml);
    $zip->close();
}

// -- GET /api/director/cartas/proyectos ---------------------------------------

test('lista proyectos del director habilitados tras el cierre de desarrollo', function () {
    Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'desarrollo',
        'title' => 'Avance final',
        'due_date' => now()->subDay()->toDateString(),
        'status' => 'pendiente',
        'hora_maxima' => '18:00',
    ]);

    $response = $this->actingAs($this->director)
        ->getJson('/api/director/cartas/proyectos');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $this->proyecto->id)
        ->assertJsonPath('data.0.cartas_habilitadas', true)
        ->assertJsonPath('data.0.estudiantes.0.codigo_estudiante', 'U0012345');
    expect($response->json('data.0.cierre_efectivo'))->not->toBeNull();
});

test('deshabilita cartas cuando el semestre no tiene entregas de desarrollo', function () {
    $response = $this->actingAs($this->director)
        ->getJson('/api/director/cartas/proyectos');

    $response->assertOk()
        ->assertJsonPath('data.0.cartas_habilitadas', false);
    expect($response->json('data.0.cierre_efectivo'))->toBeNull();
});

test('proyecto sin estudiantes se lista con estudiantes vacíos (RF-CA-01)', function () {
    $sinEstudiantes = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);

    $response = $this->actingAs($this->director)
        ->getJson('/api/director/cartas/proyectos');

    $entry = collect($response->json('data'))->firstWhere('id', $sinEstudiantes->id);
    expect($entry['estudiantes'])->toBe([]);
});

test('advierta cuando faltan jurados de presentación final (D2)', function () {
    $response = $this->actingAs($this->director)
        ->getJson('/api/director/cartas/proyectos');

    expect($response->json('data.0.estudiantes.0.warnings'))
        ->toContain('Faltan asignaciones de jurados para presentación final');
});

test('no lista proyectos de otros directores', function () {
    $otroDirector = User::factory()->director()->create();

    $response = $this->actingAs($otroDirector)
        ->getJson('/api/director/cartas/proyectos');

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

test('rol no director no accede al listado de cartas', function () {
    $response = $this->actingAs($this->estudiante)
        ->getJson('/api/director/cartas/proyectos');

    $response->assertStatus(403);
});

// -- descargas ----------------------------------------------------------------

test('descarga carta de aval de sustentación con nombre D4', function () {
    crearTemplateCarta('aval-sustentacion.docx', [
        'nombre_estudiante', 'codigo_estudiante', 'titulo_proyecto',
        'jurado_1_nombre', 'jurado_2_nombre', 'jurado_3_nombre', 'nombre_director',
        'ciudad', 'fecha',
    ]);

    $response = $this->actingAs($this->director)
        ->get("/api/director/cartas/{$this->proyecto->id}/estudiante/{$this->estudiante->id}/aval-sustentacion");

    $response->assertOk();
    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    expect($response->headers->get('content-disposition'))
        ->toContain('Aval Sustentacion Publica [Juan Perez].docx');
});

test('descarga carta de aval a jurados con nombre D4', function () {
    crearTemplateCarta('carta-jurados.docx', [
        'nombre_estudiante', 'codigo_estudiante', 'titulo_proyecto', 'nombre_director',
        'ciudad', 'fecha',
    ]);

    $response = $this->actingAs($this->director)
        ->get("/api/director/cartas/{$this->proyecto->id}/estudiante/{$this->estudiante->id}/carta-jurados");

    $response->assertOk();
    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('content-disposition'))
        ->toContain('Carta de Aval Entrega a Jurados [Juan Perez].docx');
});

test('template faltante responde 500 con mensaje claro (RF-CA-02/03)', function () {
    $response = $this->actingAs($this->director)
        ->get("/api/director/cartas/{$this->proyecto->id}/estudiante/{$this->estudiante->id}/aval-sustentacion");

    $response->assertStatus(500);
    expect($response->json('error'))
        ->toBe('La plantilla de carta no está disponible. Contacte al administrador.');
});

test('estudiante ajeno al proyecto responde 404', function () {
    crearTemplateCarta('aval-sustentacion.docx', ['nombre_estudiante']);
    $ajeno = User::factory()->create([
        'role' => UserRole::Estudiante->value,
        'codigo_estudiante' => 'OTRO',
    ]);

    $response = $this->actingAs($this->director)
        ->get("/api/director/cartas/{$this->proyecto->id}/estudiante/{$ajeno->id}/aval-sustentacion");

    $response->assertStatus(404);
});

test('director no descarga cartas de proyecto ajeno', function () {
    crearTemplateCarta('aval-sustentacion.docx', ['nombre_estudiante']);
    $ajeno = Proyecto::factory()->create(['semester_id' => $this->semestre->id]);

    $response = $this->actingAs($this->director)
        ->get("/api/director/cartas/{$ajeno->id}/estudiante/{$this->estudiante->id}/aval-sustentacion");

    $response->assertStatus(404);
});
