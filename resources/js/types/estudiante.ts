export interface EntregaData {
    id: number;
    fase: string;
    title: string;
    status: 'approved' | 'pending' | 'locked' | 'enviada';
    deadline: string;
    startDate?: string;
    grade: number | null;
    versions: VersionData[];
}

export interface VersionData {
    version: number;
    date: string;
    status: 'approved' | 'pending' | 'rejected';
    fileName: string;
    observaciones?: string | null;
}

export interface PhaseStep {
    id: string;
    label: string;
    status: 'done' | 'current' | 'future';
}
