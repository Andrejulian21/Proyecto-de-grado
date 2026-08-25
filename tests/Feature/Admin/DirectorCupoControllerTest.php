<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Issue #51 — Defect 5: DirectorCupoController keyBy('email') collapse
|--------------------------------------------------------------------------
|
| The director list was selected with ['id','name','areas','max_capacity']
| and then keyBy('email'); because 'email' was not selected, every key was
| null and the collection collapsed to a single entry, so the whitelist
| filter (`$usedEmails = [null]`) excluded nobody. The fix selects 'email'
| so keyBy works and all directors plus whitelist-only entries are shown.
*/

it('lists every director without collapsing the collection', function () {
    $coord = User::factory()->coordinador()->create();

    $d1 = User::factory()->director()->create(['name' => 'Ana Director']);
    $d2 = User::factory()->director()->create(['name' => 'Luis Director']);

    // Whitelist-only director (no users row) — must appear as an extra row.
    AuthorizedEmail::create([
        'email' => 'solo@unab.edu.co',
        'name' => 'Solo White',
        'role' => UserRole::Director->value,
    ]);

    $response = $this->actingAs($coord)->getJson('/api/admin/directores/cupos');
    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('Ana Director');
    expect($names)->toContain('Luis Director');
    expect($names)->toContain('Solo White');

    // No collapse: exactly 3 distinct rows (2 users + 1 whitelist-only).
    expect($response->json('data'))->toHaveCount(3);

    // The whitelist-only director is NOT duplicated by being filtered out.
    expect($names->filter(fn ($n) => $n === 'Solo White')->count())->toBe(1);
});
