<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrega;
use App\Models\EvaluacionEvaluador;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * External-evaluator endpoints for the evaluation flow (PR 3).
 *
 * Every route resolves the EvaluadorProyecto assignment by id and enforces
 * that it belongs to the authenticated evaluador (403 otherwise). The
 * evaluation itself is immutable: POST /evaluar creates the record and flips
 * evaluado=true; there is no PUT/DELETE surface (RF-EVA-03). The DB UNIQUE
 * constraint on evaluaciones_evaluador.evaluador_proyecto_id is the
 * backstop for duplicate submissions.
 */
class EvaluadorAsignacionesController extends Controller
{
    /**
     * GET /api/evaluador/mis-asignaciones — RF-EVA-01.
     *
     * Cards of the authenticated evaluador's assignments in the active flow.
     * Eager loads proyecto.estudiantes and proyecto.director to avoid N+1.
     */
    public function index(Request $request): JsonResponse
    {
        $asignaciones = EvaluadorProyecto::query()
            ->with(['proyecto.estudiantes:id,name', 'proyecto.director:id,name,email'])
            ->where('evaluador_id', $request->user()->id)
            ->get();

        return response()->json([
            'data' => $asignaciones->map(
                fn (EvaluadorProyecto $asignacion) => $this->mapCard($asignacion)
            ),
        ]);
    }

    /**
     * GET /api/evaluador/asignaciones/{id}/detalle — RF-EVA-02.
     *
     * Full context: proyecto, fase, entrega (archivos_requeridos, due_date,
     * director_grade, versiones_documento) and the evaluation (null when
     * pending). The entrega is resolved by semester + delivery phase because
     * EvaluadorProyecto has no entrega_id (design TBD-4).
     */
    public function detalle(Request $request, int $id): JsonResponse
    {
        $asignacion = EvaluadorProyecto::find($id);

        if ($asignacion === null) {
            return $this->error(404, 'Asignación no encontrada');
        }

        if ($asignacion->evaluador_id !== $request->user()->id) {
            return $this->error(403, 'No tiene acceso a esta asignación');
        }

        $asignacion->load('proyecto.estudiantes:id,name', 'proyecto.director:id,name,email');

        $fase = $this->faseEntrega((string) $asignacion->fase);

        $entrega = Entrega::query()
            ->with(['versiones' => fn ($query) => $query->ultima()])
            ->where('semester_id', $asignacion->proyecto?->semester_id)
            ->where('phase', $fase)
            ->first();

        $evaluacion = EvaluacionEvaluador::where('evaluador_proyecto_id', $asignacion->id)->first();

        return response()->json([
            'proyecto' => $this->mapProyecto($asignacion->proyecto),
            'fase' => $fase,
            'entrega' => $entrega !== null ? $this->mapEntrega($entrega) : null,
            'evaluacion' => $evaluacion !== null ? [
                'nota' => (float) $evaluacion->nota,
                'observaciones' => $evaluacion->observaciones,
                'evaluated_at' => $evaluacion->evaluated_at?->toDateTimeString(),
            ] : null,
        ]);
    }

