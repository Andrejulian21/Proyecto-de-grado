<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the whitelist store payload (H-008, H-010).
 *
 * Only Coordinador can add whitelist entries (enforced by route middleware).
 */
class StoreWhitelistRequest extends FormRequest
{
    /**
     * The WHITELIST_ROLES constant from UserController is replicated here
     * so the FormRequest is self-contained. EvaluadorExterno accounts
     * are created via storeExternal, not via the whitelist.
     */
    private const WHITELIST_ROLES = [
        UserRole::Estudiante,
        UserRole::Director,
        UserRole::Coordinador,
    ];

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
        return [
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                'ends_with:@unab.edu.co',
                Rule::unique('authorized_emails', 'email')->whereNull('deleted_at'),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'areas' => ['nullable', 'string', 'max:1000'],
            'codigo_estudiante' => ['nullable', 'string', 'max:20'],
            'role' => [
                'required',
                'string',
                Rule::in(array_map(fn (UserRole $r) => $r->value, self::WHITELIST_ROLES)),
            ],
        ];
    }
}
