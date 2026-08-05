<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 10: single-session enforcement on login (T-021).
 *
 * When a user logs in, all prior Sanctum tokens for that user MUST
 * be deleted before the new token is issued. This is enforced in
 * the AuthController::loginExterno method.
 */
it('loginExterno deletes prior tokens for the same user', function () {
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);

    // Device A: existing token.
    $user->createToken('device-a');
    expect($user->fresh()->tokens)->toHaveCount(1);

    // Device B: new login. No new token is created (H-004 — cookie-only auth).
    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'TempPass!2026',
    ]);

    $response->assertOk();

    // The old token is gone. No new token was created (cookie-only auth).
    expect($user->fresh()->tokens)->toHaveCount(0);
});

/**
 * Issue #10 — single-session enforcement on cookie-based sessions.
 *
 * The Sanctum-token invalidation above is not enough: the SPA also
 * authenticates via the cookie-based session driver, and that
 * session row in the `sessions` table is not deleted by
 * `$user->tokens()->delete()`. When a user logs in on device B
 * they must also have their prior session row(s) wiped, otherwise
 * device A stays authenticated.
 *
 * (See spec #10 — "Second login invalidates prior cookie session".)
 */
it('loginExterno deletes prior session rows for the same user', function () {
    $user = User::factory()->external()->create([
        'email' => 'pedro@evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);

    // Simulate device A's existing cookie session by inserting the
    // exact row that SESSION_DRIVER=database would have written
    // when device A logged in earlier.
    DB::table('sessions')->insert([
        'id' => 'device-a-session-id',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'device-a',
        'payload' => base64_encode('serialized-session-payload'),
        'last_activity' => now()->timestamp,
    ]);

    // Sanity check: device A's session is in place.
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1);

    // Device B: new login.
    $this->postJson('/api/auth/externo/login', [
        'email' => 'pedro@evaluador.com',
        'password' => 'TempPass!2026',
    ])->assertOk();

    // Device A's session row was wiped by the login handler.
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

/**
 * Issue #10 — Google OAuth callback must also wipe prior sessions.
 *
 * `handleGoogleCallback()` is the institutional login path. Without
 * the session-row cleanup, a Google login on device B would leave
 * device A's cookie session alive.
 *
 * Exercising the full OAuth dance in a feature test is impractical
 * (Socialite would need a fake driver), so we test the post-validation
 * side effect by calling the controller method directly with a
 * stubbed Socialite user.
 */
it('handleGoogleCallback deletes prior session rows for the same user', function () {
    $user = User::factory()->create([
        'email' => 'maria@unab.edu.co',
        'role' => UserRole::Coordinador->value,
        'es_externo' => false,
    ]);

    // Whitelist the email so validateOAuth() passes.
    AuthorizedEmail::create([
        'email' => 'maria@unab.edu.co',
        'role' => UserRole::Coordinador->value,
    ]);

    DB::table('sessions')->insert([
        'id' => 'device-a-session-id',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'device-a',
        'payload' => base64_encode('serialized-session-payload'),
        'last_activity' => now()->timestamp,
    ]);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1);

    // Stub Socialite to return a valid UNAB user.
    $socialite = Mockery::mock(Provider::class);
    $abstractUser = new Laravel\Socialite\Two\User;
    $abstractUser->id = 'google-12345';
    $abstractUser->nickname = 'maria';
    $abstractUser->name = 'Maria Coordinator';
    $abstractUser->email = 'maria@unab.edu.co';
    $abstractUser->avatar = 'https://example.com/avatar.png';
    $abstractUser->user = ['hd' => 'unab.edu.co'];

    $socialite->shouldReceive('user')->andReturn($abstractUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($socialite);

    $this->get('/auth/callback');

    // Device A's session was wiped by the callback. The number of
    // remaining rows depends on the SESSION_DRIVER:
    //   - 'database': a new session row is created for the callback
    //     request itself, so device A is gone and 1 row remains.
    //   - 'array' (phpunit.xml default): sessions are kept in memory
    //     and never persisted, so both device A and the callback's
    //     own session are absent → 0 rows.
    $expected = config('session.driver') === 'database' ? 1 : 0;
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe($expected);
});
