<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Contracts\Assistant\DirectorCatalogEnricher;
use App\Enums\UserRole;
use App\Models\User;

/**
 * Builds the director recommendation catalog from real DB data.
 * Extra criteria are applied via {@see DirectorCatalogEnricher} without changing the assistant core.
 */
final class DirectorCatalogBuilder
{
    /**
     * @param  list<DirectorCatalogEnricher>  $enrichers
     */
    public function __construct(
        private readonly array $enrichers = [],
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function build(): array
    {
        $directors = User::query()
            ->where('role', UserRole::Director)
            ->with(['academicProfile', 'proyectosDirigidos'])
            ->orderBy('name')
            ->get();

        $catalog = [];
        foreach ($directors as $director) {
            $profile = $director->academicProfile;
            $areasFallback = $this->splitAreas($director->areas);
            $activeProjects = $director->proyectosDirigidos->count();
            $maxCapacity = (int) ($director->max_capacity ?? 3);
            $remaining = max(0, $maxCapacity - $activeProjects);

            $researchLines = $this->stringList($profile?->research_lines);
            if ($researchLines === []) {
                $researchLines = $areasFallback;
            }

            $catalog[] = [
                'id' => $director->id,
                'nombre' => (string) $director->name,
                'email' => (string) $director->email,
                'areas_especializacion' => $areasFallback,
                'lineas_investigacion' => $researchLines,
                'tecnologias' => $this->stringList($profile?->technologies),
                'metodologias' => $this->stringList($profile?->methodologies),
                'experiencia_academica' => filled($profile?->academic_experience)
                    ? (string) $profile->academic_experience
                    : null,
                'anos_experiencia' => $profile?->years_of_experience !== null
                    ? (int) $profile->years_of_experience
                    : null,
                'areas_legacy' => $areasFallback,
                'cupo_maximo' => $maxCapacity,
                'proyectos_activos' => $activeProjects,
                'cupo_disponible' => $remaining,
                'disponible' => $remaining > 0,
            ];
        }

        foreach ($this->enrichers as $enricher) {
            $catalog = $enricher->enrich($catalog);
        }

        return $catalog;
    }

    /**
     * @return list<string>
     */
    private function splitAreas(?string $areas): array
    {
        if ($areas === null || trim($areas) === '') {
            return [];
        }

        $parts = preg_split('/\r\n|\r|\n/', $areas) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $parts,
        )));
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $items[] = trim($item);
            }
        }

        return $items;
    }
}
