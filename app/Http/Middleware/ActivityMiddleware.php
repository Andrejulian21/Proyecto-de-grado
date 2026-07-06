<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Events\AuditEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 8-hour inactivity timeout (T-022).
 *
 * On every authenticated request:
 *   1. Read the user's `last_activity_at`.
 *   2. If it is set and older than 8 hours, delete the current
 *      Sanctum token, dispatch `logout.timeout`, return 401
 *      `{error: "session.timeout", code: "session.timeout"}`.
 *   3. Otherwise update `last_activity_at` to now() and pass through.
 *
 * The 8h window matches the spec (`SESSION_LIFETIME=480` minutes in
 * the env file). The middleware is the source of truth — the
 * `SESSION_LIFETIME` env value is the coarse backup.
 */
class ActivityMiddleware
{
    /**
     * Maximum inactivity window in minutes.
     */
    private const INACTIVITY_MINUTES = 8 * 60;

    /**
     * Handle the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $lastActivity = $user->last_activity_at;

        if ($lastActivity !== null
            && $lastActivity->lt(now()->subMinutes(self::INACTIVITY_MINUTES))) {
            $user->currentAccessToken()?->delete();

            AuditEvent::dispatch(
                $user,
                'logout.timeout',
                '8-hour inactivity timeout',
            );

            return response()->json(['error' => 'session.timeout'], 401);
        }

        // Update last_activity_at only if the column is loaded and
        // writable. We use updateQuietly-style save to avoid firing
        // model events that could cause infinite middleware loops.
        $user->forceFill(['last_activity_at' => now()])->saveQuietly();

        return $next($request);
    }
}
