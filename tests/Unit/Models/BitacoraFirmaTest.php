<?php

declare(strict_types=1);

use App\Enums\EstadoFirma;
use App\Models\Bitacora;
use App\Models\Proyecto;
use App\Models\Semestre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $semestre = Semestre::create([
        'name' => '2026-1',
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
    ]);
    $this->proyecto = Proyecto::create([
        'title' => 'Proyecto Test',
        'semester_id' => $semestre->id,
    ]);
});

// -- hasValidSignature --------------------------------------------------

test('hasValidSignature returns false when status is not FirmadaDirector', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Test',
        'meeting_date' => '2026-04-01',
        'signature_status' => EstadoFirma::Pendiente,
    ]);

    expect($bitacora->hasValidSignature())->toBeFalse();
});

test('hasValidSignature returns true when status is FirmadaDirector', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Test',
        'meeting_date' => '2026-04-01',
        'signature_status' => EstadoFirma::FirmadaDirector,
        'director_signed_at' => now(),
    ]);

    expect($bitacora->hasValidSignature())->toBeTrue();
});

// -- generateSignatureCode ----------------------------------------------

test('generateSignatureCode stores a hashed code, sets expiration +2 min, returns plain text', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Test',
        'meeting_date' => '2026-04-01',
    ]);

    $plain = $bitacora->generateSignatureCode();

    expect($plain)->toBeString()
        ->and(strlen($plain))->toBe(6)
        ->and(ctype_digit($plain))->toBeTrue();

    $fresh = $bitacora->fresh();
    expect($fresh->signature_code)->not->toBeNull()
        ->and($fresh->signature_code)->not->toBe($plain) // hashed, not plain
        ->and(Hash::check($plain, $fresh->signature_code))->toBeTrue()
        ->and($fresh->signature_code_expires_at)->not->toBeNull()
        ->and($fresh->signature_code_expires_at->isFuture())->toBeTrue()
        ->and($fresh->signature_code_expires_at->diffInSeconds(now(), false))->toBeGreaterThanOrEqual(-130) // ~2 min ahead
        ->and($fresh->signature_code_expires_at->diffInSeconds(now(), false))->toBeLessThanOrEqual(-110); //  ~2 min ahead
});

test('generateSignatureCode stores code in the 100000-999999 range across many calls', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Test',
        'meeting_date' => '2026-04-01',
    ]);

    for ($i = 0; $i < 20; $i++) {
        $code = $bitacora->generateSignatureCode();
        expect((int) $code)->toBeGreaterThanOrEqual(100000)
            ->and((int) $code)->toBeLessThanOrEqual(999999);
    }
});

// -- canResendCode ------------------------------------------------------

test('canResendCode returns true when status is NoFirmada and retries < 1', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Test',
        'meeting_date' => '2026-04-01',
        'signature_status' => EstadoFirma::NoFirmada,
        'signature_retries' => 0,
    ]);

    expect($bitacora->canResendCode())->toBeTrue();
});

test('canResendCode returns false when status is Pendiente', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Test',
        'meeting_date' => '2026-04-01',
        'signature_status' => EstadoFirma::Pendiente,
    ]);

    expect($bitacora->canResendCode())->toBeFalse();
});

test('canResendCode returns false when status is FirmadaDirector', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Test',
        'meeting_date' => '2026-04-01',
        'signature_status' => EstadoFirma::FirmadaDirector,
    ]);

    expect($bitacora->canResendCode())->toBeFalse();
});

test('canResendCode returns false when retries already 1', function () {
    $bitacora = Bitacora::create([
        'proyecto_id' => $this->proyecto->id,
        'topic' => 'Test',
        'meeting_date' => '2026-04-01',
        'signature_status' => EstadoFirma::NoFirmada,
        'signature_retries' => 1,
    ]);

    expect($bitacora->canResendCode())->toBeFalse();
});
