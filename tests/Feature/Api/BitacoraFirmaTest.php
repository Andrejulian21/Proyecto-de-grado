<?php

declare(strict_types=1);

use App\Enums\EstadoFirma;
use App\Enums\UserRole;
use App\Models\Bitacora;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);

    $this->director = User::factory()->director()->create();
    $this->estudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);
    $this->otroEstudiante = User::factory()->create(['role' => UserRole::Estudiante->value]);

    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto Test',
        'semester_id' => $this->semestre->id,
        'director_id' => $this->director->id,
    ]);
    $this->proyecto->estudiantes()->attach($this->estudiante->id);

});

/**
 * Helper: create a bitacora and return both the model and the plain
 * signature code, so each test can call /firmar with whatever it needs.
 */
function makeBitacoraConCodigo(Proyecto $proyecto, mixed $expiresAt = null): array
{
    $bitacora = Bitacora::create([
        'proyecto_id' => $proyecto->id,
        'topic' => 'Para firmar',
        'meeting_date' => '2026-04-01',
    ]);

    $plain = (string) random_int(100_000, 999_999);
    $bitacora->signature_code = Hash::make($plain);
    $bitacora->signature_code_expires_at = $expiresAt ?? now()->addMinutes(2);
    $bitacora->save();

    return [$bitacora, $plain];
}

// -- STORE: codigo devuelto al crear ------------------------------------

it('store genera un codigo hasheado y devuelve el plain text en la respuesta', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Nueva bitacora',
            'notes' => 'Avances de la semana',
            'meeting_date' => '2026-04-10',
            'duration_hours' => 1.5,
            'semana' => 1,
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'topic', 'signature_code_plain', 'signature_code_expires_at']]);

    $plain = $response->json('data.signature_code_plain');
    expect($plain)->toBeString()
        ->and(strlen($plain))->toBe(6)
        ->and(ctype_digit($plain))->toBeTrue();

    $bitacora = Bitacora::find($response->json('data.id'));
    expect($bitacora->signature_code)->not->toBeNull()
        ->and(Hash::check($plain, $bitacora->signature_code))->toBeTrue()
        ->and($bitacora->signature_code_expires_at->isFuture())->toBeTrue();
});

it('el codigo persistido en BD esta hasheado, no en texto plano', function () {
    $response = $this->actingAs($this->estudiante)
        ->postJson('/api/bitacoras', [
            'proyecto_id' => $this->proyecto->id,
            'topic' => 'Hash check',
            'meeting_date' => '2026-04-10',
            'semana' => 1,
        ]);
    $response->assertCreated();

    $plain = $response->json('data.signature_code_plain');
    $bitacora = Bitacora::find($response->json('data.id'));

    // The DB column must never store the plain digits.
    expect($bitacora->signature_code)->not->toBe($plain)
        ->and($bitacora->signature_code)->not->toContain($plain)
        ->and(strlen((string) $bitacora->signature_code))->toBeGreaterThan(20) // bcrypt hash length
        ->and(Hash::check($plain, $bitacora->signature_code))->toBeTrue();
});

// -- FIRMAR: exito -----------------------------------------------------

it('firmar con codigo correcto transiciona a FirmadaDirector y registra director_signed_at', function () {
    [$bitacora, $plain] = makeBitacoraConCodigo($this->proyecto);

    $response = $this->actingAs($this->director)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar", [
            'code' => $plain,
        ]);

    $response->assertOk();
    $b = $bitacora->fresh();
    expect($b->signature_status)->toBe(EstadoFirma::FirmadaDirector)
        ->and($b->director_signed_at)->not->toBeNull();
});

// -- FIRMAR: autorizacion (issue #45) ----------------------------------

it('un estudiante no puede firmar la bitacora de su propio proyecto', function () {
    [$bitacora, $plain] = makeBitacoraConCodigo($this->proyecto);

    $this->actingAs($this->estudiante)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar", ['code' => $plain])
        ->assertForbidden();
});

it('un director ajeno al proyecto no puede firmar la bitacora', function () {
    [$bitacora, $plain] = makeBitacoraConCodigo($this->proyecto);
    $directorAjeno = User::factory()->director()->create();

    $this->actingAs($directorAjeno)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar", ['code' => $plain])
        ->assertForbidden();
});

it('la firma exitosa queda registrada en audit_logs', function () {
    [$bitacora, $plain] = makeBitacoraConCodigo($this->proyecto);

    $this->actingAs($this->director)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar", ['code' => $plain])
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->director->id,
        'action' => 'bitacora.firmada',
        'metadata->bitacora_id' => $bitacora->id,
    ]);
});

