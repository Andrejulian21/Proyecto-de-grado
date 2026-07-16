import { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '@/lib/utils';

export interface DirectorCupo {
    id: number;
    name: string;
    areas: string[];
    active_projects: number;
    max_capacity: number;
}

export function useCupos() {
    const [data, setData] = useState<DirectorCupo[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchData = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await apiFetch('/api/admin/directores/cupos');
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}: ${res.statusText}`);
            }
            const json = await res.json();
            setData(json.data ?? json);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            setError(message);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const updateCupo = useCallback(async (directorId: number, newMax: number, newAreas?: string): Promise<{ ok: boolean; error?: string }> => {
        const director = data.find((d) => d.id === directorId);
        if (!director) return { ok: false, error: 'Director no encontrado' };

        if (newMax < director.active_projects) {
            return {
                ok: false,
                error: `El cupo máximo (${newMax}) no puede ser menor que los proyectos activos (${director.active_projects}).`,
            };
        }

        try {
            const body: Record<string, unknown> = { max_capacity: newMax };
            if (newAreas !== undefined) {
                body.areas = newAreas;
            }
            const res = await apiFetch(`/api/admin/directores/${directorId}/cupo`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}`);
            }
            const json = await res.json();
            const updated: DirectorCupo = json.data ?? json;
            setData((prev) =>
                prev.map((d) => (d.id === updated.id ? updated : d)),
            );
            return { ok: true };
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            return { ok: false, error: message };
        }
    }, [data]);

    return { data, loading, error, refetch: fetchData, updateCupo };
}
