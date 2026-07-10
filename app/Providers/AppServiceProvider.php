<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Default rate limiter for the /api/* routes (referenced by
        // ->throttleApi() in bootstrap/app.php). 60 requests per minute
        // per (user, IP) — generous for now, tighten in production.
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(60)->by((string) $key);
        });

        // Dedicated rate limiter for the external login endpoint (H-003).
        // 5 attempts per minute per (IP + email) combination to mitigate
        // credential stuffing and brute-force attacks. The key uses both
        // IP and email so a single compromised credential across multiple
        // IPs doesn't exhaust the global API budget.
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by($request->ip().'|'.$email),
            ];
        });
    }
}
