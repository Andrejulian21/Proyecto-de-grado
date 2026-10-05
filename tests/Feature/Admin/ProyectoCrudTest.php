<?php

declare(strict_types=1);

use App\Enums\EstadoFirma;
use App\Enums\EstadoProyecto;
use App\Enums\FaseProyecto;
use App\Enums\TipoAlerta;
use App\Enums\UserRole;
use App\Models\Alerta;
use App\Models\Bitacora;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->coordinador = User::factory()->coordinador()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);
});

it('coordinador puede listar proyectos', function () {
    Proyecto::create([
        'title' => 'Proyecto Alpha',
        'semester_id' => $this->semestre->id,
    ]);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'code', 'title', 'current_phase', 'status']],
        ]);
    expect($response->json('data'))->toHaveCount(1);
});

it('coordinador puede crear proyecto con título, semestre y director opcional', function () {
    $director = User::factory()->director()->create();
    $payload = [
        'title' => 'Sistema de Gestión',
        'semester_id' => $this->semestre->id,
        'director_id' => $director->id,
    ];

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/proyectos', $payload);

    $response->assertCreated()
        ->assertJson(['data' => ['title' => 'Sistema de Gestión']]);
    expect(Proyecto::count())->toBe(1);
});

it('proyecto creado tiene código auto-generado (PG-20261001)', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/proyectos', [
            'title' => 'IA para cultivos',
            'semester_id' => $this->semestre->id,
        ]);

    $response->assertCreated();
    $code = $response->json('data.code');
    expect($code)->toMatch('/^PG-20261\d{3}$/');
});

it('asociar 1-3 estudiantes al proyecto', function () {
    $estudiantes = User::factory()->count(2)->create(['role' => UserRole::Estudiante->value]);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/proyectos', [
            'title' => 'Proyecto con estudiantes',
            'semester_id' => $this->semestre->id,
            'student_ids' => $estudiantes->pluck('id')->toArray(),
        ]);

    $response->assertCreated();
    $proyecto = Proyecto::find($response->json('data.id'));
    expect($proyecto->estudiantes)->toHaveCount(2);
});

it('3 estudiantes requiere requires_group_justification=true', function () {
    $estudiantes = User::factory()->count(3)->create(['role' => UserRole::Estudiante->value]);

    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/proyectos', [
            'title' => 'Proyecto grupal',
            'semester_id' => $this->semestre->id,
            'student_ids' => $estudiantes->pluck('id')->toArray(),
        ]);

    $response->assertCreated();
    $proyecto = Proyecto::find($response->json('data.id'));
    expect($proyecto->requires_group_justification)->toBeTrue();
});

it('estudiante NO puede crear proyecto (403)', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/admin/proyectos', [
            'title' => 'Hack',
            'semester_id' => $this->semestre->id,
        ]);

    $response->assertStatus(403);
});

// -- KPIs -----------------------------------------------------------------

/**
 * One overdue delivery with nothing uploaded on the pivot — the exact state
 * `entrega_vencida` (R2) describes.
 *
 * Alerts are never seeded by hand here: `kpis()` calls the real
 * `AlertaGenerator`, which reconciles and deletes every row whose `clave` is no
 * longer vigente, so a fabricated alert would simply be erased by the request.
 */
function crearEntregaVencidaPara(int $semestreId, int $proyectoId, string $titulo = 'Anteproyecto vencido'): EntregaProyecto
{
    $entrega = Entrega::create([
        'semester_id' => $semestreId,
        'phase' => 'anteproyecto',
        'title' => $titulo,
        'description' => 'Descripción del anteproyecto.',
        'due_date' => now()->subDays(3)->toDateString(),
        'hora_maxima' => null,
        'status' => 'pendiente',
        'evaluation_complete' => false,
    ]);

    return EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $proyectoId,
    ]);
}

it('kpis endpoint devuelve estructura correcta con 3 campos', function () {
    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos/kpis');

    $response->assertOk()
        ->assertJsonStructure([
            'proyectos_activos',
            'en_riesgo',
            'alertas_sin_revisar',
        ]);
});

it('el KPI ya no expone tasa_cumplimiento', function () {
    Proyecto::create([
        'title' => 'Proyecto completado',
        'semester_id' => $this->semestre->id,
        'status' => EstadoProyecto::Completado->value,
    ]);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos/kpis');

    $response->assertOk();
    expect($response->json())->not->toHaveKey('tasa_cumplimiento');
});

it('en_riesgo cuenta un proyecto con una entrega vencida sin entregar', function () {
    $proyecto = Proyecto::create([
        'title' => 'Proyecto con entrega vencida',
        'semester_id' => $this->semestre->id,
    ]);

    crearEntregaVencidaPara($this->semestre->id, $proyecto->id);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos/kpis');

    $response->assertOk();
    expect($response->json('en_riesgo'))->toBeGreaterThanOrEqual(1);
});

