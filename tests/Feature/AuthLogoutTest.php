<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->external()->create([
        'email' => 'logout@test.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);
});

// ----------------------------------------------------------------------
// RF-AUTH-LOGOUT-01: logout ends the session, revokes tokens, 204.
// ----------------------------------------------------------------------

it('logout responds 204', function () {
    $this->actingAs($this->user)
        ->postJson('/api/auth/logout')
        ->assertNoContent();
});

it('logout revokes every token of the user, not just the current one', function () {
    // Two pre-existing tokens (e.g. two devices). The session-cookie
    // logout carries no Bearer token, so SingleSessionMiddleware passes
    // through and the controller must revoke ALL of them.
    $this->user->createToken('device-a');
    $this->user->createToken('device-b');
    expect($this->user->fresh()->tokens)->toHaveCount(2);

    $this->actingAs($this->user)
        ->postJson('/api/auth/logout')
        ->assertNoContent();

    expect($this->user->fresh()->tokens)->toHaveCount(0);
});

it('a bearer token no longer authenticates after logout (no session restore on refresh)', function () {
    $plain = $this->user->createToken('device-a')->plainTextToken;

    // Authenticate the logout itself with the Bearer token (no
    // actingAs: the test app instance would otherwise keep the
    // be()-set user on the web guard and mask the token lookup,
    // exactly like a stale browser session would not).
    $this->withHeaders(['Authorization' => 'Bearer '.$plain])
        ->postJson('/api/auth/logout')
        ->assertNoContent();

    // A browser refresh boots a new request lifecycle with no memoized
    // guard state. The test app is shared across calls, and guards
    // memoize their user — forget them so the refresh really
    // re-authenticates (fresh Sanctum token lookup).
    Auth::forgetGuards();

    // Simulate a refresh holding the old token: must stay logged out.
    $this->getJson('/api/auth/user', ['Authorization' => 'Bearer '.$plain])
        ->assertUnauthorized();
});
