<?php

declare(strict_types=1);

namespace App\Actions\Entrega;

use App\Enums\EstadoProyecto;
use App\Models\Entrega;
use App\Models\Proyecto;

/**
 * Single-purpose use case: create an entrega for a semester and link it to
 * every active project of that semester.
 */
final class StoreEntregaAction
{
    /**
     * @param  array<string, mixed>  $data  validated store payload
     */
    public function handle(array $data): Entrega
    {
        $archivos = collect($data['archivos_requeridos'])->map(function (array $item) {
            return [
                'slug' => $item['id'],
                'nombre' => $item['nombre'],
                'versionamiento' => (bool) $item['versionamiento'],
                'analizable_ia' => (bool) ($item['analizable_ia'] ?? false),
            ];
        })->toArray();

        unset($data['archivos_requeridos']);

        $entrega = Entrega::create([
            'semester_id' => $data['grupo_id'],
            'phase' => $data['fase'],
            'title' => $data['titulo'],
            'description' => $data['descripcion'],
            'due_date' => $data['fecha_limite'],
            'start_date' => $data['fecha_inicio'] ?? null,
            'start_time' => $data['hora_inicio'] ?? null,
            'hora_maxima' => $data['hora_maxima'] ?? null,
            'acceptance_criteria' => $data['criterios'] ?? null,
            'status' => 'pendiente',
            'grade_percentage' => $data['grade_percentage'] ?? null,
            'archivos_requeridos' => $archivos,
        ]);

        // Link to all active projects of the semester
        $proyectos = Proyecto::where('semester_id', $data['grupo_id'])
            ->whereIn('status', [EstadoProyecto::EnCurso->value, EstadoProyecto::EnRiesgo->value, EstadoProyecto::Completado->value])
            ->pluck('id');

        $entrega->proyectos()->attach($proyectos);
        $entrega->load('semestre:id,name');

        return $entrega;
    }
}
