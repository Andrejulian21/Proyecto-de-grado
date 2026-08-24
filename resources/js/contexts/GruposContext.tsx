import { createContext, useState, useEffect, useCallback, type ReactNode } from 'react';
import { apiFetch } from '@/lib/utils';
import type { Grupo } from '@/hooks/useGrupos';

export interface GruposContextValue {
    data: Grupo[];
    loading: boolean;
    error: string | null;
    refetch: () => Promise<void>;
    crear: (payload: { name: string; start_date: string; end_date: string; is_active?: boolean }) => Promise<Grupo>;
    actualizar: (id: number, payload: Record<string, unknown>) => Promise<Grupo>;
    eliminar: (id: number) => Promise<void>;
}

const GruposContext = createContext<GruposContextValue | null>(null);

export function GruposProvider({ children }: { children: ReactNode }) {
    const [data, setData] = useState<Grupo[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchData = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await apiFetch('/api/admin/semestres');
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

    const crear = useCallback(async (payload: { name: string; start_date: string; end_date: string; is_active?: boolean }) => {
        try {
            const res = await apiFetch('/api/admin/semestres', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                // Extract the first validation error message when Laravel returns 422
                const firstError = body?.errors
                    ? Object.values(body.errors).flat()[0]
                    : null;
                throw new Error(firstError ?? body?.message ?? `Error ${res.status}`);
            }
            const json = await res.json();
            const nuevo: Grupo = json.data ?? json;
            setData((prev) => [...prev, nuevo]);
            return nuevo;
        } catch (err) {
            throw err;
        }
    }, []);

    const actualizar = useCallback(async (id: number, payload: Record<string, unknown>) => {
        try {
            const res = await apiFetch(`/api/admin/semestres/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                const firstError = body?.errors
                    ? Object.values(body.errors).flat()[0]
                    : null;
                throw new Error(firstError ?? body?.message ?? `Error ${res.status}`);
            }
            const json = await res.json();
            const updated: Grupo = json.data ?? json;
            setData((prev) => prev.map((g) => (g.id === updated.id ? updated : g)));
            return updated;
        } catch (err) {
            throw err;
        }
    }, []);

    const eliminar = useCallback(async (id: number) => {
        try {
            const res = await apiFetch(`/api/admin/semestres/${id}`, {
                method: 'DELETE',
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.error ?? body?.message ?? `Error ${res.status}`);
            }
            setData((prev) => prev.filter((g) => g.id !== id));
        } catch (err) {
            throw err;
        }
    }, []);

    return (
        <GruposContext.Provider value={{ data, loading, error, refetch: fetchData, crear, actualizar, eliminar }}>
            {children}
        </GruposContext.Provider>
    );
}

export { GruposContext };