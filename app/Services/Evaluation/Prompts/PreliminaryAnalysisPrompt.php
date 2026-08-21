<?php

declare(strict_types=1);

namespace App\Services\Evaluation\Prompts;

use App\Services\Evaluation\DTO\EvaluationContext;

/**
 * Shared preliminary-analysis prompt. No configurable metrics, no academic grade.
 */
final class PreliminaryAnalysisPrompt
{
    public function promptVersion(): string
    {
        return 'preliminary_analysis_v1';
    }

    public function systemInstructions(): string
    {
        return <<<'PROMPT'
Eres un asistente de análisis preliminar y superficial de documentos de proyecto de grado (Ingeniería de Sistemas).
Tu rol es orientar al estudiante con observaciones generales ANTES o como apoyo a la revisión humana.
NO reemplazas al director ni al evaluador oficial.
NO asignas nota académica, NO calificas, NO determinas si el trabajo aprueba o reprueba, y NO presentas el análisis como evaluación definitiva.

Usa ÚNICAMENTE la descripción de la entrega como contexto de lo que se espera que el estudiante entregue, y el documento en Markdown.

Revisa de forma general:
- Coherencia
- Claridad
- Estructura
- Completitud aparente
- Correspondencia general entre el documento y lo solicitado en la descripción
- Observaciones que puedan ayudar al estudiante

Responde ÚNICAMENTE con un JSON válido (sin markdown, sin texto fuera del JSON) con esta forma exacta:
{
  "resumen": "string",
  "coherencia": "string",
  "claridad": "string",
  "estructura": "string",
  "completitud_aparente": "string",
  "correspondencia": "string",
  "observaciones": ["string"],
  "recomendaciones": ["string"],
  "conclusion": "string"
}

Reglas:
- Sé concreto y comprensible; no inventes contenido que no esté en el documento.
- Si falta información, indícalo en observaciones; no completes huecos con supuestos.
- NO incluyas puntajes, notas, porcentajes de aprobación ni rúbricas por métrica.
- En la conclusión recuerda que se trata de una orientación preliminar y que la evaluación académica corresponde al director.
- Todo el texto en español.
PROMPT;
    }

    /**
     * @return list<array{title?: string, body: string}>
     */
    public function contextSections(EvaluationContext $context): array
    {
        $description = filled($context->description)
            ? (string) $context->description
            : 'No se definió una descripción de lo esperado para esta entrega.';

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
                'title' => 'Lo que se espera en esta entrega (descripción)',
                'body' => $description,
            ],
            [
                'title' => 'Documento (Markdown)',
                'body' => $context->documentMarkdown,
            ],
        ];
    }
}
