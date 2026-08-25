<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce one active Sanctum token per user (T-021).
 *
 * The single-session rule is enforced at login time: the
 * AuthController (and Google callback) delete all prior tokens
 * AND prior session rows for the user before issuing a new one.
 *
 * This middleware is a belt-and-suspenders check that runs on
 * every request and guards the Bearer-token path against the
 * edge case where a second token exists (e.g. a login path that
 * did not purge). When that happens, the MOST RECENT token is
 * the active session and any older token is a previous session
 * that must be invalidated.
 *
 * Cookie/Sanctum-SPA sessions carry no API token, so there is
 * nothing to compare — single-session is inherent to the session
 * driver (prior session rows are purged at login) and we pass
 * through.
 */
class SingleSessionMiddleware
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

        $token = $user->currentAccessToken();

        // Cookie/Sanctum-SPA session auth has no API token. Single-session
        // is inherent to the session driver (AuthController purges prior
        // session rows at login), so there is nothing to enforce here.
        if (! $token) {
            return $next($request);
        }

        // Single-session invariant: "at most one token per user", normally
        // guaranteed by the login-time purge. If a second token ever exists,
        // the most recent token is the active session; any older token is a
        // previous session and must be rejected (and revoked).
        $tokens = $user->tokens()->orderByDesc('id')->get();

        if ($tokens->count() > 1) {
            $active = $tokens->first();

            if ((int) $token->getKey() !== (int) $active->getKey()) {
                // This request rides a stale (previous) session → kill it.
                $token->delete();

                return response()->json(['error' => 'session.replaced'], 401);
            }

            // Current token is the active session → purge the stale ones.
            $user->tokens()->whereKeyNot($token->getKey())->delete();
        }

        return $next($request);
    }
}
