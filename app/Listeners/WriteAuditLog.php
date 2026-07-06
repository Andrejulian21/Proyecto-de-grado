<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Persists an AuditEvent to the immutable `audit_logs` table.
 *
 * Resolves the current HTTP request from the container at write time.
 * When no HTTP request is active (artisan command, queue worker), the
 * IP and user-agent columns fall back to null.
 */
class WriteAuditLog
{
    /**
     * Handle the event.
     */
    public function handle(AuditEvent $event): void
    {
        $request = $this->resolveRequest();

        AuditLog::create([
            'user_id' => $event->user?->id,
            'action' => $event->action,
            'description' => $event->description,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $event->meta,
        ]);
    }

    /**
     * Return the current request if one is bound, otherwise null.
     * We avoid instantiating a new Request when none is active so
     * background jobs and artisan commands get null IP/UA.
     */
    private function resolveRequest(): ?Request
    {
        if (app()->bound('request')) {
            $request = app('request');
            if ($request instanceof Request) {
                return $request;
            }
        }

        return null;
    }
}
