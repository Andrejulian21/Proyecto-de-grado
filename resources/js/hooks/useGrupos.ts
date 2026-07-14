import { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '@/lib/utils';

export interface Grupo {
    id: number;
    name: string;
    period: string;
    created_at?: string;
}

export function useGrupos() {
    const [data, setData] = useState<Grupo[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchData = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await apiFetch('/api/admin/proyectos/grupos');
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

    const crear = useCallback(async (payload: { name: string; period?: string }) => {
        try {
            const res = await apiFetch('/api/admin/proyectos/grupos', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}`);
            }
            const json = await res.json();
            const nuevo: Grupo = json.data ?? json;
            setData((prev) => [...prev, nuevo]);
            return nuevo;
        } catch (err) {
            throw err;
        }
    }, []);

    return { data, loading, error, refetch: fetchData, crear };
}
