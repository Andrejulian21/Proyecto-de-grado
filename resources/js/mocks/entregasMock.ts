import type {
    DeliveryTimelineStatus,
    DeliveryVersionMock,
    DeliveryVersionObservation,
    EntregaMock,
    VersionReviewStatus,
} from '@/types/entregas';
import { sortByTimelineStatus } from '@/types/entregas';
import type { DeliverySupervisionItem } from '@/components/entregas/DeliverySupervisionRow';

const PROJECT = {
    id: 1,
    code: 'PG-2026-014',
    title: 'Sistema Centralizado de Proyectos de Grado',
    directorName: 'Dr. Carlos Andrés Gómez',
    members: ['Ana Martínez', 'Luis Felipe Ríos'],
};

function obs(
    text: string | null,
    reviewStatus: VersionReviewStatus,
    reviewedAt: string | null = null,
): DeliveryVersionObservation {
    return { text, reviewedAt, reviewStatus };
}

export const MOCK_ENTREGAS: EntregaMock[] = [
    {
        id: 1,
        title: 'Documento de Anteproyecto',
        phase: 'anteproyecto',
        status: 'revisada',
        description:
            'Entrega del documento de anteproyecto con marco teórico, objetivos, metodología y cronograma inicial.',
        dueDate: '2026-03-15T23:59:00',
        startDate: '2026-02-01T08:00:00',
        startTime: '08:00',
        horaMaxima: '23:59',
        acceptanceCriteria:
            'PDF o DOCX. Mínimo 15 páginas. Incluir referencias bibliográficas en formato APA.',
        project: PROJECT,
        versiones: [
            {
                id: 101,
                versionNumber: 2,
                fileName: 'anteproyecto_v2.pdf',
                uploadedAt: '2026-03-10T14:30:00',
                observation: obs(
                    'Mejoró el cronograma, pero aún falta detallar los entregables por sprint en la sección 4.2.',
                    'necesita_ajustes',
                    '2026-03-12T09:15:00',
                ),
            },
            {
                id: 100,
                versionNumber: 1,
                fileName: 'anteproyecto_v1.pdf',
                uploadedAt: '2026-03-01T09:15:00',
                observation: obs(
                    'Falta detallar el cronograma por sprints. El marco teórico está bien estructurado.',
                    'necesita_ajustes',
                    '2026-03-03T11:00:00',
                ),
            },
        ],
        timelineStatus: 'on_time',
    },
    {
        id: 2,
        title: 'Presentación Anteproyecto',
        phase: 'presentacion_anteproyecto',
        status: 'solicitada',
        description: 'Diapositivas de presentación del anteproyecto ante el comité evaluador.',
        dueDate: '2026-08-15T17:00:00',
        startDate: '2026-07-01T08:00:00',
        startTime: '08:00',
        horaMaxima: '17:00',
        acceptanceCriteria: 'Formato PPT o PDF. Máximo 20 diapositivas.',
        project: PROJECT,
        versiones: [],
        timelineStatus: 'not_delivered',
    },
    {
        id: 3,
        title: 'Informe de Avance — Desarrollo',
        phase: 'desarrollo',
        status: 'aprobada',
        description: 'Informe parcial del avance en la fase de desarrollo del sistema.',
        dueDate: '2026-06-30T23:59:00',
        startDate: null,
        startTime: null,
        horaMaxima: null,
        acceptanceCriteria: null,
        project: PROJECT,
        versiones: [
            {
                id: 302,
                versionNumber: 2,
                fileName: 'informe_avance_v2.pdf',
                uploadedAt: '2026-06-25T10:00:00',
                observation: obs(
                    'Excelente avance. La documentación de arquitectura cumple con los criterios establecidos.',
                    'aprobada',
                    '2026-06-28T14:20:00',
                ),
            },
            {
                id: 301,
                versionNumber: 1,
                fileName: 'informe_avance_v1.pdf',
                uploadedAt: '2026-06-10T18:30:00',
                observation: obs(
                    'Incluir diagrama de despliegue y matriz de trazabilidad requisitos–casos de uso.',
                    'necesita_ajustes',
                    '2026-06-12T08:45:00',
                ),
            },
        ],
        timelineStatus: 'late',
    },
    {
        id: 4,
        title: 'Informe Final',
        phase: 'presentacion_final',
        status: 'solicitada',
        description: 'Entrega final del proyecto de grado.',
        dueDate: '2026-06-30T23:59:00',
        startDate: '2026-05-01T08:00:00',
        startTime: '08:00',
        horaMaxima: '23:59',
        acceptanceCriteria: 'Documento completo según plantilla institucional.',
        project: PROJECT,
        versiones: [],
        timelineStatus: 'overdue',
    },
    {
        id: 5,
        title: 'Entrega complementaria — Desarrollo',
        phase: 'desarrollo',
        status: 'enviada',
        description: 'Anexos técnicos de la fase de desarrollo.',
        dueDate: '2026-07-10T23:59:00',
        startDate: null,
        startTime: null,
        horaMaxima: null,
        acceptanceCriteria: null,
        project: PROJECT,
        versiones: [
            {
                id: 501,
                versionNumber: 1,
                fileName: 'anexos_desarrollo_v1.pdf',
                uploadedAt: '2026-07-18T11:00:00',
                observation: obs(null, 'sin_revisar', null),
            },
        ],
        timelineStatus: 'late',
    },
];

