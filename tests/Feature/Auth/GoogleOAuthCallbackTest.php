<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 11: Google OAuth callback with triple validation (T-014).
 *
 * The spec mandates three independent validations, ALL of which must
 * pass:
 *   1. `hd` claim === unab.edu.co (when present).
 *   2. Email ends with @unab.edu.co (case-insensitive).
 *   3. Email is in the `authorized_emails` whitelist.
 *
 * Success: findOrCreate User, delete prior sessions, create Sanctum
 * token, write `login.success` audit, redirect to /dashboard/{role}.
 *
 * Failure: write `login.rejected` audit, redirect to /login.
 */
beforeEach(function () {
    // Disable the real Google redirect in tests.
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
        'services.google.redirect' => 'http://localhost/auth/callback',
    ]);
});

/**
 * Build a fake Socialite user with the given Google claims.
 *
 * @return SocialiteUser
 */
function fakeGoogleUser(array $attrs = []): SocialiteUser
{
    $user = new SocialiteUser();
    $user->id = $attrs['id'] ?? 'google-'.uniqid();
    $user->nickname = $attrs['nickname'] ?? 'maria';
    $user->name = $attrs['name'] ?? 'Maria Test';
    $user->email = $attrs['email'] ?? 'maria@unab.edu.co';
    $user->avatar = $attrs['avatar'] ?? 'https://example.com/a.png';
    $user->user = $attrs;

    // `hd` lives in a private `attributes` array. Populate it via
    // the original `user` array passed to the SocialiteUser.
    if (isset($attrs['hd'])) {
        $user->user['hd'] = $attrs['hd'];
    }

    return $user;
}

it('rejects login when the email is from a non-UNAB domain', function () {
    Socialite::shouldReceive('driver->user')->andReturn(
        fakeGoogleUser(['email' => 'someone@gmail.com', 'hd' => 'gmail.com'])
    );

    $response = $this->get('/auth/callback');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/login');
    expect(User::query()->count())->toBe(0);

    $row = AuditLog::query()->where('action', 'login.rejected')->first();
    expect($row)
        ->not->toBeNull()
        ->and($row->description)->toBe('domain_mismatch');
});

it('rejects login when the email is UNAB but not whitelisted', function () {
    Socialite::shouldReceive('driver->user')->andReturn(
        fakeGoogleUser(['email' => 'john@unab.edu.co', 'hd' => 'unab.edu.co'])
    );

    $response = $this->get('/auth/callback');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/login');
    expect(User::query()->count())->toBe(0);

    $row = AuditLog::query()->where('action', 'login.rejected')->first();
    expect($row)
        ->not->toBeNull()
        ->and($row->description)->toBe('not_whitelisted');
});

it('rejects login when the hd claim is missing', function () {
    Socialite::shouldReceive('driver->user')->andReturn(
        fakeGoogleUser(['email' => 'maria@unab.edu.co', 'hd' => null])
    );
    AuthorizedEmail::create(['email' => 'maria@unab.edu.co', 'role' => UserRole::Estudiante->value]);

    $response = $this->get('/auth/callback');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/login');
    expect(User::query()->count())->toBe(0);

    $row = AuditLog::query()->where('action', 'login.rejected')->first();
    expect($row)
        ->not->toBeNull()
        ->and($row->description)->toBe('hd_missing');
});

it('accepts login when hd + suffix + whitelist all pass', function () {
    AuthorizedEmail::create([
        'email' => 'maria@unab.edu.co',
        'role' => UserRole::Estudiante->value,
    ]);

    Socialite::shouldReceive('driver->user')->andReturn(
        fakeGoogleUser([
            'id' => 'google-maria-1',
            'name' => 'Maria Test',
            'email' => 'maria@unab.edu.co',
            'avatar' => 'https://example.com/maria.png',
            'hd' => 'unab.edu.co',
        ])
    );

    $response = $this->get('/auth/callback');

    $response->assertRedirect('/dashboard/estudiante');

    $user = User::query()->where('email', 'maria@unab.edu.co')->first();
    expect($user)
        ->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Estudiante)
        ->and($user->google_id)->toBe('google-maria-1')
        ->and($user->avatar)->toBe('https://example.com/maria.png');

    $row = AuditLog::query()->where('action', 'login.success')->first();
    expect($row)->not->toBeNull();
});

