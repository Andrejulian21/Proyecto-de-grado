export type SignatureStatus = 'pendiente' | 'firmado' | 'rechazado';

export interface BitacoraProject {
    id: number;
    code: string;
    title: string;
    directorName: string;
    members: string[];
    meetingCount: number;
    pendingCount: number;
}

export interface BitacoraMeeting {
    id: number;
    projectId: number;
    projectName: string;
    projectCode: string;
    directorName: string;
    members: string[];
    createdAt: string;
    meetingDate: string;
    topic: string;
    summary: string;
    content: string;
    signatureStatus: SignatureStatus;
    signedAt?: string;
    signedTime?: string;
    rejectionComment?: string;
}

export const MOCK_BITACORA_PROJECTS: BitacoraProject[] = [
    {
        id: 1,
        code: 'PG-2026-014',
        title: 'Sistema Centralizado de Proyectos de Grado',
        directorName: 'Dr. Carlos Andrés Gómez',
        members: ['Ana Martínez', 'Luis Felipe Ríos'],
        meetingCount: 5,
        pendingCount: 2,
    },
    {
        id: 2,
        code: 'PG-2026-015',
        title: 'Plataforma de Análisis de Sentimientos para Redes Sociales',
        directorName: 'Dr. Carlos Andrés Gómez',
        members: ['María Fernanda Rincón'],
        meetingCount: 3,
        pendingCount: 1,
    },
    {
        id: 3,
        code: 'PG-2026-008',
        title: 'Dashboard de Indicadores de Gestión Académica',
        directorName: 'Dra. Laura Martínez',
        members: ['Andrés Felipe Torres', 'Diana Carolina Rojas'],
        meetingCount: 4,
        pendingCount: 0,
    },
    {
        id: 4,
        code: 'PG-2026-005',
        title: 'Plataforma E-Learning para Cursos de Programación',
        directorName: 'Dr. Andrés Vega',
        members: ['Juan David Pérez'],
        meetingCount: 2,
        pendingCount: 1,
    },
    {
        id: 5,
        code: 'PG-2026-012',
        title: 'App Móvil para Gestión de Inventarios Hospitalarios',
        directorName: 'Dra. Laura Martínez',
        members: ['Laura Patricia Gómez'],
        meetingCount: 6,
        pendingCount: 0,
    },
];

export const MOCK_BITACORA_MEETINGS: BitacoraMeeting[] = [
    {
        id: 101,
        projectId: 1,
        projectName: 'Sistema Centralizado de Proyectos de Grado',
        projectCode: 'PG-2026-014',
        directorName: 'Dr. Carlos Andrés Gómez',
        members: ['Ana Martínez', 'Luis Felipe Ríos'],
        createdAt: '2026-04-10T09:00:00',
        meetingDate: '2026-04-08T14:00:00',
        topic: 'Revisión de arquitectura del sistema',
        summary: 'Se acordó adoptar una arquitectura en capas con API REST y frontend SPA. Pendiente definir módulo de autenticación.',
        content: 'Durante la sesión se revisó el diagrama de componentes propuesto...',
        signatureStatus: 'pendiente',
    },
    {
        id: 102,
        projectId: 1,
        projectName: 'Sistema Centralizado de Proyectos de Grado',
        projectCode: 'PG-2026-014',
        directorName: 'Dr. Carlos Andrés Gómez',
        members: ['Ana Martínez', 'Luis Felipe Ríos'],
        createdAt: '2026-04-05T10:30:00',
        meetingDate: '2026-04-03T16:00:00',
        topic: 'Planificación del sprint 2',
        summary: 'Se priorizaron entregas de bitácoras y módulo de evaluación. Próxima reunión en dos semanas.',
        content: 'Revisión del backlog y asignación de tareas...',
        signatureStatus: 'firmado',
        signedAt: '05/04/2026',
        signedTime: '11:15',
    },
    {
        id: 103,
        projectId: 1,
        projectName: 'Sistema Centralizado de Proyectos de Grado',
        projectCode: 'PG-2026-014',
        directorName: 'Dr. Carlos Andrés Gómez',
        members: ['Ana Martínez', 'Luis Felipe Ríos'],
        createdAt: '2026-03-28T08:00:00',
        meetingDate: '2026-03-25T10:00:00',
        topic: 'Avance del anteproyecto',
        summary: 'El documento requiere ampliar marco teórico y corregir citas bibliográficas.',
        content: 'Observaciones sobre estructura del anteproyecto...',
        signatureStatus: 'rechazado',
        rejectionComment: 'El marco teórico no incluye referencias recientes (últimos 5 años). Corregir y reenviar.',
    },
    {
        id: 201,
        projectId: 2,
        projectName: 'Plataforma de Análisis de Sentimientos para Redes Sociales',
        projectCode: 'PG-2026-015',
        directorName: 'Dr. Carlos Andrés Gómez',
        members: ['María Fernanda Rincón'],
        createdAt: '2026-04-12T11:00:00',
        meetingDate: '2026-04-11T09:00:00',
        topic: 'Selección del modelo NLP',
        summary: 'Se compararon BERT vs modelos ligeros para despliegue en campus.',
        content: 'Análisis de alternativas técnicas...',
        signatureStatus: 'pendiente',
    },
    {
        id: 301,
        projectId: 3,
        projectName: 'Dashboard de Indicadores de Gestión Académica',
        projectCode: 'PG-2026-008',
        directorName: 'Dra. Laura Martínez',
        members: ['Andrés Felipe Torres', 'Diana Carolina Rojas'],
        createdAt: '2026-04-01T14:00:00',
        meetingDate: '2026-03-30T11:00:00',
        topic: 'Validación de indicadores KPI',
        summary: 'Indicadores aprobados por coordinación académica.',
        content: 'Revisión de métricas propuestas...',
        signatureStatus: 'firmado',
        signedAt: '01/04/2026',
        signedTime: '14:32',
    },
];

const DIRECTOR_NAME = 'Dr. Carlos Andrés Gómez';

export function getDirectorProjects(): BitacoraProject[] {
    return MOCK_BITACORA_PROJECTS.filter((p) => p.directorName === DIRECTOR_NAME);
}

export function getAllProjects(): BitacoraProject[] {
    return MOCK_BITACORA_PROJECTS;
}

export function getProjectById(id: number): BitacoraProject | undefined {
    return MOCK_BITACORA_PROJECTS.find((p) => p.id === id);
}

export function getMeetingsByProject(projectId: number): BitacoraMeeting[] {
    return MOCK_BITACORA_MEETINGS.filter((m) => m.projectId === projectId);
}

export function getMeetingById(id: number): BitacoraMeeting | undefined {
    return MOCK_BITACORA_MEETINGS.find((m) => m.id === id);
}

export function formatMeetingDate(iso: string): string {
    return new Date(iso).toLocaleDateString('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

export function formatCreatedAt(iso: string): string {
    return new Date(iso).toLocaleString('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export const signatureStatusConfig: Record<
    SignatureStatus,
    { label: string; variant: 'warning' | 'success' | 'error' }
> = {
    pendiente: { label: 'Pendiente', variant: 'warning' },
    firmado: { label: 'Firmado', variant: 'success' },
    rechazado: { label: 'Rechazado', variant: 'error' },
};
