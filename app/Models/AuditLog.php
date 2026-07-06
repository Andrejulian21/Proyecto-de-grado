<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable, append-only audit trail entry (T-012, T-024).
 *
 * Immutability is enforced three ways:
 *   1. The model overrides `save()` (when the row already exists) and
 *      `delete()` / `forceDelete()` to throw LogicException.
 *   2. The query-builder level `update()` / `delete()` / `forceDelete()`
 *      are blocked via a custom Eloquent builder (newEloquentBuilder).
 *   3. The DB role used by the application has no UPDATE/DELETE
 *      privilege on this table (deployment runbook, T-010).
 *
 * The query scopes (`forUser`, `forAction`, `betweenDates`, `ordered`)
 * power the AuditLogController@index filters (T-024) and the
 * audit:archive command (T-025).
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

    // -- query scopes (T-012, T-024) ------------------------------------

    /**
     * Filter to a specific user (or null for system events).
     */
    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Filter to a specific action (e.g. 'login.success').
     */
    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Restrict to rows whose `created_at` falls inside the half-open
     * range [from, to). Both bounds are optional.
     */
    public function scopeBetweenDates(Builder $query, ?Carbon $from, ?Carbon $to): Builder
    {
        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('created_at', '<', $to);
        }

        return $query;
    }

    /**
     * Order by created_at descending. The composite index added in
     * migration `add_action_created_at_index_to_audit_logs` keeps this
     * O(log n) for the coordinator filter view.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    // -- immutability guards --------------------------------------------

    /**
     * Return a custom Eloquent builder that blocks query-level
     * `update()` and `delete()` so `AuditLog::query()->update(...)` and
     * `AuditLog::query()->delete()` both throw immediately. INSERTs
     * (which go through `Model::create()` → `newEloquentBuilder()` →
     * `Model::performInsert()`) still work because they don't call
     * the builder's `update()` method.
     */
    public function newEloquentBuilder($query): Builder
    {
        return new class($query) extends Builder
        {
            public function update(array $values, array $options = []): int
            {
                throw new LogicException('audit_logs is append-only; mass UPDATE is not allowed');
            }

            public function delete(): int
            {
                throw new LogicException('audit_logs is append-only; mass DELETE is not allowed');
            }

            public function forceDelete(): int
            {
                throw new LogicException('audit_logs is append-only; mass DELETE is not allowed');
            }
        };
    }

    /**
     * Block any save() that would UPDATE the row. INSERTs (when the
     * model is new) are allowed.
     */
    public function save(array $options = []): bool
    {
        if (! $this->exists) {
            return parent::save($options);
        }

        throw new LogicException('audit_logs is append-only; UPDATE is not allowed');
    }

    /**
     * Block delete() on a single row.
     */
    public function delete(): ?bool
    {
        throw new LogicException('audit_logs is append-only; DELETE is not allowed');
    }

    /**
     * Block forceDelete() too (no soft deletes on this model, but be
     * defensive in case someone wires SoftDeletes later).
     */
    public function forceDelete(): ?bool
    {
        throw new LogicException('audit_logs is append-only; forceDelete is not allowed');
    }
}
