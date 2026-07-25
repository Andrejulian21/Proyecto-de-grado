/**
 * Shared evaluator rubric + hydration from persisted Evaluacion rows.
 * Source of truth when reopening: GET /api/evaluaciones?entrega_id=
 */

export interface EvaluacionCriterion {
    id: string;
    name: string;
    description?: string;
    maxScore: number;
    percentage: number;
    score: number;
}

export interface SavedEvaluacionRow {
    criterio: string;
    grade: number | string | null;
    percentage: number | string;
    comment: string | null;
}

/** Default rubric used when creating a new evaluation (EvaluarProyecto). */
export const DEFAULT_EVALUADOR_RUBRIC: EvaluacionCriterion[] = [
    {
        id: 'c1',
        name: 'Cumplimiento de Objetivos',
        description:
            'Grado en que el proyecto cumple con los objetivos planteados en la propuesta inicial.',
        maxScore: 5,
        percentage: 30,
        score: 0,
    },
    {
        id: 'c2',
        name: 'Calidad Técnica y Metodológica',
        description:
            'Aplicación correcta de metodologías, herramientas y estándares de ingeniería.',
        maxScore: 5,
        percentage: 25,
        score: 0,
    },
    {
        id: 'c3',
        name: 'Presentación y Sustentación',
        description:
            'Claridad en la exposición, dominio del tema y capacidad de respuesta a preguntas.',
        maxScore: 5,
        percentage: 25,
        score: 0,
    },
    {
        id: 'c4',
        name: 'Aportes y Resultados',
        description:
            'Valor de los resultados obtenidos y su contribución al área de conocimiento.',
        maxScore: 5,
        percentage: 20,
        score: 0,
    },
];

/**
 * Rebuild UI criteria from persisted rows. When saved data exists it is the
 * source of truth (avoids mismatch with hardcoded local names).
 */
export function hydrateCriteriaFromSaved(
    saved: SavedEvaluacionRow[],
    fallback: EvaluacionCriterion[] = DEFAULT_EVALUADOR_RUBRIC,
): EvaluacionCriterion[] {
    if (!saved.length) {
        return fallback.map((c) => ({ ...c, score: 0 }));
    }

    return saved.map((row, index) => {
        const name = row.criterio;
        const fromFallback = fallback.find((c) => c.name === name);

        return {
            id: fromFallback?.id ?? `saved-${index}`,
            name,
            description: fromFallback?.description,
            maxScore: fromFallback?.maxScore ?? 5,
            percentage: Number(row.percentage),
            score: row.grade != null && row.grade !== '' ? Number(row.grade) : 0,
        };
    });
}

export function extractComment(saved: SavedEvaluacionRow[]): string {
    const withComment = saved.find((s) => s.comment != null && String(s.comment).trim() !== '');
    return withComment?.comment ?? '';
}

export function hasStoredGrades(saved: SavedEvaluacionRow[]): boolean {
    return saved.some((s) => s.grade != null && s.grade !== '');
}