export function getEntregaById(id: number): EntregaMock | undefined {
    const found = MOCK_ENTREGAS.find((e) => e.id === id);
    return found ? structuredClone(found) : undefined;
}

export function updateVersionObservation(
    entrega: EntregaMock,
    versionId: number,
    patch: Partial<DeliveryVersionObservation>,
): EntregaMock {
    return {
        ...entrega,
        versiones: entrega.versiones.map((v) =>
            v.id === versionId
                ? { ...v, observation: { ...v.observation, ...patch } }
                : v,
        ),
    };
}

export function addMockVersion(entrega: EntregaMock, fileName: string): EntregaMock {
    const nextNumber =
        entrega.versiones.reduce((max, v) => Math.max(max, v.versionNumber), 0) + 1;
    const newVersion: DeliveryVersionMock = {
        id: Date.now(),
        versionNumber: nextNumber,
        fileName,
        uploadedAt: new Date().toISOString(),
        observation: obs(null, 'sin_revisar', null),
    };
    return {
        ...entrega,
        status: 'enviada',
        versiones: [...entrega.versiones, newVersion],
    };
}

/** Entregas resumidas para DeliveryAccordion en dashboard mock */
export function getEntregasSummaryForDashboard(): Array<{
    id: number;
    fase: string;
    label: string;
    status: 'approved' | 'pending' | 'locked' | 'enviada';
    deadline: string;
    grade: number | null;
    versions: Array<{
        version: number;
        date: string;
        status: 'approved' | 'pending' | 'rejected';
        fileName: string;
        hasObservation: boolean;
        reviewStatus: VersionReviewStatus;
    }>;
}> {
    const labels: Record<string, string> = {
        anteproyecto: 'Documento de Anteproyecto',
        presentacion_anteproyecto: 'Presentación Anteproyecto',
        desarrollo: 'Informe de Avance',
        presentacion_final: 'Informe Final',
    };

    function mapEntregaStatus(status: string): 'approved' | 'pending' | 'locked' | 'enviada' {
        if (status === 'aprobada' || status === 'aprobado') return 'approved';
        if (status === 'enviada') return 'enviada';
        if (status === 'solicitada' || status === 'creacion') return 'pending';
        return 'pending';
    }

    function mapVersionStatus(rs: VersionReviewStatus): 'approved' | 'pending' | 'rejected' {
        if (rs === 'aprobada') return 'approved';
        if (rs === 'necesita_ajustes') return 'rejected';
        return 'pending';
    }

    return MOCK_ENTREGAS.map((e) => ({
        id: e.id,
        fase: e.phase,
        label: labels[e.phase] ?? e.title,
        status: mapEntregaStatus(e.status),
        deadline: e.dueDate
            ? new Date(e.dueDate).toLocaleDateString('es-CO', {
                  day: 'numeric',
                  month: 'short',
                  year: 'numeric',
              })
            : '—',
        grade: null,
        versions: e.versiones.map((v) => ({
            version: v.versionNumber,
            date: new Date(v.uploadedAt).toLocaleDateString('es-CO', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            }),
            status: mapVersionStatus(v.observation.reviewStatus),
            fileName: v.fileName,
            hasObservation: Boolean(v.observation.text?.trim()),
            reviewStatus: v.observation.reviewStatus,
        })),
    }));
}

export function getEntregasByProjectId(projectId: number): EntregaMock[] {
    return structuredClone(MOCK_ENTREGAS.filter((e) => e.project.id === projectId));
}

export function toSupervisionItem(entrega: EntregaMock): DeliverySupervisionItem {
    return {
        id: entrega.id,
        title: entrega.title,
        dueDate: entrega.dueDate ?? '',
        phase: entrega.phase,
        timelineStatus: entrega.timelineStatus,
        grade: null,
    };
}

export function getSupervisionDeliveries(projectId: number): DeliverySupervisionItem[] {
    return sortByTimelineStatus(getEntregasByProjectId(projectId).map(toSupervisionItem));
}

export interface DirectorDashboardEntregaRow {
    id: number;
    codigo: string;
    proyecto: string;
    estudiante: string;
    title: string;
    due_date: string;
    timelineStatus: DeliveryTimelineStatus;
}

export function getDirectorDashboardEntregas(): DirectorDashboardEntregaRow[] {
    const rows = MOCK_ENTREGAS.map((e) => ({
        id: e.id,
        codigo: e.project.code,
        proyecto: e.project.title,
        estudiante: (e.project.members ?? []).join(', '),
        title: e.title,
        due_date: e.dueDate
            ? new Date(e.dueDate).toLocaleDateString('es-CO', {
                  day: '2-digit',
                  month: 'short',
                  year: 'numeric',
              })
            : '—',
        timelineStatus: e.timelineStatus,
    }));
    return sortByTimelineStatus(rows);
}
