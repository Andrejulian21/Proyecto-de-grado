<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

// Unit test for an Eloquent model needs the actual DB schema to verify
// casts, relations, and factory state. RefreshDatabase gives each test
// a clean in-memory SQLite with all migrations applied.
uses(RefreshDatabase::class);

test('User model exists and extends Authenticatable', function () {
    $user = new User;
    expect($user)->toBeInstanceOf(Illuminate\Foundation\Auth\User::class);
});

test('User has the auth-access-module fillable fields', function () {
    $user = new User;
    $expected = [
        'name', 'email', 'password',
        'role', 'es_externo', 'google_id', 'avatar',
        'last_activity_at', 'totp_secret',
    ];

    foreach ($expected as $field) {
        expect(in_array($field, $user->getFillable(), true))
            ->toBeTrue("User should be fillable for {$field}");
    }
});

test('User hides password and remember_token', function () {
    $user = new User;
    $hidden = $user->getHidden();

    expect($hidden)->toContain('password');
    expect($hidden)->toContain('remember_token');
});

test('User casts role to UserRole enum', function () {
    $user = User::factory()->create(['role' => UserRole::Coordinador->value]);

    expect($user->role)->toBeInstanceOf(UserRole::class);
    expect($user->role)->toBe(UserRole::Coordinador);
});

test('User casts es_externo to bool', function () {
    $user = User::factory()->create(['es_externo' => 1]);

    expect($user->es_externo)->toBeTrue();
    expect($user->es_externo)->toBeBool();
});

test('User casts last_activity_at to datetime', function () {
    $user = User::factory()->create(['last_activity_at' => now()]);

    expect($user->last_activity_at)->toBeInstanceOf(Carbon::class);
});

test('User factory defaults to Estudiante role (most common)', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Estudiante);
});

test('User factory can produce a Coordinador', function () {
    $user = User::factory()->create(['role' => UserRole::Coordinador->value]);

    expect($user->role)->toBe(UserRole::Coordinador);
});

test('User factory external() state sets es_externo=true and role=EvaluadorExterno', function () {
    $user = User::factory()->external()->create();

    expect($user->es_externo)->toBeTrue();
    expect($user->role)->toBe(UserRole::EvaluadorExterno);
    expect($user->password)->not->toBeNull();
});

test('User.auditLogs relation returns a HasMany of AuditLog', function () {
    $user = new User;
    expect($user->auditLogs())->toBeInstanceOf(HasMany::class);
    expect($user->auditLogs()->getRelated())->toBeInstanceOf(AuditLog::class);
});

test('User.authorizedEmailsCreated relation returns a HasMany of AuthorizedEmail', function () {
    $user = new User;
    expect($user->authorizedEmailsCreated())->toBeInstanceOf(HasMany::class);
    expect($user->authorizedEmailsCreated()->getRelated())->toBeInstanceOf(AuthorizedEmail::class);
});

test('User has the Sanctum HasApiTokens trait', function () {
    $traits = class_uses_recursive(User::class);
    expect($traits)->toContain(HasApiTokens::class);
});

test('User password is automatically hashed when set via attribute', function () {
    $user = User::factory()->create(['password' => 'plain-secret']);
    // The default cast 'hashed' means the stored value is bcrypt.
    expect($user->password)->not->toBe('plain-secret');
    expect(Hash::check('plain-secret', $user->password))->toBeTrue();
});
