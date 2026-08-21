<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Exceptions\AiException;
use App\Services\Assistant\DTO\StructuredAssistantResult;

/**
 * Parses provider-agnostic AI text into a structured assistant result.
 * Discards director recommendations whose ids are not in the supplied catalog.
 */
final class AssistantResultParser
{
    /**
     * @param  list<array<string, mixed>>  $directorCatalog
     */
    public function parse(string $rawContent, array $directorCatalog): StructuredAssistantResult
    {
        $json = $this->extractJson($rawContent);
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw AiException::providerFailed('La respuesta de IA no tiene un formato JSON válido.');
        }

        $catalogById = [];

        foreach ($directorCatalog as $entry) {
            if (isset($entry['id']) && is_numeric($entry['id'])) {
                $catalogById[(int) $entry['id']] = $entry;
            }
        }

        return new StructuredAssistantResult(
            mensaje: $this->stringField($data, 'mensaje'),
            resumenConversacion: $this->stringField($data, 'resumen_conversacion'),
            ideaRefinada: $this->stringField($data, 'idea_refinada'),
            lineasInvestigacion: $this->stringList($data, 'lineas_investigacion'),
            tecnologiasRecomendadas: $this->stringList($data, 'tecnologias_recomendadas'),
            metodologiasSugeridas: $this->stringList($data, 'metodologias_sugeridas'),
            directoresRecomendados: $this->directors($data, $catalogById),
            riesgos: $this->stringList($data, 'riesgos'),
            proximosPasos: $this->stringList($data, 'proximos_pasos'),
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
     * @param  array<int, array<string, mixed>>  $catalogById
     * @return list<array{id: int, nombre: string, justificacion: string, afinidad: float|null}>
     */
    private function directors(array $data, array $catalogById): array
    {
        $value = $data['directores_recomendados'] ?? [];

        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $row) {
            if (! is_array($row) || ! isset($row['id']) || ! is_numeric($row['id'])) {
                continue;
            }

            $id = (int) $row['id'];

            if (! isset($catalogById[$id])) {
                continue;
            }

            $nombreCatalogo = isset($catalogById[$id]['nombre']) && is_string($catalogById[$id]['nombre'])
                ? $catalogById[$id]['nombre']
                : '';
            $nombre = isset($row['nombre']) && is_string($row['nombre']) && trim($row['nombre']) !== ''
                ? trim($row['nombre'])
                : $nombreCatalogo;

            $justificacion = isset($row['justificacion']) && is_string($row['justificacion'])
                ? trim($row['justificacion'])
                : '';

            $afinidad = null;

            if (isset($row['afinidad']) && is_numeric($row['afinidad'])) {
                $afinidad = max(0.0, min(1.0, (float) $row['afinidad']));
            }

            $items[] = [
                'id' => $id,
                'nombre' => $nombre,
                'justificacion' => $justificacion,
                'afinidad' => $afinidad,
            ];
        }

        return $items;
    }
}
