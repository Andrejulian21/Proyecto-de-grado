<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;

/**
 * Persists an AuditEvent to the immutable `audit_logs` table (H-011).
 *
 * Implements ShouldQueue so audit writes are processed asynchronously.
 * The AuditEvent captures ip_address and user_agent at dispatch time
 * so they are available even in the queue worker context.
 *
 * As a fallback, if the event does not carry IP/UA (e.g., direct
 * `new AuditEvent()` without the factory), the listener resolves
 * them from the current request.
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
            'ip_address' => $event->ip_address ?? $this->resolveIp(),
            'user_agent' => $event->user_agent ?? $this->resolveUserAgent(),
            'metadata' => $event->meta,
        ]);
    }

    private function resolveIp(): ?string
    {
        if (app()->bound('request')) {
            $request = app('request');

            if ($request instanceof Request) {
                return $request->ip();
            }
        }

        return null;
    }

    private function resolveUserAgent(): ?string
    {
        if (app()->bound('request')) {
            $request = app('request');

            if ($request instanceof Request) {
                return $request->userAgent();
            }
        }

        return null;
    }
}