it('el contador de intentos de firma es por bitacora y por usuario', function () {
    [$bitacora] = makeBitacoraConCodigo($this->proyecto);
    $otroDirector = User::factory()->director()->create();

    // Dos fallos del director real por HTTP: su clave acumula los intentos
    foreach ([1, 2] as $i) {
        $this->actingAs($this->director)
            ->postJson("/api/bitacoras/{$bitacora->id}/firmar", ['code' => '000000'])
            ->assertStatus(422);
    }

    expect(RateLimiter::attempts('firmar:'.$bitacora->id.':'.$this->director->id))->toBe(2)
        ->and(RateLimiter::attempts('firmar:'.$bitacora->id.':'.$otroDirector->id))->toBe(0);
});

it('agotar los intentos desde una cuenta no afecta los intentos de otra sobre la misma bitacora', function () {
    [$bitacora, $plain] = makeBitacoraConCodigo($this->proyecto);

    // Un tercero (estudiante ajeno al proyecto) intenta firmar 5 veces:
    // siempre 403, sus intentos nunca consumen el presupuesto del director.
    for ($i = 1; $i <= 5; $i++) {
        $this->actingAs($this->otroEstudiante)
            ->postJson("/api/bitacoras/{$bitacora->id}/firmar", ['code' => '000000'])
            ->assertForbidden();
    }

    // El director conserva su presupuesto intacto y firma correctamente.
    $this->actingAs($this->director)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar", ['code' => $plain])
        ->assertOk();
});

// -- FIRMAR: codigo expirado -------------------------------------------

it('firmar despues de 2 minutos transiciona a NoFirmada y devuelve 422', function () {
    [$bitacora, $plain] = makeBitacoraConCodigo($this->proyecto, now()->subSecond());

    $response = $this->actingAs($this->director)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar", [
            'code' => $plain,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('data.signature_status', 'NoFirmada');

    expect($bitacora->fresh()->signature_status)->toBe(EstadoFirma::NoFirmada);
});

// -- FIRMAR: bitacora en estado no-Pendiente ---------------------------

it('firmar una bitacora ya firmada devuelve 422', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Ya firmada',
        'meeting_date' => '2026-04-01',
        'signature_status' => EstadoFirma::FirmadaDirector,
        'director_signed_at' => now(),
    ]);

    $response = $this->actingAs($this->director)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar", [
            'code' => '123456',
        ]);

    $response->assertStatus(422);
});

it('firmar una bitacora inexistente devuelve 404', function () {
    $response = $this->actingAs($this->director)
        ->postJson('/api/bitacoras/999999/firmar', [
            'code' => '123456',
        ]);

    $response->assertStatus(404);
});

it('firmar requiere autenticacion', function () {
    [$bitacora] = makeBitacoraConCodigo($this->proyecto);

    $response = $this->postJson("/api/bitacoras/{$bitacora->id}/firmar", [
        'code' => '123456',
    ]);

    $response->assertStatus(401);
});

it('firmar requiere el campo code', function () {
    [$bitacora] = makeBitacoraConCodigo($this->proyecto);

    $response = $this->actingAs($this->director)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

// -- FIRMAR: rate limiter ---------------------------------------------

it('firmar con 5 codigos incorrectos transiciona a NoFirmada y bloquea el 6to intento', function () {
    [$bitacora] = makeBitacoraConCodigo($this->proyecto);

    for ($i = 1; $i <= 5; $i++) {
        $wrongCode = str_pad((string) (100_000 + $i), 6, '0', STR_PAD_LEFT);
        $response = $this->actingAs($this->director)
            ->postJson("/api/bitacoras/{$bitacora->id}/firmar", [
                'code' => $wrongCode,
            ]);
        $response->assertStatus(422);
    }

    $b = $bitacora->fresh();
    expect($b->signature_status)->toBe(EstadoFirma::NoFirmada);

    // 6th attempt: even with the correct code, the bitacora is already
    // NoFirmada so it must be rejected (and the correct code alone is
    // no longer enough).
    $plain = (string) random_int(100_000, 999_999);
    $bitacora->signature_code = Hash::make($plain);
    $bitacora->signature_code_expires_at = now()->addMinutes(2);
    $bitacora->save();

    $response = $this->actingAs($this->director)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar", [
            'code' => $plain,
        ]);
    $response->assertStatus(422);
});

