<?php

declare(strict_types=1);

namespace App\Models;

use App\Auth\LoginAttemptPolicy;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
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
 * @property Carbon|null $last_activity_at
 * @property string|null $totp_secret
 * @property string|null $remember_token
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mass-assignable fields. The auth-access-module adds role, es_externo,
     * google_id, avatar, last_activity_at, totp_secret, password_changed_at,
     * failed_attempts, and locked_until to the default name/email/password trio.
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
        'password_changed_at',
        'failed_attempts',
        'locked_until',
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
            'password_changed_at' => 'datetime',
            'locked_until' => 'datetime',
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

    // -- external-evaluator helpers (T-016, T-017) -----------------------

    /**
     * True when the lockout window is still in the future. Coordinators
     * are never locked (T-016 scenario "Reject external login with
     * wrong password" only applies to credential-based accounts).
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null
            && $this->locked_until->isFuture();
    }

    /**
     * True when the user has never set their own password (the
     * coordinator-issued temporary password is still in place).
     * Triggers the forced password change on first successful login.
     */
    public function mustChangePassword(): bool
    {
        return $this->password_changed_at === null
            && $this->es_externo === true;
    }

    /**
     * Increment the failed-attempts counter. After
     * `LoginAttemptPolicy::MAX_ATTEMPTS` failures in the last
     * `LoginAttemptPolicy::WINDOW_MINUTES` minutes, the account is
     * locked for `LoginAttemptPolicy::LOCK_MINUTES` minutes.
     */
    public function registerFailedLogin(): void
    {
        $policy = app(LoginAttemptPolicy::class);
        $this->failed_attempts = ($this->failed_attempts ?? 0) + 1;

        if ($this->failed_attempts >= $policy->maxAttempts()) {
            $this->locked_until = now()->addMinutes($policy->lockMinutes());
            $this->failed_attempts = 0;
        }

        $this->save();
    }

    /**
     * Reset the failed-attempts counter and the lockout window.
     * Called on every successful credential login.
     */
    public function clearFailedLogin(): void
    {
        $this->failed_attempts = 0;
        $this->locked_until = null;
        $this->save();
    }
}
