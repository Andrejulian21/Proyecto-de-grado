<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Bitacora;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\SeguimientoObservacion;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use App\Services\SeguimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

/**
 * Parsea el contenido binario de un XLSX y devuelve sus filas como
 * arreglo de arreglos (toArray), para inspeccionar celdas en el test.
 *
 * @return array<int, array<int, mixed>>
 */
function parsearXlsxExport(string $contenido): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'seguimiento_export_');
    file_put_contents($tmp, $contenido);

    try {
        $reader = new Xlsx;
        $spreadsheet = $reader->load($tmp);

        return $spreadsheet->getActiveSheet()->toArray();
    } finally {
        @unlink($tmp);
    }
}

beforeEach(function () {
    $this->coordinador = User::factory()->create(['role' => UserRole::Coordinador->value]);
    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
        'is_active' => true,
    ]);
});

/**
 * Crea un proyecto con director, 2 estudiantes, una entrega de
 * desarrollo entregada (con versión), una pendiente (futura), una
 * bitácora del grupo A, y una observación de fase desarrollo.
 */
function crearProyectoExport(Semestre $semestre, string $titulo): Proyecto
{
    $director = User::factory()->director()->create();
    $estudiante = User::factory()->create([
        'role' => UserRole::Estudiante->value,
        'name' => 'Estudiante '.$titulo,
        'codigo_estudiante' => 'U-'.fake()->unique()->numerify('######'),
    ]);

    $proyecto = Proyecto::factory()->create([
        'semester_id' => $semestre->id,
        'director_id' => $director->id,
        'title' => $titulo,
    ]);
    $proyecto->estudiantes()->attach($estudiante->id);

    $entregada = Entrega::create([
        'proyecto_id' => $proyecto->id,
        'semester_id' => $semestre->id,
        'phase' => 'desarrollo',
        'title' => 'Avance 1',
        'due_date' => now()->subWeek()->toDateString(),
        'status' => 'enviada',
    ]);
    VersionDocumento::create([
        'entrega_id' => $entregada->id,
        'version_number' => 1,
        'file_path' => '/tmp/avance1.pdf',
        'file_size' => 1024,
        'original_name' => 'avance1.pdf',
        'uploaded_at' => now(),
    ]);

    Entrega::create([
        'proyecto_id' => $proyecto->id,
        'semester_id' => $semestre->id,
        'phase' => 'desarrollo',
        'title' => 'Avance 2',
        'due_date' => now()->addWeek()->toDateString(),
        'status' => 'pendiente',
    ]);

    Bitacora::factory()->create([
        'proyecto_id' => $proyecto->id,
        'semana' => 3,
        'meeting_date' => now(),
    ]);

    SeguimientoObservacion::create([
        'proyecto_id' => $proyecto->id,
        'semestre_id' => $semestre->id,
        'fase' => 'desarrollo',
        'observacion' => 'Revisar avance 1',
    ]);

    return $proyecto;
}

// -- GET /api/admin/seguimiento/semestre/{id}/export --------------------------

test('exporta xlsx con 5 filas y todas las columnas para un semestre con 5 proyectos', function () {
    foreach (['Proyecto A', 'Proyecto B', 'Proyecto C', 'Proyecto D', 'Proyecto E'] as $titulo) {
        crearProyectoExport($this->semestre, $titulo);
    }

    $response = $this->actingAs($this->coordinador)
        ->get("/api/admin/seguimiento/semestre/{$this->semestre->id}/export");

    $response->assertOk();
    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $filas = parsearXlsxExport($response->streamedContent());

    // 1 header + 5 filas de datos
    expect($filas)->toHaveCount(6);
    expect($filas[0])->toContain('Estudiantes');
    expect($filas[0])->toContain('Proyecto');
    expect($filas[0])->toContain('Director');
    expect($filas[0])->toContain('Bitácoras PG1');
    expect($filas[0])->toContain('Bitácoras PG2');
    expect($filas[0])->toContain('Observaciones');

    // Primera fila de datos: estudiante, proyecto y director poblados
    expect($filas[1][0])->toContain('Estudiante Proyecto A');
    expect($filas[1][1])->toBe('Proyecto A');
    expect($filas[1][3])->not->toBeEmpty();
});

test('incluye el estado de cada entrega por fase (Entregado/Pendiente/No entregó)', function () {
    crearProyectoExport($this->semestre, 'Proyecto A');

    $response = $this->actingAs($this->coordinador)
        ->get("/api/admin/seguimiento/semestre/{$this->semestre->id}/export");

    $filas = parsearXlsxExport($response->streamedContent());

    $header = $filas[0];
    $dato = $filas[1];

    // Columna dinámica de la entrega entregada → "Entregado"
    $idxEntregada = array_search('Desarrollo - Avance 1', $header, true);
    expect($idxEntregada)->not->toBeFalse();
    expect($dato[$idxEntregada])->toBe('Entregado');

    // Columna dinámica de la entrega pendiente → "Pendiente"
    $idxPendiente = array_search('Desarrollo - Avance 2', $header, true);
    expect($idxPendiente)->not->toBeFalse();
    expect($dato[$idxPendiente])->toBe('Pendiente');
});

test('exporta xlsx con headers y 0 filas para un semestre sin proyectos', function () {
    $response = $this->actingAs($this->coordinador)
        ->get("/api/admin/seguimiento/semestre/{$this->semestre->id}/export");

    $response->assertOk();
    $filas = parsearXlsxExport($response->streamedContent());

    expect($filas)->toHaveCount(1); // solo header
    expect($filas[0])->toContain('Estudiantes');
    expect($filas[0])->toContain('Proyecto');
});

test('usa el nombre de archivo D5 con el grupo del semestre y fecha-hora', function () {
    crearProyectoExport($this->semestre, 'Proyecto A');

    $response = $this->actingAs($this->coordinador)
        ->get("/api/admin/seguimiento/semestre/{$this->semestre->id}/export");

    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain('attachment');
    expect($disposition)->toMatch('/Seguimiento del 2026-1 \d{4}-\d{2}-\d{2}_\d{2}-\d{2}\.xlsx/');
});

test('emite filename* RFC 5987 cuando el nombre del archivo tiene no-ASCII', function () {
    $this->semestre->update(['name' => '2026-1 Prueba Á']);
    crearProyectoExport($this->semestre, 'Proyecto A');

    $response = $this->actingAs($this->coordinador)
        ->get("/api/admin/seguimiento/semestre/{$this->semestre->id}/export");

    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain("filename*=utf-8''");
});

test('rechaza export con 404 para semestre inexistente', function () {
    $response = $this->actingAs($this->coordinador)
        ->get('/api/admin/seguimiento/semestre/999999/export');

    $response->assertStatus(404);
});

test('rechaza export con 403 para rol no coordinador', function () {
    $estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $response = $this->actingAs($estudiante)
        ->get("/api/admin/seguimiento/semestre/{$this->semestre->id}/export");

    $response->assertStatus(403);
});

test('responde 500 con mensaje claro si la librería no está disponible', function () {
    $this->mock(SeguimientoService::class, function ($mock) {
        $mock->shouldReceive('obtenerSeguimiento')
            ->once()
            ->andThrow(new RuntimeException('phpspreadsheet ausente'));
    });

    $response = $this->actingAs($this->coordinador)
        ->get("/api/admin/seguimiento/semestre/{$this->semestre->id}/export");

    $response->assertStatus(500);
    expect($response->json('error'))
        ->toBe('Error al generar el archivo Excel. Verifique que la librería esté instalada.');
});
