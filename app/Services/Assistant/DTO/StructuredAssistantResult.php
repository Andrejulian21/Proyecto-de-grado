<?php

declare(strict_types=1);

namespace App\Services\Assistant\DTO;

/**
 * Normalized assistant payload returned to consumers / UI.
 *
 * @phpstan-type DirectorRecommendation array{
 *     id: int,
 *     nombre: string,
 *     justificacion: string,
 *     afinidad: float|null
 * }
 */
final readonly class StructuredAssistantResult
{
    /**
     * @param  list<string>  $lineasInvestigacion
     * @param  list<string>  $tecnologiasRecomendadas
     * @param  list<string>  $metodologiasSugeridas
     * @param  list<array{id: int, nombre: string, justificacion: string, afinidad: float|null}>  $directoresRecomendados
     * @param  list<string>  $riesgos
     * @param  list<string>  $proximosPasos
     */
    public function __construct(
        public string $mensaje,
        public string $resumenConversacion,
        public string $ideaRefinada,
        public array $lineasInvestigacion,
        public array $tecnologiasRecomendadas,
        public array $metodologiasSugeridas,
        public array $directoresRecomendados,
        public array $riesgos,
        public array $proximosPasos,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mensaje' => $this->mensaje,
            'resumen_conversacion' => $this->resumenConversacion,
            'idea_refinada' => $this->ideaRefinada,
            'lineas_investigacion' => $this->lineasInvestigacion,
            'tecnologias_recomendadas' => $this->tecnologiasRecomendadas,
            'metodologias_sugeridas' => $this->metodologiasSugeridas,
            'directores_recomendados' => $this->directoresRecomendados,
            'riesgos' => $this->riesgos,
            'proximos_pasos' => $this->proximosPasos,
        ];
    }
}
