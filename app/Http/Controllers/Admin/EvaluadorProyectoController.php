<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoInvitacionEvaluador;
use App\Http\Controllers\Controller;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvaluadorProyectoController extends Controller
{
    /**
     * GET /api/admin/evaluador-proyecto
     *
     * Devuelve asignaciones agrupadas por proyecto con todos los evaluadores
     * y los campos fecha / hora_inicio / hora_fin / fase.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EvaluadorProyecto::with([
            'proyecto:id,code,title,status,current_phase,director_id',
            'proyecto.estudiantes:id,name',
            'proyecto.director:id,name,email',
            'evaluador:id,name,email,role',
        ]);

        if ($request->has('proyecto_id')) {
            $validator = Validator::make($request->all(), [
                'proyecto_id' => 'required|exists:proyectos,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $query->where('proyecto_id', $request->integer('proyecto_id'));
        }

        $asignaciones = $query->get();

        // Group by proyecto_id, preserving the first record's date/time/fase
        $grouped = $asignaciones->groupBy('proyecto_id');

        $mapped = $grouped->map(function (Collection $items) {
            /** @var EvaluadorProyecto $first */
            $first = $items->first();
            $proyecto = $first->proyecto;

            $evaluadores = $items->values();

            return [
                // id es el proyecto_id (compatibilidad con destroy/delete por proyecto).
                'id' => $first->proyecto_id,
                // assignment_id es el ID real de la fila evaluador_proyecto (para el PUT de edición).
                'assignment_id' => $first->id,
                'proyecto_id' => $first->proyecto_id,
                'proyecto_codigo' => $proyecto?->code ?? '',
                'proyecto_nombre' => $proyecto?->title ?? '',
                'proyecto_director_id' => $proyecto?->director_id,
                'proyecto_director_nombre' => $proyecto?->director?->name ?? '',
                'estudiantes' => $proyecto?->estudiantes->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                ])->toArray() ?? [],
                'fase' => $first->fase ?? 'Anteproyecto',
                'fecha' => $first->fecha?->format('Y-m-d') ?? '',
                'hora_inicio' => $first->hora_inicio ?? '',
                'hora_fin' => $first->hora_fin ?? '',
                'evaluadores_list' => $evaluadores->map(fn (EvaluadorProyecto $ep) => [
                    'id' => $ep->evaluador_id,
                    'name' => $ep->evaluador?->name ?? '',
                    'email' => $ep->evaluador?->email ?? '',
                    'role' => $ep->evaluador?->role?->value ?? '',
                    'assignment_id' => $ep->id,
                ])->toArray(),
                // Keep backward-compat fields
                'evaluador_principal_id' => $evaluadores->get(0)?->evaluador_id ?? 0,
                'evaluador_principal_nombre' => $evaluadores->get(0)?->evaluador?->name ?? '',
                'evaluador_secundario_id' => $evaluadores->get(1)?->evaluador_id ?? null,
                'evaluador_secundario_nombre' => $evaluadores->get(1)?->evaluador?->name ?? null,
                'evaluador_tercero_id' => $evaluadores->get(2)?->evaluador_id ?? null,
                'evaluador_tercero_nombre' => $evaluadores->get(2)?->evaluador?->name ?? null,
                'hora' => $first->hora_inicio ?? '',
            ];
        })->values()->toArray();

        return response()->json(['data' => $mapped]);
    }

    /**
     * POST /api/admin/evaluador-proyecto
     *
     * Crea 2-3 registros de evaluador_proyecto con la misma fecha/hora/fase.
     *
     * Body esperado:
     * {
     *   "proyecto_id": 1,
     *   "evaluador_ids": [2, 3],
     *   "fecha": "2026-08-15",
     *   "hora_inicio": "14:00",
     *   "hora_fin": "16:00",
     *   "fase": "Anteproyecto"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'proyecto_id' => 'required|exists:proyectos,id',
            'evaluador_ids' => 'required|array|min:2|max:3',
            'evaluador_ids.*' => 'required|exists:users,id|distinct',
            'fecha' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'fase' => 'required|string|in:Anteproyecto,Final',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Validate: no asignar un Director como evaluador de su propio proyecto
        $proyecto = Proyecto::findOrFail($data['proyecto_id']);

        foreach ($data['evaluador_ids'] as $evaluadorId) {
            if ((int) $evaluadorId === (int) $proyecto->director_id) {
                return response()->json([
                    'error' => 'Un director no puede evaluar su propio proyecto.',
                ], 422);
            }
        }

        // Validate: no time conflict — overlap: nuevoInicio < existenteFin && nuevoFin > existenteInicio
        $timeConflict = EvaluadorProyecto::where('fecha', $data['fecha'])
            ->where('hora_inicio', '<', $data['hora_fin'])
            ->where('hora_fin', '>', $data['hora_inicio'])
            ->exists();

        if ($timeConflict) {
            return response()->json([
                'error' => 'Ya existe una asignación programada que se superpone con este horario. Por favor, seleccione un horario diferente.',
            ], 422);
        }

        $created = [];

        foreach ($data['evaluador_ids'] as $evaluadorId) {
            $asignacion = EvaluadorProyecto::create([
                'proyecto_id' => $data['proyecto_id'],
                'evaluador_id' => $evaluadorId,
                'invitation_status' => EstadoInvitacionEvaluador::Pendiente,
                'assigned_at' => now(),
                'fecha' => $data['fecha'],
                'hora_inicio' => $data['hora_inicio'],
                'hora_fin' => $data['hora_fin'],
                'fase' => $data['fase'],
            ]);

            $asignacion->load('evaluador:id,name,email,role');
            $created[] = $asignacion;
        }

        // Return the first created record (consistent with grouped frontend)
        $first = $created[0];
        $first->load('proyecto.estudiantes:id,name', 'proyecto.director:id,name');

        return response()->json(['data' => [
            'id' => $first->id,
            'proyecto_id' => $first->proyecto_id,
            'proyecto_codigo' => $first->proyecto?->code ?? '',
            'proyecto_nombre' => $first->proyecto?->title ?? '',
            'proyecto_director_id' => $first->proyecto?->director_id,
            'proyecto_director_nombre' => $first->proyecto?->director?->name ?? '',
            'estudiantes' => $first->proyecto?->estudiantes->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
            ])->toArray() ?? [],
            'fase' => $first->fase ?? 'Anteproyecto',
            'fecha' => $first->fecha?->format('Y-m-d') ?? '',
            'hora_inicio' => $first->hora_inicio ?? '',
            'hora_fin' => $first->hora_fin ?? '',
            'evaluadores_list' => array_map(fn ($ep, $i) => [
                'id' => $ep->evaluador_id,
                'name' => $ep->evaluador?->name ?? '',
                'email' => $ep->evaluador?->email ?? '',
                'role' => $ep->evaluador?->role?->value ?? '',
                'assignment_id' => $ep->id,
            ], $created, array_keys($created)),
            'evaluador_principal_id' => $created[0]->evaluador_id,
            'evaluador_principal_nombre' => $created[0]->evaluador?->name ?? '',
            'evaluador_secundario_id' => $created[1]->evaluador_id,
            'evaluador_secundario_nombre' => $created[1]->evaluador?->name ?? '',
            'evaluador_tercero_id' => $created[2]->evaluador_id ?? null,
            'evaluador_tercero_nombre' => $created[2]->evaluador?->name ?? null,
            'hora' => $created[0]->hora_inicio ?? '',
        ]], 201);
    }

    /**
     * PUT /api/admin/evaluador-proyecto/{id}
     *
     * Actualiza fecha/hora_inicio/hora_fin/fase para todos los registros
     * que comparten el mismo proyecto_id que el registro con la ID dada.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $asignacion = EvaluadorProyecto::find($id);

        if (! $asignacion) {
            return response()->json(['error' => 'Asignación no encontrada.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'fecha' => 'sometimes|required|date',
            'hora_inicio' => 'sometimes|required|date_format:H:i',
            'hora_fin' => 'sometimes|required|date_format:H:i',
            'fase' => 'sometimes|required|string|in:Anteproyecto,Final',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Validate hora_fin > hora_inicio if both present
        $horaInicio = $data['hora_inicio'] ?? $asignacion->hora_inicio;
        $horaFin = $data['hora_fin'] ?? $asignacion->hora_fin;

        if ($horaInicio && $horaFin && $horaFin <= $horaInicio) {
            return response()->json([
                'errors' => ['hora_fin' => ['La hora fin debe ser posterior a la hora inicio.']],
            ], 422);
        }

        // Update all rows for the same proyecto_id
        EvaluadorProyecto::where('proyecto_id', $asignacion->proyecto_id)
            ->update($data);

        // Reload and return the updated group
        $updated = EvaluadorProyecto::with([
            'proyecto:id,code,title,status,current_phase,director_id',
            'proyecto.estudiantes:id,name',
            'proyecto.director:id,name',
            'evaluador:id,name,email,role',
        ])->where('proyecto_id', $asignacion->proyecto_id)->get();

        $first = $updated->first();
        $evaluadores = $updated->values();

        return response()->json(['data' => [
            'id' => $first->id,
            'assignment_id' => $first->id,
            'proyecto_id' => $first->proyecto_id,
            'proyecto_codigo' => $first->proyecto?->code ?? '',
            'proyecto_nombre' => $first->proyecto?->title ?? '',
            'proyecto_director_id' => $first->proyecto?->director_id,
            'proyecto_director_nombre' => $first->proyecto?->director?->name ?? '',
            'estudiantes' => $first->proyecto?->estudiantes->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
            ])->toArray() ?? [],
            'fase' => $first->fase ?? 'Anteproyecto',
            'fecha' => $first->fecha?->format('Y-m-d') ?? '',
            'hora_inicio' => $first->hora_inicio ?? '',
            'hora_fin' => $first->hora_fin ?? '',
            'evaluadores_list' => $evaluadores->map(fn (EvaluadorProyecto $ep) => [
                'id' => $ep->evaluador_id,
                'name' => $ep->evaluador?->name ?? '',
                'email' => $ep->evaluador?->email ?? '',
                'role' => $ep->evaluador?->role?->value ?? '',
                'assignment_id' => $ep->id,
            ])->toArray(),
            'evaluador_principal_id' => $evaluadores->get(0)?->evaluador_id ?? 0,
            'evaluador_principal_nombre' => $evaluadores->get(0)?->evaluador?->name ?? '',
            'evaluador_secundario_id' => $evaluadores->get(1)?->evaluador_id ?? null,
            'evaluador_secundario_nombre' => $evaluadores->get(1)?->evaluador?->name ?? null,
            'evaluador_tercero_id' => $evaluadores->get(2)?->evaluador_id ?? null,
            'evaluador_tercero_nombre' => $evaluadores->get(2)?->evaluador?->name ?? null,
            'hora' => $first->hora_inicio ?? '',
        ]]);
    }

    /**
     * DELETE /api/admin/evaluador-proyecto/{id}
     *
     * Elimina TODOS los registros de evaluador_proyecto para el proyecto dado (proyecto_id).
     * El frontend envía el proyecto_id como {id}, no el ID individual de la tabla pivote.
     */
    public function destroy(int $id): JsonResponse
    {
        // Delete ALL evaluador records for this project
        $deleted = EvaluadorProyecto::where('proyecto_id', $id)->delete();

        if ($deleted === 0) {
            return response()->json(['error' => 'Asignación no encontrada.'], 404);
        }

        return response()->json(['message' => "{$deleted} evaluador(es) desasignado(s) correctamente."]);
    }
}
