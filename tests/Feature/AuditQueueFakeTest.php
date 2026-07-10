<?php

declare(strict_types=1);

use App\Events\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('Event fake verifica que GET a whitelist no despacha AuditEvent', function () {
    Event::fake();

    $coordinador = User::factory()->coordinador()->create();
    $this->actingAs($coordinador)
        ->getJson('/api/admin/whitelist');

    // Read-only endpoints should not trigger audit events
    Event::assertNotDispatched(AuditEvent::class);
});

test('Event fake verifica que POST a whitelist despacha AuditEvent', function () {
    Event::fake();

    $coordinador = User::factory()->coordinador()->create();
    $this->actingAs($coordinador)
        ->postJson('/api/admin/whitelist', [
            'email' => 'nuevo@unab.edu.co',
            'name' => 'Nuevo Usuario',
            'role' => 'Estudiante',
        ]);

    Event::assertDispatched(AuditEvent::class);
});
