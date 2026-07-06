<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

/**
 * Authentication controller stub.
 *
 * The full triple validation (hd claim + email suffix + whitelist) and
 * the external evaluator credential flow land in PR 2 (T-014, T-016).
 * For PR 1 — Foundation — this controller only proves the routes resolve
 * and Socialite is wired up. The redirect points to a placeholder login
 * page until the React UI ships in PR 3.
 *
 * @see /openspec/changes/auth-access-module/tasks.md — T-014, T-016
 */
class AuthController extends Controller
{
    /**
     * Initiate the Google OAuth dance.
     *
     * In production: Socialite::driver('google')
     *     ->scopes(['openid', 'profile', 'email'])
     *     ->with(['hd' => 'unab.edu.co'])
     *     ->redirect();
     *
     * For PR 1, we issue the redirect so the route resolves. The
     * `redirect()` call works only when GOOGLE_CLIENT_ID is set; if the
     * placeholder env is empty, we fall back to a friendly message so
     * the foundation work doesn't hard-fail.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        if (empty(config('services.google.client_id'))) {
            return redirect('/login')
                ->with('status', 'Google OAuth no está configurado. Completa GOOGLE_CLIENT_ID en .env para activar el login institucional.');
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the Google OAuth callback. Full implementation in PR 2 (T-014):
     *  1. Validate the Socialite user.
     *  2. Check `hd === unab.edu.co` (when present).
     *  3. Check the email ends in @unab.edu.co.
     *  4. Check the whitelist (authorized_emails).
     *  5. findOrCreate User, assign role from whitelist.
     *  6. Delete prior Sanctum tokens (single-session).
     *  7. Dispatch AuditEvent('login.success' | 'login.rejected').
     *  8. Redirect to /dashboard/{role}.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        // PR 2 implementation. PR 1 returns a placeholder so the route
        // resolves and the test suite can exercise the wiring.
        return redirect('/login')
            ->with('status', 'OAuth callback pendiente. Implementación completa en PR 2 (T-014).');
    }
}
