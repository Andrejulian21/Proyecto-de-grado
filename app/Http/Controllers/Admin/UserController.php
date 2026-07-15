<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Events\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateEvaluadorRequest;
use App\Http\Requests\StoreWhitelistRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * GET /api/admin/usuarios
     *
     * Returns a paginated list of all users (from the users table)
     * with optional search by name or email.
     */
    public function usuarios(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 50), 200);
        $search = $request->query('search');
        $role = $request->query('role');

        $query = User::query()->orderByDesc('id');

        if ($search) {
            $op = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $op) {
                $q->where('email', $op, "%{$search}%")
                  ->orWhere('name', $op, "%{$search}%");
            });
        }

        if ($role) {
            // Normalize: "estudiante" → "Estudiante"
            $role = ucfirst(strtolower($role));

            // Only apply filter if it's a valid role
            if (in_array($role, UserRole::values(), true)) {
                $query->where('role', $role);
            }
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * PUT /api/admin/usuarios/{id}
     *
     * Updates a user's role. Only Coordinador can do this.
     */
    public function updateUsuario(UpdateUserRequest $request, User $user): JsonResponse
    {
        $payload = $request->validated();
        $oldRole = $user->role->value;
        $user->role = $payload['role'];
        $user->save();

        AuditEvent::dispatch(
            $request->user(),
            'role.changed',
            "{$oldRole}→{$user->role->value}",
            ['subject_id' => $user->id, 'new_role' => $user->role->value],
        );

        return response()->json(['id' => $user->id, 'role' => $user->role->value]);
    }

    /**
     * DELETE /api/admin/usuarios/{id}
     *
     * Soft-delete or remove a user from the system.
     */
    public function destroyUsuario(Request $request, User $user): JsonResponse
    {
        $email = $user->email;
        $id = $user->id;
        $user->delete();

        // Also remove the whitelist entry so the email can be re-registered
        AuthorizedEmail::where('email', $email)->delete();

        AuditEvent::dispatch(
            $request->user(),
            'user.deleted',
            "Deleted user {$email}",
            ['deleted_user_id' => $id],
        );

        return response()->json(['message' => 'Usuario eliminado']);
    }

    /**
     * Allowed internal roles for whitelist entries.
     *
     * EvaluadorExterno accounts are not added to the whitelist —
     * they are created directly via `storeExternal()` instead, so
     * they live on a separate authentication path.
     */
    private const WHITELIST_ROLES = [
        UserRole::Estudiante,
        UserRole::Director,
        UserRole::Coordinador,
    ];

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
    public function storeExternal(CreateEvaluadorRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $temporaryPassword = Str::password(length: 16, symbols: true);

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

    // -------------------------------------------------------------------
    // Whitelist CRUD (T-020)
    // -------------------------------------------------------------------

    /**
     * GET /api/admin/whitelist
     *
     * Returns a paginated list of authorized emails. The
     * `created_by` is the id of the coordinador who added the row.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 50), 200);

        $page = AuthorizedEmail::query()
            ->with('creator:id,name')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($page);
    }

    /**
     * POST /api/admin/whitelist
     *
     * Validates that the email is well-formed, ends in
     * `@unab.edu.co`, and is not already in the whitelist.
     */
    public function store(StoreWhitelistRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $areas = $payload['areas'] ?? null;

        $email = strtolower($payload['email']);

        // Force-delete any soft-deleted record with the same email
        // to avoid UNIQUE constraint violations on re-add
        AuthorizedEmail::withTrashed()
            ->where('email', $email)
            ->forceDelete();

        $entry = AuthorizedEmail::create([
            'email' => $email,
            'name' => $payload['name'] ?? null,
            'role' => $payload['role'],
            'created_by' => $request->user()?->id,
        ]);

        // Also create a User record so the person appears in user listings,
        // director cupos, etc. before their first login.
        // Password is null → they must use Google OAuth or institutional login.
        $existingUser = User::where('email', $email)->first();
        if (! $existingUser) {
            User::create([
                'email' => $email,
                'name' => $payload['name'] ?? explode('@', $email)[0],
                'password' => null,
                'role' => $payload['role'],
                'areas' => $areas,
            ]);
        }

        AuditEvent::dispatch(
            $request->user(),
            'whitelist.add',
            "Added {$entry->email} as {$entry->role->value}",
            [
                'email' => $entry->email,
                'role' => $entry->role->value,
            ],
        );

        return response()->json([
            'id' => $entry->id,
            'email' => $entry->email,
            'role' => $entry->role->value,
        ], 201);
    }

    /**
     * PUT /api/admin/whitelist/{id}
     *
     * Updates the role of an existing whitelist entry. If a User
     * already exists with that email, the user's role is synced
     * so the change is visible on their next /api/user fetch.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $entry = AuthorizedEmail::query()->find($id);

        if (! $entry) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $payload = $request->validate([
            'role' => [
                'required',
                'string',
                Rule::in(array_map(fn (UserRole $r) => $r->value, self::WHITELIST_ROLES)),
            ],
        ]);

        $previousRole = $entry->role;
        $newRole = UserRole::from($payload['role']);

        $entry->role = $newRole;
        $entry->save();

        // Sync the matching User's role so the change applies on
        // their next session check (T-020 "Coordinator updates
        // an existing whitelist entry's role").
        User::query()
            ->where('email', $entry->email)
            ->update(['role' => $newRole->value]);

        AuditEvent::dispatch(
            $request->user(),
            'whitelist.role_changed',
            "{$entry->email}: {$previousRole->value}→{$newRole->value}",
            [
                'email' => $entry->email,
                'previous_role' => $previousRole->value,
                'new_role' => $newRole->value,
            ],
        );

        return response()->json(['ok' => true]);
    }

    /**
     * DELETE /api/admin/whitelist/{id}
     *
     * Hard-deletes the row (the spec allowed soft-delete, but the
     * test contract is "row no longer exists"). The matching
     * User's existing session will be invalidated on their next
     * request because their email no longer matches the whitelist
     * — but the spec delegates that to a future "session invalidation
     // on whitelist removal" hook. We just remove the row here.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $entry = AuthorizedEmail::query()->find($id);

        if (! $entry) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $email = $entry->email;

        // Also remove the corresponding user account if one exists,
        // so re-adding the email creates a clean slate.
        User::where('email', $email)->delete();

        $entry->delete();

        AuditEvent::dispatch(
            $request->user(),
            'whitelist.removed',
            "Removed {$email} from whitelist",
            [
                'email' => $email,
            ],
        );

        return response()->json(['ok' => true]);
    }
}
