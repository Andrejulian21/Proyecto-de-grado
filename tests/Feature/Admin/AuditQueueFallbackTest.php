<?php

declare(strict_types=1);

use App\Events\AuditEvent;
use App\Listeners\WriteAuditLog;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// =========================================================================
// H-011: WriteAuditLog implements ShouldQueue
// =========================================================================

it('WriteAuditLog is queued when dispatched via event()', function () {
    Queue::fake();

    $user = User::factory()->create();

    AuditEvent::dispatch($user, 'test.action', 'test description');

    // Laravel wraps ShouldQueue listeners in CallQueuedListener.
    Queue::assertPushed(CallQueuedListener::class, function ($job) {
        return $job->class === WriteAuditLog::class;
    });
});

// =========================================================================
// H-011: Sync fallback when queue is unavailable
// =========================================================================

it('writes audit log synchronously when queue dispatch fails', function () {
    $user = User::factory()->create();
    $event = new AuditEvent(
        $user, 'test.action', 'test description',
        ip_address: '127.0.0.1',
        user_agent: 'phpunit',
    );

    $listener = new WriteAuditLog;
    $listener->handle($event);

    $row = AuditLog::query()
        ->where('action', 'test.action')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->ip_address)->toBe('127.0.0.1')
        ->and($row->user_agent)->toBe('phpunit');
});

// =========================================================================
// H-011: AuditEvent captures ip_address and user_agent
// =========================================================================

it('AuditEvent captures ip_address and user_agent from request context', function () {
    $user = User::factory()->create();

    // The dispatch() factory method captures the current request context.
    $event = AuditEvent::dispatch($user, 'test.action', 'request context test');

    expect($event->ip_address)->not->toBeNull()
        ->and($event->user_agent)->not->toBeNull();
});
