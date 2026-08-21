<?php

declare(strict_types=1);

namespace App\Services\Evaluation\DTO;

/**
 * Normalized preliminary analysis payload returned to consumers / UI.
 * Must not include an academic grade.
 */
final readonly class StructuredEvaluationResult
{
    /**
     * @param  list<string>  $observaciones
     * @param  list<string>  $recomendaciones
     */
    public function __construct(
        public string $resumen,
        public string $coherencia,
        public string $claridad,
        public string $estructura,
        public string $completitudAparente,
        public string $correspondencia,
        public array $observaciones,
        public array $recomendaciones,
        public string $conclusion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'resumen' => $this->resumen,
            'coherencia' => $this->coherencia,
            'claridad' => $this->claridad,
            'estructura' => $this->estructura,
            'completitud_aparente' => $this->completitudAparente,
            'correspondencia' => $this->correspondencia,
            'observaciones' => $this->observaciones,
            'recomendaciones' => $this->recomendaciones,
            'conclusion' => $this->conclusion,
        ];
    }
}
