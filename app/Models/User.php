<?php

declare(strict_types=1);

namespace App\Models;

use App\Auth\LoginAttemptPolicy;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property Carbon|null $last_failed_at
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mass-assignable fields. The auth-access-module adds role, es_externo,
     * google_id, avatar, last_activity_at, totp_secret, password_changed_at,
     * failed_attempts, and locked_until to the default name/email/password trio.
     * Sprint 5 added max_capacity (director quota) and areas (director expertise).
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
        'last_failed_at',
        'max_capacity',
        'areas',
        'codigo_estudiante',
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
            'last_failed_at' => 'datetime',
            'max_capacity' => 'integer',
            'areas' => 'string',
            'codigo_estudiante' => 'string',
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

    /**
     * Projects this user directs (director role). Used for capacity/quotas
     * in the coordinator dashboard.
     */
    public function proyectosDirigidos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'director_id');
    }

    /**
     * Structured academic profile for directors (research lines, technologies, …).
     */
    public function academicProfile(): HasOne
    {
        return $this->hasOne(DirectorAcademicProfile::class, 'user_id');
    }

    // -- external-evaluator helpers (T-016, T-017) -----------------------

    /**
     * True when the lockout window is still in the future OR the
     * cumulative failed-attempt counter has reached the policy
     * threshold WITHIN the sliding window.
     *
     * The sliding window is anchored on `last_failed_at`: if the
     * most-recent failure is older than `LoginAttemptPolicy::windowMinutes()`
     * the counter is stale and the user is NOT locked regardless
     * of how many old attempts accumulated. See issue #13.
     *
     * Coordinators (Google OAuth) are never locked — this method is
     * only consulted on the external credential login path
     * (`loginExterno`, T-016).
     */
    public function isLocked(): bool
    {
        // Future lockout window still in effect.
        if ($this->locked_until !== null && $this->locked_until->isFuture()) {
            return true;
        }

        $policy = app(LoginAttemptPolicy::class);

        // No failures recorded or the most-recent failure is outside
        // the sliding window → stale counter, not locked.
        if ($this->last_failed_at === null
            || $this->last_failed_at->diffInMinutes(now(), absolute: true) >= $policy->windowMinutes()
        ) {
            return false;
        }

        // Within the window: locked if cumulative attempts hit the cap.
        return $this->failed_attempts >= $policy->maxAttempts();
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
     * Increment the failed-attempts counter with a sliding window.
     *
     * If the most-recent prior failure is older than
     * `LoginAttemptPolicy::windowMinutes()`, the counter is reset
     * to 0 before incrementing — old attempts are discarded.
     * Otherwise the counter is incremented as before.
     *
     * When the new counter reaches the policy's maxAttempts the
     * account is locked for `lockMinutes()` and the counter resets
     * to 0 so the post-lockout window starts fresh.
     */
    public function registerFailedLogin(): void
    {
        $policy = app(LoginAttemptPolicy::class);

        if ($this->last_failed_at !== null
            && $this->last_failed_at->diffInMinutes(now(), absolute: true) >= $policy->windowMinutes()
        ) {
            $this->failed_attempts = 0;
        }

        $this->failed_attempts = ($this->failed_attempts ?? 0) + 1;
        $this->last_failed_at = now();

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
