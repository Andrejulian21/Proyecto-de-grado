<?php

declare(strict_types=1);

use App\Enums\EstadoFirma;
use App\Enums\SeveridadAlerta;
use App\Enums\TipoAlerta;
use App\Enums\UserRole;
use App\Models\Alerta;
use App\Models\Bitacora;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use App\Models\VersionDocumento;
use App\Services\Alertas\AlertaGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Alertas como módulo backend.
 *
 * Antes estas alertas se derivaban en el navegador desde dos endpoints
 * genéricos, se reconstruían en cada mount, no tenían estado y no se podían
 * descartar. Este archivo fija el comportamiento del servicio en backend:
 * idempotencia por clave estable, aislamiento por proyecto en R2, umbral
 * de 2 firmas en 60 minutos en R3, y una KPI que cuenta la verdad.
 */
beforeEach(function () {
    $this->semestre = Semestre::factory()->create(['is_active' => true]);

    $this->coordinador = User::factory()->coordinador()->create();
    $this->director = User::factory()->director()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->proyecto = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);

    $this->generar = fn () => app(AlertaGenerator::class)->generar();

    /**
     * One unsigned bitácora past the signing window + the alert it generates.
     * Used by every test whose subject is "an alert exists".
     */
    $this->generarAlertaPendiente = function (): void {
        $bitacora = Bitacora::factory()->create([
            'proyecto_id' => $this->proyecto->id,
            'signature_status' => EstadoFirma::Pendiente->value,
        ]);
        $bitacora->forceFill(['created_at' => now()->subHours(2)])->save();

        ($this->generar)();

        $this->alertaPendiente = Alerta::firstOrFail();
    };
});

/**
 * Entrega vencida: el plazo ya pasó por días completos y la habilitación
 * existe (status distinto de `solicitada`), que es exactamente la semántica
 * de `EstadoEntregaNota::NoEntregada`.
 */
function entregaVencida(int $semestreId, string $status = 'pendiente', ?string $horaMaxima = null): Entrega
{
    return Entrega::create([
        'semester_id' => $semestreId,
        'phase' => 'anteproyecto',
        'title' => 'Anteproyecto vencido',
        'description' => 'Descripción del anteproyecto.',
        'due_date' => now()->subDays(3)->toDateString(),
        'hora_maxima' => $horaMaxima,
        'status' => $status,
        'evaluation_complete' => false,
    ]);
}

function versionEnPivote(Entrega $entrega, EntregaProyecto $pivot): VersionDocumento
{
    return VersionDocumento::create([
        'entrega_id' => $entrega->id,
        'entrega_proyecto_id' => $pivot->id,
        'archivo_requerido_id' => 'documento-proyecto',
        'version_number' => 1,
        'file_path' => 'documentos/ok.pdf',
        'file_size' => 1024,
        'original_name' => 'ok.pdf',
        'uploaded_at' => now(),
    ]);
}

// -- R1: bitácora sin firmar -------------------------------------------

it('R1 genera exactamente una alerta para una bitacora sin firmar de mas de 1 hora', function () {
    $bitacora = Bitacora::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'signature_status' => EstadoFirma::Pendiente->value,
    ]);
    $bitacora->forceFill(['created_at' => now()->subHours(2)])->save();

    ($this->generar)();

    $alertas = Alerta::where('tipo', TipoAlerta::BitacoraSinFirmar->value)->get();

    expect($alertas)->toHaveCount(1);
    expect($alertas->first()->clave)->toBe("bitacora_sin_firmar:{$bitacora->id}");
    expect($alertas->first()->proyecto_id)->toBe($this->proyecto->id);
});

it('R1 sube la severidad a alta cuando la bitacora supera las 24 horas', function () {
    $bitacora = Bitacora::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'signature_status' => EstadoFirma::Pendiente->value,
    ]);
    $bitacora->forceFill(['created_at' => now()->subHours(30)])->save();

    ($this->generar)();

    $alerta = Alerta::where('tipo', TipoAlerta::BitacoraSinFirmar->value)->first();

    expect($alerta)->not->toBeNull();
    expect($alerta->severidad)->toBe(SeveridadAlerta::Alta);
});

