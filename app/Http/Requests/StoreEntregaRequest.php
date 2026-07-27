<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreEntregaRequest extends FormRequest
{
    /**
     * Only Coordinador can create entregas.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role === UserRole::Coordinador;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'grupo_id' => ['required', 'exists:semestres,id'],
            'fase' => ['required', 'string', 'max:50'],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:500'],
            'fecha_limite' => ['required', 'date'],
            'fecha_inicio' => ['nullable', 'date', 'before_or_equal:fecha_limite'],
            'hora_inicio' => ['nullable', 'string', 'max:10'],
            'criterios' => ['nullable', 'string'],
            'hora_maxima' => ['nullable', 'string', 'max:10'],
            'archivos_requeridos' => ['required', 'array', 'min:1', 'max:6'],
            'archivos_requeridos.*.id' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
            ],
            'archivos_requeridos.*.nombre' => ['required', 'string', 'max:255'],
            'archivos_requeridos.*.versionamiento' => ['required', 'boolean'],
        ];
    }

    /**
     * Validate that IDs within the array are unique.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $archivos = $this->input('archivos_requeridos');

            if (! is_array($archivos)) {
                return;
            }

            $ids = array_column($archivos, 'id');

            if (count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add(
                    'archivos_requeridos',
                    'Los IDs de los archivos requeridos deben ser únicos.'
                );
            }
        });
    }

    /**
     * Map the incoming field names to database column names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'grupo_id' => 'semestre',
            'fase' => 'fase',
            'titulo' => 'título',
            'descripcion' => 'descripción',
            'fecha_limite' => 'fecha límite',
            'fecha_inicio' => 'fecha de inicio',
            'hora_inicio' => 'hora de inicio',
            'criterios' => 'criterios de aceptación',
            'hora_maxima' => 'hora máxima',
            'archivos_requeridos' => 'archivos requeridos',
            'archivos_requeridos.*.id' => 'identificador del archivo',
            'archivos_requeridos.*.nombre' => 'nombre del archivo',
            'archivos_requeridos.*.versionamiento' => 'versionamiento',
        ];
    }
}
