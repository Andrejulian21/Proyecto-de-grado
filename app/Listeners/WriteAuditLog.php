<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Persists an AuditEvent to the immutable `audit_logs` table.
 *
 * The request is injected explicitly by the EventServiceProvider
 * (T-013) using `Request::capture()` so the listener never depends
 * on the global `request()` helper — that makes the listener fully
 * testable without booting a full HTTP context.
 */
class WriteAuditLog
{
    /**
     * Handle the event. Reads IP / user-agent from the captured
     * request and writes a new row. INSERT is the only operation
     * allowed on the audit_logs table (see AuditLog model for
     * the immutability guards).
     */
    public function handle(AuditEvent $event, Request $request): void
    {
        AuditLog::create([
            'user_id' => $event->user?->id,
            'action' => $event->action,
            'description' => $event->description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $event->meta,
        ]);
    }
}
