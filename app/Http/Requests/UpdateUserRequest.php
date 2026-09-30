<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the user update payload (H-008, H-010).
 *
 * Only Coordinador can change roles, and EvaluadorExterno is excluded
 * from the allowed roles list — those accounts are managed via
 * storeExternal / destroyUsuario instead.
 *
 * `name` and `email` are accepted so the coordinator's edit form stops
 * losing them: `validated()` silently drops any field absent from the
 * rules, which is what made the rename a no-op.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role === UserRole::Coordinador;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        // Exclude EvaluadorExterno — those accounts are managed via
        // storeExternal / destroyUsuario.
        $allowedRoles = [
            UserRole::Estudiante->value,
            UserRole::Director->value,
            UserRole::Coordinador->value,
        ];

        return [
            // `sometimes` + `required`: this endpoint is also the role-only
            // update (DirectorDeletionTest, UsuarioAuditTest and the
            // `updateRole` helper send just `{ role }`), so `name` cannot be
            // unconditionally required. But once the caller DOES send it, a
            // blank or oversized value must fail loudly instead of being
            // dropped by `validated()` — that silent discard is the bug.
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'string',
                'email',
                'max:255',
                // `users.email` is unique (case-insensitively since
                // 2026_08_24_990003). Validating it here turns a duplicate into
                // a 422 instead of a 500 from the DB constraint.
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],

            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'codigo_estudiante' => ['nullable', 'string', 'max:20'],
        ];
    }
}
