<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiDocumentEvaluation;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Services\Entregas\NotaEntregaResolver;
use App\Services\Entregas\VersionIsolationScope;
use App\Services\Evaluation\AiFeedbackPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class EstudianteController extends Controller
{
    public function __construct(
        private readonly VersionIsolationScope $versionIsolation,
        private readonly NotaEntregaResolver $notas,
    ) {}

    /**
     * GET /api/estudiante/proyecto
     *
     * Returns the active project for the authenticated student,
     * including director, co-students, semester, and deliveries
     * with their versions.
     */
    public function proyecto(Request $request): JsonResponse
    {
        $user = $request->user();

        $proyecto = Proyecto::whereHas('estudiantes', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->with([
                'director:id,name,email',
                'estudiantes:id,name,email',
                'semestre:id,name,is_active',
            ])
            ->first();

        if (! $proyecto) {
            return response()->json([
                'error' => 'No tienes un proyecto asignado.',
            ], 404);
        }

        // Load ALL entregas: direct FK + pivot-linked, with versiones
        $entregas = Entrega::paraProyecto($proyecto->id)
            ->with('versiones')
            ->orderBy('due_date')
            ->get();

        $proyecto->setRelation('entregas', $entregas);

        return response()->json(['data' => $proyecto]);
    }

    /**
     * PUT /api/estudiante/proyecto
     *
     * Update the title of the student's own project.
     */
    public function actualizarProyecto(Request $request): JsonResponse
    {
        $user = $request->user();

        $proyecto = Proyecto::whereHas('estudiantes', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->first();

        if (! $proyecto) {
            return response()->json(['error' => 'No tienes un proyecto asignado.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $proyecto->title = $request->input('title');
        $proyecto->save();

        return response()->json(['data' => [
            'id' => $proyecto->id,
            'title' => $proyecto->title,
        ]]);
    }

    /**
     * GET /api/estudiante/entregas
     *
     * Returns all deliveries for the student's project
     * with version count and last version info.
     */
    public function entregas(Request $request): JsonResponse
    {
        $user = $request->user();

        $proyecto = Proyecto::whereHas('estudiantes', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->first();

        if (! $proyecto) {
            return response()->json([
                'error' => 'No tienes un proyecto asignado.',
            ], 404);
        }

        // The entrega is a shared template; only the versions uploaded by
        // THIS project (through its entrega_proyecto pivots) belong to the
        // student. `ruta_archivo` is dropped below — a public-disk path is
        // downloadable without a session (issue #47).
        $entregas = Entrega::paraProyecto($proyecto->id)
            ->with(['versiones' => fn ($q) => $this->versionIsolation->apply($q, $user)
                ->orderBy('version_number')
                ->with('analisisIa')])
            ->orderBy('due_date')
            ->get();

        // The grade and the verdict of a delivery belong to THIS project, so
        // they are read from its own entrega_proyecto pivot. The shared
        // semester template is only the legacy fallback (see
        // NotaEntregaResolver) — without it the student stops seeing a grade
        // that was recorded before the pivot existed.
        $pivotes = EntregaProyecto::query()
            ->where('proyecto_id', $proyecto->id)
            ->whereIn('entrega_id', $entregas->pluck('id'))
            ->get()
            ->keyBy('entrega_id');

        $entregas = $entregas
            ->map(function (Entrega $entrega) use ($pivotes) {
                $pivot = $pivotes->get($entrega->id);
                $statusValue = $this->notas->estado($entrega, $pivot);

                $versiones = $entrega->versiones->map(function ($version) use ($statusValue) {
                    $hasNotes = filled($version->director_notes);
                    $estadoVersion = 'pendiente';

                    if ($hasNotes) {
                        $estadoVersion = $statusValue === 'aprobada' ? 'aprobado' : 'rechazado';
                    }

                    $uploadedAt = $version->uploaded_at ?? $version->created_at;

                    return [
                        'id' => $version->id,
                        'numero_version' => $version->version_number,
                        'nombre_archivo' => $version->original_name,
                        'archivo_requerido_id' => $version->archivo_requerido_id,
                        'subido_en' => $uploadedAt
                            ? Carbon::parse($uploadedAt)->toIso8601String()
                            : null,
                        'observacion' => $version->director_notes,
                        'estado' => $estadoVersion,
                        'analisis_ia' => $version->analisisIa
                            ->map(fn (AiDocumentEvaluation $evaluation) => AiFeedbackPresenter::toArray($evaluation))
                            ->values()
                            ->all(),
                    ];
                })->values();

                return [
                    'id' => $entrega->id,
                    'fase' => $entrega->phase?->value ?? $entrega->phase,
                    'titulo' => $entrega->title,
                    'descripcion' => $entrega->description,
                    'fecha_limite' => $entrega->due_date?->toDateString(),
                    'estado' => $statusValue,
                    'nota' => $this->notas->nota($entrega, $pivot),
                    'observaciones' => $this->notas->observaciones($pivot),
                    'evaluacion_completa' => $this->notas->evaluacionCompleta($entrega, $pivot),
                    'criterios' => $entrega->acceptance_criteria,
                    'archivos_requeridos' => $entrega->archivos_requeridos,
                    'documento_analizable_ia' => $entrega->idDocumentoAnalizableIa(),
                    'total_versiones' => $versiones->count(),
                    'ultima_version' => $versiones->last()['numero_version'] ?? null,
                    'versiones' => $versiones,
                ];
            });

        return response()->json(['data' => $entregas]);
    }
}