it('en_riesgo no cuenta alertas de bitacora sin firmar', function () {
    // Decisión de negocio: una bitácora sin firmar es un incumplimiento de
    // FIRMA, no de ENTREGA. Sólo `entrega_vencida` pone un proyecto en riesgo.
    $proyecto = Proyecto::create([
        'title' => 'Proyecto con bitácora sin firmar',
        'semester_id' => $this->semestre->id,
    ]);

    $bitacora = Bitacora::factory()->create([
        'proyecto_id' => $proyecto->id,
        'signature_status' => EstadoFirma::Pendiente->value,
    ]);
    $bitacora->forceFill(['created_at' => now()->subHours(2)])->save();

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos/kpis');

    $response->assertOk();
    // La alerta R1 existe de verdad: sin esta comprobación, `en_riesgo === 0`
    // pasaría igual si el generador no hubiera creado nada.
    expect(Alerta::where('tipo', TipoAlerta::BitacoraSinFirmar->value)->count())->toBe(1);
    expect($response->json('alertas_sin_revisar'))->toBe(1);
    expect($response->json('en_riesgo'))->toBe(0);
});

it('en_riesgo no cuenta alertas de firmas sospechosas', function () {
    $director = User::factory()->director()->create();

    $proyecto = Proyecto::create([
        'title' => 'Proyecto con firmas sospechosas',
        'semester_id' => $this->semestre->id,
        'director_id' => $director->id,
    ]);

    // Ancla en una hora exacta para que la ventana de 60 min sea determinista.
    $this->travelTo(now()->startOfHour()->addHours(2));

    foreach ([50, 10] as $minutosAtras) {
        Bitacora::factory()->create([
            'proyecto_id' => $proyecto->id,
            'signature_status' => EstadoFirma::FirmadaDirector->value,
            'director_signed_at' => now()->subMinutes($minutosAtras),
        ]);
    }

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos/kpis');

    $response->assertOk();
    // La alerta R3 existe de verdad. Además R3 nunca apunta a un proyecto
    // (`proyecto_id = null`): este test blinda el filtro por `tipo`.
    expect(Alerta::where('tipo', TipoAlerta::FirmasSospechosas->value)->count())->toBe(1);
    expect($response->json('en_riesgo'))->toBe(0);

    $this->travelBack();
});

it('en_riesgo ignora que la alerta fue revisada', function () {
    // Descartar una alerta significa "ya la vi", NO "ya está bien". El proyecto
    // sigue en riesgo hasta que la alerta desaparezca, y el generador sólo la
    // borra cuando el documento se sube. Por eso no se filtra por `reviewed_at`.
    $proyecto = Proyecto::create([
        'title' => 'Proyecto con entrega vencida ya revisada',
        'semester_id' => $this->semestre->id,
    ]);

    crearEntregaVencidaPara($this->semestre->id, $proyecto->id);

    // Primera llamada: el generador crea la alerta `entrega_vencida`.
    $this->actingAs($this->coordinador)->getJson('/api/admin/proyectos/kpis');

    $alerta = Alerta::where('tipo', TipoAlerta::EntregaVencida->value)->firstOrFail();
    expect($alerta->estaRevisada())->toBeFalse();

    $alerta->forceFill(['reviewed_at' => now(), 'reviewed_by' => $this->coordinador->id])->save();

    // Segunda llamada: la alerta sigue vigente, así que el generador la conserva
    // (regenerar nunca borra `reviewed_at`) y el KPI debe seguir contando.
    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos/kpis');

    $response->assertOk();
    expect(Alerta::where('tipo', TipoAlerta::EntregaVencida->value)->firstOrFail()->estaRevisada())->toBeTrue();
    expect($response->json('en_riesgo'))->toBeGreaterThanOrEqual(1);
    expect($response->json('alertas_sin_revisar'))->toBe(0);
});

it('en_riesgo cuenta una sola vez un proyecto con varias entregas vencidas', function () {
    $proyecto = Proyecto::create([
        'title' => 'Proyecto con varias entregas vencidas',
        'semester_id' => $this->semestre->id,
    ]);

    crearEntregaVencidaPara($this->semestre->id, $proyecto->id, 'Anteproyecto vencido');
    crearEntregaVencidaPara($this->semestre->id, $proyecto->id, 'Documentación vencida');
    crearEntregaVencidaPara($this->semestre->id, $proyecto->id, 'Avance vencido');

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos/kpis');

    $response->assertOk();
    // Hay 3 alertas distintas, pero el KPI cuenta PROYECTOS, no alertas.
    expect(Alerta::where('tipo', TipoAlerta::EntregaVencida->value)->count())->toBe(3);
    expect($response->json('en_riesgo'))->toBe(1);
});

