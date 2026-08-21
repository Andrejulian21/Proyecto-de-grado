<?php

declare(strict_types=1);

namespace App\Services\Evaluation\DTO;

/**
 * Normalized evaluation payload returned to consumers / UI.
 *
 * @phpstan-type PriorityItem array{item: string, criticidad: string}
 */
final readonly class StructuredEvaluationResult
{
    /**
     * @param  list<string>  $fortalezas
     * @param  list<string>  $aspectosMejorar
     * @param  list<string>  $errores
     * @param  list<string>  $recomendaciones
     * @param  list<array{item: string, criticidad: string}>  $prioridades
     */
    public function __construct(
        public string $resumen,
        public array $fortalezas,
        public array $aspectosMejorar,
        public array $errores,
        public array $recomendaciones,
        public string $conclusion,
        public array $prioridades = [],
        public ?float $confianza = null,
        public ?int $puntajeOrientativo = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'resumen' => $this->resumen,
            'fortalezas' => $this->fortalezas,
            'aspectos_mejorar' => $this->aspectosMejorar,
            'errores' => $this->errores,
            'recomendaciones' => $this->recomendaciones,
            'conclusion' => $this->conclusion,
            'prioridades' => $this->prioridades,
            'confianza' => $this->confianza,
            'puntaje_orientativo' => $this->puntajeOrientativo,
        ];
    }
}
