<?php

declare(strict_types=1);

use App\Services\Evaluation\EvaluationResultParser;

it('parsea observaciones generales y no expone puntaje', function () {
    $parser = new EvaluationResultParser;
    $result = $parser->parse(json_encode([
        'resumen' => 'Relación razonable con lo pedido.',
        'coherencia' => 'Aceptable.',
        'claridad' => 'Clara.',
        'estructura' => 'Ordenada.',
        'completitud_aparente' => 'Aparente.',
        'correspondencia' => 'Coincide en lo general.',
        'observaciones' => ['Ampliar causas.'],
        'recomendaciones' => ['Revisar con el director.'],
        'conclusion' => 'Preliminar.',
        'puntaje_orientativo' => 88,
        'perfil_metricas' => 'abet_placeholder_v1',
    ], JSON_THROW_ON_ERROR));

    $array = $result->toArray();

    expect($array['resumen'])->toBe('Relación razonable con lo pedido.')
        ->and($array['observaciones'])->toBe(['Ampliar causas.'])
        ->and($array)->not->toHaveKey('puntaje_orientativo')
        ->and($array)->not->toHaveKey('perfil_metricas')
        ->and($array)->not->toHaveKey('criterios_evaluados');
});
