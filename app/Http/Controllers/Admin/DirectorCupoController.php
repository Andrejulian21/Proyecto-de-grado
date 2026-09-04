<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoProyecto;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuthorizedEmail;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Director quota/capacity management.
 *
 * Exposes the two endpoints consumed by the coordinator dashboard for
 * viewing and updating the maximum number of active projects each
 * director can supervise simultaneously.
 */
class DirectorCupoController extends Controller
{
    /**
     * GET /api/admin/directores/cupos
     *
     * Returns all directors with their current capacity info:
     * id, name, areas (expertise), active_projects count, max_capacity.
     *
     * Includes directors that exist ONLY in the whitelist (authorized_emails)
     * and have not yet logged in — they won't have a users row yet.
     */
    public function index(): JsonResponse
    {
        // 1. Directors that have a User record (have logged in or were created)
        $directores = User::where('role', UserRole::Director)
            ->withCount(['proyectosDirigidos as active_projects' => function ($q) {
                $q->whereIn('status', [
                    EstadoProyecto::EnCurso,
                    EstadoProyecto::EnRiesgo,
                ]);
            }])
            // 'email' MUST be selected: keyBy('email') below would otherwise
            // resolve to null for every row and collapse the collection into
            // a single entry (issue #51 — Defect 5).
            ->get(['id', 'name', 'areas', 'max_capacity', 'email'])
            ->keyBy('email');

        // 2. Whitelist entries with role Director whose email is NOT in users
        $usedEmails = $directores->keys()->all();
        $whitelistDirectores = AuthorizedEmail::where('role', UserRole::Director)
            ->whereNotIn('email', $usedEmails)
            ->get();

        // Merge: user-based directors first, then whitelist-only as extra rows
        $result = $directores->values()->map(function (User $director): array {
            return [
                'id' => $director->id,
                'name' => $director->name,
                'areas' => $director->areas
                    ? array_values(array_filter(
                        explode("\n", str_replace("\r\n", "\n", $director->areas)),
                        fn (string $a): bool => trim($a) !== '',
                    ))
                    : [],
                'active_projects' => (int) $director->active_projects,
                'max_capacity' => (int) $director->max_capacity,
            ];
        })->all();

        foreach ($whitelistDirectores as $w) {
            $result[] = [
                'id' => null,
                'name' => $w->name ?? $w->email,
                'areas' => null,
                'active_projects' => 0,
                'max_capacity' => 3,
            ];
        }

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/admin/directores
     *
     * Returns all directors with basic info: id, name, areas.
     * Used by the /directores page to populate the director list.
     */
    public function directores(): JsonResponse
    {
        $directores = User::where('role', UserRole::Director)
            ->get(['id', 'name', 'email', 'areas']);

        $result = $directores->map(fn (User $d) => [
            'id' => $d->id,
            'name' => $d->name,
            'email' => $d->email,
            'areas' => $d->areas
                ? array_values(array_filter(
                    explode("\n", str_replace("\r\n", "\n", $d->areas)),
                    fn (string $a) => trim($a) !== '',
                ))
                : [],
        ]);

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/admin/directores/{director}/proyectos
     *
     * Returns projects for a given director, with their students.
     */
    public function directorProyectos(Request $request, int $id): JsonResponse
    {
        $query = Proyecto::where('director_id', $id)
            ->with('estudiantes:id,name', 'semestre:id,name,is_active');

        if (! $request->boolean('todas')) {
            $query->enSemestresActivos();
        }

        $proyectos = $query->get();

        $result = $proyectos->map(fn (Proyecto $p) => [
            'id' => $p->id,
            'code' => $p->code,
            'title' => $p->title,
            'estudiantes' => $p->estudiantes->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
            ]),
            'current_phase' => $p->current_phase?->value,
            'status' => $p->status?->value,
            'semestre' => $p->semestre ? [
                'id' => $p->semestre->id,
                'name' => $p->semestre->name,
                'is_active' => $p->semestre->is_active,
            ] : null,
        ]);

        return response()->json(['data' => $result]);
    }

    /**
     * PUT /api/admin/directores/{director}/cupo
     *
     * Updates a director's max_capacity.
     * Validates that the new cap is not lower than the current active project count.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'max_capacity' => 'required|integer|min:1',
            'areas' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $director = User::where('role', UserRole::Director)->findOrFail($id);

        $activeProjects = Proyecto::where('director_id', $id)
            ->whereIn('status', [
                EstadoProyecto::EnCurso,
                EstadoProyecto::EnRiesgo,
            ])
            ->count();

        $newCapacity = (int) $request->max_capacity;

        if ($newCapacity < $activeProjects) {
            return response()->json([
                'errors' => ['max_capacity' => [
                    "El cupo máximo no puede ser menor a los proyectos activos ({$activeProjects}).",
                ]],
            ], 422);
        }

        $updateData = ['max_capacity' => $newCapacity];

        if ($request->has('areas')) {
            $updateData['areas'] = $request->areas;
        }
        $director->update($updateData);
        $director->refresh();

        return response()->json([
            'data' => [
                'id' => $director->id,
                'name' => $director->name,
                'areas' => $director->areas
                    ? array_values(array_filter(
                        explode("\n", str_replace("\r\n", "\n", $director->areas)),
                        fn (string $a): bool => trim($a) !== '',
                    ))
                    : [],
                'active_projects' => $activeProjects,
                'max_capacity' => $director->max_capacity,
            ],
        ]);
    }
}
