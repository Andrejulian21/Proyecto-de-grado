<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable, append-only audit trail entry.
 *
 * IMPORTANT: This model is append-only by design (spec domain `audit-log`).
 * The DB role used by the application MUST NOT have UPDATE/DELETE privilege
 * on the `audit_logs` table (see deployment runbook). The model also
 * intentionally disables `updated_at` (`$timestamps = false` but the DB
 * still has `created_at` because Laravel requires it for inserts).
 *
 * The full append-only API is implemented in PR 2 (T-013). For now, this
 * stub lets User::auditLogs() resolve the relation.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $description
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 */
class AuditLog extends Model
{
    /**
     * Only `created_at` is set automatically. `updated_at` is suppressed
     * (column doesn't exist on the table).
     */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The user who triggered the action (system actions have null here).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
