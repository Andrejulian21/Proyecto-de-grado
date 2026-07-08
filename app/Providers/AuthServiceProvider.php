<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as BaseAuthServiceProvider;
use Illuminate\Support\Facades\Gate;

/**
 * Authorization provider (T-019).
 *
 * Gates:
 *   - manage-users:  Coordinador only
 *   - view-admin:    Coordinador + Director
 *
 * Policies:
 *   - User → UserPolicy (coordinador-only mutating actions)
 */
class AuthServiceProvider extends BaseAuthServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('manage-users', function (User $user): bool {
            return $user->role === UserRole::Coordinador;
        });

        Gate::define('view-admin', function (User $user): bool {
            return in_array($user->role, [
                UserRole::Coordinador,
                UserRole::Director,
            ], true);
        });
    }
}
