<?php

declare(strict_types=1);

namespace App\Services\Evaluation\Strategies;

use App\Contracts\Evaluation\EvaluationMetricsDefinition;
use App\Contracts\Evaluation\EvaluationPromptStrategy;
use App\Enums\AiEvaluationType;
use App\Services\Evaluation\DTO\EvaluationContext;
use App\Services\Evaluation\Metrics\PlaceholderAbetMetricsDefinition;

/**
 * Prompt strategy for Director-facing ABET-oriented document evaluation.
 * Metrics come from {@see EvaluationMetricsDefinition} — swap metrics without changing this pipeline.
 */
final class AbetDirectorEvaluationStrategy implements EvaluationPromptStrategy
{
    public function __construct(
        private readonly PlaceholderAbetMetricsDefinition $metrics,
    ) {}

    public function type(): AiEvaluationType
    {
        return AiEvaluationType::Abet;
    }

    public function promptVersion(): string
    {
        return 'abet_director_v1';
    }

    public function metrics(): EvaluationMetricsDefinition
    {
        return $this->metrics;
    }

    public function systemInstructions(): string
    {
        $profileKey = $this->metrics->key();

        return <<<PROMPT
Eres un asistente de evaluación académica para Directores de proyectos de grado (Ingeniería de Sistemas).
Tu rol es apoyar una evaluación orientada a métricas tipo ABET sobre el documento del estudiante.
NO reemplazas la evaluación oficial del Director ni emites calificación definitiva institucional.

Responde ÚNICAMENTE con un JSON válido (sin markdown, sin texto fuera del JSON) con esta forma exacta:
{
  "resumen_ejecutivo": "string",
  "criterios_evaluados": [
    {
      "id": "string",
      "nombre": "string",
      "cumplimiento": "alto|medio|bajo|no_evidencia",
      "evidencias": ["string"],
      "observaciones": "string"
    }
  ],
  "fortalezas": ["string"],
  "oportunidades_mejora": ["string"],
  "observaciones": ["string"],
  "recomendaciones": ["string"],
  "riesgos": ["string"],
  "conclusion": "string",
  "perfil_metricas": "{$profileKey}"
}

Reglas:
- Evalúa usando SOLO los criterios del perfil de métricas suministrado en el contexto.
- Cita evidencias textuales o estructurales del documento cuando existan; si no hay evidencia, usa "no_evidencia".
- Sé específico y accionable; no inventes contenido del documento.
- "perfil_metricas" debe ser exactamente "{$profileKey}".
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
                'title' => 'Perfil de métricas',
                'body' => $this->metrics->promptSectionBody(),
            ],
            [
                'title' => 'Métricas configuradas por el Coordinador (complementarias)',
                'body' => filled($context->evaluationMetrics)
                    ? (string) $context->evaluationMetrics
                    : 'No se configuraron métricas libres adicionales para esta entrega.',
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
