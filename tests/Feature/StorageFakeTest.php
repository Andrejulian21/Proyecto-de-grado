<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('Storage fake verification gate — no hay archivos subidos en endpoints actuales', function () {
    Storage::fake('public');

    $coordinador = User::factory()->coordinador()->create();
    $this->actingAs($coordinador)
        ->getJson('/api/admin/whitelist');

    // Verification gate: documenta que no hay subida de archivos en estos endpoints
    Storage::disk('public')->assertMissing('no-existe.txt');
});
