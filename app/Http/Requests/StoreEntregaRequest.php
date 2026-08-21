<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Http\Requests\Concerns\ValidatesDocumentosSolicitados;
use App\Models\User;
use App\Services\EntregaPesoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;

class StoreEntregaRequest extends FormRequest
{
    use ValidatesDocumentosSolicitados;

    /**
     * Only Coordinador can create entregas.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role === UserRole::Coordinador;
    }

    /**
     * Normalize `archivos_requeridos.*.slug` → `*.id` so the persisted JSON
     * shape (slug-based) is accepted as an alias of the builder shape
     * (id-based). The canonical identity is `id` (RF-ENT-01).
     */
    protected function prepareForValidation(): void
    {
        $archivos = $this->input('archivos_requeridos');

        if (! is_array($archivos)) {
            return;
        }

        $archivos = array_map(static function (array $item): array {
            if (! isset($item['id']) && isset($item['slug']) && is_string($item['slug'])) {
                $item['id'] = $item['slug'];
            }

            return $item;
        }, $archivos);

        $this->merge(['archivos_requeridos' => $archivos]);
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
            'descripcion' => ['required', 'string', 'max:2000'],
            'fecha_limite' => ['required', 'date'],
            'fecha_inicio' => ['nullable', 'date', 'before_or_equal:fecha_limite'],
            'hora_inicio' => ['nullable', 'string', 'max:10'],
            'criterios' => ['nullable', 'string'],
            'hora_maxima' => ['nullable', 'string', 'max:10'],
            'grade_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'archivos_requeridos' => ['required', 'array', 'min:1', 'max:6'],
            'archivos_requeridos.*.id' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_-]+$/',
            ],
            'archivos_requeridos.*.nombre' => ['required', 'string', 'max:255'],
            'archivos_requeridos.*.versionamiento' => ['required', 'boolean'],
            'archivos_requeridos.*.analizable_ia' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Cross-field rules: unique document ids, at most one analizable_ia
     * document, and the 100% pair rule (RF-ENT-04) via EntregaPesoService.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $archivos = $this->input('archivos_requeridos');

            if (is_array($archivos)) {
                $this->validarUnicidadDocumentos($validator, $archivos);
                $this->validarUnicoDocumentoAnalizableIa($validator, $archivos);
            }

            $this->validarPesos($validator);
        });
    }

    /**
     * RF-ENT-04: enforce the 100% pair rule through EntregaPesoService.
     */
    private function validarPesos($validator): void
    {
        $peso = $this->input('grade_percentage');

        if ($peso === null) {
            return;
        }

        $semestreId = $this->input('grupo_id');
        $fase = $this->input('fase');

        if ($semestreId === null || $fase === null) {
            return;
        }

        try {
            App::make(EntregaPesoService::class)
                ->validarSumaPar((int) $semestreId, (string) $fase, (float) $peso);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $validator->errors()->add($campo, $mensaje);
                }
            }
        }
    }

    public function messages(): array
    {
        return [
            'grade_percentage.min' => 'El porcentaje de nota debe estar entre 0 y 100',
            'grade_percentage.max' => 'El porcentaje de nota debe estar entre 0 y 100',
            'fecha_inicio.before_or_equal' => 'La fecha de apertura debe ser anterior o igual a la fecha de cierre.',
        ];
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
            'fecha_limite' => 'fecha de cierre',
            'fecha_inicio' => 'fecha de apertura',
            'hora_inicio' => 'hora de apertura',
            'criterios' => 'criterios de aceptación',
            'hora_maxima' => 'hora de cierre',
            'grade_percentage' => 'porcentaje de nota',
            'archivos_requeridos' => 'archivos requeridos',
            'archivos_requeridos.*.id' => 'identificador del archivo',
            'archivos_requeridos.*.nombre' => 'nombre del archivo',
            'archivos_requeridos.*.versionamiento' => 'versionamiento',
            'archivos_requeridos.*.analizable_ia' => 'analizable con IA',
        ];
    }
}
