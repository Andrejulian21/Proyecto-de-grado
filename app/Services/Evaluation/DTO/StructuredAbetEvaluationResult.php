<?php

declare(strict_types=1);

namespace App\Services\Evaluation\DTO;

/**
 * Structured ABET-oriented evaluation payload (extensible; metrics profile is explicit).
 *
 * @phpstan-type CriterionResult array{
 *     id: string,
 *     nombre: string,
 *     cumplimiento: string,
 *     evidencias: list<string>,
 *     observaciones: string
 * }
 */
final readonly class StructuredAbetEvaluationResult
{
    /**
     * @param  list<array{id: string, nombre: string, cumplimiento: string, evidencias: list<string>, observaciones: string}>  $criteriosEvaluados
     * @param  list<string>  $fortalezas
     * @param  list<string>  $oportunidadesMejora
     * @param  list<string>  $observaciones
     * @param  list<string>  $recomendaciones
     * @param  list<string>  $riesgos
     */
    public function __construct(
        public string $resumenEjecutivo,
        public array $criteriosEvaluados,
        public array $fortalezas,
        public array $oportunidadesMejora,
        public array $observaciones,
        public array $recomendaciones,
        public array $riesgos,
        public string $conclusion,
        public string $perfilMetricas,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'resumen_ejecutivo' => $this->resumenEjecutivo,
            'criterios_evaluados' => $this->criteriosEvaluados,
            'fortalezas' => $this->fortalezas,
            'oportunidades_mejora' => $this->oportunidadesMejora,
            'observaciones' => $this->observaciones,
            'recomendaciones' => $this->recomendaciones,
            'riesgos' => $this->riesgos,
            'conclusion' => $this->conclusion,
            'perfil_metricas' => $this->perfilMetricas,
        ];
    }
}
