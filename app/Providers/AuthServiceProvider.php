<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as BaseAuthServiceProvider;

/**
 * Authorization provider (T-019, H-009).
 *
 * Gates that had no call-sites in production code have been removed.
 * The `role:Coordinador` middleware enforces access at the route level.
 *
 * UserPolicy is retained — it is still exercised by tests.
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
    }
}
