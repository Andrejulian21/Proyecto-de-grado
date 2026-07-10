<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('Mail fake verification gate — no se envian correos en endpoints actuales', function () {
    Mail::fake();

    $coordinador = User::factory()->coordinador()->create();
    $this->actingAs($coordinador)
        ->getJson('/api/admin/whitelist');

    // Verification gate: documenta que actualmente no hay envio de correos
    Mail::assertNothingSent();
});
