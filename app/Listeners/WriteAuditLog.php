<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Persists an AuditEvent to the immutable `audit_logs` table (H-011).
 *
 * Implements ShouldQueue so audit writes are processed asynchronously.
 * The AuditEvent captures ip_address and user_agent at dispatch time
 * so they are available even in the queue worker context.
 *
 * In case the queue connection is unavailable, AuditEvent::dispatch()
 * falls back to synchronous write to prevent data loss.
 */
class WriteAuditLog implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(AuditEvent $event): void
    {
        AuditLog::create([
            'user_id' => $event->user?->id,
            'action' => $event->action,
            'description' => $event->description,
            'ip_address' => $event->ip_address,
            'user_agent' => $event->user_agent,
            'metadata' => $event->meta,
        ]);
    }
}
