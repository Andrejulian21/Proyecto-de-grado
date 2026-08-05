export interface ArchivoRequeridoConfig {
    id: string;
    nombre: string;
    versionamiento: boolean;
    /**
     * Only the main project file (slug `documento-proyecto`) may be
     * AI-analyzable (RF-ENT-02). Backend rejects `true` on any other file.
     */
    analizable_ia?: boolean;
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

/**
 * Weight/grade fields added by PR1/PR2 (RF-ENT-03, RF-NOT-01).
 * `grade_percentage` is a 0-100 weight; `director_grade` is a 0-5 note.
 */
export interface EntregaPeso {
    grade_percentage?: number | null;
    director_grade?: number | null;
}

/** Archivo requerido as persisted inside `entregas.archivos_requeridos` JSON. */
export interface ArchivoRequeridoPersistido {
    slug?: string;
    id?: string;
    nombre: string;
    versionamiento: boolean;
    analizable_ia?: boolean;
}

/** Project context shared by evaluator cards and detail (RF-EVA-01/02). */
export interface ProyectoEvaluador {
    id: number;
    codigo: string;
    titulo: string;
    estudiantes: { id: number; name: string }[];
    director: { id: number; name: string; email: string } | null;
}

/** Assignment card returned by GET /api/evaluador/mis-asignaciones (RF-EVA-01). */
export interface AsignacionEvaluador {
    id: number;
    proyecto: ProyectoEvaluador | null;
    fase: string;
    evaluado: boolean;
    created_at: string | null;
    /**
     * Optional — the list endpoint does NOT include the note today; it is
     * shown on evaluated cards only when the backend provides it. The
     * definitive note is always available in the detail (RF-EVA-02/04).
     */
    nota?: number | null;
}

/** Evaluation sent by the evaluador (RF-EVA-02/03) — immutable. */
export interface Evaluacion {
    nota: number;
    observaciones: string;
    evaluated_at: string | null;
}

/** Latest document version inside an assignment detail (RF-EVA-02). */
export interface VersionDocumentoResumen {
    version_number: number;
    file_path: string;
    original_name: string;
    director_notes: string | null;
    uploaded_at: string | null;
}

/** Detail returned by GET /api/evaluador/asignaciones/{id}/detalle (RF-EVA-02, RF-NOT-04). */
export interface DetalleAsignacionEvaluador {
    proyecto: ProyectoEvaluador | null;
    fase: string;
    entrega: {
        id: number;
        archivos_requeridos: ArchivoRequeridoPersistido[];
        due_date: string | null;
        director_grade: number | null;
        versiones_documento: VersionDocumentoResumen[];
    } | null;
    evaluacion: Evaluacion | null;
}
