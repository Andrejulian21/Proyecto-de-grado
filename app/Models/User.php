<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property UserRole $role
 * @property bool $es_externo
 * @property string|null $google_id
 * @property string|null $avatar
 * @property \Illuminate\Support\Carbon|null $last_activity_at
 * @property string|null $totp_secret
 * @property string|null $remember_token
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mass-assignable fields. The auth-access-module adds role, es_externo,
     * google_id, avatar, last_activity_at, and totp_secret to the default
     * name/email/password trio.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'es_externo',
        'google_id',
        'avatar',
        'last_activity_at',
        'totp_secret',
    ];

    /**
     * Fields hidden from JSON / array serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
    ];

    /**
     * Attribute casts. The `role` string is bound to the UserRole PHP enum
     * so application code can use typed comparisons (e.g. `$user->role ===
     * UserRole::Coordinador`) and `match` expressions. `es_externo` is
     * cast to bool so `if ($user->es_externo)` works without `=== 1` noise.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'es_externo' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    // -- relations -----------------------------------------------------

    /**
     * Audit log entries this user produced (or system entries where
     * user_id is null — those are reachable only through the audit
     * controller, not via a user relation).
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    /**
     * Whitelist entries this user (a Coordinador) added. Nullable
     * `created_by` allows system-seeded rows that pre-date any admin.
     */
    public function authorizedEmailsCreated(): HasMany
    {
        return $this->hasMany(AuthorizedEmail::class, 'created_by');
    }
}
