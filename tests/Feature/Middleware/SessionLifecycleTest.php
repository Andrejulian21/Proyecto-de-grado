<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Events\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 9: SingleSessionMiddleware (T-021) + 8h inactivity
 * timeout via ActivityMiddleware (T-022).
 *
 * The single-session rule: each user has at most one active Sanctum
 * token at a time. Logging in on a new device deletes prior tokens.
 *
 * The 8h inactivity rule: if `now() - last_activity_at > 8h`, the
 * request is rejected with 401 session.timeout, the token is
 * deleted, and a `logout.timeout` audit event is written.
 */
beforeEach(function () {
    Route::middleware([
        'auth:sanctum',
        \App\Http\Middleware\SingleSessionMiddleware::class,
        \App\Http\Middleware\ActivityMiddleware::class,
    ])->get('/api/_protected', fn () => response()->json(['ok' => true]));
});

it('allows a request from a freshly-issued token', function () {
    $user = User::factory()->external()->create();
    $token = $user->createToken('web')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/_protected')
        ->assertOk();
});

it('blocks a request whose token has been deleted (single-session purge)', function () {
    $user = User::factory()->external()->create();
    $tokenA = $user->createToken('device-a')->plainTextToken;

    // A new login purges all prior tokens for that user.
    $user->tokens()->delete();
    $tokenB = $user->createToken('device-b')->plainTextToken;

    // Device A's old token is now invalid.
    $this->withToken($tokenA)
        ->getJson('/api/_protected')
        ->assertStatus(401);

    // Device B's new token works.
    $this->withToken($tokenB)
        ->getJson('/api/_protected')
        ->assertOk();
});

it('records last_activity_at on a successful request', function () {
    $user = User::factory()->external()->create([
        'last_activity_at' => null,
    ]);
    $token = $user->createToken('web')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/_protected')
        ->assertOk();

    $user = $user->fresh();
    expect($user->last_activity_at)->not->toBeNull();
});

it('blocks a request after 8h of inactivity and writes a logout.timeout audit log', function () {
    $user = User::factory()->external()->create([
        'last_activity_at' => now()->subHours(8)->subMinute(),
    ]);
    $token = $user->createToken('web')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/_protected');

    $response->assertStatus(401)
        ->assertJson(['error' => 'session.timeout']);

    $row = AuditLog::query()
        ->where('user_id', $user->id)
        ->where('action', 'logout.timeout')
        ->first();

    expect($row)->not->toBeNull();
    expect($user->fresh()->tokens)->toHaveCount(0); // token revoked
});

it('does NOT block a request at the 7h59m boundary (sanity check on the 8h window)', function () {
    $user = User::factory()->external()->create([
        'last_activity_at' => now()->subHours(7)->subMinutes(59),
    ]);
    $token = $user->createToken('web')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/_protected')
        ->assertOk();
});

it('resets the inactivity clock on a successful request', function () {
    $user = User::factory()->external()->create([
        'last_activity_at' => now()->subHours(5),
    ]);
    $token = $user->createToken('web')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/_protected')
        ->assertOk();

    $user = $user->fresh();
    expect($user->last_activity_at->diffInSeconds(now()))->toBeLessThan(5);
});
