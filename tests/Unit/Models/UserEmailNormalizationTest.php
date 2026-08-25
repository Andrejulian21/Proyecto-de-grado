<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Issue #51 — Defect 4: case-insensitive email uniqueness
|--------------------------------------------------------------------------
|
| Emails are normalized to lowercase on write (User email mutator) and on
| read (loginExterno lookup), and the users.lower(email) index is UNIQUE.
| A user created with uppercase can therefore log in typing the email in
| lowercase, and case-variant duplicates are rejected at the DB level.
*/

it('normalizes the email to lowercase on create', function () {
    $user = User::factory()->create(['email' => 'Foo.Bar@Evaluador.com']);

    expect($user->email)->toBe('foo.bar@evaluador.com');
    expect(User::query()->find($user->id)->email)->toBe('foo.bar@evaluador.com');
});

it('lets an external evaluator created with uppercase log in using lowercase', function () {
    $user = User::factory()->external()->create([
        'email' => 'Pedro@Evaluador.com',
        'password' => Hash::make('TempPass!2026'),
        'password_changed_at' => now(),
    ]);

    // Stored canonical form is lowercase.
    expect(User::query()->find($user->id)->email)->toBe('pedro@evaluador.com');

    $response = $this->postJson('/api/auth/externo/login', [
        'email' => 'PEDRO@EVALUADOR.COM',
        'password' => 'TempPass!2026',
    ]);

    $response->assertOk();
});

it('rejects a case-variant duplicate email at the DB level', function () {
    User::factory()->create(['email' => 'Dupe@Example.com']);

    expect(fn () => User::factory()->create(['email' => 'dupe@example.com']))
        ->toThrow(QueryException::class);
});

it('rejects a case-variant duplicate email at validation with 422', function () {
    $coord = User::factory()->coordinador()->create();
    User::factory()->external()->create(['email' => 'pedro@evaluador.com']);

    $this->actingAs($coord)
        ->postJson('/api/admin/evaluadores', [
            'name' => 'Pedro Dup',
            'email' => 'PEDRO@EVALUADOR.COM',
            'password' => 'MiPassword123!',
            'password_confirmation' => 'MiPassword123!',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
