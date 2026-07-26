<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDirectorAcademicProfileRequest;
use App\Models\User;
use App\Services\Directors\DirectorAcademicProfileWriter;
use Illuminate\Http\JsonResponse;

/**
 * Coordinador CRUD for Director academic profiles (no AI logic).
 */
class DirectorAcademicProfileController extends Controller
{
    public function __construct(
        private readonly DirectorAcademicProfileWriter $writer,
    ) {}

    /**
     * GET /api/admin/directores/{director}/perfil-academico
     */
    public function show(User $director): JsonResponse
    {
        if ($director->role !== UserRole::Director) {
            return response()->json([
                'error' => 'El usuario indicado no es un Director.',
                'code' => 'not_director',
            ], 422);
        }

        return response()->json([
            'data' => $this->writer->toArray($director),
        ]);
    }

    /**
     * PUT /api/admin/directores/{director}/perfil-academico
     */
    public function update(UpdateDirectorAcademicProfileRequest $request, User $director): JsonResponse
    {
        if ($director->role !== UserRole::Director) {
            return response()->json([
                'error' => 'El usuario indicado no es un Director.',
                'code' => 'not_director',
            ], 422);
        }

        $payload = $request->validated();
        $normalized = $this->normalizePayload($payload);
        $this->writer->upsert($director, $normalized);

        return response()->json([
            'data' => $this->writer->toArray($director->fresh()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $out = [];

        if (array_key_exists('areas', $payload)) {
            $out['areas'] = $payload['areas'];
        }
        if (array_key_exists('academic_experience', $payload)) {
            $out['academic_experience'] = $payload['academic_experience'];
        }
        if (array_key_exists('years_of_experience', $payload)) {
            $out['years_of_experience'] = $payload['years_of_experience'];
        }

        $out['research_lines'] = $payload['research_lines_text']
            ?? $payload['research_lines']
            ?? null;
        $out['technologies'] = $payload['technologies_text']
            ?? $payload['technologies']
            ?? null;
        $out['methodologies'] = $payload['methodologies_text']
            ?? $payload['methodologies']
            ?? null;

        // Only include list keys that were actually sent.
        foreach (['research_lines', 'technologies', 'methodologies'] as $key) {
            $textKey = $key.'_text';
            if (! array_key_exists($key, $payload) && ! array_key_exists($textKey, $payload)) {
                unset($out[$key]);
            }
        }

        return $out;
    }
}
