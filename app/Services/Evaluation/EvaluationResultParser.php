<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

use App\Exceptions\AiException;
use App\Services\Evaluation\DTO\StructuredEvaluationResult;

/**
 * Parses provider-agnostic AI text into a structured evaluation result.
 */
final class EvaluationResultParser
{
    public function parse(string $rawContent): StructuredEvaluationResult
    {
        $json = $this->extractJson($rawContent);
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw AiException::providerFailed('La respuesta de IA no tiene un formato JSON válido.');
        }

        return new StructuredEvaluationResult(
            resumen: $this->stringField($data, 'resumen'),
            fortalezas: $this->stringList($data, 'fortalezas'),
            aspectosMejorar: $this->stringList($data, 'aspectos_mejorar'),
            errores: $this->stringList($data, 'errores'),
            recomendaciones: $this->stringList($data, 'recomendaciones'),
            conclusion: $this->stringField($data, 'conclusion'),
            prioridades: $this->priorities($data),
            confianza: $this->optionalFloat($data, 'confianza'),
            puntajeOrientativo: $this->optionalIntScore($data, 'puntaje_orientativo'),
        );
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
     * @return list<array{item: string, criticidad: string}>
     */
    private function priorities(array $data): array
    {
        $value = $data['prioridades'] ?? [];

        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }
            $item = isset($row['item']) && is_string($row['item']) ? trim($row['item']) : '';
            $criticidad = isset($row['criticidad']) && is_string($row['criticidad'])
                ? strtolower(trim($row['criticidad']))
                : 'media';

            if ($item === '') {
                continue;
            }

            if (! in_array($criticidad, ['alta', 'media', 'baja'], true)) {
                $criticidad = 'media';
            }
            $items[] = ['item' => $item, 'criticidad' => $criticidad];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function optionalFloat(array $data, string $key): ?float
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        if (! is_numeric($data[$key])) {
            return null;
        }

        return max(0.0, min(1.0, (float) $data[$key]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function optionalIntScore(array $data, string $key): ?int
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        if (! is_numeric($data[$key])) {
            return null;
        }

        return max(0, min(100, (int) $data[$key]));
    }
}
