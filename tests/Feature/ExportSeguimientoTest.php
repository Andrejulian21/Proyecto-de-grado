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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

/**
 * Carga el contenido binario de un XLSX y devuelve el libro (Spreadsheet)
 * para inspeccionar celdas y estilos en el test.
 */
function cargarXlsxExport(string $contenido): Spreadsheet
{
    $tmp = tempnam(sys_get_temp_dir(), 'seguimiento_export_');
    file_put_contents($tmp, $contenido);

    try {
        $reader = new Xlsx;

        return $reader->load($tmp);
    } finally {
        @unlink($tmp);
    }
}

/**
 * Parsea el contenido binario de un XLSX y devuelve sus filas como
 * arreglo de arreglos (toArray), para inspeccionar celdas en el test.
 *
 * @return array<int, array<int, mixed>>
 */
function parsearXlsxExport(string $contenido): array
{
    return cargarXlsxExport($contenido)->getActiveSheet()->toArray();
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
        'semester_id' => $semestre->id,
        'phase' => 'desarrollo',
        'title' => 'Avance 1',
        'due_date' => now()->subWeek()->toDateString(),
        'status' => 'enviada',
    ]);
    $entregada->proyectos()->attach($proyecto->id);
    VersionDocumento::create([
        'entrega_id' => $entregada->id,
        'version_number' => 1,
        'file_path' => '/tmp/avance1.pdf',
        'file_size' => 1024,
        'original_name' => 'avance1.pdf',
        'uploaded_at' => now(),
    ]);

    $pendiente = Entrega::create([
        'semester_id' => $semestre->id,
        'phase' => 'desarrollo',
        'title' => 'Avance 2',
        'due_date' => now()->addWeek()->toDateString(),
        'status' => 'pendiente',
    ]);
    $pendiente->proyectos()->attach($proyecto->id);

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

    // 1 título + 1 header + 5 filas de datos + 1 totales
    expect($filas)->toHaveCount(8);
    expect($filas[0][0])->toContain('Seguimiento del Semestre');
    expect($filas[1])->toContain('Estudiantes');
    expect($filas[1])->toContain('Proyecto');
    expect($filas[1])->toContain('Director');
    expect($filas[1])->toContain('Bitácoras PG1');
    expect($filas[1])->toContain('Bitácoras PG2');
    expect($filas[1])->toContain('Observaciones');

    // Primera fila de datos: estudiante, proyecto y director poblados
    expect($filas[2][0])->toContain('Estudiante Proyecto A');
    expect($filas[2][1])->toBe('Proyecto A');
    expect($filas[2][3])->not->toBeEmpty();
});

test('incluye el estado de cada entrega por fase (Entregado/Pendiente/No entregó)', function () {
    crearProyectoExport($this->semestre, 'Proyecto A');

    $response = $this->actingAs($this->coordinador)
        ->get("/api/admin/seguimiento/semestre/{$this->semestre->id}/export");

    $filas = parsearXlsxExport($response->streamedContent());

    $header = $filas[1];
    $dato = $filas[2];

    // Columna dinámica de la entrega entregada → "Entregado"
    $idxEntregada = array_search('Desarrollo - Avance 1', $header, true);
    expect($idxEntregada)->not->toBeFalse();
    expect($dato[$idxEntregada])->toBe('Entregado');

    // Columna dinámica de la entrega pendiente → "Pendiente"
    $idxPendiente = array_search('Desarrollo - Avance 2', $header, true);
    expect($idxPendiente)->not->toBeFalse();
    expect($dato[$idxPendiente])->toBe('Pendiente');
});

test('formatea la columna de observaciones con wrapText, etiqueta de fase y ancho fijo', function () {
    crearProyectoExport($this->semestre, 'Proyecto A');

    $response = $this->actingAs($this->coordinador)
        ->get("/api/admin/seguimiento/semestre/{$this->semestre->id}/export");

    $response->assertOk();
    $sheet = cargarXlsxExport($response->streamedContent())->getActiveSheet();

    $filas = $sheet->toArray();
    $header = $filas[1];
    $obsIdx = array_search('Observaciones', $header, true);
    expect($obsIdx)->not->toBeFalse();

    // La celda de observaciones muestra la etiqueta legible de la fase
    expect($filas[2][$obsIdx])->toContain('Desarrollo: Revisar avance 1');

    // La columna de observaciones usa ancho fijo (no autoSize)
    $obsCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($obsIdx + 1);
    expect($sheet->getColumnDimension($obsCol)->getWidth())->toBe(50.0);

    // wrapText habilitado con alineación arriba-izquierda en la celda
    $alignment = $sheet->getStyle($obsCol.'3')->getAlignment();
    expect($alignment->getWrapText())->toBeTrue();
    expect($alignment->getHorizontal())->toBe(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
    expect($alignment->getVertical())->toBe(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
});

test('exporta xlsx con headers y 0 filas para un semestre sin proyectos', function () {
    $response = $this->actingAs($this->coordinador)
        ->get("/api/admin/seguimiento/semestre/{$this->semestre->id}/export");

    $response->assertOk();
    $filas = parsearXlsxExport($response->streamedContent());

    expect($filas)->toHaveCount(3); // título + header + totales
    expect($filas[1])->toContain('Estudiantes');
    expect($filas[1])->toContain('Proyecto');
});

test('aplica formato profesional: título mergeado, encabezado bold con fondo', function () {
    crearProyectoExport($this->semestre, 'Proyecto A');

    $response = $this->actingAs($this->coordinador)
        ->get("/api/admin/seguimiento/semestre/{$this->semestre->id}/export");

    $response->assertOk();
    $sheet = cargarXlsxExport($response->streamedContent())->getActiveSheet();

    // Título en A1, mergeado sobre todas las columnas, bold con fondo naranja
    $merged = $sheet->getMergeCells();
    expect($merged)->toHaveCount(1);
    expect(array_key_first($merged))->toStartWith('A1:');
    expect($sheet->getCell('A1')->getValue())->toContain('Seguimiento del Semestre');

    $titleStyle = $sheet->getStyle('A1');
    expect($titleStyle->getFont()->getBold())->toBeTrue();
    expect($titleStyle->getFont()->getSize())->toBe(14.0);
    expect(strtoupper((string) $titleStyle->getFill()->getStartColor()->getRGB()))->toBe('C2410C');

    // Encabezado en A2, bold con fondo índigo
    $headerStyle = $sheet->getStyle('A2');
    expect($headerStyle->getFont()->getBold())->toBeTrue();
    expect(strtoupper((string) $headerStyle->getFill()->getStartColor()->getRGB()))->toBe('4F46E5');
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
