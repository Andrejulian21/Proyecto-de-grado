<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\User;
use App\Services\EntregaPesoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;

class UpdateEntregaRequest extends FormRequest
{
    /**
     * Only Coordinador can update entregas.
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
     * Same validation contract as StoreEntregaRequest (D4) with `sometimes`
     * semantics so partial updates are allowed.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'grupo_id' => ['sometimes', 'required', 'exists:semestres,id'],
            'fase' => ['sometimes', 'required', 'string', 'max:50'],
            'phase' => ['sometimes', 'required', 'string', 'max:50'],
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion' => ['sometimes', 'required', 'string', 'max:2000'],
            'fecha_limite' => ['sometimes', 'required', 'date'],
            'fecha_inicio' => ['sometimes', 'nullable', 'date', 'before_or_equal:fecha_limite'],
            'hora_inicio' => ['sometimes', 'nullable', 'string', 'max:10'],
            'criterios' => ['sometimes', 'nullable', 'string'],
            'hora_maxima' => ['sometimes', 'nullable', 'string', 'max:10'],
            'grade_percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'proyecto_id' => ['sometimes', 'required', 'exists:proyectos,id'],
            'archivos_requeridos' => ['sometimes', 'required', 'array', 'min:1', 'max:6'],
            'archivos_requeridos.*.id' => [
                'required_with:archivos_requeridos',
                'string',
                'max:50',
                'regex:/^[a-z0-9_-]+$/',
            ],
            'archivos_requeridos.*.nombre' => ['required_with:archivos_requeridos', 'string', 'max:255'],
            'archivos_requeridos.*.versionamiento' => ['required_with:archivos_requeridos', 'boolean'],
            'archivos_requeridos.*.analizable_ia' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Cross-field rules mirroring StoreEntregaRequest (RF-ENT-01/02, D4)
     * plus the 100% pair rule excluding the entrega's own current value.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $archivos = $this->input('archivos_requeridos');

            if (is_array($archivos)) {
                $this->validarArchivoPrincipal($validator, $archivos);
                $this->validarAnalizableIa($validator, $archivos);
            }

            $this->validarPesos($validator);
        });
    }

    /**
     * RF-ENT-01: the main project file must always be present.
     *
     * @param  array<int, array<string, mixed>>  $archivos
     */
    private function validarArchivoPrincipal($validator, array $archivos): void
    {
        $slugs = array_column($archivos, 'id');

        if (! in_array('documento-proyecto', $slugs, true)) {
            $validator->errors()->add(
                'archivos_requeridos',
                "Debe existir al menos el archivo 'documento del proyecto' en los archivos requeridos"
            );
        }
    }

    /**
     * RF-ENT-02: only the main project file may be AI-analyzable.
     *
     * @param  array<int, array<string, mixed>>  $archivos
     */
    private function validarAnalizableIa($validator, array $archivos): void
    {
        foreach ($archivos as $archivo) {
            $slug = $archivo['id'] ?? null;
            $analizable = $archivo['analizable_ia'] ?? false;

            if ($slug !== 'documento-proyecto' && filter_var($analizable, FILTER_VALIDATE_BOOLEAN)) {
                $validator->errors()->add(
                    'archivos_requeridos',
                    "Solo el archivo 'documento del proyecto' puede ser analizable con IA"
                );
            }
        }
    }

    /**
     * RF-ENT-04: enforce the 100% pair rule, excluding this entrega's own
     * current value so the update replaces instead of double-counting.
     */
    private function validarPesos($validator): void
    {
        $peso = $this->input('grade_percentage');

        if ($peso === null) {
            return;
        }

        $entregaId = (int) $this->route('id');
        $entrega = Entrega::find($entregaId);

        $semestreId = $this->input('grupo_id') ?? $entrega?->semester_id;
        $fase = $this->input('fase') ?? $this->input('phase') ?? $entrega?->phase;

        if ($semestreId === null || $fase === null) {
            return;
        }

        try {
            App::make(EntregaPesoService::class)
                ->validarSumaPar((int) $semestreId, (string) $fase, (float) $peso, $entregaId);
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
            'phase' => 'fase',
            'titulo' => 'título',
            'descripcion' => 'descripción',
            'fecha_limite' => 'fecha límite',
            'fecha_inicio' => 'fecha de inicio',
            'hora_inicio' => 'hora de inicio',
            'criterios' => 'criterios de aceptación',
            'hora_maxima' => 'hora máxima',
            'grade_percentage' => 'porcentaje de nota',
            'proyecto_id' => 'proyecto',
            'archivos_requeridos' => 'archivos requeridos',
            'archivos_requeridos.*.id' => 'identificador del archivo',
            'archivos_requeridos.*.nombre' => 'nombre del archivo',
            'archivos_requeridos.*.versionamiento' => 'versionamiento',
            'archivos_requeridos.*.analizable_ia' => 'analizable con IA',
        ];
    }
}