it('en_riesgo no cuenta proyectos de semestres cerrados', function () {
    $semestreCerrado = Semestre::create([
        'name' => '2025-2',
        'start_date' => '2025-08-01',
        'end_date' => '2025-12-31',
        'is_active' => false,
    ]);

    $proyecto = Proyecto::create([
        'title' => 'Proyecto de semestre cerrado',
        'semester_id' => $semestreCerrado->id,
    ]);

    crearEntregaVencidaPara($semestreCerrado->id, $proyecto->id);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos/kpis');

    $response->assertOk();
    // La alerta SÍ existe (el generador no mira el semestre) pero el KPI filtra
    // por semestre activo, igual que `proyectos_activos`.
    expect(Alerta::where('tipo', TipoAlerta::EntregaVencida->value)->count())->toBe(1);
    expect($response->json('en_riesgo'))->toBe(0);
});

it('kpis reflejan proyectos creados', function () {
    Proyecto::create([
        'title' => 'Proyecto en curso',
        'semester_id' => $this->semestre->id,
        'status' => EstadoProyecto::EnCurso->value,
    ]);
    Proyecto::create([
        'title' => 'Proyecto completado',
        'semester_id' => $this->semestre->id,
        'status' => EstadoProyecto::Completado->value,
    ]);
    $conBitacora = Proyecto::create([
        'title' => 'Proyecto con bitácora sin firmar',
        'semester_id' => $this->semestre->id,
    ]);

    // `alertas_sin_revisar` ya no lee `proyectos.alert_count` (que no tenía
    // escritor en toda la app y por eso valía siempre 0): cuenta las alertas
    // reales sin revisar. Este test siembra una para conservar la cobertura
    // del KPI; la cobertura completa vive en tests/Feature/AlertasTest.php.
    $bitacora = Bitacora::factory()->create([
        'proyecto_id' => $conBitacora->id,
        'signature_status' => EstadoFirma::Pendiente->value,
    ]);
    $bitacora->forceFill(['created_at' => now()->subHours(2)])->save();

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos/kpis');

    $response->assertOk();
    expect($response->json('proyectos_activos'))->toBe(2);
    expect($response->json('alertas_sin_revisar'))->toBe(1);
    // `en_riesgo` ya NO se deriva de `proyectos.status`: ese campo no lo escribe
    // ningún código de la app, por eso la tarjeta valía siempre 0. Ahora viene
    // de las alertas `entrega_vencida` reales (cubierto por los tests de arriba).
    expect($response->json('en_riesgo'))->toBe(0);
});

it('proyectos_activos y alertas_sin_revisar siguen funcionando', function () {
    $enCurso = Proyecto::create([
        'title' => 'Proyecto en curso',
        'semester_id' => $this->semestre->id,
        'status' => EstadoProyecto::EnCurso->value,
    ]);
    Proyecto::create([
        'title' => 'Proyecto completado',
        'semester_id' => $this->semestre->id,
        'status' => EstadoProyecto::Completado->value,
    ]);

    $bitacora = Bitacora::factory()->create([
        'proyecto_id' => $enCurso->id,
        'signature_status' => EstadoFirma::Pendiente->value,
    ]);
    $bitacora->forceFill(['created_at' => now()->subHours(2)])->save();

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos/kpis');

    $response->assertOk();
    // El completado se excluye de `proyectos_activos`.
    expect($response->json('proyectos_activos'))->toBe(1);
    expect($response->json('alertas_sin_revisar'))->toBe(1);
    expect($response->json('en_riesgo'))->toBe(0);
});

it('kpis solo consideran semestres activos', function () {
    $inactivo = Semestre::create([
        'name' => '2025-2',
        'start_date' => '2025-08-01',
        'end_date' => '2025-12-31',
        'is_active' => false,
    ]);
    Proyecto::create([
        'title' => 'Proyecto en semestre inactivo',
        'semester_id' => $inactivo->id,
    ]);

    $response = $this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos/kpis');

    $response->assertOk();
    expect($response->json('proyectos_activos'))->toBe(0);
});

it('fase y estado son los enums correctos', function () {
    $response = $this->actingAs($this->coordinador)
        ->postJson('/api/admin/proyectos', [
            'title' => 'Verificación enums',
            'semester_id' => $this->semestre->id,
        ]);

    $response->assertCreated();
    $proyecto = Proyecto::find($response->json('data.id'));
    expect($proyecto->current_phase)->toBe(FaseProyecto::Anteproyecto);
    expect($proyecto->current_phase->value)->toBe('anteproyecto');
    expect($proyecto->status)->toBe(EstadoProyecto::EnCurso);
    expect($proyecto->status->value)->toBe('en_curso');
});
