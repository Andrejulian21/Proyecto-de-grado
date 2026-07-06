<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Events\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin endpoints for the auth-access-module (PR 2).
 *
 *   - POST /api/admin/evaluadores    → store external evaluator (T-017)
 *   - GET/POST/PUT/DELETE /api/admin/whitelist   → CRUD on authorized emails (T-020)
 *   - GET  /api/admin/audit-logs     → audit log view (T-024)
 *
 * Every action writes an AuditEvent and every mutating endpoint is
 * gated by `role:Coordinador` middleware.
 */
class UserController extends Controller
{
    /**
     * Create a new external evaluator account (T-017).
     *
     * The endpoint:
     *   - Validates name + email.
     *   - Generates a random temporary password (12 chars).
     *   - Creates the user with `es_externo = true`,
     *     `role = EvaluadorExterno`, `password_changed_at = null`.
     *   - Returns the user payload and the plain password (so the
     *     coordinator can share it manually — no email integration
     *     in PR 2; that's a future addition).
     *   - Writes an audit log entry: action=user.created_external.
     */
    public function storeExternal(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);

        $temporaryPassword = Str::password(length: 12, symbols: true);

        $user = User::create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($temporaryPassword),
            'role' => UserRole::EvaluadorExterno->value,
            'es_externo' => true,
            'password_changed_at' => null, // force change on first login
        ]);

        AuditEvent::dispatch(
            $request->user(),
            'user.created_external',
            "Created external evaluator {$user->email}",
            [
                'created_user_id' => $user->id,
                'created_email' => $user->email,
            ],
        );

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'es_externo' => $user->es_externo,
            ],
            'temporary_password' => $temporaryPassword,
        ], 201);
    }
}