it('el rate limiter cuenta los intentos incluso si el codigo expiro', function () {
    [$bitacora] = makeBitacoraConCodigo($this->proyecto);

    // First two failures while still valid
    for ($i = 1; $i <= 2; $i++) {
        $this->actingAs($this->director)
            ->postJson("/api/bitacoras/{$bitacora->id}/firmar", [
                'code' => '000000',
            ])->assertStatus(422);
    }

    // Now expire the code
    $bitacora->update(['signature_code_expires_at' => now()->subSecond()]);

    // Three more attempts, all should now return 422 due to expiration
    for ($i = 1; $i <= 3; $i++) {
        $this->actingAs($this->director)
            ->postJson("/api/bitacoras/{$bitacora->id}/firmar", [
                'code' => '111111',
            ])->assertStatus(422);
    }

    // After expiration, status should be NoFirmada
    expect($bitacora->fresh()->signature_status)->toBe(EstadoFirma::NoFirmada);
});

// -- RE-SOLICITAR CODIGO ------------------------------------------------

it('re-solicitar regenera el codigo cuando la bitacora esta en NoFirmada', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Re-solicitar',
        'meeting_date' => '2026-04-01',
        'signature_status' => EstadoFirma::NoFirmada,
        'signature_retries' => 0,
    ]);
    $previousHash = $bitacora->signature_code;

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/bitacoras/{$bitacora->id}/re-solicitar-codigo");

    $response->assertOk()
        ->assertJsonStructure(['data' => ['id', 'signature_code_plain', 'signature_code_expires_at']]);

    $b = $bitacora->fresh();
    // After re-solicitar, the bitacora goes back to Pendiente so the
    // freshly-issued code is actually usable by /firmar.
    expect($b->signature_status)->toBe(EstadoFirma::Pendiente)
        ->and($b->signature_retries)->toBe(1)
        ->and($b->signature_code)->not->toBe($previousHash)
        ->and($b->signature_code_expires_at->isFuture())->toBeTrue();

    $plain = $response->json('data.signature_code_plain');
    expect(Hash::check($plain, $b->signature_code))->toBeTrue();
});

it('re-solicitar rechaza cuando la bitacora NO esta en NoFirmada', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Pendiente',
        'meeting_date' => '2026-04-01',
        'signature_status' => EstadoFirma::Pendiente,
    ]);

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/bitacoras/{$bitacora->id}/re-solicitar-codigo");

    $response->assertStatus(422);
    expect($bitacora->fresh()->signature_retries)->toBe(0);
});

it('re-solicitar rechaza la segunda peticion porque retries ya llego a 1', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Re-intentar',
        'meeting_date' => '2026-04-01',
        'signature_status' => EstadoFirma::NoFirmada,
        'signature_retries' => 1,
    ]);

    $response = $this->actingAs($this->estudiante)
        ->postJson("/api/bitacoras/{$bitacora->id}/re-solicitar-codigo");

    $response->assertStatus(422);
});

it('re-solicitar no permite a un usuario sin relacion al proyecto', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Acceso denegado',
        'meeting_date' => '2026-04-01',
        'signature_status' => EstadoFirma::NoFirmada,
        'signature_retries' => 0,
    ]);

    $response = $this->actingAs($this->otroEstudiante)
        ->postJson("/api/bitacoras/{$bitacora->id}/re-solicitar-codigo");

    $response->assertStatus(403);
});

it('re-solicitar limpia el rate limiter para que el director pueda intentar de nuevo', function () {
    [$bitacora] = makeBitacoraConCodigo($this->proyecto);

    // Burn 5 wrong attempts so the bitacora transitions to NoFirmada.
    for ($i = 1; $i <= 5; $i++) {
        $this->actingAs($this->director)
            ->postJson("/api/bitacoras/{$bitacora->id}/firmar", [
                'code' => '000000',
            ])->assertStatus(422);
    }
    expect($bitacora->fresh()->signature_status)->toBe(EstadoFirma::NoFirmada);

    // Re-solicitar and confirm the new code is now signable.
    $re = $this->actingAs($this->estudiante)
        ->postJson("/api/bitacoras/{$bitacora->id}/re-solicitar-codigo");
    $re->assertOk();
    $newPlain = $re->json('data.signature_code_plain');

    $sign = $this->actingAs($this->director)
        ->postJson("/api/bitacoras/{$bitacora->id}/firmar", [
            'code' => $newPlain,
        ]);
    $sign->assertOk();
    expect($bitacora->fresh()->signature_status)->toBe(EstadoFirma::FirmadaDirector);
});
