<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Events\AuditEvent;
use App\Listeners\WriteAuditLog;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Strict TDD Cycle 2: AuditEvent + WriteAuditLog listener (T-013, T-015).
 *
 * RED → GREEN → TRIANGULATE → REFACTOR
 *
 * The audit pipeline is a Laravel event/listener pair. Controllers
 * dispatch `AuditEvent` with a user, action, description and metadata.
 * The listener captures the request context (IP, user-agent) and
 * inserts an `audit_logs` row.
 */
it('constructs an AuditEvent with user, action, description, and metadata', function () {
    $user = User::factory()->create();
    $event = new AuditEvent(
        user: $user,
        action: 'login.success',
        description: 'first login',
        meta: ['channel' => 'google'],
    );

    expect($event->user)->toBe($user)
        ->and($event->action)->toBe('login.success')
        ->and($event->description)->toBe('first login')
        ->and($event->meta)->toBe(['channel' => 'google']);
});

it('AuditEvent user can be null for system actions', function () {
    $event = new AuditEvent(
        user: null,
        action: 'access.denied',
        description: 'unauthenticated',
    );

    expect($event->user)->toBeNull()
        ->and($event->action)->toBe('access.denied')
        ->and($event->meta)->toBe([]);
});

it('WriteAuditLog persists a row from the event using request context', function () {
    $user = User::factory()->create();
    $request = Request::create('/api/login', 'POST', [], [], [], [
        'REMOTE_ADDR' => '203.0.113.5',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Pest)',
    ]);
    $this->app->instance('request', $request);

    $event = new AuditEvent(
        user: $user,
        action: 'login.success',
        description: 'oauth callback',
        meta: ['channel' => 'google'],
    );

    (new WriteAuditLog())->handle($event);

    $row = AuditLog::query()->latest('id')->first();

    expect($row)
        ->toBeInstanceOf(AuditLog::class)
        ->and($row->user_id)->toBe($user->id)
        ->and($row->action)->toBe('login.success')
        ->and($row->description)->toBe('oauth callback')
        ->and($row->ip_address)->toBe('203.0.113.5')
        ->and($row->user_agent)->toBe('Mozilla/5.0 (Pest)')
        ->and($row->metadata)->toBe(['channel' => 'google']);
});

it('WriteAuditLog accepts a null user and still writes the row', function () {
    $request = Request::create('/api/x', 'GET');
    $this->app->instance('request', $request);

    $event = new AuditEvent(
        user: null,
        action: 'access.denied',
        description: 'unauthenticated request',
    );

    (new WriteAuditLog())->handle($event);

    $row = AuditLog::query()->where('action', 'access.denied')->first();

    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBeNull()
        ->and($row->description)->toBe('unauthenticated request');
});

it('AuditEvent is registered with WriteAuditLog as its handler', function () {
    Event::fake([AuditEvent::class]);

    $user = User::factory()->create(['role' => UserRole::Coordinador->value]);
    // Dispatchable::dispatch uses func_get_args() so we must use
    // positional arguments here, not named.
    AuditEvent::dispatch(
        $user,
        'whitelist.add',
        'maria@unab.edu.co',
        ['role' => 'Estudiante'],
    );

    Event::assertDispatched(
        AuditEvent::class,
        fn (AuditEvent $e) => $e->action === 'whitelist.add'
            && $e->user?->id === $user->id
            && $e->description === 'maria@unab.edu.co'
            && $e->meta === ['role' => 'Estudiante'],
    );
});