    /**
     * POST /api/evaluador/asignaciones/{id}/evaluar — RF-EVA-03.
     *
     * Validates ownership, pending state and nota range (0-5, 2 decimals),
     * then creates the EvaluacionEvaluador row with evaluated_at = now() and
     * flips evaluador_proyecto.evaluado to true inside one transaction.
     */
    public function evaluar(Request $request, int $id): JsonResponse
    {
        $asignacion = EvaluadorProyecto::find($id);

        if ($asignacion === null) {
            return $this->error(404, 'Asignación no encontrada');
        }

        if ($asignacion->evaluador_id !== $request->user()->id) {
            return $this->error(403, 'No tiene acceso a esta asignación');
        }

        // RF-EVA-03 / D6: immutable once evaluated. The flag keeps the 409
        // predictable; the UNIQUE constraint is the defense in depth.
        if ($asignacion->evaluado || EvaluacionEvaluador::where('evaluador_proyecto_id', $asignacion->id)->exists()) {
            return $this->error(409, 'La evaluación ya fue enviada y no puede modificarse');
        }

        $validator = Validator::make($request->all(), [
            'nota' => ['required', 'numeric'],
            'observaciones' => ['required', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return $this->error(422, $validator->errors()->first());
        }

        $nota = (float) $request->input('nota');

        if ($nota < 0 || $nota > 5) {
            return $this->error(422, 'La nota debe estar entre 0 y 5');
        }

        if (round($nota, 2) !== $nota) {
            return $this->error(422, 'La nota debe tener máximo 2 decimales');
        }

        $observaciones = trim((string) $request->input('observaciones'));

        if ($observaciones === '') {
            return $this->error(422, 'El campo observaciones es obligatorio.');
        }

        DB::transaction(function () use ($asignacion, $nota, $observaciones): void {
            EvaluacionEvaluador::create([
                'evaluador_proyecto_id' => $asignacion->id,
                'nota' => $nota,
                'observaciones' => $observaciones,
                'evaluated_at' => now(),
            ]);

            $asignacion->update(['evaluado' => true]);
        });

        return response()->json([
            'data' => [
                'id' => $asignacion->id,
                'evaluado' => true,
            ],
        ], 201);
    }

    /**
     * Map an assignment to the evaluator card shape (RF-EVA-01).
     *
     * @return array<string, mixed>
     */
    private function mapCard(EvaluadorProyecto $asignacion): array
    {
        return [
            'id' => $asignacion->id,
            'proyecto' => $this->mapProyecto($asignacion->proyecto),
            'fase' => $this->faseEntrega((string) $asignacion->fase),
            'evaluado' => (bool) $asignacion->evaluado,
            'created_at' => $asignacion->created_at?->toDateString(),
        ];
    }

    /**
     * Map a project to the public shape: codigo, titulo, estudiantes, director.
     *
     * @return array<string, mixed>|null
     */
    private function mapProyecto(?Proyecto $proyecto): ?array
    {
        if ($proyecto === null) {
            return null;
        }

        return [
            'id' => $proyecto->id,
            'codigo' => $proyecto->code,
            'titulo' => $proyecto->title,
            'estudiantes' => $proyecto->estudiantes->map(
                fn (User $estudiante) => ['id' => $estudiante->id, 'name' => $estudiante->name]
            )->values()->toArray(),
            'director' => $proyecto->director !== null ? [
                'id' => $proyecto->director->id,
                'name' => $proyecto->director->name,
                'email' => $proyecto->director->email,
            ] : null,
        ];
    }

    /**
     * Map a delivery to the detail shape (RF-EVA-02, RF-NOT-04).
     *
     * @return array<string, mixed>
     */
    private function mapEntrega(Entrega $entrega): array
    {
        return [
            'id' => $entrega->id,
            'archivos_requeridos' => $entrega->archivos_requeridos ?? [],
            'due_date' => $entrega->due_date?->toDateString(),
            'director_grade' => $entrega->director_grade !== null ? (float) $entrega->director_grade : null,
            'versiones_documento' => $entrega->versiones->map(
                fn (VersionDocumento $version) => [
                    'version_number' => $version->version_number,
                    'file_path' => $version->file_path,
                    'original_name' => $version->original_name,
                    'director_notes' => $version->director_notes,
                    'uploaded_at' => $version->uploaded_at,
                ]
            )->values()->toArray(),
        ];
    }

    /**
     * Map the assignment fase ('Anteproyecto' / 'Final') to the delivery
     * phase used by the entregas table (design TBD-4).
     */
    private function faseEntrega(string $faseAsignacion): string
    {
        return match ($faseAsignacion) {
            'Anteproyecto' => 'anteproyecto',
            'Final' => 'presentacion_final',
            default => strtolower($faseAsignacion),
        };
    }

    /**
     * Spec-compliant error envelope: {"error": {"message": "..."}}.
     */
    private function error(int $status, string $message): JsonResponse
    {
        return response()->json(['error' => ['message' => $message]], $status);
    }
}
