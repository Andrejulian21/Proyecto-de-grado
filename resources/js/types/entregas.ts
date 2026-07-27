export interface ArchivoRequeridoConfig {
    id: string;
    nombre: string;
    versionamiento: boolean;
}

export interface ArchivoRequeridoEstado {
    id: string;
    nombre: string;
    versionamiento: boolean;
    completo: boolean;
    versiones_count: number;
    ultima_version?: {
        id: number;
        version_number: number;
        file_path: string;
        original_name: string;
        uploaded_at: string;
        director_notes?: string | null;
    };
}

export interface EntregaEstadoResponse {
    completos: number;
    pendientes: number;
    archivos: ArchivoRequeridoEstado[];
}
