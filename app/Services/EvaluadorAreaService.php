<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class EvaluadorAreaService
{
    /**
     * @param  array{q?: mixed, estado?: mixed}  $filters
     * @return list<array<string, mixed>>
     */
    public function listarAsignaciones(User $user, array $filters): array
    {
        return $this->queryAsignaciones($user, $filters)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(fn (EvaluadorProyecto $asignacion) => $this->mapCard($asignacion))
            ->values()
            ->all();
    }

    /**
     * @return array{evaluador: array<string, mixed>, resumen: array<string, int>, proximas: list<array<string, mixed>>}
     */
    public function dashboard(User $user): array
    {
        $asignaciones = $this->queryAsignaciones($user, [])->get();

        $pendientes = $asignaciones->where('evaluado', false);
        $realizadas = $asignaciones->where('evaluado', true);
        $sinFecha = $asignaciones->filter(fn (EvaluadorProyecto $a) => $a->fecha === null);

        $hoy = now()->toDateString();
        $proximas = $pendientes
            ->filter(fn (EvaluadorProyecto $a) => $a->fecha !== null && $a->fecha->toDateString() >= $hoy)
            ->sortBy(fn (EvaluadorProyecto $a) => $a->fecha?->toDateString().' '.($a->hora_inicio ?? ''))
            ->take(5)
            ->values()
            ->map(fn (EvaluadorProyecto $a) => $this->mapEvento($a))
            ->all();

        return [
            'evaluador' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'resumen' => [
                'asignadas' => $asignaciones->count(),
                'pendientes' => $pendientes->count(),
                'realizadas' => $realizadas->count(),
                'sin_fecha' => $sinFecha->count(),
            ],
            'proximas' => $proximas,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function calendario(User $user): array
    {
        return $this->queryAsignaciones($user, [])
            ->whereNotNull('fecha')
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get()
            ->map(fn (EvaluadorProyecto $asignacion) => $this->mapEvento($asignacion))
            ->values()
            ->all();
    }

    /**
     * @param  array{q?: mixed, estado?: mixed}  $filters
     */
    private function queryAsignaciones(User $user, array $filters): Builder
    {
        $query = EvaluadorProyecto::query()
            ->with(['proyecto.estudiantes:id,name', 'proyecto.director:id,name,email', 'evaluacion'])
            ->where('evaluador_id', $user->id);

        $estado = (string) ($filters['estado'] ?? '');

        if ($estado === 'pendiente') {
            $query->where('evaluado', false);
        } elseif ($estado === 'evaluada') {
            $query->where('evaluado', true);
        }

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $inner) use ($like) {
                $inner->whereHas('proyecto', function (Builder $proyecto) use ($like) {
                    $proyecto->where('title', 'like', $like)
                        ->orWhere('code', 'like', $like);
                })->orWhereHas('proyecto.estudiantes', function (Builder $estudiante) use ($like) {
                    $estudiante->where('users.name', 'like', $like);
                });
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapCard(EvaluadorProyecto $asignacion): array
    {
        $nota = $asignacion->evaluacion?->nota;

        return [
            'id' => $asignacion->id,
            'proyecto' => $this->mapProyecto($asignacion->proyecto),
            'fase' => $this->faseEntrega((string) $asignacion->fase),
            'evaluado' => (bool) $asignacion->evaluado,
            'estado' => $asignacion->evaluado ? 'evaluada' : 'pendiente',
            'fecha' => $asignacion->fecha?->toDateString(),
            'hora_inicio' => $this->horaCorta($asignacion->hora_inicio),
            'hora_fin' => $this->horaCorta($asignacion->hora_fin),
            'nota' => ($nota === null || $nota === '') ? null : (float) $nota,
            'evaluated_at' => $asignacion->evaluacion?->evaluated_at?->toDateTimeString(),
            'director_grade' => $this->directorGradeDeAsignacion($asignacion),
            'created_at' => $asignacion->created_at?->toDateString(),
        ];
    }

    private function directorGradeDeAsignacion(EvaluadorProyecto $asignacion): ?float
    {
        $proyecto = $asignacion->proyecto;

        if ($proyecto === null || $proyecto->semester_id === null) {
            return null;
        }

        $entrega = Entrega::query()
            ->where('semester_id', $proyecto->semester_id)
            ->where('phase', $this->faseEntrega((string) $asignacion->fase))
            ->first();

        if ($entrega === null) {
            return null;
        }

        $pivot = EntregaProyecto::query()
            ->where('entrega_id', $entrega->id)
            ->where('proyecto_id', $proyecto->id)
            ->first();

        $raw = $pivot?->getRawOriginal('director_grade') ?? $pivot?->director_grade;

        if ($raw === null || $raw === '') {
            return null;
        }

        return (float) $raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapEvento(EvaluadorProyecto $asignacion): array
    {
        return [
            'id' => $asignacion->id,
            'fecha' => $asignacion->fecha?->toDateString(),
            'hora_inicio' => $this->horaCorta($asignacion->hora_inicio),
            'hora_fin' => $this->horaCorta($asignacion->hora_fin),
            'fase' => $this->faseEntrega((string) $asignacion->fase),
            'estado' => $asignacion->evaluado ? 'evaluada' : 'pendiente',
            'proyecto' => $asignacion->proyecto === null ? null : [
                'id' => $asignacion->proyecto->id,
                'codigo' => $asignacion->proyecto->code,
                'titulo' => $asignacion->proyecto->title,
            ],
        ];
    }

    public function faseEntrega(string $faseAsignacion): string
    {
        return match ($faseAsignacion) {
            'Anteproyecto' => 'anteproyecto',
            'Final' => 'presentacion_final',
            default => strtolower($faseAsignacion),
        };
    }

    private function horaCorta(?string $hora): ?string
    {
        if ($hora === null || $hora === '') {
            return null;
        }

        return substr($hora, 0, 5);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function mapProyecto(?Proyecto $proyecto): ?array
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
}
