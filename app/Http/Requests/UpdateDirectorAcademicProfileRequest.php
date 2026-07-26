<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates academic profile updates for Directors (Coordinador only).
 */
class UpdateDirectorAcademicProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role === UserRole::Coordinador;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'areas' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'research_lines' => ['sometimes', 'nullable'],
            'research_lines.*' => ['string', 'max:255'],
            'technologies' => ['sometimes', 'nullable'],
            'technologies.*' => ['string', 'max:255'],
            'methodologies' => ['sometimes', 'nullable'],
            'methodologies.*' => ['string', 'max:255'],
            'academic_experience' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'years_of_experience' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            // Allow newline-separated strings from the SPA as an alternative to arrays.
            'research_lines_text' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'technologies_text' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'methodologies_text' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }
}