it('es idempotente: correr la generacion dos veces no duplica alertas', function () {
    $bitacora = Bitacora::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'signature_status' => EstadoFirma::Pendiente->value,
    ]);
    $bitacora->forceFill(['created_at' => now()->subHours(3)])->save();

    ($this->generar)();
    ($this->generar)();

    expect(Alerta::where('tipo', TipoAlerta::BitacoraSinFirmar->value)->count())->toBe(1);
    expect(Alerta::where('clave', "bitacora_sin_firmar:{$bitacora->id}")->count())->toBe(1);
});

it('R1 negativo: una bitacora de menos de 1 hora NO genera alerta', function () {
    $bitacora = Bitacora::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'signature_status' => EstadoFirma::Pendiente->value,
    ]);
    $bitacora->forceFill(['created_at' => now()->subMinutes(20)])->save();

    ($this->generar)();

    expect(Alerta::where('tipo', TipoAlerta::BitacoraSinFirmar->value)->count())->toBe(0);
});

it('R1 negativo: una bitacora ya firmada por el director NO genera alerta', function () {
    $bitacora = Bitacora::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'signature_status' => EstadoFirma::FirmadaDirector->value,
        'director_signed_at' => now()->subHours(2),
    ]);
    $bitacora->forceFill(['created_at' => now()->subHours(5)])->save();

    ($this->generar)();

    expect(Alerta::where('tipo', TipoAlerta::BitacoraSinFirmar->value)->count())->toBe(0);
});

// -- R2: entrega vencida sin envio --------------------------------------

it('R2 genera alerta cuando la entrega vencio sin versiones en el pivote', function () {
    $entrega = entregaVencida($this->semestre->id);

    $pivot = EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $this->proyecto->id,
    ]);

    ($this->generar)();

    $alerta = Alerta::where('tipo', TipoAlerta::EntregaVencida->value)->first();

    expect($alerta)->not->toBeNull();
    expect($alerta->clave)->toBe("entrega_vencida:{$pivot->id}");
    expect($alerta->proyecto_id)->toBe($this->proyecto->id);
});

it('R2 no genera alerta si el proyecto si subio una version', function () {
    $entrega = entregaVencida($this->semestre->id);

    $pivot = EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $this->proyecto->id,
    ]);

    versionEnPivote($entrega, $pivot);

    ($this->generar)();

    expect(Alerta::where('tipo', TipoAlerta::EntregaVencida->value)->count())->toBe(0);
});

it('R2 respeta hora_maxima: una entrega de hoy con hora_maxima ya vencida si genera alerta', function () {
    // Plazo de HOY: sin `hora_maxima` NO estaria vencido (se comparan dias
    // completos), pero con `hora_maxima = 00:00` la ventana ya cerro.
    $entrega = Entrega::create([
        'semester_id' => $this->semestre->id,
        'phase' => 'anteproyecto',
        'title' => 'Anteproyecto de hoy',
        'description' => 'Descripción del anteproyecto.',
        'due_date' => now()->toDateString(),
        'hora_maxima' => '00:00',
        'status' => 'pendiente',
        'evaluation_complete' => false,
    ]);

    EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $this->proyecto->id,
    ]);

    ($this->generar)();

    expect(Alerta::where('tipo', TipoAlerta::EntregaVencida->value)->count())->toBe(1);
});

it('R2 no genera alerta si la entrega nunca fue habilitada (NoIniciada, no NoEntregada)', function () {
    $entrega = entregaVencida($this->semestre->id, 'solicitada');

    EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $this->proyecto->id,
    ]);

    ($this->generar)();

    expect(Alerta::where('tipo', TipoAlerta::EntregaVencida->value)->count())->toBe(0);
});

