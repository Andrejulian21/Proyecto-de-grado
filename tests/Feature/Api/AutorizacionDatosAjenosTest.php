<?php

declare(strict_types=1);

use App\Enums\EstadoInvitacionEvaluador;
use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

uses(RefreshDatabase::class);

/**
 * Guardián de la issue #47 (datos de proyectos ajenos).
 *
 * Recorre UserRole::cases() contra los cuatro endpoints afectados y afirma
 * el estado esperado por rol (incluidos los roles sin acceso → 403). Un rol
 * nuevo en el enum rompe este guardián hasta que se defina su comportamiento
 * explícitamente (default-deny). Las pruebas de contenido complementarias
 * verifican el scoping por pertenencia.
 */
function contextoDatosAjenos(): array
{
    $semestre = Semestre::factory()->create(['is_active' => true]);
    $coordinador = User::factory()->coordinador()->create();
    $directorA = User::factory()->director()->create();
    $directorB = User::factory()->director()->create();
    $estudianteA = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $estudianteB = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $evaluador = User::factory()->external()->create(['password_changed_at' => now()]);

    $proyectoA = Proyecto::factory()->create([
        'semester_id' => $semestre->id,
        'director_id' => $directorA->id,
    ]);
    $proyectoA->estudiantes()->attach($estudianteA->id);

    $proyectoB = Proyecto::factory()->create([
        'semester_id' => $semestre->id,
        'director_id' => $directorB->id,
    ]);
    $proyectoB->estudiantes()->attach($estudianteB->id);

    EvaluadorProyecto::create([
        'proyecto_id' => $proyectoA->id,
        'evaluador_id' => $evaluador->id,
        'invitation_status' => EstadoInvitacionEvaluador::Aceptada,
        'assigned_at' => now(),
        'evaluado' => false,
    ]);

    $entregaA = crearEntregaEnviada($semestre, $proyectoA);
    $entregaB = crearEntregaEnviada($semestre, $proyectoB);

    return compact(
        'semestre',
        'coordinador',
        'directorA',
        'directorB',
        'estudianteA',
        'estudianteB',
        'evaluador',
        'proyectoA',
        'proyectoB',
        'entregaA',
        'entregaB',
    );
}

function crearEntregaEnviada(Semestre $semestre, Proyecto $proyecto): Entrega
{
    $entrega = Entrega::create([
        'semester_id' => $semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega '.$proyecto->title,
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => 'enviada',
    ]);
    // Production shape: project linked via the pivot.
    $entrega->proyectos()->attach($proyecto->id);

    return $entrega;
}

function usuarioDelRolDatosAjenos(array $ctx, UserRole $rol): User
{
    return match ($rol) {
        UserRole::Coordinador => $ctx['coordinador'],
        UserRole::Director => $ctx['directorA'],
        UserRole::Estudiante => $ctx['estudianteA'],
        UserRole::EvaluadorExterno => $ctx['evaluador'],
        default => throw new RuntimeException("Rol {$rol->value} sin usuario definido en el guardián #47."),
    };
}

/**
 * Matriz de acceso por endpoint. `expected` usa UserRole::value como clave:
 * un rol nuevo sin entrada rompe el guardián con excepción.
 */
$matrizDatosAjenos = [
    'evaluaciones.consolidado' => [
        'method' => 'getJson',
        'url' => fn (array $ctx): string => "/api/evaluaciones/{$ctx['entregaA']->id}/consolidado",
        'expected' => ['Coordinador' => 200, 'Director' => 200, 'Estudiante' => 403, 'EvaluadorExterno' => 200],
    ],
    'admin.entregas.index' => [
        'method' => 'getJson',
        'url' => fn (array $ctx): string => '/api/admin/entregas',
        'expected' => ['Coordinador' => 200, 'Director' => 200, 'Estudiante' => 200, 'EvaluadorExterno' => 200],
    ],
    'director.entregas' => [
        'method' => 'getJson',
        'url' => fn (array $ctx): string => '/api/director/entregas',
        'expected' => ['Coordinador' => 200, 'Director' => 200, 'Estudiante' => 200, 'EvaluadorExterno' => 200],
    ],
    'entregas.versiones' => [
        'method' => 'getJson',
        'url' => fn (array $ctx): string => "/api/entregas/{$ctx['entregaA']->id}/versiones",
        'expected' => ['Coordinador' => 200, 'Director' => 200, 'Estudiante' => 200, 'EvaluadorExterno' => 200],
    ],
];

