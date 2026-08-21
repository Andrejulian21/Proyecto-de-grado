<?php

declare(strict_types=1);

namespace App\Actions\Entrega;

use App\Actions\Entrega\Exceptions\EntregaActionException;
use App\Models\Entrega;

/**
 * Single-purpose use case: apply the mutable fields of an entrega (partial
 * update contract, same field names as StoreEntregaRequest plus legacy
 * `phase`).
 */
final class UpdateEntregaAction
{
    /**
     * @param  array<string, mixed>  $data  validated update payload
     */
    public function handle(Entrega $entrega, array $data): Entrega
    {
        if (isset($data['titulo'])) {
            $entrega->title = $data['titulo'];
        }

        if (array_key_exists('descripcion', $data)) {
            $entrega->description = $data['descripcion'];
        }

        if (isset($data['fecha_limite'])) {
            $entrega->due_date = $data['fecha_limite'];
        }

        if (array_key_exists('fecha_inicio', $data)) {
            $entrega->start_date = $data['fecha_inicio'];
        }

        if (array_key_exists('hora_inicio', $data)) {
            $entrega->start_time = $data['hora_inicio'];
        }

        if (array_key_exists('criterios', $data)) {
            $entrega->acceptance_criteria = $data['criterios'];
        }

        if (array_key_exists('metricas_evaluacion', $data) || array_key_exists('evaluation_metrics', $data)) {
            $entrega->evaluation_metrics = $data['metricas_evaluacion'] ?? $data['evaluation_metrics'];
        }

        if (array_key_exists('hora_maxima', $data)) {
            $entrega->hora_maxima = $data['hora_maxima'];
        }

        $phase = $data['fase'] ?? $data['phase'] ?? null;

        if ($phase !== null) {
            $entrega->phase = $phase;
        }

        if (isset($data['proyecto_id'])) {
            $entrega->proyecto_id = $data['proyecto_id'];
        }

        if (array_key_exists('grade_percentage', $data)) {
            $entrega->grade_percentage = $data['grade_percentage'];
        }

        if (isset($data['archivos_requeridos'])) {
            $this->actualizarArchivosRequeridos($entrega, $data['archivos_requeridos']);
        }

        $entrega->save();

        return $entrega;
    }

    /**
     * Replace the archivos_requeridos JSON, keeping the legacy guards:
     * cannot disable versioning of a file that already has versions, and
     * slugs must stay unique.
     *
     * @param  array<int, array<string, mixed>>  $nuevosArchivosPayload
     */
    private function actualizarArchivosRequeridos(Entrega $entrega, array $nuevosArchivosPayload): void
    {
        $actuales = $entrega->archivos_requeridos ?? [];
        $nuevosArchivos = collect($nuevosArchivosPayload)->map(function (array $item) use ($actuales) {
            $actual = collect($actuales)->firstWhere('slug', $item['id'] ?? $item['slug'] ?? null);

            return [
                'slug' => $item['id'],
                'nombre' => $item['nombre'],
                'versionamiento' => (bool) $item['versionamiento'],
                'analizable_ia' => (bool) ($item['analizable_ia'] ?? ($actual['analizable_ia'] ?? false)),
            ];
        });

        foreach ($actuales as $actual) {
            $nuevo = $nuevosArchivos->firstWhere('slug', $actual['slug'] ?? $actual['id'] ?? null);

            if ($nuevo && ($actual['versionamiento'] ?? false) === true && $nuevo['versionamiento'] === false) {
                $tieneVersiones = $entrega->versiones()
                    ->where('archivo_requerido_id', $actual['slug'] ?? $actual['id'] ?? null)
                    ->exists();

                if ($tieneVersiones) {
                    throw new EntregaActionException(
                        "No se puede deshabilitar el versionamiento para '{$actual['nombre']}' porque ya tiene versiones subidas."
                    );
                }
            }
        }

        $ids = $nuevosArchivos->pluck('slug')->toArray();

        if (count($ids) !== count(array_unique($ids))) {
            throw new EntregaActionException('Los IDs de los archivos requeridos deben ser únicos.');
        }

        $entrega->archivos_requeridos = $nuevosArchivos->toArray();
    }
}
