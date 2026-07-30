import { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '@/lib/utils';

/* ------------------------------------------------------------------ */
/*  Data types                                                         */
/* ------------------------------------------------------------------ */

export interface EntregaItem {
    id: number;
    nombre: string;
    estado: 'entregado' | 'pendiente' | 'no_entrego';
}

export interface FaseEntregas {
    fase: string;
    key: string;
    entregas: EntregaItem[];
}

export interface ObservacionItem {
    fase: string;
    contenido: string;
}

export interface ProyectoSeguimiento {
    id: number;
    estudiantes: string;
    proyecto_nombre: string;
    proyecto_codigo: string;
    director: string;
    fases: FaseEntregas[];
    bitacoras_grupo_a: number;
    bitacoras_grupo_b: number;
    observaciones: ObservacionItem[];
}

interface SeguimientoResponse {
    semestre: { id: number; nombre: string };
    proyectos: ProyectoSeguimiento[];
}

/* ------------------------------------------------------------------ */
/*  Hook                                                               */
/* ------------------------------------------------------------------ */

interface UseSeguimientoSemestreResult {
    data: SeguimientoResponse | null;
    loading: boolean;
    error: string | null;
    refetch: () => void;
}

export function useSeguimientoSemestre(
    semestreId: number | null,
): UseSeguimientoSemestreResult {
    const [data, setData] = useState<SeguimientoResponse | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchData = useCallback(async () => {
        if (!semestreId) {
            setData(null);
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const res = await apiFetch(
                `/api/admin/seguimiento/semestre/${semestreId}`,
            );
            if (!res.ok) {
                const text = await res.text().catch(() => '');
                throw new Error(text || `Error ${res.status}: al cargar seguimiento`);
            }
            const json = await res.json();
            setData(json.data ?? json);
        } catch (err) {
            setError(
                err instanceof Error ? err.message : 'Error desconocido',
            );
        } finally {
            setLoading(false);
        }
    }, [semestreId]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    return { data, loading, error, refetch: fetchData };
}
