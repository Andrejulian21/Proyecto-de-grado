<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force external evaluators to change their temporary password
 * before they can hit any other endpoint (T-017).
 *
 * Registered after `auth:sanctum`. The unauthenticated case is
 * handled upstream and we pass through.
 *
 * Whitelist: the only route that may be reached while
 * `User::mustChangePassword()` is true is the change-password
 * endpoint itself (named `auth.change_password`).
 */
class EnsurePasswordChanged
{
    /**
     * Handle the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Pass through the change-password endpoint — that's the
        // whole point: the user is here to fix the very flag the
        // middleware checks.
        if ($request->routeIs('auth.change_password')) {
            return $next($request);
        }

        if ($user->mustChangePassword()) {
            return response()->json(['error' => 'password_change_required'], 403);
        }

        return $next($request);
    }
}
