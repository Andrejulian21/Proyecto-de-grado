import { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '@/lib/utils';
import { FRONTEND_VALIDATION_MODE, mockDelay } from '@/mocks/validationMode';
import { getMockEvaluaciones } from '@/mocks/evaluacionesMock';

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
    return raw.map((item: Record<string, unknown>) => {
        const puntuaciones = (item.puntuaciones as number[]) ?? (item.puntajes as number[]) ?? [];
        return {
            id: item.id as number,
            proyecto_id: item.proyecto_id as number,
            proyecto_nombre: (item.proyecto_nombre ?? item.proyecto ?? '') as string,
            proyecto_codigo: (item.proyecto_codigo ?? item.codigo ?? '') as string,
            estudiantes: (item.estudiantes ?? []) as string[],
            director: (item.director ?? '') as string,
            fase: (item.fase ?? '') as string,
            evaluadores: (item.evaluadores ?? []) as string[],
            nota_promedio:
                (item.nota_promedio as number | null) ?? calcularPromedio(puntuaciones),
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
            if (FRONTEND_VALIDATION_MODE) {
                await mockDelay();
                const raw = getMockEvaluaciones().map((e) => ({
                    id: e.id,
                    proyecto_id: e.proyecto_id,
                    proyecto_nombre: e.proyecto_title,
                    proyecto_codigo: e.proyecto_code,
                    estudiantes: e.estudiantes.split(', '),
                    director: 'Dr. Carlos Andrés Gómez',
                    fase: e.fase,
                    evaluadores: e.evaluador_name ? [e.evaluador_name] : [],
                    nota_promedio: e.nota ?? null,
                }));
                setData(normalizarResultados(raw));
                return;
            }
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