it('R2 aislamiento: con dos proyectos sobre la misma entrega solo se alerta al que no subio', function () {
    $proyectoB = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);

    $entrega = entregaVencida($this->semestre->id);

    $pivotA = EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $this->proyecto->id,
    ]);

    $pivotB = EntregaProyecto::create([
        'entrega_id' => $entrega->id,
        'proyecto_id' => $proyectoB->id,
    ]);

    // Solo el proyecto A subio. La entrega es la misma plantilla.
    versionEnPivote($entrega, $pivotA);

    ($this->generar)();

    $alertas = Alerta::where('tipo', TipoAlerta::EntregaVencida->value)->get();

    expect($alertas)->toHaveCount(1);
    expect($alertas->first()->clave)->toBe("entrega_vencida:{$pivotB->id}");
    expect($alertas->first()->proyecto_id)->toBe($proyectoB->id);
});

// -- R3: firmas sospechosas ---------------------------------------------

it('R3 genera alerta con 2 firmas del mismo director dentro de 60 minutos', function () {
    // Ancla en una hora exacta para que la ventana de 60 minutos sea
    // determinista y no dependa del minuto en que corra el test.
    $this->travelTo(now()->startOfHour()->addHours(2));

    foreach ([50, 10] as $minutosAtras) {
        Bitacora::factory()->create([
            'proyecto_id' => $this->proyecto->id,
            'signature_status' => EstadoFirma::FirmadaDirector->value,
            'director_signed_at' => now()->subMinutes($minutosAtras),
        ]);
    }

    ($this->generar)();

    $alerta = Alerta::where('tipo', TipoAlerta::FirmasSospechosas->value)->first();

    expect($alerta)->not->toBeNull();
    expect($alerta->severidad)->toBe(SeveridadAlerta::Media);
    expect($alerta->clave)->toStartWith("firmas_sospechosas:{$this->director->id}:");
    expect($alerta->mensaje)->toContain($this->director->name);
    expect($alerta->mensaje)->toContain('2');

    $this->travelBack();
});

it('R3 negativo: 2 firmas separadas por mas de 60 minutos NO generan alerta', function () {
    $this->travelTo(now()->startOfHour()->addHours(4));

    foreach ([120, 30] as $minutosAtras) {
        Bitacora::factory()->create([
            'proyecto_id' => $this->proyecto->id,
            'signature_status' => EstadoFirma::FirmadaDirector->value,
            'director_signed_at' => now()->subMinutes($minutosAtras),
        ]);
    }

    ($this->generar)();

    expect(Alerta::where('tipo', TipoAlerta::FirmasSospechosas->value)->count())->toBe(0);

    $this->travelBack();
});

it('R3 no mezcla firmas de directores distintos', function () {
    $otroDirector = User::factory()->director()->create();

    $proyectoB = Proyecto::factory()->create([
        'semester_id' => $this->semestre->id,
        'director_id' => $otroDirector->id,
    ]);

    $this->travelTo(now()->startOfHour()->addHours(6));

    Bitacora::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'signature_status' => EstadoFirma::FirmadaDirector->value,
        'director_signed_at' => now()->subMinutes(30),
    ]);

    Bitacora::factory()->create([
        'proyecto_id' => $proyectoB->id,
        'signature_status' => EstadoFirma::FirmadaDirector->value,
        'director_signed_at' => now()->subMinutes(20),
    ]);

    ($this->generar)();

    expect(Alerta::where('tipo', TipoAlerta::FirmasSospechosas->value)->count())->toBe(0);

    $this->travelBack();
});

// -- Endpoints ----------------------------------------------------------

it('el listado devuelve solo las alertas no revisadas por defecto', function () {
    $bitacora = Bitacora::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'signature_status' => EstadoFirma::Pendiente->value,
    ]);
    $bitacora->forceFill(['created_at' => now()->subHours(2)])->save();

    $response = $this->actingAs($this->coordinador)->getJson('/api/admin/alertas');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'clave', 'tipo', 'mensaje', 'proyecto_id', 'severidad', 'datos', 'reviewed_at', 'reviewed_by']]]);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.clave'))->toBe("bitacora_sin_firmar:{$bitacora->id}");
});

