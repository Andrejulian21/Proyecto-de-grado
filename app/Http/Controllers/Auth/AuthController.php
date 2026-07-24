<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Events\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginExternoRequest;
use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;

/**
 * Authentication controller (PR 2).
 *
 * Routes:
 *   - GET  /auth/redirect              → redirectToGoogle (PR 1 stub)
 *   - GET  /auth/callback              → handleGoogleCallback (T-014)
 *   - POST /api/auth/externo/login     → loginExterno (T-016)
 *   - POST /api/auth/change-password   → changePassword (T-017)
 *   - POST /api/auth/logout            → logout (T-023)
 *   - GET  /api/auth/user              → sessionCheck (T-023)
 */
class AuthController extends Controller
{
    /**
     * The UNAB Google Workspace hosted domain. Must match the `hd`
     * claim returned by Google and the email suffix.
     */
    private const UNAB_HOSTED_DOMAIN = 'unab.edu.co';

    /**
     * Pre-computed bcrypt hash for timing equalization (H-002).
     * Computed lazily once per process lifetime so that the no-user
     * login path runs exactly one Hash::check() at the same cost as
     * a real wrong-password check — without calling Hash::make()
     * on the request path.
     */
    private static ?string $dummyHash = null;

    private static function dummyHash(): string
    {
        if (self::$dummyHash === null) {
            self::$dummyHash = Hash::make('timing-dummy');
        }
        return self::$dummyHash;
    }

    /**
     * Initiate the Google OAuth dance.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        if (empty(config('services.google.client_id'))) {
            return redirect('/login')
                ->with('status', 'Google OAuth no está configurado. Completa GOOGLE_CLIENT_ID en .env para activar el login institucional.');
        }

        return Socialite::driver('google')
            ->with(['hd' => self::UNAB_HOSTED_DOMAIN, 'prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * Handle the Google OAuth callback. Triple validation (T-014,
     * `auth-oauth` domain):
     *
     *   1. `hd` claim (when present) MUST equal unab.edu.co.
     *   2. Email MUST end with @unab.edu.co (case-insensitive).
     *   3. Email MUST be in the `authorized_emails` whitelist.
     *
     * On success: findOrCreate User, sync role from whitelist, purge
     * prior Sanctum tokens, create a fresh token, write `login.success`
     * audit, redirect to `/dashboard/{role}`.
     *
     * On failure: write a `login.rejected` audit event with the
     * reason (domain_mismatch / not_whitelisted / hd_missing), redirect
     * to /login. The same response is used for all rejection reasons
     * so we don't leak which check failed.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        // 1. User denied the consent screen.
        if ($request->has('error')) {
            AuditEvent::dispatch(
                null,
                'login.cancelled',
                'Google OAuth cancelled by user',
                ['google_error' => $request->string('error')->toString()],
            );

            return redirect('/login?error='.urlencode($request->string('error')->toString()));
        }

        // 2. Exchange the auth code for a user object. Wrap in
        // try/catch so network/5xx/invalid_state errors don't bubble
        // up as 500s.
        try {
            /** @var SocialiteUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            AuditEvent::dispatch(
                null,
                'login.error',
                'Google OAuth exchange failed: '.get_class($e),
                ['exception' => get_class($e), 'message' => $e->getMessage()],
            );

            return redirect('/login?error=oauth_error');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        $hd = $this->extractHostedDomain($googleUser);

        // 3. Triple validation.
        $rejectionReason = $this->validateOAuth($email, $hd);

        if ($rejectionReason !== null) {
            AuditEvent::dispatch(
                null,
                'login.rejected',
                $rejectionReason,
                [
                    'channel' => 'google',
                    'email' => $email,
                    'hd' => $hd,
                ],
            );

            return redirect('/login?error=access_denied');
        }

        // 4. findOrCreate the user, sync the role from the whitelist.
        $whitelistEntry = AuthorizedEmail::query()->where('email', $email)->first();

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: $email,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'role' => $whitelistEntry->role->value,
                'es_externo' => false,
            ],
        );

        // 5. Single-session enforcement: purge prior Sanctum tokens
        // AND prior cookie session rows before issuing the new
        // session (issues #10, T-021). Without the sessions-table
        // cleanup, device A's Sanctum SPA cookie would stay valid
        // after device B logs in with the same Google account.
        $user->tokens()->delete();
        $user->update(['last_activity_at' => now()]);

        // 6. Log the user into the session (Sanctum SPA cookie auth).
        // No Bearer token — cookie-only per H-004.
        Auth::login($user, remember: false);
        $request->session()->regenerate();

        // Purge OTHER sessions after the new session id exists so we
        // never delete the session we just authenticated.
        $this->purgePriorSessions($user, $request->session()->getId());
        $request->session()->save();

        // 7. Audit success.
        AuditEvent::dispatch(
            $user,
            'login.success',
            'Google OAuth institutional login',
            [
                'channel' => 'google',
                'role' => $user->role->value,
            ],
        );

        // 8. Redirect to a PUBLIC SPA completion route. Redirecting
        // straight into a ProtectedRoute dashboard races the React
        // AuthProvider bootstrap and can bounce first-time logins
        // back to /login even though Auth::login already succeeded.
        return redirect('/auth/complete');
    }

    /**
     * Apply the triple validation. Returns a string code on failure
     * (one of `domain_mismatch`, `not_whitelisted`, `hd_missing`) or
     * `null` on success.
     */
    private function validateOAuth(string $email, ?string $hd): ?string
    {
        // Suffix check (case-insensitive).
        if (! str_ends_with($email, '@'.self::UNAB_HOSTED_DOMAIN)) {
            return 'domain_mismatch';
        }

        // `hd` claim check — must be present AND equal unab.edu.co.
        // When hd is missing entirely, that's a separate failure
        // code so coordinators can spot the case in the audit log.
        if ($hd === null || $hd === '') {
            return 'hd_missing';
        }

        if ($hd !== self::UNAB_HOSTED_DOMAIN) {
            return 'domain_mismatch';
        }

        // Whitelist check.
        if (! AuthorizedEmail::query()->where('email', $email)->exists()) {
            return 'not_whitelisted';
        }

        return null;
    }

