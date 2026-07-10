<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Middleware\ActivityMiddleware;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

// =========================================================================
// H-004: loginExterno does not return a bearer token
// =========================================================================

test('loginExterno does not return bearer token', function () {
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);

    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'TempPass!2026',
    ]);

    $response->assertOk();
    expect($response->json())->not->toHaveKey('token');
});

// =========================================================================
// H-004: handleGoogleCallback does not create a bearer token
// =========================================================================

test('handleGoogleCallback does not create an unnecessary token', function () {
    // Verify that the AuthController handleGoogleCallback method
    // does NOT contain $user->createToken('google-oauth').
    $source = file_get_contents(app_path('Http/Controllers/Auth/AuthController.php'));

    expect($source)
        ->not->toContain("createToken('google-oauth')")
        ->and($source)->not->toContain('$accessToken');
});

// =========================================================================
// H-005: ActivityMiddleware calls Auth::logout and invalidates session
// =========================================================================

test('ActivityMiddleware calls Auth::logout and invalidates session on timeout', function () {
    $user = User::factory()->external()->create([
        'last_activity_at' => now()->subHours(1)->subMinute(),
    ]);

    // Register a protected route with Sanctum auth + ActivityMiddleware
    Route::middleware(['auth:sanctum', ActivityMiddleware::class])
        ->get('/api/_activity_timeout_test', fn () => response()->json(['ok' => true]));

    // Authenticate via Sanctum Bearer token (what the existing tests do)
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/_activity_timeout_test');

    $response->assertStatus(401)
        ->assertJson(['error' => 'session.timeout']);

    // The token should be deleted
    expect($user->fresh()->tokens)->toHaveCount(0);

    // A logout.timeout audit entry should exist
    $row = AuditLog::query()
        ->where('user_id', $user->id)
        ->where('action', 'logout.timeout')
        ->first();

    expect($row)->not->toBeNull();
});

// =========================================================================
// H-005: Admin routes include activity middleware
// =========================================================================

test('admin routes return 401 for inactive coordinator via activity middleware', function () {
    // Register a temporary admin-like route with the full middleware stack
    Route::middleware(['auth:sanctum', 'activity', 'role:Coordinador'])
        ->get('/api/_admin_activity_test', fn () => response()->json(['ok' => true]));

    $user = User::factory()->create([
        'role' => UserRole::Coordinador,
        'es_externo' => false,
        'last_activity_at' => now()->subHours(1)->subMinute(),
    ]);

    $token = $user->createToken('admin-test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/_admin_activity_test');

    $response->assertStatus(401)
        ->assertJson(['error' => 'session.timeout']);
});

test('admin routes in routes/api.php include activity middleware', function () {
    // Verify the admin route group middleware array includes 'activity'
    $routes = file_get_contents(base_path('routes/api.php'));

    // The admin group should have auth:sanctum, activity, and role:Coordinador
    expect($routes)
        ->toContain("'auth:sanctum'")
        ->toContain("'activity'")
        ->toContain("'role:Coordinador'")
        ->and($routes)->toContain("'single_session'")
        ->toContain("'ensure_password_changed'");
});

// =========================================================================
// H-006: Session cookie secure by default
// =========================================================================

test('session cookie is secure by default', function () {
    $config = require config_path('session.php');

    expect($config['secure'])->toBe(true);
});

// =========================================================================
// H-006: SESSION_SECURE_COOKIE in .env.example
// =========================================================================

test('SESSION_SECURE_COOKIE is documented in env example', function () {
    $envExample = file_get_contents(base_path('.env.example'));

    expect($envExample)->toContain('SESSION_SECURE_COOKIE=true');
});
