/* ── Shared types & mock data for project phases, deliveries and bitácoras ──
 * Replace with apiFetch() responses when backend is ready.
 */

export type PhaseId = 'inscripcion' | 'anteproyecto' | 'presentacion' | 'desarrollo' | 'final';
export type PhaseStatus = 'done' | 'current' | 'future';
export type DeliveryStatus = 'approved' | 'pending' | 'locked' | 'rejected';
export type ReviewStatus = 'pending_review' | 'no_pending';
export type BitacoraSignatureStatus = 'signed' | 'pending_student' | 'pending_director';

export interface ProjectPhase {
    id: PhaseId;
    label: string;
    status: PhaseStatus;
}

export interface AssignedProject {
    id: number;
    code: string;
    title: string;
    students: string[];
    currentPhase: PhaseId;
    reviewStatus: ReviewStatus;
    director: string;
    faculty: string;
}

export interface ProjectDelivery {
    id: number;
    projectId: number;
    phaseId: PhaseId;
    label: string;
    status: DeliveryStatus;
    deadline: string;
    description: string;
    grade: number | null;
    versions: DeliveryVersion[];
}

export interface DeliveryVersion {
    id: number;
    version: number;
    date: string;
    time: string;
    fileName: string;
    directorComment: DirectorComment | null;
}

export interface DirectorComment {
    title: string;
    comment: string;
}

export interface BitacoraEntry {
    id: number;
    projectId: number;
    phaseId: PhaseId;
    author: string;
    date: string;
    content: string;
    weeklySummary: string;
    status: BitacoraSignatureStatus;
}

export interface BitacoraProjectSummary {
    id: number;
    code: string;
    title: string;
    students: string[];
    lastBitacoraDate: string;
    signatureStatus: BitacoraSignatureStatus;
}

export interface BitacoraSignature {
    role: 'director' | 'student';
    name: string;
    signed: boolean;
    signedAt: string | null;
}

export interface BitacoraDetail extends BitacoraEntry {
    projectCode: string;
    projectTitle: string;
    students: string[];
    signatures: BitacoraSignature[];
}

/* ── Phases (director view includes Inscripción) ── */

export const DIRECTOR_PHASES: ProjectPhase[] = [
    { id: 'inscripcion', label: 'Inscripción', status: 'done' },
    { id: 'anteproyecto', label: 'Anteproyecto', status: 'done' },
    { id: 'presentacion', label: 'Presentación', status: 'current' },
    { id: 'desarrollo', label: 'Desarrollo', status: 'future' },
    { id: 'final', label: 'Final', status: 'future' },
];

export const STUDENT_PHASES: ProjectPhase[] = DIRECTOR_PHASES.filter((p) => p.id !== 'inscripcion');

/* ── Assigned projects (director dashboard) ── */

export const MOCK_ASSIGNED_PROJECTS: AssignedProject[] = [
    {
        id: 1,
        code: 'PG-2026-014',
        title: 'Sistema predictivo de deserción estudiantil basado en ML',
        students: ['Ana Martínez', 'Luis Rojas'],
        currentPhase: 'presentacion',
        reviewStatus: 'pending_review',
        director: 'Carlos Andrés Gómez',
        faculty: 'Ingeniería de Sistemas',
    },
    {
        id: 2,
        code: 'PG-2026-015',
        title: 'Plataforma de Análisis de Sentimientos para Redes Sociales',
        students: ['María Fernanda Rincón'],
        currentPhase: 'desarrollo',
        reviewStatus: 'no_pending',
        director: 'Carlos Andrés Gómez',
        faculty: 'Ingeniería de Sistemas',
    },
    {
        id: 3,
        code: 'PG-2026-008',
        title: 'Dashboard de Indicadores de Gestión Académica',
        students: ['Andrés Felipe Torres', 'Diana Pardo'],
        currentPhase: 'anteproyecto',
        reviewStatus: 'pending_review',
        director: 'Carlos Andrés Gómez',
        faculty: 'Ingeniería de Sistemas',
    },
];

/* ── Deliveries per project ── */

