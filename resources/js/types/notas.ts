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

/* ── Coordinator-specific types ──────────────────────────────────────── */

export interface NotaEntregaPonderada {
    titulo: string;
    nota: number | null;
    /** grade_percentage — weight assigned to this delivery */
    peso: number;
}

export interface ProyectoNotasCoordinador {
    id: number;
    codigo: string;
    titulo: string;
    director: string | null;
    estudiantes: string;
    semestre_id: number;
    tipo: 'pg1' | 'pg2';
    /* PG1 */
    notas_entregas_anteproyecto: NotaEntregaPonderada[];
    nota_entregas_ponderada: number | null;
    nota_evaluadores_anteproyecto: number | null;
    nota_presentacion_anteproyecto: number | null;
    nota_final_pg1: number | null;
    /* PG2 */
    notas_entregas_desarrollo: NotaEntregaPonderada[];
    nota_entregas_desarrollo_ponderada: number | null;
    nota_evaluadores_presentacion_final: number | null;
    nota_director_presentacion_final: number | null;
    nota_final_pg2: number | null;
    /* Common */
    pesos: { entregas: number; evaluadores: number; presentacion: number };
}

export interface PesoConfig {
    semestre_id: number;
    tipo: 'pg1' | 'pg2';
    peso_entregas: number;
    peso_evaluadores: number;
    peso_presentacion: number;
}

export interface ConsultaNotasCoordinadorResponse {
    semestres: SemestreOpcionNotas[];
    proyectos: ProyectoNotasCoordinador[];
    pesos: PesoConfig[];
}
