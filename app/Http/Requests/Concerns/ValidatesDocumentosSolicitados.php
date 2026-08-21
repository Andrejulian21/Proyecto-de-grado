<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait ValidatesDocumentosSolicitados
{
    /**
     * @param  array<int, array<string, mixed>>  $archivos
     */
    protected function validarUnicidadDocumentos($validator, array $archivos): void
    {
        $ids = [];

        foreach ($archivos as $archivo) {
            $id = $archivo['id'] ?? $archivo['slug'] ?? null;

            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        if (count($ids) !== count(array_unique($ids))) {
            $validator->errors()->add(
                'archivos_requeridos',
                'Los IDs de los archivos requeridos deben ser únicos.'
            );
        }
    }

    /**
     * At most one requested document may be AI-analyzable.
     *
     * @param  array<int, array<string, mixed>>  $archivos
     */
    protected function validarUnicoDocumentoAnalizableIa($validator, array $archivos): void
    {
        $iaCount = 0;

        foreach ($archivos as $archivo) {
            if (filter_var($archivo['analizable_ia'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $iaCount++;
            }
        }

        if ($iaCount > 1) {
            $validator->errors()->add(
                'archivos_requeridos',
                'Solo un documento de la entrega puede analizarse con IA.'
            );
        }
    }
}
