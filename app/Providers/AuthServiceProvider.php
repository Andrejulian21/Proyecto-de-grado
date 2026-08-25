<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Bitacora;
use App\Models\Entrega;
use App\Models\User;
use App\Policies\BitacoraPolicy;
use App\Policies\EntregaPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as BaseAuthServiceProvider;

/**
 * Authorization provider (T-019, H-009, issue #38).
 *
 * Gates that had no call-sites in production code have been removed.
 * The `role:Coordinador` middleware enforces access at the route level.
 *
 * Issue #38: EntregaPolicy and BitacoraPolicy centralize the default-deny
 * authorization rule for deliveries and bitácoras (coordinator, director,
 * student, or assigned evaluator). UserPolicy is retained — it is still
 * exercised by tests.
 */
class AuthServiceProvider extends BaseAuthServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Entrega::class => EntregaPolicy::class,
        Bitacora::class => BitacoraPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
