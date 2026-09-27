<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AiEvaluationStatus;
use App\Enums\AiEvaluationType;
use App\Exceptions\DocumentEvaluationException;
use App\Http\Controllers\Controller;
use App\Models\AiDocumentEvaluation;
use App\Models\VersionDocumento;
use App\Services\Evaluation\Access\DirectorEntregaAccessResolver;
use App\Services\Evaluation\AiFeedbackPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only director view of the AI analysis requested by the student.
 *
 * The student-requested analysis (type pre_submission) is shown on the
 * delivery from the director side. The director never re-runs the analysis
 * here: there is no POST endpoint on purpose.
 *
 * Version rule: with ?version_id, the latest completed student analysis
 * attached to that official version is returned; a temporary (temporal)
 * analysis whose document hash matches the official version file is also
 * a match, reusing the existing hash cache. Without ?version_id, the
 * latest completed student analysis of the entrega is returned.
 * When the student has not requested any analysis yet, data is null.
 */
class EvaluacionAbetController extends Controller
{
    public function __construct(
        private readonly DirectorEntregaAccessResolver $access,
    ) {}

    /**
     * GET /api/director/entregas/{entrega}/evaluacion-abet
     */
    public function show(Request $request, int $entrega): JsonResponse
    {
        try {
            $this->access->resolve($request->user(), $entrega);
        } catch (DocumentEvaluationException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], $exception->httpStatus);
        }

        $versionId = $request->query('version_id');
        $versionId = $versionId !== null && $versionId !== '' ? (int) $versionId : null;
        $versionHash = null;

        if ($versionId !== null) {
            $version = VersionDocumento::query()
                ->where('entrega_id', $entrega)
                ->where('id', $versionId)
                ->first();

            if (! $version) {
                return response()->json([
                    'error' => 'No se encontró la versión del documento.',
                    'code' => 'not_found',
                ], 404);
            }

            $versionHash = $this->versionFileHash($version);
        }

        $historial = AiDocumentEvaluation::query()
            ->where('entrega_id', $entrega)
            ->where('type', AiEvaluationType::PreSubmission)
            ->where('status', AiEvaluationStatus::Completed)
            ->when($versionId !== null, function ($query) use ($versionId, $versionHash) {
                $query->where(function ($scoped) use ($versionId, $versionHash) {
                    $scoped->where('version_documento_id', $versionId);

                    if ($versionHash !== null) {
                        $scoped->orWhere(function ($temporal) use ($versionHash) {
                            $temporal->whereNull('version_documento_id')
                                ->where('document_hash', $versionHash);
                        });
                    }
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $latest = $historial->first();

        if (! $latest) {
            return response()->json([
                'data' => null,
                'historial' => [],
            ]);
        }

        return response()->json([
            'data' => AiFeedbackPresenter::toArray($latest),
            'historial' => $historial->map(fn (AiDocumentEvaluation $row) => AiFeedbackPresenter::toArray($row))->values(),
        ]);
    }

    private function versionFileHash(VersionDocumento $version): ?string
    {
        try {
            $absolute = Storage::disk('public')->path($version->file_path);
        } catch (\Throwable) {
            return null;
        }

        if (! is_file($absolute)) {
            return null;
        }

        $hash = hash_file('sha256', $absolute);

        return $hash === false || $hash === '' ? null : $hash;
    }
}
