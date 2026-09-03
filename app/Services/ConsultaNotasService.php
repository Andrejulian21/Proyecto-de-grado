<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FaseProyecto;
use App\Enums\UserRole;
use App\Models\CoordinadorGradeWeight;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ConsultaNotasService
{
    private const DEFAULT_WEIGHTS = [
        'entregas' => 40,
        'evaluadores' => 30,
        'presentacion' => 30,
    ];

    /**
     * @param  array{semestre_id?: mixed, proyecto_id?: mixed, entrega_id?: mixed, estado_nota?: mixed, q?: mixed, tipo?: mixed}  $filters
     * @return array{semestres: list<array<string, mixed>>, proyectos: list<array<string, mixed>>}
     */
    public function listar(User $user, array $filters): array
    {
        $tipo = isset($filters['tipo']) ? strtolower(trim((string) $filters['tipo'])) : null;

        if ($tipo !== null && ! in_array($tipo, ['pg1', 'pg2'], true)) {
            $tipo = null;
        }

        // Coordinator view — always use coordinator-specific logic, default to pg1
        if ($user->role === UserRole::Coordinador) {
            return $this->listarCoordinador($user, $filters, $tipo ?? 'pg1');
        }

        // Existing view for student/director/evaluator (unchanged)
        return $this->listarEstudianteDirectorEvaluador($user, $filters);
    }

    // -------------------------------------------------------------------------
    // Coordinator view — PG1/PG2 weighted grades
    // -------------------------------------------------------------------------

    private function listarCoordinador(User $user, array $filters, string $tipo): array
    {
        $proyectoId = isset($filters['proyecto_id']) && $filters['proyecto_id'] !== ''
            ? (int) $filters['proyecto_id']
            : null;

        if ($proyectoId !== null) {
            $this->assertPuedeVerProyecto($user, $proyectoId);
        }

        $proyectosQuery = Proyecto::query()
            ->with(['director:id,name', 'estudiantes:id,name', 'semestre:id,name,is_active'])
            ->orderBy('code');

        if (isset($filters['semestre_id']) && $filters['semestre_id'] !== '') {
            $proyectosQuery->where('semester_id', (int) $filters['semestre_id']);
        } else {
            $proyectosQuery->whereHas('semestre', fn (Builder $q) => $q->where('is_active', true));
        }

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';

        if ($q !== '') {
            $proyectosQuery->where(function (Builder $query) use ($q) {
                $query->where('code', 'like', '%'.$q.'%')
                    ->orWhere('title', 'like', '%'.$q.'%');
            });
        }

        if ($proyectoId !== null) {
            $proyectosQuery->whereKey($proyectoId);
        }

        $proyectos = $proyectosQuery->get();
        $proyectoIds = $proyectos->pluck('id');

        // Gather all pivot data, entregas, and evaluator grades in bulk
        $pivotesPorProyecto = EntregaProyecto::query()
            ->whereIn('proyecto_id', $proyectoIds)
            ->get()
            ->groupBy('proyecto_id');

        $entregaIds = $pivotesPorProyecto->flatten()->pluck('entrega_id')->unique()->filter()->values();

        $entregas = Entrega::query()
            ->whereIn('id', $entregaIds)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $evaluacionesPorProyecto = $this->evaluacionesEvaluadorPorProyecto($proyectoIds->all());

        $payload = [];

        foreach ($proyectos as $proyecto) {
            $pesos = $this->obtenerPesos((int) $proyecto->semester_id, $tipo);

            $idsDeEsteProyecto = collect($pivotesPorProyecto->get($proyecto->id, collect()))
                ->pluck('entrega_id')
                ->unique()
                ->values();

            $pivotes = collect($pivotesPorProyecto->get($proyecto->id, collect()))->keyBy('entrega_id');

            $row = [
                'id' => $proyecto->id,
                'codigo' => $proyecto->code,
                'titulo' => $proyecto->title,
                'director' => $proyecto->director?->name,
                'estudiantes' => $proyecto->estudiantes->pluck('name')->filter()->implode(', '),
                'semestre_id' => $proyecto->semester_id,
                'tipo' => $tipo,
                'pesos' => $pesos,
            ];

            if ($tipo === 'pg1') {
                $row += $this->calcularPG1($idsDeEsteProyecto, $entregas, $pivotes, $evaluacionesPorProyecto[$proyecto->id] ?? [], $pesos);
            } else {
                $row += $this->calcularPG2($idsDeEsteProyecto, $entregas, $pivotes, $evaluacionesPorProyecto[$proyecto->id] ?? [], $pesos);
            }

            $payload[] = $row;
        }

        // Fetch default/editable weights for the coordinator (per semester)
        $filterSemestreId = isset($filters['semestre_id']) && $filters['semestre_id'] !== ''
            ? (int) $filters['semestre_id']
            : ($proyectos->first() ? (int) $proyectos->first()->semester_id : 0);
        $pesosPg1 = $this->obtenerPesos($filterSemestreId, 'pg1');
        $pesosPg2 = $this->obtenerPesos($filterSemestreId, 'pg2');

        return [
            'semestres' => $this->semestresVisibles($user),
            'proyectos' => $payload,
            'pesos' => [
                ['semestre_id' => $filterSemestreId, 'tipo' => 'pg1', ...$pesosPg1],
                ['semestre_id' => $filterSemestreId, 'tipo' => 'pg2', ...$pesosPg2],
            ],
        ];
    }

    /**
     * PG1: anteproyecto phase deliveries + evaluator grades for presentacion_anteproyecto.
     *
     * @param  Collection<int, int>  $idsDeEsteProyecto
     * @param  Collection<int, Entrega>  $entregas
     * @param  Collection<int, EntregaProyecto>  $pivotes
     * @param  list<array{nota: float|null, fase: string}>  $evaluaciones
     * @param  array{entregas: float, evaluadores: float, presentacion: float}  $pesos
     * @return array<string, mixed>
     */
    private function calcularPG1(
        Collection $idsDeEsteProyecto,
        Collection $entregas,
        Collection $pivotes,
        array $evaluaciones,
        array $pesos,
    ): array {
        // 1. Nota Entregas — anteproyecto phase deliveries
        $entregasAnteproyecto = [];
        $notaEntregasPonderada = null;

        foreach ($idsDeEsteProyecto as $entregaId) {
            $entrega = $entregas->get($entregaId);

            if ($entrega === null || $entrega->phase !== FaseProyecto::Anteproyecto->value) {
                continue;
            }

            $pivot = $pivotes->get($entregaId);
            $nota = $this->notaDePivot($pivot);
            $peso = $entrega->grade_percentage !== null ? (float) $entrega->grade_percentage : null;

            $entregasAnteproyecto[] = [
                'titulo' => $entrega->title,
                'nota' => $nota,
                'peso' => $peso,
            ];
        }

        $notaEntregasPonderada = $this->calcularNotaPonderada($entregasAnteproyecto);

        // 2. Nota Evaluadores — evaluators assigned to presentacion_anteproyecto
        $notaEvaluadores = $this->promedioEvaluaciones($evaluaciones, FaseProyecto::PresentacionAnteproyecto->value);

        // 3. Nota Presentación Anteproyecto — director grade on presentacion_anteproyecto
        $notaPresentacion = $this->notaFase($idsDeEsteProyecto, $entregas, $pivotes, FaseProyecto::PresentacionAnteproyecto->value);

        // 4. Nota Final PG1
        $notaFinal = null;

        if ($notaEntregasPonderada !== null && $notaEvaluadores !== null && $notaPresentacion !== null) {
            $notaFinal = round(
                ($notaEntregasPonderada * $pesos['entregas'] / 100)
                + ($notaEvaluadores * $pesos['evaluadores'] / 100)
                + ($notaPresentacion * $pesos['presentacion'] / 100),
                2,
            );
        }

        return [
            'notas_entregas_anteproyecto' => $entregasAnteproyecto,
            'nota_entregas_ponderada' => $notaEntregasPonderada,
            'nota_evaluadores_anteproyecto' => $notaEvaluadores,
            'nota_presentacion_anteproyecto' => $notaPresentacion,
            'nota_final_pg1' => $notaFinal,
        ];
    }

    /**
     * PG2: desarrollo phase deliveries + evaluator grades for presentacion_final.
     *
     * @param  Collection<int, int>  $idsDeEsteProyecto
     * @param  Collection<int, Entrega>  $entregas
     * @param  Collection<int, EntregaProyecto>  $pivotes
     * @param  list<array{nota: float|null, fase: string}>  $evaluaciones
     * @param  array{entregas: float, evaluadores: float, presentacion: float}  $pesos
     * @return array<string, mixed>
     */
    private function calcularPG2(
        Collection $idsDeEsteProyecto,
        Collection $entregas,
        Collection $pivotes,
        array $evaluaciones,
        array $pesos,
    ): array {
        // 1. Nota Entregas — desarrollo phase deliveries
        $entregasDesarrollo = [];

        foreach ($idsDeEsteProyecto as $entregaId) {
            $entrega = $entregas->get($entregaId);

            if ($entrega === null || $entrega->phase !== FaseProyecto::Desarrollo->value) {
                continue;
            }

            $pivot = $pivotes->get($entregaId);
            $nota = $this->notaDePivot($pivot);
            $peso = $entrega->grade_percentage !== null ? (float) $entrega->grade_percentage : null;

            $entregasDesarrollo[] = [
                'titulo' => $entrega->title,
                'nota' => $nota,
                'peso' => $peso,
            ];
        }

        $notaEntregasPonderada = $this->calcularNotaPonderada($entregasDesarrollo);

        // 2. Nota Evaluadores — evaluators assigned to presentacion_final
        $notaEvaluadores = $this->promedioEvaluaciones($evaluaciones, FaseProyecto::PresentacionFinal->value);

        // 3. Nota Director Entrega Final — director grade on presentacion_final
        $notaDirectorFinal = $this->notaFase($idsDeEsteProyecto, $entregas, $pivotes, FaseProyecto::PresentacionFinal->value);

        // 4. Nota Final PG2
        $notaFinal = null;

        if ($notaEntregasPonderada !== null && $notaEvaluadores !== null && $notaDirectorFinal !== null) {
            $notaFinal = round(
                ($notaEntregasPonderada * $pesos['entregas'] / 100)
                + ($notaEvaluadores * $pesos['evaluadores'] / 100)
                + ($notaDirectorFinal * $pesos['presentacion'] / 100),
                2,
            );
        }

        return [
            'notas_entregas_desarrollo' => $entregasDesarrollo,
            'nota_entregas_desarrollo_ponderada' => $notaEntregasPonderada,
            'nota_evaluadores_presentacion_final' => $notaEvaluadores,
            'nota_director_presentacion_final' => $notaDirectorFinal,
            'nota_final_pg2' => $notaFinal,
        ];
    }

    // -------------------------------------------------------------------------
    // Shared helpers for coordinator view
    // -------------------------------------------------------------------------

    /**
     * Weighted average of deliveries. Each delivery has a grade (nota) and
     * a percentage weight (peso). The sum of pesos should be 100%.
     *
     * @param  list<array{titulo: string, nota: float|null, peso: float|null}>  $entregas
     */
    private function calcularNotaPonderada(array $entregas): ?float
    {
        $totalPeso = 0.0;
        $sumaPonderada = 0.0;

        foreach ($entregas as $item) {
            if ($item['nota'] === null || $item['peso'] === null) {
                continue;
            }

            $sumaPonderada += $item['nota'] * ($item['peso'] / 100);
            $totalPeso += $item['peso'];
        }

        if ($totalPeso <= 0.0) {
            return null;
        }

        // Normalize if the total weight doesn't add to 100
        return round($sumaPonderada * (100 / $totalPeso), 2);
    }

    /**
     * Average of evaluator grades for a specific phase.
     *
     * @param  list<array{nota: float|null, fase: string}>  $evaluaciones
     */
    private function promedioEvaluaciones(array $evaluaciones, string $fase): ?float
    {
        $notas = [];

        foreach ($evaluaciones as $ev) {
            if ($ev['fase'] === $fase && $ev['nota'] !== null) {
                $notas[] = $ev['nota'];
            }
        }

        if ($notas === []) {
            return null;
        }

        return round(array_sum($notas) / count($notas), 2);
    }

    /**
     * Director grade (from pivot) for the first entrega in a given phase.
     *
     * @param  Collection<int, int>  $idsDeEsteProyecto
     * @param  Collection<int, Entrega>  $entregas
     * @param  Collection<int, EntregaProyecto>  $pivotes
     */
    private function notaFase(
        Collection $idsDeEsteProyecto,
        Collection $entregas,
        Collection $pivotes,
        string $fase,
    ): ?float {
        foreach ($idsDeEsteProyecto as $entregaId) {
            $entrega = $entregas->get($entregaId);

            if ($entrega === null || $entrega->phase !== $fase) {
                continue;
            }

            $pivot = $pivotes->get($entregaId);
            $nota = $this->notaDePivot($pivot);

            if ($nota !== null) {
                return $nota;
            }
        }

        return null;
    }

    /**
     * Load evaluator grades for all projects, grouped by proyecto_id.
     * Returns the phase and nota for each evaluator assignment.
     *
     * @param  list<int>  $proyectoIds
     * @return array<int, list<array{nota: float|null, fase: string}>>
     */
    private function evaluacionesEvaluadorPorProyecto(array $proyectoIds): array
    {
        if ($proyectoIds === []) {
            return [];
        }

        $asignaciones = EvaluadorProyecto::query()
            ->with('evaluacion')
            ->whereIn('proyecto_id', $proyectoIds)
            ->get();

        $map = [];

        foreach ($asignaciones as $asignacion) {
            $raw = $asignacion->evaluacion?->nota;
            $nota = ($raw === null || $raw === '') ? null : (float) $raw;

            $map[$asignacion->proyecto_id][] = [
                'nota' => $nota,
                'fase' => $asignacion->fase,
            ];
        }

        return $map;
    }

    /**
     * Read weights for a semester and tipo, falling back to defaults.
     * Handles the case where the coordinador_grade_weights table doesn't exist yet.
     *
     * @return array{entregas: float, evaluadores: float, presentacion: float}
     */
    private function obtenerPesos(int $semesterId, string $tipo): array
    {
        try {
            $record = CoordinadorGradeWeight::where('semestre_id', $semesterId)
                ->where('tipo', $tipo)
                ->first();
        } catch (\Throwable) {
            // Table doesn't exist yet — return defaults
            return self::DEFAULT_WEIGHTS;
        }

        if ($record === null) {
            return self::DEFAULT_WEIGHTS;
        }

        return [
            'entregas' => (float) $record->peso_entregas,
            'evaluadores' => (float) $record->peso_evaluadores,
            'presentacion' => (float) $record->peso_presentacion,
        ];
    }

    // -------------------------------------------------------------------------
    // Student / Director / Evaluator view (original logic, unchanged)
    // -------------------------------------------------------------------------

    /**
     * @param  array{semestre_id?: mixed, proyecto_id?: mixed, entrega_id?: mixed, estado_nota?: mixed, q?: mixed}  $filters
     * @return array{semestres: list<array<string, mixed>>, proyectos: list<array<string, mixed>>}
     */
    private function listarEstudianteDirectorEvaluador(User $user, array $filters): array
    {
        $proyectoId = isset($filters['proyecto_id']) && $filters['proyecto_id'] !== ''
            ? (int) $filters['proyecto_id']
            : null;

        if ($proyectoId !== null) {
            $this->assertPuedeVerProyecto($user, $proyectoId);
        }

        $proyectosQuery = Proyecto::query()
            ->with(['director:id,name', 'estudiantes:id,name', 'semestre:id,name,is_active'])
            ->orderBy('code');

        $this->aplicarScopeRol($proyectosQuery, $user);

        if (isset($filters['semestre_id']) && $filters['semestre_id'] !== '') {
            $proyectosQuery->where('semester_id', (int) $filters['semestre_id']);
        } elseif ($user->role === UserRole::Coordinador) {
            $proyectosQuery->whereHas('semestre', fn (Builder $q) => $q->where('is_active', true));
        }

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';

        if ($q !== '') {
            $proyectosQuery->where(function (Builder $query) use ($q) {
                $query->where('code', 'like', '%'.$q.'%')
                    ->orWhere('title', 'like', '%'.$q.'%');
            });
        }

        if ($proyectoId !== null) {
            $proyectosQuery->whereKey($proyectoId);
        }

        $proyectos = $proyectosQuery->get();
        $proyectoIds = $proyectos->pluck('id');

        $pivotesPorProyecto = EntregaProyecto::query()
            ->whereIn('proyecto_id', $proyectoIds)
            ->get()
            ->groupBy('proyecto_id');

        $entregaIds = $pivotesPorProyecto
            ->flatten()
            ->pluck('entrega_id')
            ->unique()
            ->filter()
            ->values();

        $entregas = Entrega::query()
            ->whereIn('id', $entregaIds)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $notasEvaluador = $this->notasEvaluadorPorProyecto($user, $proyectoIds->all());

        $entregaFiltro = isset($filters['entrega_id']) && $filters['entrega_id'] !== ''
            ? (int) $filters['entrega_id']
            : null;
        $estadoFiltro = isset($filters['estado_nota']) ? (string) $filters['estado_nota'] : '';

        $payload = [];

        foreach ($proyectos as $proyecto) {
            $idsDeEsteProyecto = collect($pivotesPorProyecto->get($proyecto->id, collect()))
                ->pluck('entrega_id')
                ->unique()
                ->values();

            $pivotes = collect($pivotesPorProyecto->get($proyecto->id, collect()))->keyBy('entrega_id');

            $entregasPayload = [];

            foreach ($idsDeEsteProyecto as $entregaId) {
                $entrega = $entregas->get($entregaId);

                if ($entrega === null) {
                    continue;
                }

                if ($entregaFiltro !== null && (int) $entrega->id !== $entregaFiltro) {
                    continue;
                }

                $nota = $this->notaDePivot($pivotes->get($entregaId));
                $estadoNota = $nota === null ? 'sin_calificar' : 'calificada';

                if ($estadoFiltro === 'calificada' && $estadoNota !== 'calificada') {
                    continue;
                }

                if ($estadoFiltro === 'sin_calificar' && $estadoNota !== 'sin_calificar') {
                    continue;
                }

                $entregasPayload[] = [
                    'id' => $entrega->id,
                    'titulo' => $entrega->title,
                    'fase' => $entrega->phase?->value ?? $entrega->phase,
                    'nota' => $nota,
                    'estado_nota' => $estadoNota,
                ];
            }

            if ($entregasPayload === [] && ($entregaFiltro !== null || in_array($estadoFiltro, ['calificada', 'sin_calificar'], true))) {
                continue;
            }

            $payload[] = [
                'id' => $proyecto->id,
                'codigo' => $proyecto->code,
                'titulo' => $proyecto->title,
                'director' => $proyecto->director?->name,
                'estudiantes' => $proyecto->estudiantes->pluck('name')->filter()->implode(', '),
                'semestre_id' => $proyecto->semester_id,
                'entregas' => $entregasPayload,
                'nota_evaluador' => $notasEvaluador[$proyecto->id] ?? null,
            ];
        }

        return [
            'semestres' => $this->semestresVisibles($user),
            'proyectos' => $payload,
        ];
    }

    // -------------------------------------------------------------------------
    // Shared helpers (unchanged)
    // -------------------------------------------------------------------------

    private function aplicarScopeRol(Builder $query, User $user): void
    {
        match ($user->role) {
            UserRole::Coordinador => null,
            UserRole::Director => $query->where('director_id', $user->id),
            UserRole::Estudiante => $query->whereHas(
                'estudiantes',
                fn (Builder $q) => $q->where('users.id', $user->id),
            ),
            UserRole::EvaluadorExterno => $query->whereHas(
                'evaluadores',
                fn (Builder $q) => $q->where('users.id', $user->id),
            ),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function assertPuedeVerProyecto(User $user, int $proyectoId): void
    {
        $query = Proyecto::query()->whereKey($proyectoId);
        $this->aplicarScopeRol($query, $user);

        if (! $query->exists()) {
            throw new AuthorizationException('No tienes permiso para consultar las notas de este proyecto.');
        }
    }

    /**
     * @param  list<int>  $proyectoIds
     * @return array<int, float|null>
     */
    private function notasEvaluadorPorProyecto(User $user, array $proyectoIds): array
    {
        if ($user->role !== UserRole::EvaluadorExterno || $proyectoIds === []) {
            return [];
        }

        $asignaciones = EvaluadorProyecto::query()
            ->with('evaluacion')
            ->where('evaluador_id', $user->id)
            ->whereIn('proyecto_id', $proyectoIds)
            ->get();

        $map = [];

        foreach ($asignaciones as $asignacion) {
            $raw = $asignacion->evaluacion?->nota;
            $map[$asignacion->proyecto_id] = ($raw === null || $raw === '') ? null : (float) $raw;
        }

        return $map;
    }

    /**
     * @return list<array{id: int, nombre: string, is_active: bool}>
     */
    private function semestresVisibles(User $user): array
    {
        $query = Semestre::query()->orderByDesc('id');

        if ($user->role === UserRole::Coordinador) {
            $query->where('is_active', true);
        } else {
            $ids = Proyecto::query();
            $this->aplicarScopeRol($ids, $user);
            $query->whereIn('id', $ids->select('semester_id'));
        }

        return $query->get(['id', 'name', 'is_active'])->map(fn (Semestre $s) => [
            'id' => $s->id,
            'nombre' => $s->name,
            'is_active' => (bool) $s->is_active,
        ])->values()->all();
    }

    private function notaDePivot(mixed $pivot): ?float
    {
        if (! $pivot instanceof EntregaProyecto) {
            return null;
        }

        $raw = $pivot->getRawOriginal('director_grade');

        if ($raw === null || $raw === '') {
            return null;
        }

        return (float) $raw;
    }
}
