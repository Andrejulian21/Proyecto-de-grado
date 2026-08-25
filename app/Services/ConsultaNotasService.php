<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final class ConsultaNotasService
{
    /**
     * @param  array{semestre_id?: mixed, proyecto_id?: mixed, entrega_id?: mixed, estado_nota?: mixed, q?: mixed}  $filters
     * @return array{semestres: list<array<string, mixed>>, proyectos: list<array<string, mixed>>}
     */
    public function listar(User $user, array $filters): array
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
