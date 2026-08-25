<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Bitacora;
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
 * Guardián de la épica #38 (autorización por denegación por defecto).
 *
 * Recorre UserRole::cases() contra cada endpoint protegido de entregas y
 * bitácoras y afirma el resultado esperado para cada rol (incluidos los
 * roles sin acceso → 403). Si mañana se agrega un rol al enum, este test
 * falla hasta que se defina su comportamiento explícitamente (la matriz
 * `expected` y el mapeo de usuario lanzan excepción ante un rol nuevo).
 */
function contextoPolicyGuard(): array
{
    $semestre = Semestre::factory()->create(['is_active' => true]);
    $director = User::factory()->director()->create();
    $estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $evaluador = User::factory()->external()->create(['password_changed_at' => now()]);
    $coordinador = User::factory()->coordinador()->create();

    $proyecto = Proyecto::factory()->create([
        'semester_id' => $semestre->id,
        'director_id' => $director->id,
    ]);
    $proyecto->estudiantes()->attach($estudiante->id);

    EvaluadorProyecto::create([
        'proyecto_id' => $proyecto->id,
        'evaluador_id' => $evaluador->id,
        'invitation_status' => 'Aceptada',
        'assigned_at' => now(),
        'evaluado' => false,
    ]);

    return compact('semestre', 'director', 'estudiante', 'evaluador', 'coordinador', 'proyecto');
}

/**
 * Mapea cada rol del enum a un usuario del fixture. Un rol nuevo en el
 * enum rompe el guardián hasta que se defina aquí.
 */
function usuarioDelRolPolicy(array $ctx, UserRole $rol): User
{
    return match ($rol) {
        UserRole::Coordinador => $ctx['coordinador'],
        UserRole::Director => $ctx['director'],
        UserRole::Estudiante => $ctx['estudiante'],
        UserRole::EvaluadorExterno => $ctx['evaluador'],
        default => throw new RuntimeException("Rol {$rol->value} sin usuario definido en el guardián #38."),
    };
}

function crearEntregaGuard(array $ctx, string $status = 'pendiente'): Entrega
{
    $entrega = Entrega::create([
        'semester_id' => $ctx['semestre']->id,
        'phase' => 'anteproyecto',
        'title' => 'Entrega guard',
        'due_date' => now()->addMonths(2)->toDateString(),
        'status' => $status,
    ]);
    // Production shape (StoreEntregaAction): project linked via pivot.
    $entrega->proyectos()->attach($ctx['proyecto']->id);

    return $entrega;
}

function crearVersionGuard(int $entregaId): VersionDocumento
{
    return VersionDocumento::create([
        'entrega_id' => $entregaId,
        'version_number' => 1,
        'file_path' => 'entregas/guard/v1.pdf',
        'file_size' => 1024,
        'original_name' => 'v1.pdf',
    ]);
}

function crearBitacoraGuard(array $ctx, string $signatureStatus = 'Pendiente'): Bitacora
{
    return Bitacora::create([
        'proyecto_id' => $ctx['proyecto']->id,
        'topic' => 'Bitácora guard',
        'meeting_date' => now()->toDateString(),
        'signature_status' => $signatureStatus,
    ]);
}

/**
 * Matriz de acceso por endpoint. `expected` usa UserRole::value como clave:
 * un rol nuevo sin entrada rompe el guardián con excepción.
 */
