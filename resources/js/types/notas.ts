export interface SemestreOpcionNotas {
    id: number;
    nombre: string;
    is_active: boolean;
}

export interface EntregaNota {
    id: number;
    titulo: string;
    fase: string;
    /** Null when the project delivery has no director_grade. Never coerce to 0. */
    nota: number | null;
    estado_nota: 'calificada' | 'sin_calificar';
}

export interface ProyectoNotas {
    id: number;
    codigo: string;
    titulo: string;
    director: string | null;
    estudiantes: string;
    semestre_id: number;
    entregas: EntregaNota[];
    /** Evaluator-own grade; null for other roles or when not submitted. */
    nota_evaluador: number | null;
}

export interface ConsultaNotasResponse {
    semestres: SemestreOpcionNotas[];
    proyectos: ProyectoNotas[];
}
