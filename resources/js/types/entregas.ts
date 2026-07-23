export type VersionReviewStatus = 'sin_revisar' | 'aprobada' | 'necesita_ajustes';

/** Cumplimiento de plazo — preasignado en mock (ENT-TL-TYPES). No calcular en UI. */
export type DeliveryTimelineStatus = 'not_delivered' | 'on_time' | 'late' | 'overdue';

export interface DeliveryVersionObservation {
    text: string | null;
    reviewedAt: string | null;
    reviewStatus: VersionReviewStatus;
}

export interface DeliveryVersionMock {
    id: number;
    versionNumber: number;
    fileName: string;
    uploadedAt: string;
    observation: DeliveryVersionObservation;
}

export interface EntregaMock {
    id: number;
    title: string;
    phase: string;
    status: string;
    description: string | null;
    dueDate: string | null;
    startDate: string | null;
    startTime: string | null;
    horaMaxima: string | null;
    acceptanceCriteria: string | null;
    project: {
        id: number;
        code: string;
        title: string;
        directorName?: string;
        members?: string[];
    };
    versiones: DeliveryVersionMock[];
    timelineStatus: DeliveryTimelineStatus;
}

export const REVIEW_STATUS_LABELS: Record<VersionReviewStatus, string> = {
    sin_revisar: 'Sin revisar',
    aprobada: 'Aprobada',
    necesita_ajustes: 'Necesita ajustes',
};

export const REVIEW_STATUS_VARIANTS: Record<
    VersionReviewStatus,
    'success' | 'warning' | 'info' | 'inactivo'
> = {
    sin_revisar: 'warning',
    aprobada: 'success',
    necesita_ajustes: 'warning',
};

export function hasVersionObservation(version: DeliveryVersionMock): boolean {
    return Boolean(version.observation.text?.trim());
}

export function sortVersionsDesc(versions: DeliveryVersionMock[]): DeliveryVersionMock[] {
    return [...versions].sort((a, b) => b.versionNumber - a.versionNumber);
}

export function sortVersionsAsc(versions: DeliveryVersionMock[]): DeliveryVersionMock[] {
    return [...versions].sort((a, b) => a.versionNumber - b.versionNumber);
}

export const TIMELINE_STATUS_LABELS: Record<DeliveryTimelineStatus, string> = {
    not_delivered: 'Aún no entregado',
    on_time: 'Entregado en horario',
    late: 'Entregado fuera del plazo',
    overdue: 'Atrasado / No entregado',
};

export const TIMELINE_STATUS_VARIANTS: Record<
    DeliveryTimelineStatus,
    'inactivo' | 'success' | 'warning' | 'error'
> = {
    not_delivered: 'inactivo',
    on_time: 'success',
    late: 'warning',
    overdue: 'error',
};

/** Lower = higher priority in Director lists (ENT-TL07) */
export const TIMELINE_STATUS_SORT_ORDER: Record<DeliveryTimelineStatus, number> = {
    overdue: 0,
    late: 1,
    not_delivered: 2,
    on_time: 3,
};

export function sortByTimelineStatus<T extends { timelineStatus: DeliveryTimelineStatus }>(
    items: T[],
): T[] {
    return [...items].sort(
        (a, b) =>
            TIMELINE_STATUS_SORT_ORDER[a.timelineStatus] -
            TIMELINE_STATUS_SORT_ORDER[b.timelineStatus],
    );
}
