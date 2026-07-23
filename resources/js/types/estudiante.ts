export interface EntregaData {
    id: number;
    fase: string;
    label: string;
    status: 'approved' | 'pending' | 'locked' | 'enviada';
    deadline: string;
    grade: number | null;
    versions: VersionData[];
}

export interface VersionData {
    version: number;
    date: string;
    status: 'approved' | 'pending' | 'rejected';
    fileName: string;
    /** Whether this version has a director observation (ENT-ACCORDION) */
    hasObservation?: boolean;
    /** Per-version review status label source */
    reviewStatus?: 'sin_revisar' | 'aprobada' | 'necesita_ajustes';
}

export interface PhaseStep {
    id: string;
    label: string;
    status: 'done' | 'current' | 'future';
}
