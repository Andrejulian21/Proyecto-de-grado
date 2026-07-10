<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the user role update payload (H-008, H-010).
 *
 * Only Coordinador can change roles, and EvaluadorExterno is excluded
 * from the allowed roles list — those accounts are managed via
 * storeExternal / destroyUsuario instead.
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
            'role' => ['required', 'string', Rule::in($allowedRoles)],
        ];
    }
}
