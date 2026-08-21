<?php

declare(strict_types=1);

use App\Services\Evaluation\DTO\EvaluationContext;
use App\Services\Evaluation\Prompts\PreliminaryAnalysisPrompt;

it('incluye la descripción como lo esperado y no métricas configurables', function () {
    $prompt = new PreliminaryAnalysisPrompt;
    $context = new EvaluationContext(
        documentMarkdown: '# Planteamiento\n\nTexto del documento.',
        entregaTitle: 'Entrega 1',
        phase: 'anteproyecto',
        proyectoTitle: 'Sistema de grado',
        proyectoCode: 'PG-0001',
        description: 'En esta entrega el estudiante debe presentar el planteamiento del problema.',
        originalFileName: 'avance.docx',
    );

    $sections = $prompt->contextSections($context);
    $bodies = collect($sections)->pluck('body')->implode("\n");
    $titles = collect($sections)->pluck('title')->all();

    expect($bodies)->toContain('En esta entrega el estudiante debe presentar el planteamiento del problema.')
        ->and($bodies)->toContain('# Planteamiento')
        ->and($bodies)->not->toContain('Métricas de evaluación')
        ->and($titles)->not->toContain('Métricas de evaluación (Coordinador)')
        ->and($titles)->not->toContain('Perfil de métricas');
});

it('pide análisis preliminar y prohíbe calificación académica', function () {
    $instructions = (new PreliminaryAnalysisPrompt)->systemInstructions();

    expect($instructions)->toContain('preliminar')
        ->and($instructions)->toContain('NO reemplazas')
        ->and($instructions)->not->toContain('puntaje_orientativo')
        ->and($instructions)->not->toContain('perfil_metricas');
});
