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
    /** Truncated director observation preview for accordion UI */
    observationPreview: string | null;
}

export interface PhaseStep {
    id: string;
    label: string;
    status: 'done' | 'current' | 'future';
}
