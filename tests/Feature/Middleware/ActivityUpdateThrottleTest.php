<?php

declare(strict_types=1);

use App\Http\Middleware\ActivityMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Issue #53: ActivityMiddleware ejecutaba un UPDATE sobre `users` en
 * cada petición autenticada (~40k escrituras/día a la escala del issue,
 * todas redundantes salvo la primera de cada minuto). Este test fija que
 * el throttle de 60s escribe como máximo una vez por minuto por usuario,
 * sin alterar el timeout de inactividad de 1 hora (cubierto por
 * SessionLifecycleTest).
 */
beforeEach(function () {
    Route::middleware([
        'auth:sanctum',
        ActivityMiddleware::class,
    ])->get('/api/_activity', fn () => response()->json(['ok' => true]));
});

it('escribe last_activity_at como máximo una vez por minuto por usuario', function () {
    Carbon::setTestNow(now());

    $user = User::factory()->external()->create(['last_activity_at' => null]);
    $token = $user->createToken('web')->plainTextToken;

    $updates = 0;

    DB::listen(function ($query) use (&$updates): void {
        if (str_contains($query->sql, 'update') && str_contains($query->sql, 'users')) {
            $updates++;
        }
    });

    // 1ª petición: last_activity_at null → escribe.
    $this->withToken($token)->getJson('/api/_activity')->assertOk();
    expect($updates)->toBe(1);

    // 2ª petición inmediata (mismo minuto): no escribe de nuevo.
    $this->withToken($token)->getJson('/api/_activity')->assertOk();
    expect($updates)->toBe(1);

    // +61 segundos: vuelve a escribir.
    Carbon::setTestNow(now()->addSeconds(61));
    $this->withToken($token)->getJson('/api/_activity')->assertOk();
    expect($updates)->toBe(2);

    Carbon::setTestNow();
});

it('no escribe si last_activity_at está dentro de los últimos 60 segundos', function () {
    Carbon::setTestNow(now());

    $user = User::factory()->external()->create([
        'last_activity_at' => now()->subSeconds(30),
    ]);
    $token = $user->createToken('web')->plainTextToken;

    $updates = 0;

    DB::listen(function ($query) use (&$updates): void {
        if (str_contains($query->sql, 'update') && str_contains($query->sql, 'users')) {
            $updates++;
        }
    });

    $this->withToken($token)->getJson('/api/_activity')->assertOk();
    expect($updates)->toBe(0);

    Carbon::setTestNow();
});

it('no bloquea la sesión por el throttle: sigue actualizando tras 60s', function () {
    Carbon::setTestNow(now());

    $user = User::factory()->external()->create([
        'last_activity_at' => now()->subMinutes(2),
    ]);
    $token = $user->createToken('web')->plainTextToken;

    $this->withToken($token)->getJson('/api/_activity')->assertOk();

    Carbon::setTestNow(now()->addSeconds(61));

    $this->withToken($token)->getJson('/api/_activity')->assertOk();

    Carbon::setTestNow();

    expect($user->fresh()->last_activity_at)->not->toBeNull();
});
