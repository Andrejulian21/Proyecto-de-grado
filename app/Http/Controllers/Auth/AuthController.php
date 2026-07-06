<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\LoginAttemptPolicy;
use App\Enums\UserRole;
use App\Events\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

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
     * Initiate the Google OAuth dance.
     */
    public function redirectToGoogle()
    {
        if (empty(config('services.google.client_id'))) {
            return redirect('/login')
                ->with('status', 'Google OAuth no está configurado. Completa GOOGLE_CLIENT_ID en .env para activar el login institucional.');
        }

        return \Laravel\Socialite\Facades\Socialite::driver('google')->redirect();
    }

    /**
     * Google OAuth callback. Real triple validation lands in T-014.
     */
    public function handleGoogleCallback()
    {
        return redirect('/login')
            ->with('status', 'OAuth callback pendiente. Implementación completa en T-014.');
    }

    /**
     * External evaluator credential login (T-016, `auth-external` domain).
     *
     * Validation:
     *   - email + password required.
     *   - user must have es_externo = true (otherwise 403).
     *   - user must not be currently locked (otherwise 423).
     *
     * On success: create a Sanctum token, reset the failure counter,
     * dispatch `login.success` audit event with `channel=external`, and
     * return `{token, user, must_change_password}`.
     *
     * On failure: increment the failure counter (lockout after
     * `LoginAttemptPolicy::maxAttempts()` attempts), dispatch
     * `login.rejected` audit event, return 401.
     */
    public function loginExterno(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $payload['email'])
            ->first();

        // Reject unverified user identities without leaking whether
        // the email exists.
        if (! $user || ! $user->es_externo) {
            AuditEvent::dispatch(
                $user,
                'login.rejected',
                'invalid_credentials',
                ['channel' => 'external', 'reason' => 'not_external_evaluator'],
            );

            if ($user && ! $user->es_externo) {
                return response()->json(['error' => 'not_external_evaluator'], 403);
            }

            return response()->json(['error' => 'invalid_credentials'], 401);
        }

        if ($user->isLocked()) {
            AuditEvent::dispatch(
                $user,
                'login.rejected',
                'account_locked',
                ['channel' => 'external', 'locked_until' => $user->locked_until?->toIso8601String()],
            );

            return response()->json(['error' => 'account_locked'], 423);
        }

        if (! Hash::check($payload['password'], $user->password)) {
            $user->registerFailedLogin();

            AuditEvent::dispatch(
                $user,
                'login.rejected',
                'invalid_credentials',
                ['channel' => 'external', 'failed_attempts' => $user->failed_attempts],
            );

            // The current attempt still returns 401 (it was the failure
            // that triggered the lockout). The next request will see
            // `isLocked()` at the top of this method and return 423.
            return response()->json(['error' => 'invalid_credentials'], 401);
        }

        // Success — reset counters, purge prior sessions, issue token,
        // audit, return.
        $user->clearFailedLogin();
        $user->update(['last_activity_at' => now()]);

        // Single-session enforcement (T-021): delete any existing
        // Sanctum tokens for this user before issuing the new one.
        $user->tokens()->delete();

        /** @var NewAccessToken $accessToken */
        $accessToken = $user->createToken('external-evaluator', ['*'], now()->addMinutes(15));

        AuditEvent::dispatch(
            $user,
            'login.success',
            'external evaluator credential login',
            ['channel' => 'external'],
        );

        return response()->json([
            'token' => $accessToken->plainTextToken,
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
     *
     * On success: sets `password` and `password_changed_at = now()`.
     * Triggers a `password.changed` audit event.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! $user || ! Hash::check($payload['current_password'], $user->password)) {
            throw ValidationException::withMessages([
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
     * Logout the current user (T-023). Deletes the Sanctum token and
     * dispatches a `logout.user_initiated` audit event.
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
