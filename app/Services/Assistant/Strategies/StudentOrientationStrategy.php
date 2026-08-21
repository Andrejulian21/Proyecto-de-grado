<?php

declare(strict_types=1);

namespace App\Services\Assistant\Strategies;

use App\Contracts\Assistant\AssistantPromptStrategy;
use App\Enums\AiAssistantType;
use App\Services\Assistant\DTO\AssistantContext;

/**
 * Prompt strategy for the student academic orientation assistant.
 */
final class StudentOrientationStrategy implements AssistantPromptStrategy
{
    public function type(): AiAssistantType
    {
        return AiAssistantType::StudentOrientation;
    }

    public function promptVersion(): string
    {
        return 'student_orientation_v1';
    }

    public function systemInstructions(): string
    {
        return <<<'PROMPT'
Eres un asistente académico especializado en Proyectos de Grado de Ingeniería de Sistemas (UNAB).
Tu propósito es orientar al estudiante en la definición de su proyecto y recomendar Directores
usando ÚNICAMENTE el catálogo de Directores suministrado en el contexto.

NO eres un chatbot genérico. NO inventes Directores fuera del catálogo.
NO reemplazas al Coordinador ni al Director oficial.

Responde ÚNICAMENTE con un JSON válido (sin markdown, sin texto fuera del JSON) con esta forma exacta:
{
  "mensaje": "string",
  "resumen_conversacion": "string",
  "idea_refinada": "string",
  "lineas_investigacion": ["string"],
  "tecnologias_recomendadas": ["string"],
  "metodologias_sugeridas": ["string"],
  "directores_recomendados": [
    {"id": 0, "nombre": "string", "justificacion": "string", "afinidad": 0.0}
  ],
  "riesgos": ["string"],
  "proximos_pasos": ["string"]
}

Reglas:
- "mensaje" es la respuesta conversacional clara para el estudiante (español).
- Fundamenta cada Director recomendado en líneas, tecnologías, metodologías, experiencia y cupo disponible del catálogo.
- Prefiere Directores con "disponible": true cuando sea razonable; si no hay cupo, explícalo en la justificación.
- "afinidad" es un número entre 0 y 1.
- Si aún falta información del estudiante, pregunta en "mensaje" y refleja gaps en "proximos_pasos".
- No inventes datos del catálogo ni del proyecto.
- Todo el texto en español.
PROMPT;
    }

    public function contextSections(AssistantContext $context): array
    {
        $proyectoBody = 'El estudiante aún no tiene un proyecto asignado.';

        if (is_array($context->proyecto)) {
            $proyectoBody = implode("\n", array_filter([
                'Código: '.($context->proyecto['code'] ?? 'N/A'),
                'Título: '.($context->proyecto['title'] ?? 'N/A'),
                'Fase: '.($context->proyecto['phase'] ?? 'N/A'),
                'Estado: '.($context->proyecto['status'] ?? 'N/A'),
                'Director actual ID: '.($context->proyecto['director_id'] ?? 'sin asignar'),
            ]));
        }

        $historyLines = [];

        foreach ($context->history as $message) {
            $role = strtoupper((string) ($message['role'] ?? 'user'));
            $content = (string) ($message['content'] ?? '');
            $historyLines[] = "{$role}: {$content}";
        }

        $directorsJson = json_encode(
            $context->directors,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
        ) ?: '[]';

        return [
            [
                'title' => 'Estudiante',
                'body' => trim(implode("\n", array_filter([
                    'Nombre: '.$context->studentName,
                    'Email: '.$context->studentEmail,
                    $context->studentCode ? 'Código: '.$context->studentCode : null,
                ]))),
            ],
            [
                'title' => 'Proyecto actual',
                'body' => $proyectoBody,
            ],
            [
                'title' => 'Historial de conversación',
                'body' => $historyLines === []
                    ? 'Sin mensajes previos.'
                    : implode("\n", $historyLines),
            ],
            [
                'title' => 'Catálogo de Directores (fuente de verdad)',
                'body' => $directorsJson,
            ],
            [
                'title' => 'Mensaje actual del estudiante',
                'body' => $context->userMessage,
            ],
        ];
    }
}
