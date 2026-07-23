import type { Proyecto } from '@/hooks/useProyectos';
import type { DirectorProyecto } from '@/hooks/useDirectorProyectos';
import type { KpiResponse } from '@/hooks/useKpis';

export const MOCK_KPIS: KpiResponse = {
    proyectos_activos: 24,
    en_riesgo: 3,
    alertas_sin_revisar: 7,
    tasa_cumplimiento: 78,
};

export const MOCK_SEMESTRES = [
    { id: 1, name: '2026-1', start_date: '2026-02-01', end_date: '2026-06-30', is_active: true },
    { id: 2, name: '2025-2', start_date: '2025-08-01', end_date: '2025-12-15', is_active: false },
];

export const MOCK_ADMIN_PROYECTOS: Proyecto[] = [
    {
        id: 1,
        code: 'PG-2026-014',
        title: 'Sistema Centralizado de Proyectos de Grado',
        estudiantes: [
            { id: 201, name: 'Ana Martínez', email: 'ana.m@unab.edu.co' },
            { id: 202, name: 'Luis Felipe Ríos', email: 'lf.rios@unab.edu.co' },
        ],
        director: { id: 10, name: 'Dr. Carlos Andrés Gómez', email: 'cgomez@unab.edu.co' },
        current_phase: 'desarrollo',
        status: 'active',
        semester_id: 1,
        created_at: '2026-02-10T10:00:00',
    },
    {
        id: 2,
        code: 'PG-2026-015',
        title: 'Plataforma de Análisis de Sentimientos para Redes Sociales',
        estudiantes: [{ id: 203, name: 'María Fernanda Rincón', email: 'mf.rincon@unab.edu.co' }],
        director: { id: 10, name: 'Dr. Carlos Andrés Gómez', email: 'cgomez@unab.edu.co' },
        current_phase: 'anteproyecto',
        status: 'at-risk',
        semester_id: 1,
        created_at: '2026-02-12T14:00:00',
    },
    {
        id: 3,
        code: 'PG-2026-008',
        title: 'Dashboard de Indicadores de Gestión Académica',
        estudiantes: [
            { id: 204, name: 'Andrés Felipe Torres', email: 'af.torres@unab.edu.co' },
            { id: 205, name: 'Diana Carolina Rojas', email: 'dc.rojas@unab.edu.co' },
        ],
        director: { id: 11, name: 'Dra. Laura Martínez', email: 'lmartinez@unab.edu.co' },
        current_phase: 'presentacion_final',
        status: 'active',
        semester_id: 1,
        created_at: '2026-01-20T09:00:00',
    },
    {
        id: 4,
        code: 'PG-2026-005',
        title: 'Plataforma E-Learning para Cursos de Programación',
        estudiantes: [{ id: 206, name: 'Juan David Pérez', email: 'jd.perez@unab.edu.co' }],
        director: { id: 12, name: 'Dr. Andrés Vega', email: 'avega@unab.edu.co' },
        current_phase: 'desarrollo',
        status: 'completed',
        semester_id: 1,
        created_at: '2026-01-05T11:00:00',
    },
    {
        id: 5,
        code: 'PG-2026-012',
        title: 'App Móvil para Gestión de Inventarios Hospitalarios',
        estudiantes: [{ id: 207, name: 'Laura Patricia Gómez', email: 'lp.gomez@unab.edu.co' }],
        director: { id: 11, name: 'Dra. Laura Martínez', email: 'lmartinez@unab.edu.co' },
        current_phase: 'presentacion_anteproyecto',
        status: 'active',
        semester_id: 1,
        created_at: '2026-02-18T16:00:00',
    },
];

export const MOCK_DIRECTOR_PROYECTOS: DirectorProyecto[] = MOCK_ADMIN_PROYECTOS.filter((p) =>
    p.director?.id === 10,
).map((p) => ({
    id: p.id,
    code: p.code,
    title: p.title,
    current_phase: p.current_phase,
    status: p.status,
    estudiantes: p.estudiantes.map((e) => ({ id: e.id, name: e.name })),
    semestre: MOCK_SEMESTRES.find((s) => s.id === p.semester_id) ?? null,
}));