export const MOCK_DELIVERIES: ProjectDelivery[] = [
    {
        id: 101,
        projectId: 1,
        phaseId: 'inscripcion',
        label: 'Formulario de Inscripción',
        status: 'approved',
        deadline: '01/02/2026',
        description: 'Documento de inscripción del proyecto de grado firmado por el director y estudiantes.',
        grade: 100,
        versions: [{ id: 1, version: 1, date: '28/01/2026', time: '10:00', fileName: 'inscripcion_v1.pdf', directorComment: null }],
    },
    {
        id: 1,
        projectId: 1,
        phaseId: 'anteproyecto',
        label: 'Documento de Anteproyecto',
        status: 'approved',
        deadline: '15/03/2026',
        description: 'Documento completo del anteproyecto con problema, objetivos, metodología y cronograma.',
        grade: 92,
        versions: [
            { id: 1, version: 2, date: '10/03/2026', time: '14:30', fileName: 'anteproyecto_v2.pdf', directorComment: { title: 'Comentario — v2', comment: 'Aprobado con observaciones menores de formato.' } },
            { id: 2, version: 1, date: '01/03/2026', time: '09:15', fileName: 'anteproyecto_v1.pdf', directorComment: { title: 'Comentario — v1', comment: 'Falta detallar el cronograma por sprints.' } },
        ],
    },
    {
        id: 2,
        projectId: 1,
        phaseId: 'presentacion',
        label: 'Presentación Anteproyecto',
        status: 'pending',
        deadline: '10/04/2026',
        description: 'Presentación en diapositivas (mínimo 12) que explique el problema, objetivos y metodología.',
        grade: null,
        versions: [
            { id: 3, version: 1, date: '05/04/2026', time: '16:42', fileName: 'presentacion_v1.pptx', directorComment: null },
        ],
    },
    {
        id: 6,
        projectId: 1,
        phaseId: 'presentacion',
        label: 'Diapositivas de Presentación',
        status: 'pending',
        deadline: '20/04/2026',
        description: 'Diapositivas finales para la sustentación del anteproyecto.',
        grade: null,
        versions: [],
    },
    {
        id: 3,
        projectId: 1,
        phaseId: 'desarrollo',
        label: 'Informe de Avance 1',
        status: 'locked',
        deadline: '15/06/2026',
        description: 'Primer informe de avance del desarrollo del proyecto.',
        grade: null,
        versions: [],
    },
    {
        id: 4,
        projectId: 1,
        phaseId: 'desarrollo',
        label: 'Informe de Avance 2',
        status: 'locked',
        deadline: '15/08/2026',
        description: 'Segundo informe de avance con resultados parciales.',
        grade: null,
        versions: [],
    },
    {
        id: 5,
        projectId: 1,
        phaseId: 'final',
        label: 'Documento Final de Grado',
        status: 'locked',
        deadline: '15/12/2026',
        description: 'Documento final consolidado del proyecto de grado.',
        grade: null,
        versions: [],
    },
];

/* ── Bitácoras ── */

export const MOCK_BITACORA_PROJECTS: BitacoraProjectSummary[] = [
    {
        id: 1,
        code: 'PG-2026-014',
        title: 'Sistema predictivo de deserción estudiantil basado en ML',
        students: ['Ana Martínez', 'Luis Rojas'],
        lastBitacoraDate: '15/04/2026',
        signatureStatus: 'pending_director',
    },
    {
        id: 2,
        code: 'PG-2026-015',
        title: 'Plataforma de Análisis de Sentimientos',
        students: ['María Fernanda Rincón'],
        lastBitacoraDate: '12/04/2026',
        signatureStatus: 'signed',
    },
    {
        id: 3,
        code: 'PG-2026-008',
        title: 'Dashboard de Indicadores de Gestión Académica',
        students: ['Andrés Felipe Torres', 'Diana Pardo'],
        lastBitacoraDate: '10/04/2026',
        signatureStatus: 'pending_student',
    },
];

export const MOCK_BITACORAS: BitacoraEntry[] = [
    { id: 1, projectId: 1, phaseId: 'inscripcion', author: 'Ana Martínez', date: '05/02/2026', content: 'Reunión inicial de inscripción del proyecto. Se definieron roles y compromisos del equipo.', weeklySummary: 'Inicio formal del PG-2026-014.', status: 'signed' },
    { id: 2, projectId: 1, phaseId: 'anteproyecto', author: 'Luis Rojas', date: '10/03/2026', content: 'Revisión del documento de anteproyecto. Se acordaron ajustes en la metodología.', weeklySummary: 'Correcciones al anteproyecto antes de entrega final.', status: 'signed' },
    { id: 3, projectId: 1, phaseId: 'anteproyecto', author: 'Ana Martínez', date: '24/03/2026', content: 'Sesión de trabajo en la redacción del marco teórico y estado del arte.', weeklySummary: 'Avance del 60% en marco teórico.', status: 'signed' },
    { id: 4, projectId: 1, phaseId: 'presentacion', author: 'Luis Rojas', date: '07/04/2026', content: 'Preparación de diapositivas para la presentación del anteproyecto.', weeklySummary: 'Borrador de 10 diapositivas completado.', status: 'pending_student' },
    { id: 5, projectId: 1, phaseId: 'presentacion', author: 'Ana Martínez', date: '15/04/2026', content: 'Revisión de la arquitectura del sistema predictivo. Se validó el pipeline de ML propuesto.', weeklySummary: 'Definición de arquitectura y stack tecnológico.', status: 'pending_director' },
    { id: 6, projectId: 1, phaseId: 'desarrollo', author: 'Ana Martínez', date: '21/04/2026', content: 'Inicio del módulo de recolección de datos académicos.', weeklySummary: 'Primer sprint de desarrollo iniciado.', status: 'pending_student' },
];

