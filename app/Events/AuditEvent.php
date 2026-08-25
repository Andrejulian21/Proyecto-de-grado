<?php

declare(strict_types=1);

namespace App\Events;

use App\Listeners\WriteAuditLog;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Generic audit event. Controllers and middleware dispatch this; the
 * WriteAuditLog listener persists it to the immutable `audit_logs` table.
 *
 * The static `dispatch()` factory captures the current HTTP request
 * context (ip_address, user_agent) at event creation time, because
 * queued jobs lose access to the request object.
 *
 * If the queue connection is unavailable, the dispatch falls back to
 * synchronous write to prevent audit data loss (H-011).
 *
 * Naming convention for `action` (stable identifiers used by filters
 * in the AuditLogController view):
 *
 *   - login.success         — successful Google OAuth login
 *   - login.rejected        — OAuth triple validation failed
 *   - login.cancelled       — user denied Google consent
 *   - login.error           — OAuth network / token-exchange error
 *   - login.locked          — external evaluator account locked
 *   - logout.user_initiated — explicit logout
 *   - logout.timeout        — 8-hour inactivity timeout fired
 *   - access.denied         — RoleMiddleware / auth middleware denied
 *   - whitelist.add         — entry added
 *   - whitelist.role_changed — entry role updated
 *   - whitelist.removed     — entry deleted
 *   - role.changed          — user role changed
 *   - user.created_external — coordinator created an external evaluator
 *   - password.changed      — external evaluator changed password
 *   - bitacora.firmada      — director signed a bitacora (issue #45)
 */
class AuditEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly ?User $user,
        public readonly string $action,
        public readonly string $description,
        public readonly array $meta = [],
        public readonly ?string $ip_address = null,
        public readonly ?string $user_agent = null,
    ) {}

    /**
     * Static factory that auto-captures request context at dispatch
     * time and falls back to synchronous write if the queue is
     * unavailable (H-011).
     *
     * Call this instead of `event(new AuditEvent(...))` to get
     * ip_address/user_agent captured and queue-failure protection.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function dispatch(
        ?User $user,
        string $action,
        string $description,
        array $meta = [],
    ): static {
        $request = app()->bound('request') ? app('request') : null;

        $event = new static(
            $user,
            $action,
            $description,
            $meta,
            ip_address: $request?->ip(),
            user_agent: $request?->userAgent(),
        );

        try {
            event($event);
        } catch (Throwable $e) {
            // Queue down — write synchronously to prevent data loss.
            (new WriteAuditLog)->handle($event);
            report($e);
        }

        return $event;
    }
}