$casosDatosAjenos = [];

foreach (UserRole::cases() as $rol) {
    foreach ($matrizDatosAjenos as $nombre => $spec) {
        $casosDatosAjenos[] = [
            'rol' => $rol,
            'endpoint' => $nombre,
            'esperado' => $spec['expected'][$rol->value]
                ?? throw new RuntimeException(
                    "Rol {$rol->value} sin acceso definido en {$nombre} del guardián #47."
                ),
        ];
    }
}

it('Datos ajenos #47: rol {rol} en {endpoint} → {esperado}', function (UserRole $rol, string $endpoint, int $esperado) use ($matrizDatosAjenos) {
    $spec = $matrizDatosAjenos[$endpoint];
    $ctx = contextoDatosAjenos();
    $usuario = usuarioDelRolDatosAjenos($ctx, $rol);
    $url = ($spec['url'])($ctx);

    $response = $this->actingAs($usuario)->{$spec['method']}($url);

    $response->assertStatus($esperado);
})->with($casosDatosAjenos);

// ---------------------------------------------------------------------------
// Scoping por pertenencia
// ---------------------------------------------------------------------------

it('un evaluador externo sin asignaciones ve cero entregas en /api/admin/entregas', function () {
    $ctx = contextoDatosAjenos();
    $evaluadorSinAsignaciones = User::factory()->external()->create(['password_changed_at' => now()]);

    $this->actingAs($evaluadorSinAsignaciones)
        ->getJson('/api/admin/entregas')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('un evaluador externo ve solo las entregas de sus proyectos asignados', function () {
    $ctx = contextoDatosAjenos();

    $response = $this->actingAs($ctx['evaluador'])
        ->getJson('/api/admin/entregas')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($ctx['entregaA']->id)
        ->and($ids)->not->toContain($ctx['entregaB']->id);
});

it('un estudiante no obtiene entregas globales desde el endpoint de director', function () {
    $ctx = contextoDatosAjenos();

    $this->actingAs($ctx['estudianteA'])
        ->getJson('/api/director/entregas')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('un estudiante ve entregas_pendientes en cero en /api/director/kpis', function () {
    $ctx = contextoDatosAjenos();

    $this->actingAs($ctx['estudianteA'])
        ->getJson('/api/director/kpis')
        ->assertOk()
        ->assertJsonPath('data.entregas_pendientes', 0);
});

it('un director ve solo las entregas de sus proyectos', function () {
    $ctx = contextoDatosAjenos();

    $response = $this->actingAs($ctx['directorA'])
        ->getJson('/api/director/entregas')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($ctx['entregaA']->id)
        ->and($ids)->not->toContain($ctx['entregaB']->id);
});

it('un director ajeno al proyecto recibe 403 en /api/entregas/{id}/versiones', function () {
    $ctx = contextoDatosAjenos();

    $this->actingAs($ctx['directorB'])
        ->getJson("/api/entregas/{$ctx['entregaA']->id}/versiones")
        ->assertForbidden();
});

it('el listado de versiones no expone file_path', function () {
    $ctx = contextoDatosAjenos();

    VersionDocumento::create([
        'entrega_id' => $ctx['entregaA']->id,
        'version_number' => 1,
        'file_path' => 'entregas/privado/v1.pdf',
        'file_size' => 1024,
        'original_name' => 'v1.pdf',
    ]);

    $response = $this->actingAs($ctx['coordinador'])
        ->getJson("/api/entregas/{$ctx['entregaA']->id}/versiones")
        ->assertOk();

    $version = $response->json('data.0');
    expect($version)->not->toHaveKey('file_path')
        ->and($version)->toHaveKey('id');
});
