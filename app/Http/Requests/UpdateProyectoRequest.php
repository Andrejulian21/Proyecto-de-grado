<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates coordinator updates to a project (title, director, students).
 */
class UpdateProyectoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role === UserRole::Coordinador;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:500'],
            'director_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Director->value),
            ],
            'student_ids' => ['sometimes', 'array', 'max:3'],
            'student_ids.*' => ['integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'director_id.exists' => 'El director seleccionado no es válido o no tiene rol de director.',
            'student_ids.max' => 'Máximo 3 estudiantes por proyecto.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $studentIds = $this->input('student_ids');

            if (! is_array($studentIds) || $studentIds === []) {
                return;
            }

            /** @var Proyecto|null $proyecto */
            $proyecto = $this->route('proyecto');
            $proyectoId = $proyecto instanceof Proyecto ? $proyecto->id : null;

            $alreadyAssigned = DB::table('proyecto_estudiante')
                ->whereIn('user_id', $studentIds)
                ->when($proyectoId !== null, fn ($q) => $q->where('proyecto_id', '!=', $proyectoId))
                ->pluck('user_id')
                ->unique()
                ->all();

            if ($alreadyAssigned === []) {
                return;
            }

            $names = User::query()->whereIn('id', $alreadyAssigned)->pluck('name')->join(', ');
            $validator->errors()->add(
                'student_ids',
                "Los siguientes estudiantes ya tienen un proyecto asignado: {$names}",
            );
        });
    }
}
