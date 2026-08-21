<?php

declare(strict_types=1);

namespace App\Services\Evaluation\Interpreters;

use App\Contracts\Evaluation\EvaluationMetricsDefinition;
use App\Contracts\Evaluation\EvaluationResultInterpreter;
use App\Exceptions\AiException;
use App\Services\Evaluation\DTO\StructuredAbetEvaluationResult;

/**
 * Parses ABET-oriented structured JSON from a provider-agnostic completion.
 */
final class AbetEvaluationResultInterpreter implements EvaluationResultInterpreter
{
    public function __construct(
        private readonly EvaluationMetricsDefinition $metrics,
    ) {}

    public function interpret(string $rawContent): array
    {
        $json = $this->extractJson($rawContent);
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw AiException::providerFailed('La respuesta de IA no tiene un formato JSON válido.');
        }

        $result = new StructuredAbetEvaluationResult(
            resumenEjecutivo: $this->stringField($data, 'resumen_ejecutivo'),
            criteriosEvaluados: $this->criteria($data),
            fortalezas: $this->stringList($data, 'fortalezas'),
            oportunidadesMejora: $this->stringList($data, 'oportunidades_mejora'),
            observaciones: $this->stringList($data, 'observaciones'),
            recomendaciones: $this->stringList($data, 'recomendaciones'),
            riesgos: $this->stringList($data, 'riesgos'),
            conclusion: $this->stringField($data, 'conclusion'),
            perfilMetricas: $this->stringField($data, 'perfil_metricas') !== ''
                ? $this->stringField($data, 'perfil_metricas')
                : $this->metrics->key(),
        );

        return $result->toArray();
    }

    private function extractJson(string $raw): string
    {
        $trimmed = trim($raw);

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        if ($start !== false && $end !== false && $end > $start) {
            return substr($trimmed, $start, $end - $start + 1);
        }

        return $trimmed;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function stringField(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function stringList(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

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

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{id: string, nombre: string, cumplimiento: string, evidencias: list<string>, observaciones: string}>
     */
    private function criteria(array $data): array
    {
        $value = $data['criterios_evaluados'] ?? [];

        if (! is_array($value)) {
            return [];
        }

        $allowed = ['alto', 'medio', 'bajo', 'no_evidencia'];
        $items = [];

        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = isset($row['id']) && is_string($row['id']) ? trim($row['id']) : '';
            $nombre = isset($row['nombre']) && is_string($row['nombre']) ? trim($row['nombre']) : '';

            if ($id === '' && $nombre === '') {
                continue;
            }

            $cumplimiento = isset($row['cumplimiento']) && is_string($row['cumplimiento'])
                ? strtolower(trim($row['cumplimiento']))
                : 'no_evidencia';

            if (! in_array($cumplimiento, $allowed, true)) {
                $cumplimiento = 'no_evidencia';
            }

            $evidencias = [];

            if (isset($row['evidencias']) && is_array($row['evidencias'])) {
                foreach ($row['evidencias'] as $ev) {
                    if (is_string($ev) && trim($ev) !== '') {
                        $evidencias[] = trim($ev);
                    }
                }
            }

            $observaciones = isset($row['observaciones']) && is_string($row['observaciones'])
                ? trim($row['observaciones'])
                : '';

            $items[] = [
                'id' => $id,
                'nombre' => $nombre,
                'cumplimiento' => $cumplimiento,
                'evidencias' => $evidencias,
                'observaciones' => $observaciones,
            ];
        }

        return $items;
    }
}
