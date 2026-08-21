export interface EvaluadorResumen {
    asignadas: number;
    pendientes: number;
    realizadas: number;
    sin_fecha: number;
}

export interface EvaluadorDashboardData {
    evaluador: { id: number; name: string; email: string };
    resumen: EvaluadorResumen;
    proximas: EvaluadorCalendarioEvento[];
}

export interface EvaluadorCalendarioEvento {
    id: number;
    fecha: string | null;
    hora_inicio: string | null;
    hora_fin: string | null;
    fase: string;
    estado: 'pendiente' | 'evaluada';
    proyecto: { id: number; codigo: string; titulo: string } | null;
}
