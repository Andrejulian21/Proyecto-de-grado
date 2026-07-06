<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Generic audit event. Controllers and middleware dispatch this; the
 * WriteAuditLog listener (T-013) persists it to the immutable
 * `audit_logs` table.
 *
 * Naming convention for `action` (stable identifiers used by filters
 * in the AuditLogController view, T-024):
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
    ) {
    }
}