$guardSpecs = [
    // ---------------------------------------------------------- Entregas
    'entregas.show' => [
        'method' => 'getJson',
        'make' => fn (array $ctx): array => ['entrega' => crearEntregaGuard($ctx)],
        'url' => fn (array $ctx, array $r): string => "/api/admin/entregas/{$r['entrega']->id}",
        'expected' => ['Coordinador' => 200, 'Director' => 200, 'Estudiante' => 200, 'EvaluadorExterno' => 200],
    ],

    'entregas.versiones' => [
        'method' => 'getJson',
        'make' => fn (array $ctx): array => ['entrega' => crearEntregaGuard($ctx)],
        'url' => fn (array $ctx, array $r): string => "/api/entregas/{$r['entrega']->id}/versiones",
        'expected' => ['Coordinador' => 200, 'Director' => 200, 'Estudiante' => 200, 'EvaluadorExterno' => 200],
    ],

    'entregas.revisar' => [
        'method' => 'putJson',
        'make' => function (array $ctx): array {
            $entrega = crearEntregaGuard($ctx, 'enviada');
            $version = crearVersionGuard($entrega->id);

            return compact('entrega', 'version');
        },
        'url' => fn (array $ctx, array $r): string => "/api/admin/entregas/{$r['entrega']->id}/revisar",
        'payload' => fn (array $ctx, array $r): array => ['status' => 'revisada', 'version_id' => $r['version']->id],
        'expected' => ['Coordinador' => 403, 'Director' => 200, 'Estudiante' => 403, 'EvaluadorExterno' => 403],
    ],

    'entregas.habilitar' => [
        'method' => 'putJson',
        'make' => fn (array $ctx): array => ['entrega' => crearEntregaGuard($ctx, 'solicitada')],
        'url' => fn (array $ctx, array $r): string => "/api/admin/entregas/{$r['entrega']->id}/habilitar",
        'expected' => ['Coordinador' => 403, 'Director' => 200, 'Estudiante' => 403, 'EvaluadorExterno' => 403],
    ],

    'entregas.solicitar' => [
        'method' => 'postJson',
        'make' => fn (array $ctx): array => ['entrega' => crearEntregaGuard($ctx, 'creacion')],
        'url' => fn (array $ctx, array $r): string => "/api/entregas/{$r['entrega']->id}/solicitar",
        'expected' => ['Coordinador' => 403, 'Director' => 403, 'Estudiante' => 200, 'EvaluadorExterno' => 403],
    ],

    'entregas.destroy' => [
        'method' => 'deleteJson',
        'make' => fn (array $ctx): array => ['entrega' => crearEntregaGuard($ctx)],
        'url' => fn (array $ctx, array $r): string => "/api/admin/entregas/{$r['entrega']->id}",
        'expected' => ['Coordinador' => 200, 'Director' => 403, 'Estudiante' => 403, 'EvaluadorExterno' => 403],
    ],

    'entregas.finales' => [
        'method' => 'getJson',
        'make' => fn (array $ctx): array => [],
        'url' => fn (array $ctx, array $r): string => '/api/admin/entregas/finales',
        'expected' => ['Coordinador' => 200, 'Director' => 403, 'Estudiante' => 403, 'EvaluadorExterno' => 403],
    ],

    'entregas.eliminar_version' => [
        'method' => 'deleteJson',
        'make' => function (array $ctx): array {
            $entrega = crearEntregaGuard($ctx);
            $version = crearVersionGuard($entrega->id);

            return compact('entrega', 'version');
        },
        'url' => fn (array $ctx, array $r): string => "/api/entregas/{$r['entrega']->id}/versiones/{$r['version']->id}",
        'expected' => ['Coordinador' => 403, 'Director' => 403, 'Estudiante' => 200, 'EvaluadorExterno' => 403],
    ],

    'entregas.update' => [
        'method' => 'putJson',
        'make' => fn (array $ctx): array => ['entrega' => crearEntregaGuard($ctx)],
        'url' => fn (array $ctx, array $r): string => "/api/admin/entregas/{$r['entrega']->id}",
        'payload' => fn (array $ctx, array $r): array => ['titulo' => 'Actualizado'],
        'expected' => ['Coordinador' => 200, 'Director' => 403, 'Estudiante' => 403, 'EvaluadorExterno' => 403],
    ],

    'entregas.store' => [
        'method' => 'postJson',
        'make' => fn (array $ctx): array => [],
        'url' => fn (array $ctx, array $r): string => '/api/admin/entregas',
        'payload' => fn (array $ctx, array $r): array => [
            'grupo_id' => $ctx['semestre']->id,
            'fase' => 'anteproyecto',
            'titulo' => 'Entrega nueva',
            'descripcion' => 'Descripción de la entrega',
            'fecha_limite' => now()->addMonths(1)->toDateString(),
            'archivos_requeridos' => [
                ['id' => 'documento-proyecto', 'nombre' => 'Documento del proyecto', 'versionamiento' => true],
            ],
        ],
        'expected' => ['Coordinador' => 201, 'Director' => 403, 'Estudiante' => 403, 'EvaluadorExterno' => 403],
    ],

    // ---------------------------------------------------------- Bitácoras
    'bitacoras.index' => [
        'method' => 'getJson',
        'make' => fn (array $ctx): array => [],
        'url' => fn (array $ctx, array $r): string => '/api/bitacoras?proyecto_id='.$ctx['proyecto']->id,
        'expected' => ['Coordinador' => 200, 'Director' => 200, 'Estudiante' => 200, 'EvaluadorExterno' => 403],
    ],

    'bitacoras.show' => [
        'method' => 'getJson',
        'make' => fn (array $ctx): array => ['bitacora' => crearBitacoraGuard($ctx)],
        'url' => fn (array $ctx, array $r): string => "/api/bitacoras/{$r['bitacora']->id}",
        'expected' => ['Coordinador' => 200, 'Director' => 200, 'Estudiante' => 200, 'EvaluadorExterno' => 403],
    ],

    'bitacoras.store' => [
        'method' => 'postJson',
        'make' => fn (array $ctx): array => [],
        'url' => fn (array $ctx, array $r): string => '/api/bitacoras',
        'payload' => fn (array $ctx, array $r): array => [
            'proyecto_id' => $ctx['proyecto']->id,
            'topic' => 'Bitácora nueva',
            'meeting_date' => now()->toDateString(),
            'semana' => 1,
        ],
        'expected' => ['Coordinador' => 201, 'Director' => 201, 'Estudiante' => 201, 'EvaluadorExterno' => 403],
    ],

    'bitacoras.update' => [
        'method' => 'putJson',
        'make' => fn (array $ctx): array => ['bitacora' => crearBitacoraGuard($ctx)],
        'url' => fn (array $ctx, array $r): string => "/api/bitacoras/{$r['bitacora']->id}",
        'payload' => fn (array $ctx, array $r): array => ['topic' => 'Actualizada'],
        'expected' => ['Coordinador' => 200, 'Director' => 200, 'Estudiante' => 200, 'EvaluadorExterno' => 403],
    ],

    'bitacoras.firmar' => [
        'method' => 'postJson',
        'make' => function (array $ctx): array {
            $bitacora = crearBitacoraGuard($ctx, 'Pendiente');
            $plain = $bitacora->generateSignatureCode();

            return compact('bitacora', 'plain');
        },
        'url' => fn (array $ctx, array $r): string => "/api/bitacoras/{$r['bitacora']->id}/firmar",
        'payload' => fn (array $ctx, array $r): array => ['code' => $r['plain']],
        'expected' => ['Coordinador' => 403, 'Director' => 200, 'Estudiante' => 403, 'EvaluadorExterno' => 403],
    ],

    'bitacoras.re-solicitar' => [
        'method' => 'postJson',
        'make' => fn (array $ctx): array => ['bitacora' => crearBitacoraGuard($ctx, 'NoFirmada')],
        'url' => fn (array $ctx, array $r): string => "/api/bitacoras/{$r['bitacora']->id}/re-solicitar-codigo",
        'expected' => ['Coordinador' => 200, 'Director' => 200, 'Estudiante' => 200, 'EvaluadorExterno' => 403],
    ],
];

$guardCases = [];

foreach (UserRole::cases() as $rol) {
    foreach ($guardSpecs as $nombre => $spec) {
        $guardCases[] = [
            'rol' => $rol,
            'endpoint' => $nombre,
            'esperado' => $spec['expected'][$rol->value]
                ?? throw new RuntimeException(
                    "Rol {$rol->value} sin acceso definido en el endpoint {$nombre} del guardián #38."
                ),
        ];
    }
}

it('Policy: rol {rol} en {endpoint} → {esperado}', function (UserRole $rol, string $endpoint, int $esperado) use ($guardSpecs) {
    $spec = $guardSpecs[$endpoint];
    $ctx = contextoPolicyGuard();
    $usuario = usuarioDelRolPolicy($ctx, $rol);
    $recursos = ($spec['make'])($ctx);

    $url = ($spec['url'])($ctx, $recursos);
    $payload = isset($spec['payload']) ? ($spec['payload'])($ctx, $recursos) : [];

    $response = $this->actingAs($usuario)->{$spec['method']}($url, $payload);

    $response->assertStatus($esperado);
})->with($guardCases);
