import { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '@/lib/utils';
import { FRONTEND_VALIDATION_MODE, mockDelay } from '@/mocks/validationMode';
import { MOCK_SEMESTRES } from '@/mocks/proyectosMock';

export interface Grupo {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active?: boolean;
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
            if (FRONTEND_VALIDATION_MODE) {
                await mockDelay();
                setData(structuredClone(MOCK_SEMESTRES));
                return;
            }
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
            if (FRONTEND_VALIDATION_MODE) {
                await mockDelay(200);
                const nuevo: Grupo = { id: Date.now(), ...payload, created_at: new Date().toISOString() };
                setData((prev) => [...prev, nuevo]);
                return nuevo;
            }
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
            if (FRONTEND_VALIDATION_MODE) {
                await mockDelay(200);
                let updated!: Grupo;
                setData((prev) =>
                    prev.map((g) => {
                        if (g.id === id) updated = { ...g, ...payload } as Grupo;
                        return g.id === id ? updated : g;
                    }),
                );
                return updated;
            }
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

    return { data, loading, error, refetch: fetchData, crear, actualizar };
}
