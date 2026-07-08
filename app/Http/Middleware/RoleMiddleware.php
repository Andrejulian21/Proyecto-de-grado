<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Events\AuditEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based access middleware (T-018).
 *
 * Usage:
 *   Route::middleware('role:Coordinador');
 *   Route::middleware('role:Coordinador,Director');
 *
 * On every request:
 *   - Read the comma-separated role list from the middleware parameter.
 *   - Compare against the authenticated user's role.
 *   - If the user's role is in the list, continue.
 *   - Otherwise return 403 with `{error: "unauthorized"}` and write
 *     an `access.denied` audit log entry with reason `role_mismatch`.
 */
class RoleMiddleware
{
    /**
     * Handle the request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // No user means the auth middleware hasn't run yet. Pass
        // through and let `auth:sanctum` reject upstream.
        if (! $user) {
            return $next($request);
        }

        $userRole = $user->role?->value;

        if ($userRole !== null && in_array($userRole, $roles, true)) {
            return $next($request);
        }

        AuditEvent::dispatch(
            $user,
            'access.denied',
            'role_mismatch',
            [
                'required_roles' => array_values($roles),
                'actual_role' => $userRole,
                'path' => $request->path(),
                'method' => $request->method(),
            ],
        );

        return response()->json(['error' => 'unauthorized'], 403);
    }
}