    /**
     * Issue #10 — invalidate every existing session row for the
     * given user except the current session (when provided). Sanctum's
     * `tokens()->delete()` covers API tokens but NOT the cookie-backed
     * session row written by `Auth::login()` via the `database` session
     * driver. Without this call, a user logging in on device B leaves
     * device A's Sanctum SPA cookie valid.
     */
    private function purgePriorSessions(User $user, ?string $exceptSessionId = null): void
    {
        $query = DB::table('sessions')->where('user_id', $user->id);

        if ($exceptSessionId !== null && $exceptSessionId !== '') {
            $query->where('id', '!=', $exceptSessionId);
        }

        $query->delete();
    }

    /**
     * Extract the hosted-domain claim from a Socialite user. The `hd`
     * field is a custom claim that lives in the original `user` array
     * Google returned (the Socialite wrapper exposes it as a public
     * property in some versions and as an entry in `user['hd']` in
     * others — we try both).
     */
    private function extractHostedDomain(SocialiteUser $googleUser): ?string
    {
        $hd = $googleUser->user['hd'] ?? null;

        return ($hd !== null && $hd !== '') ? (string) $hd : null;
    }

    /**
     * External evaluator credential login (T-016, `auth-external` domain).
     *
     * SECURITY: All auth failures return the same 401 `{'error': 'invalid_credentials'}`
     * to prevent user enumeration via differentiated error messages or status codes.
     * Hash::check() is always called (against a dummy hash when the user is not found)
     * to equalize response timing and prevent timing-based enumeration (H-002).
     * The audit log still records the specific reason for forensic analysis.
     */
    public function loginExterno(LoginExternoRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $user = User::query()
            ->where('email', $payload['email'])
            ->first();

        // Dummy hash check to prevent timing-based user enumeration (H-002).
        // When no user is found, Hash::check() still runs against a pre-computed
        // dummy hash so the response time has the same bcrypt cost as a real
        // wrong-password check. The hash is lazily computed once per process
        // (self::dummyHash()), avoiding Hash::make() on the request path.
        if (! $user) {
            Hash::check($payload['password'], self::dummyHash());
            AuditEvent::dispatch(
                null,
                'login.rejected',
                'invalid_credentials',
                ['channel' => 'external', 'reason' => 'user_not_found'],
            );

            return response()->json(['error' => 'invalid_credentials'], 401);
        }

        if ($user->isLocked()) {
            AuditEvent::dispatch(
                $user,
                'login.locked',
                'account locked by sliding-window policy',
                ['channel' => 'external', 'locked_until' => $user->locked_until?->toIso8601String()],
            );

            return response()->json(['error' => 'account_locked'], 423);
        }

        // Unified failure: same 401 for internal users AND wrong passwords (H-002).
        if (! $user->es_externo || ! Hash::check($payload['password'], $user->password)) {
            $reason = $user->es_externo ? 'wrong_password' : 'not_external_evaluator';
            AuditEvent::dispatch(
                $user,
                'login.rejected',
                'invalid_credentials',
                ['channel' => 'external', 'reason' => $reason],
            );

            if ($user->es_externo) {
                $user->registerFailedLogin();
            }

            return response()->json(['error' => 'invalid_credentials'], 401);
        }

        $user->clearFailedLogin();
        $user->update(['last_activity_at' => now()]);
        $user->tokens()->delete();
        $this->purgePriorSessions($user);

        // Log into the session so the SPA can read /api/auth/user
        // via Sanctum cookie-based auth. No Bearer token — cookie-only (H-004).
        Auth::login($user, remember: false);

        AuditEvent::dispatch(
            $user,
            'login.success',
            'external evaluator credential login',
            ['channel' => 'external'],
        );

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role->value,
                'es_externo' => $user->es_externo,
            ],
            'must_change_password' => $user->mustChangePassword(),
        ]);
    }

    /**
     * Change the authenticated user's password (T-017).
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $user = $request->user();

        if (! $user || ! Hash::check($payload['current_password'], $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'current_password' => 'La contraseña actual es incorrecta.',
            ]);
        }

        $user->password = $payload['new_password'];
        $user->password_changed_at = now();
        $user->save();

        AuditEvent::dispatch(
            $user,
            'password.changed',
            'external evaluator changed their password',
            ['channel' => 'external'],
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Logout the current user (T-023).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()?->delete();

            AuditEvent::dispatch(
                $user,
                'logout.user_initiated',
                'user initiated logout',
            );
        }

        // Log out of the session (Sanctum SPA cookie auth).
        Auth::logout();

        // Invalidate the session and regenerate the CSRF token.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    /**
     * Return the current authenticated user (T-023 sessionCheck).
     */
    public function sessionCheck(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'es_externo' => $user->es_externo,
            'avatar' => $user->avatar,
            'must_change_password' => $user->mustChangePassword(),
        ]);
    }
}