it('creates the user only on the first successful login (findOrCreate)', function () {
    AuthorizedEmail::create([
        'email' => 'maria@unab.edu.co',
        'role' => UserRole::Estudiante->value,
    ]);

    $googleUser = fakeGoogleUser([
        'id' => 'google-maria-2',
        'email' => 'maria@unab.edu.co',
        'hd' => 'unab.edu.co',
    ]);
    Socialite::shouldReceive('driver->user')->andReturn($googleUser);

    $this->get('/auth/callback')->assertRedirect('/dashboard/estudiante');
    expect(User::query()->count())->toBe(1);

    $this->get('/auth/callback')->assertRedirect('/dashboard/estudiante');
    // Still only one user.
    expect(User::query()->count())->toBe(1);
});

it('syncs the role from the whitelist on every login (so a whitelist role change takes effect)', function () {
    // First whitelist entry: Estudiante
    $entry = AuthorizedEmail::create([
        'email' => 'maria@unab.edu.co',
        'role' => UserRole::Estudiante->value,
    ]);

    Socialite::shouldReceive('driver->user')->andReturn(
        fakeGoogleUser(['email' => 'maria@unab.edu.co', 'hd' => 'unab.edu.co'])
    );

    $this->get('/auth/callback')->assertRedirect('/dashboard/estudiante');
    expect(User::query()->first()->role)->toBe(UserRole::Estudiante);

    // Coordinator changes the whitelist role to Director.
    $entry->update(['role' => UserRole::Director->value]);

    $this->get('/auth/callback')->assertRedirect('/dashboard/director');
    expect(User::query()->first()->role)->toBe(UserRole::Director);
});

it('writes audit log on successful login with user_id, ip, user-agent', function () {
    AuthorizedEmail::create([
        'email' => 'maria@unab.edu.co',
        'role' => UserRole::Estudiante->value,
    ]);

    Socialite::shouldReceive('driver->user')->andReturn(
        fakeGoogleUser(['email' => 'maria@unab.edu.co', 'hd' => 'unab.edu.co'])
    );

    $this->withServerVariables([
        'REMOTE_ADDR' => '198.51.100.7',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (OAuth-Test)',
    ])->get('/auth/callback');

    $row = AuditLog::query()->where('action', 'login.success')->first();

    expect($row)
        ->not->toBeNull()
        ->and($row->user_id)->not->toBeNull()
        ->and($row->ip_address)->toBe('198.51.100.7')
        ->and($row->user_agent)->toBe('Mozilla/5.0 (OAuth-Test)');
});

it('returns to /login with cancel message when Google returns an error', function () {
    $response = $this->get('/auth/callback?error=access_denied');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/login')
        ->and($response->headers->get('Location'))->toContain('error=access_denied');

    $row = AuditLog::query()->where('action', 'login.cancelled')->first();
    expect($row)->not->toBeNull();
});

it('catches Socialite exceptions and redirects to /login with retry message', function () {
    Socialite::shouldReceive('driver->user')->andThrow(
        new \Laravel\Socialite\Two\InvalidStateException('invalid_state')
    );

    $response = $this->get('/auth/callback');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/login')
        ->and($response->headers->get('Location'))->toContain('error=oauth_error');

    $row = AuditLog::query()->where('action', 'login.error')->first();
    expect($row)
        ->not->toBeNull()
        ->and($row->description)->toContain('InvalidStateException');
});

it('returns 403 when the email suffix is wrong even if hd is right', function () {
    // Whitelisted email is UNAB but the Google user came in with a
    // different domain entirely. Both checks fail.
    Socialite::shouldReceive('driver->user')->andReturn(
        fakeGoogleUser(['email' => 'maria@otro.edu.co', 'hd' => 'unab.edu.co'])
    );

    $response = $this->get('/auth/callback');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/login');
    expect(User::query()->count())->toBe(0);

    $row = AuditLog::query()->where('action', 'login.rejected')->first();
    expect($row->description)->toBe('domain_mismatch');
});
