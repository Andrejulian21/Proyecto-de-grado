import { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '@/lib/utils';

/* ── Types ── */

export interface EvaluacionResult {
    id: number;
    proyecto_id: number;
    proyecto_nombre: string;
    proyecto_codigo: string;
    estudiantes: string[];
    director: string;
    fase: string;
    evaluadores: string[];
    nota_promedio: number | null;
    puntuaciones?: number[];
}

interface UseEvaluacionesResult {
    data: EvaluacionResult[];
    loading: boolean;
    error: string | null;
    refetch: () => void;
}

/* ── Helper ── */

function calcularPromedio(puntuaciones?: number[]): number | null {
    if (!puntuaciones || puntuaciones.length === 0) return null;
    const suma = puntuaciones.reduce((acc, p) => acc + p, 0);
    return Math.round((suma / puntuaciones.length) * 100) / 100;
}

function normalizarResultados(raw: unknown[]): EvaluacionResult[] {
    return raw.map((item) => {
        const obj = item as Record<string, unknown>;
        const puntuaciones = (obj.puntuaciones as number[]) ?? (obj.puntajes as number[]) ?? [];
        return {
            id: obj.id as number,
            proyecto_id: obj.proyecto_id as number,
            proyecto_nombre: (obj.proyecto_nombre ?? obj.proyecto ?? '') as string,
            proyecto_codigo: (obj.proyecto_codigo ?? obj.codigo ?? '') as string,
            estudiantes: (obj.estudiantes ?? []) as string[],
            director: (obj.director ?? '') as string,
            fase: (obj.fase ?? '') as string,
            evaluadores: (obj.evaluadores ?? []) as string[],
            nota_promedio:
                (obj.nota_promedio as number | null) ?? calcularPromedio(puntuaciones),
            puntuaciones,
        };
    });
}

/* ── Hook ── */

export function useEvaluaciones(): UseEvaluacionesResult {
    const [data, setData] = useState<EvaluacionResult[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const refetch = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const res = await apiFetch('/api/evaluaciones');
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}: ${res.statusText}`);
            }
            const json = await res.json();
            const raw = Array.isArray(json) ? json : json.data ?? [];
            setData(normalizarResultados(raw));
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            setError(message);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        refetch();
    }, [refetch]);

    return { data, loading, error, refetch };
}
