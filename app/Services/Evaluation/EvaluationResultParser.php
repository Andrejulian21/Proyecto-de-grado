<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

use App\Exceptions\AiException;
use App\Services\Evaluation\DTO\StructuredEvaluationResult;

/**
 * Parses provider-agnostic AI text into a preliminary analysis result.
 * Academic scores and metric profiles are ignored even if the model emits them.
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

        $observaciones = $this->stringList($data, 'observaciones');

        if ($observaciones === []) {
            $observaciones = array_values(array_filter([
                ...$this->stringList($data, 'fortalezas'),
                ...$this->stringList($data, 'aspectos_mejorar'),
                ...$this->stringList($data, 'errores'),
            ]));
        }

        return new StructuredEvaluationResult(
            resumen: $this->stringField($data, 'resumen') !== ''
                ? $this->stringField($data, 'resumen')
                : $this->stringField($data, 'resumen_ejecutivo'),
            coherencia: $this->stringField($data, 'coherencia'),
            claridad: $this->stringField($data, 'claridad'),
            estructura: $this->stringField($data, 'estructura'),
            completitudAparente: $this->stringField($data, 'completitud_aparente'),
            correspondencia: $this->stringField($data, 'correspondencia'),
            observaciones: $observaciones,
            recomendaciones: $this->stringList($data, 'recomendaciones'),
            conclusion: $this->stringField($data, 'conclusion'),
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
}
