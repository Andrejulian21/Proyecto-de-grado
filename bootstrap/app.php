<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents(discover: false) // Listeners are registered explicitly
                                 // in App\Providers\EventServiceProvider
                                 // to avoid double-registration (the
                                 // framework's EventServiceProvider
                                 // would otherwise auto-discover and
                                 // also bind WriteAuditLog@handle).
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum cookie-based SPA auth: stateful requests from the
        // configured SANCTUM_STATEFUL_DOMAINS get session + CSRF.
        // Throttle 60/min on /api/* in production.
        $middleware->statefulApi();

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