export const MOCK_BITACORA_DETAILS: Record<number, BitacoraDetail> = {
    1: {
        ...MOCK_BITACORAS[0],
        projectCode: 'PG-2026-014',
        projectTitle: 'Sistema predictivo de deserción estudiantil basado en ML',
        students: ['Ana Martínez', 'Luis Rojas'],
        signatures: [
            { role: 'director', name: 'Carlos Andrés Gómez', signed: true, signedAt: '05/02/2026 11:00' },
            { role: 'student', name: 'Ana Martínez', signed: true, signedAt: '05/02/2026 10:45' },
            { role: 'student', name: 'Luis Rojas', signed: true, signedAt: '05/02/2026 10:50' },
        ],
    },
    5: {
        ...MOCK_BITACORAS[4],
        projectCode: 'PG-2026-014',
        projectTitle: 'Sistema predictivo de deserción estudiantil basado en ML',
        students: ['Ana Martínez', 'Luis Rojas'],
        signatures: [
            { role: 'director', name: 'Carlos Andrés Gómez', signed: false, signedAt: null },
            { role: 'student', name: 'Ana Martínez', signed: true, signedAt: '15/04/2026 09:30' },
            { role: 'student', name: 'Luis Rojas', signed: true, signedAt: '15/04/2026 09:35' },
        ],
    },
};

/* ── Helpers ── */

export function getProjectById(id: number): AssignedProject | undefined {
    return MOCK_ASSIGNED_PROJECTS.find((p) => p.id === id);
}

export function getDeliveriesByProject(projectId: number): ProjectDelivery[] {
    return MOCK_DELIVERIES.filter((d) => d.projectId === projectId);
}

export function getDeliveryById(id: number): ProjectDelivery | undefined {
    return MOCK_DELIVERIES.find((d) => d.id === id);
}

export function getBitacorasByProject(projectId: number, phaseId?: PhaseId): BitacoraEntry[] {
    return MOCK_BITACORAS.filter(
        (b) => b.projectId === projectId && (!phaseId || b.phaseId === phaseId),
    );
}

export function getBitacoraDetail(id: number): BitacoraDetail | undefined {
    const stored = MOCK_BITACORA_DETAILS[id];
    if (stored) return stored;

    const entry = MOCK_BITACORAS.find((b) => b.id === id);
    if (!entry) return undefined;

    const project = MOCK_ASSIGNED_PROJECTS.find((p) => p.id === entry.projectId);
    return {
        ...entry,
        projectCode: project?.code ?? '',
        projectTitle: project?.title ?? '',
        students: project?.students ?? [],
        signatures: [
            { role: 'director', name: project?.director ?? 'Director', signed: entry.status === 'signed', signedAt: entry.status === 'signed' ? `${entry.date} 12:00` : null },
            ...(project?.students ?? []).map((name) => ({
                role: 'student' as const,
                name,
                signed: entry.status === 'signed' || entry.status === 'pending_director',
                signedAt: entry.status !== 'pending_student' ? `${entry.date} 10:00` : null,
            })),
        ],
    };
}

export function phaseLabel(phaseId: PhaseId): string {
    return DIRECTOR_PHASES.find((p) => p.id === phaseId)?.label ?? phaseId;
}

export function bitacoraStatusLabel(status: BitacoraSignatureStatus): string {
    const map: Record<BitacoraSignatureStatus, string> = {
        signed: 'Firmada',
        pending_student: 'Pendiente estudiante',
        pending_director: 'Pendiente director',
    };
    return map[status];
}

export function bitacoraStatusEmoji(status: BitacoraSignatureStatus): string {
    const map: Record<BitacoraSignatureStatus, string> = {
        signed: '🟢',
        pending_student: '🟡',
        pending_director: '🔴',
    };
    return map[status];
}