it('marcar como revisada saca la alerta del listado por defecto y baja la KPI', function () {
    $bitacora = Bitacora::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'signature_status' => EstadoFirma::Pendiente->value,
    ]);
    $bitacora->forceFill(['created_at' => now()->subHours(2)])->save();

    $this->actingAs($this->coordinador)->getJson('/api/admin/alertas')->assertOk();

    $alerta = Alerta::firstOrFail();

    expect($this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos/kpis')
        ->json('alertas_sin_revisar'))->toBe(1);

    $response = $this->actingAs($this->coordinador)
        ->patchJson("/api/admin/alertas/{$alerta->id}/revisar");

    $response->assertOk()->assertJsonPath('data.reviewed_at', fn ($v) => $v !== null);

    expect($this->actingAs($this->coordinador)->getJson('/api/admin/alertas')->json('data'))->toHaveCount(0);

    expect($this->actingAs($this->coordinador)
        ->getJson('/api/admin/alertas?revisadas=todas')
        ->json('data'))->toHaveCount(1);

    expect($this->actingAs($this->coordinador)
        ->getJson('/api/admin/proyectos/kpis')
        ->json('alertas_sin_revisar'))->toBe(0);
});

it('marcar como revisada es idempotente', function () {
    $bitacora = Bitacora::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'signature_status' => EstadoFirma::Pendiente->value,
    ]);
    $bitacora->forceFill(['created_at' => now()->subHours(2)])->save();

    $this->actingAs($this->coordinador)->getJson('/api/admin/alertas');

    $alerta = Alerta::firstOrFail();

    $this->actingAs($this->coordinador)->patchJson("/api/admin/alertas/{$alerta->id}/revisar")->assertOk();
    $primera = $alerta->fresh()->reviewed_at;

    $this->actingAs($this->coordinador)->patchJson("/api/admin/alertas/{$alerta->id}/revisar")->assertOk();

    expect($alerta->fresh()->reviewed_at->toIso8601String())->toBe($primera->toIso8601String());
});

it('revisar una alerta inexistente devuelve 404', function () {
    $this->actingAs($this->coordinador)
        ->patchJson('/api/admin/alertas/999999/revisar')
        ->assertNotFound();
});

it('un director no puede listar ni revisar alertas', function () {
    // Se genera una alerta real para que el 403 pruebe el rol y no un 404 por
    // route model binding sobre un id inexistente.
    ($this->generarAlertaPendiente)();

    $this->actingAs($this->director)->getJson('/api/admin/alertas')->assertForbidden();
    $this->actingAs($this->director)
        ->patchJson("/api/admin/alertas/{$this->alertaPendiente->id}/revisar")
        ->assertForbidden();
});

it('un estudiante no puede listar ni revisar alertas', function () {
    ($this->generarAlertaPendiente)();

    $this->actingAs($this->estudiante)->getJson('/api/admin/alertas')->assertForbidden();
    $this->actingAs($this->estudiante)
        ->patchJson("/api/admin/alertas/{$this->alertaPendiente->id}/revisar")
        ->assertForbidden();
});

// -- Regresion de la KPI -------------------------------------------------

it('la KPI alertas_sin_revisar coincide con el conteo del listado', function () {
    foreach ([2, 5, 9] as $horas) {
        $bitacora = Bitacora::factory()->create([
            'proyecto_id' => $this->proyecto->id,
            'signature_status' => EstadoFirma::Pendiente->value,
        ]);
        $bitacora->forceFill(['created_at' => now()->subHours($horas)])->save();
    }

    $listado = $this->actingAs($this->coordinador)->getJson('/api/admin/alertas');
    $listado->assertOk();

    $kpi = $this->actingAs($this->coordinador)->getJson('/api/admin/proyectos/kpis');

    expect($listado->json('data'))->toHaveCount(3);
    expect($kpi->json('alertas_sin_revisar'))->toBe(count($listado->json('data')));
});
