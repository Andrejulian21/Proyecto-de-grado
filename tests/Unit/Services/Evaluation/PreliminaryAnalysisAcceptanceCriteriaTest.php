<?php

declare(strict_types=1);

use App\Services\Evaluation\DTO\EvaluationContext;
use App\Services\Evaluation\Prompts\PreliminaryAnalysisPrompt;

function acceptanceCriteriaContext(?string $criteria): EvaluationContext
{
    return new EvaluationContext(
        documentMarkdown: '# Planteamiento\n\nTexto del documento.',
        entregaTitle: 'Entrega 1',
        phase: 'anteproyecto',
        proyectoTitle: 'Sistema de grado',
        proyectoCode: 'PG-0001',
        description: 'En esta entrega el estudiante debe presentar el planteamiento del problema.',
        originalFileName: 'avance.docx',
        acceptanceCriteria: $criteria,
    );
}

it('bumps the prompt version to preliminary_analysis_v2', function () {
    expect((new PreliminaryAnalysisPrompt)->promptVersion())->toBe('preliminary_analysis_v2');
});

it('includes the acceptance criteria in the prompt sections when defined', function () {
    $criteria = "1. El documento incluye el planteamiento del problema.\n2. El documento incluye los objetivos.";

    $sections = (new PreliminaryAnalysisPrompt)->contextSections(acceptanceCriteriaContext($criteria));
    $titles = collect($sections)->pluck('title')->all();
    $bodies = collect($sections)->pluck('body')->implode("\n");

    expect($titles)->toContain('Criterios de aceptación')
        ->and($bodies)->toContain('El documento incluye el planteamiento del problema.')
        ->and($bodies)->toContain('El documento incluye los objetivos.');
});

it('uses fallback text when acceptance criteria are not defined', function () {
    $sections = (new PreliminaryAnalysisPrompt)->contextSections(acceptanceCriteriaContext(null));
    $criteriaSection = collect($sections)->firstWhere('title', 'Criterios de aceptación');

    expect($criteriaSection)->not->toBeNull()
        ->and($criteriaSection['body'])->toContain('No se definieron criterios de aceptación');
});

it('instructs criterion-by-criterion evaluation inside the valid JSON shape', function () {
    $instructions = (new PreliminaryAnalysisPrompt)->systemInstructions();

    expect($instructions)->toContain('criterio por criterio')
        ->and($instructions)->toContain('correspondencia')
        ->and($instructions)->toContain('observaciones');
});
