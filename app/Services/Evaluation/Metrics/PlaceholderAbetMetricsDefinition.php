<?php

declare(strict_types=1);

namespace App\Services\Evaluation\Metrics;

use App\Contracts\Evaluation\EvaluationMetricsDefinition;

/**
 * Placeholder ABET-oriented criteria for architectural scaffolding.
 * NOT the definitive institutional ABET rubric — replace by swapping this class.
 */
final class PlaceholderAbetMetricsDefinition implements EvaluationMetricsDefinition
{
    public function key(): string
    {
        return 'abet_placeholder_v1';
    }

    public function label(): string
    {
        return 'Métricas ABET (placeholder)';
    }

    public function criteria(): array
    {
        return [
            [
                'id' => 'SO1',
                'nombre' => 'Resolución de problemas complejos',
                'descripcion' => 'Identifica, formula y resuelve problemas complejos de ingeniería aplicando principios de matemáticas, ciencias e ingeniería.',
            ],
            [
                'id' => 'SO2',
                'nombre' => 'Diseño de ingeniería',
                'descripcion' => 'Aplica diseño de ingeniería para producir soluciones que satisfagan necesidades específicas considerando salud, seguridad, bienestar y factores globales/culturales/sociales.',
            ],
            [
                'id' => 'SO3',
                'nombre' => 'Comunicación efectiva',
                'descripcion' => 'Comunica de manera efectiva con diversas audiencias a través de la documentación del proyecto.',
            ],
            [
                'id' => 'SO4',
                'nombre' => 'Responsabilidad ética y profesional',
                'descripcion' => 'Reconoce responsabilidades éticas y profesionales en situaciones de ingeniería y elabora juicios informados.',
            ],
            [
                'id' => 'SO5',
                'nombre' => 'Trabajo en equipo',
                'descripcion' => 'Funciona efectivamente en equipos que establecen metas, planifican tareas y cumplen objetivos.',
            ],
            [
                'id' => 'SO6',
                'nombre' => 'Experimentación y análisis de datos',
                'descripcion' => 'Desarrolla y conduce experimentación adecuada, analiza e interpreta datos, y usa juicio de ingeniería para conclusiones.',
            ],
            [
                'id' => 'SO7',
                'nombre' => 'Aprendizaje continuo',
                'descripcion' => 'Adquiere y aplica nuevo conocimiento según sea necesario, usando estrategias de aprendizaje apropiadas.',
            ],
        ];
    }

    public function promptSectionBody(): string
    {
        $lines = [
            'Perfil: '.$this->label().' ('.$this->key().')',
            'IMPORTANTE: este conjunto es un placeholder arquitectónico; no constituye la rúbrica ABET definitiva de la institución.',
            '',
            'Criterios a evaluar:',
        ];

        foreach ($this->criteria() as $criterion) {
            $lines[] = sprintf(
                '- [%s] %s — %s',
                $criterion['id'],
                $criterion['nombre'],
                $criterion['descripcion'],
            );
        }

        return implode("\n", $lines);
    }
}
