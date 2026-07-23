export interface EvaluacionMock {
    id: number;
    proyecto_id: number;
    proyecto_code: string;
    proyecto_title: string;
    estudiantes: string;
    fase: string;
    status: 'pendiente' | 'en_progreso' | 'completada';
    evaluador_name?: string;
    nota?: number | null;
    due_date: string;
}

export const MOCK_EVALUACIONES: EvaluacionMock[] = [
    {
        id: 1,
        proyecto_id: 1,
        proyecto_code: 'PG-2026-014',
        proyecto_title: 'Sistema Centralizado de Proyectos de Grado',
        estudiantes: 'Ana Martínez, Luis Felipe Ríos',
        fase: 'presentacion_anteproyecto',
        status: 'pendiente',
        due_date: '2026-04-15',
    },
    {
        id: 2,
        proyecto_id: 3,
        proyecto_code: 'PG-2026-008',
        proyecto_title: 'Dashboard de Indicadores de Gestión Académica',
        estudiantes: 'Andrés Felipe Torres, Diana Carolina Rojas',
        fase: 'presentacion_final',
        status: 'en_progreso',
        evaluador_name: 'Pedro Evaluador',
        due_date: '2026-05-20',
    },
    {
        id: 3,
        proyecto_id: 2,
        proyecto_code: 'PG-2026-015',
        proyecto_title: 'Plataforma de Análisis de Sentimientos',
        estudiantes: 'María Fernanda Rincón',
        fase: 'anteproyecto',
        status: 'completada',
        evaluador_name: 'María Externa',
        nota: 4.2,
        due_date: '2026-03-20',
    },
];

export function getMockEvaluaciones() {
    return structuredClone(MOCK_EVALUACIONES);
}

export function getMockDirectorEvaluacionesList() {
    return [
        {
            id: 1,
            code: 'PG-2026-014',
            title: 'Sistema Centralizado de Proyectos de Grado',
            current_phase: 'desarrollo',
            status: 'en_curso',
            fase_asignada: 'Anteproyecto',
            fecha: '2026-04-15',
            hora_inicio: '14:00',
            hora_fin: '16:00',
            estudiantes: [
                { id: 201, name: 'Ana Martínez' },
                { id: 202, name: 'Luis Felipe Ríos' },
            ],
            co_evaluadores: [{ id: 30, name: 'Pedro Evaluador', email: 'pedro.eval@externo.com' }],
            semestre: { id: 1, name: '2026-1', is_active: true },
        },
        {
            id: 2,
            code: 'PG-2026-015',
            title: 'Plataforma de Análisis de Sentimientos',
            current_phase: 'anteproyecto',
            status: 'en_riesgo',
            fase_asignada: 'Final',
            fecha: '2026-05-20',
            hora_inicio: '10:00',
            hora_fin: '12:00',
            estudiantes: [{ id: 203, name: 'María Fernanda Rincón' }],
            co_evaluadores: [],
            semestre: { id: 1, name: '2026-1', is_active: true },
        },
    ];
}


export function getMockEntregaFase(proyectoId: number, fase: string) {
    return {
        id: 100 + proyectoId,
        proyecto_id: proyectoId,
        fase,
        titulo: `Entrega fase ${fase}`,
        estado: 'enviada',
        versiones: [
            {
                id: 1001,
                numero_version: 1,
                original_name: `${fase}_v1.pdf`,
                subido_en: '2026-03-10T14:00:00',
            },
        ],
        rubrica: [
            { criterio: 'Claridad', peso: 30, nota: null },
            { criterio: 'Metodología', peso: 40, nota: null },
            { criterio: 'Presentación', peso: 30, nota: null },
        ],
    };
}

export function submitMockEvaluacion(payload: Record<string, unknown>) {
    return { success: true, data: { id: Date.now(), ...payload, nota: 4.0 } };
}
