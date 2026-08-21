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
     * Institutional domain used by Google OAuth (AuthController) and whitelist.
     */
    private const INSTITUTIONAL_EMAIL_SUFFIX = '@unab.edu.co';

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }
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
                'ends_with:'.self::INSTITUTIONAL_EMAIL_SUFFIX,
                Rule::unique('authorized_emails', 'email')->whereNull('deleted_at'),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'areas' => ['nullable', 'string', 'max:2000'],
            'codigo_estudiante' => ['nullable', 'string', 'max:20'],
            'role' => [
                'required',
                'string',
                Rule::in(array_map(fn (UserRole $r) => $r->value, self::WHITELIST_ROLES)),
            ],
            // Optional academic profile (Director). Accepted as arrays or *_text strings.
            'research_lines' => ['nullable'],
            'research_lines.*' => ['string', 'max:255'],
            'technologies' => ['nullable'],
            'technologies.*' => ['string', 'max:255'],
            'methodologies' => ['nullable'],
            'methodologies.*' => ['string', 'max:255'],
            'research_lines_text' => ['nullable', 'string', 'max:4000'],
            'technologies_text' => ['nullable', 'string', 'max:4000'],
            'methodologies_text' => ['nullable', 'string', 'max:4000'],
            'academic_experience' => ['nullable', 'string', 'max:5000'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.ends_with' => 'El correo debe ser institucional (@unab.edu.co).',
        ];
    }
}
