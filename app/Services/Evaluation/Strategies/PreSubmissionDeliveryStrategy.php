<?php

declare(strict_types=1);

namespace App\Services\Evaluation\Strategies;

use App\Contracts\Evaluation\EvaluationPromptStrategy;
use App\Enums\AiEvaluationType;
use App\Services\Evaluation\DTO\EvaluationContext;

/**
 * Prompt strategy for student pre-submission feedback (Evaluador Inteligente).
 */
final class PreSubmissionDeliveryStrategy implements EvaluationPromptStrategy
{
    public function type(): AiEvaluationType
    {
        return AiEvaluationType::PreSubmission;
    }

    public function promptVersion(): string
    {
        return 'pre_submission_v1';
    }

    public function systemInstructions(): string
    {
        return <<<'PROMPT'
Eres un evaluador académico asistente para proyectos de grado de Ingeniería de Sistemas.
Tu rol es dar retroalimentación formativa al estudiante ANTES de la entrega oficial.
NO reemplazas al director ni al evaluador oficial.

Responde ÚNICAMENTE con un JSON válido (sin markdown, sin texto fuera del JSON) con esta forma exacta:
{
  "resumen": "string",
  "fortalezas": ["string"],
  "aspectos_mejorar": ["string"],
  "errores": ["string"],
  "recomendaciones": ["string"],
  "conclusion": "string",
  "prioridades": [{"item": "string", "criticidad": "alta|media|baja"}],
  "confianza": 0.0,
  "puntaje_orientativo": 0
}

Reglas:
- Sé específico y accionable; cita aspectos del documento cuando sea posible.
- Usa las métricas del coordinador como guía principal de evaluación.
- "confianza" es un número entre 0 y 1.
- "puntaje_orientativo" es un entero de 0 a 100 (orientativo, no calificación oficial).
- Si falta información, indícalo en aspectos_mejorar; no inventes contenido del documento.
- Todo el texto en español.
PROMPT;
    }

    public function contextSections(EvaluationContext $context): array
    {
        return [
            [
                'title' => 'Proyecto',
                'body' => trim($context->proyectoCode.' — '.$context->proyectoTitle),
            ],
            [
                'title' => 'Entrega',
                'body' => trim($context->entregaTitle."\nFase: ".$context->phase."\nArchivo: ".$context->originalFileName),
            ],
            [
                'title' => 'Métricas de evaluación (Coordinador)',
                'body' => filled($context->evaluationMetrics)
                    ? (string) $context->evaluationMetrics
                    : 'No se configuraron métricas específicas para esta entrega.',
            ],
            [
                'title' => 'Criterios de aceptación',
                'body' => filled($context->acceptanceCriteria)
                    ? (string) $context->acceptanceCriteria
                    : 'No se configuraron criterios de aceptación.',
            ],
            [
                'title' => 'Documento (Markdown)',
                'body' => $context->documentMarkdown,
            ],
        ];
    }
}