export function getDirectorProyectoDetail(id: number) {
    const p = MOCK_ADMIN_PROYECTOS.find((x) => x.id === id);
    if (!p) return undefined;
    return {
        id: p.id,
        code: p.code,
        title: p.title,
        description:
            'Proyecto de grado enfocado en el diseño e implementación de una solución tecnológica para la gestión académica institucional.',
        status: p.status === 'at-risk' ? 'on-hold' : p.status,
        current_phase: p.current_phase,
        estudiantes: p.estudiantes.map((e) => ({ id: e.id, name: e.name })),
        tipo: 'Desarrollo de software',
        period: '2026-1',
        start_date: '2026-02-01',
        end_date: '2026-06-30',
    };
}

export function getAdminProyectoDetail(id: number) {
    const p = MOCK_ADMIN_PROYECTOS.find((x) => x.id === id);
    if (!p) return undefined;
    return {
        ...p,
        description: 'Detalle del proyecto para supervisión del coordinador.',
        entregas_count: 4,
        bitacoras_count: 5,
        alert_count: p.status === 'at-risk' ? 2 : 0,
    };
}

export const MOCK_DIRECTOR_KPIS = {
    proyectos_activos: MOCK_DIRECTOR_PROYECTOS.length,
    entregas_pendientes: 4,
    bitacoras_pendientes: 2,
    evaluaciones_pendientes: 1,
};

export const MOCK_DIRECTORES = [
    {
        id: 10,
        name: 'Dr. Carlos Andrés Gómez',
        email: 'cgomez@unab.edu.co',
        max_capacity: 8,
        current_load: 5,
        areas: 'Ingeniería de Software, Bases de Datos',
        proyectos_count: 2,
    },
    {
        id: 11,
        name: 'Dra. Laura Martínez',
        email: 'lmartinez@unab.edu.co',
        max_capacity: 6,
        current_load: 4,
        areas: 'Inteligencia Artificial, Análisis de Datos',
        proyectos_count: 2,
    },
    {
        id: 12,
        name: 'Dr. Andrés Vega',
        email: 'avega@unab.edu.co',
        max_capacity: 5,
        current_load: 2,
        areas: 'Educación Digital, UX',
        proyectos_count: 1,
    },
];

export const MOCK_CUPOS = MOCK_DIRECTORES.map((d) => ({
    director_id: d.id,
    director_name: d.name,
    max_capacity: d.max_capacity,
    current_load: d.current_load,
    available: d.max_capacity - d.current_load,
}));

export const MOCK_ESTUDIANTES_SEARCH = [
    { id: 201, name: 'Ana Martínez', email: 'ana.m@unab.edu.co', codigo_estudiante: '2020123456' },
    { id: 202, name: 'Luis Felipe Ríos', email: 'lf.rios@unab.edu.co', codigo_estudiante: '2020123457' },
    { id: 203, name: 'María Fernanda Rincón', email: 'mf.rincon@unab.edu.co', codigo_estudiante: '2020123458' },
    { id: 204, name: 'Andrés Felipe Torres', email: 'af.torres@unab.edu.co', codigo_estudiante: '2020123459' },
    { id: 205, name: 'Diana Carolina Rojas', email: 'dc.rojas@unab.edu.co', codigo_estudiante: '2020123460' },
];

export function searchEstudiantes(query: string) {
    const q = query.toLowerCase().trim();
    if (!q) return MOCK_ESTUDIANTES_SEARCH;
    return MOCK_ESTUDIANTES_SEARCH.filter(
        (s) =>
            s.name.toLowerCase().includes(q) ||
            s.email.toLowerCase().includes(q) ||
            s.codigo_estudiante.includes(q),
    );
}
