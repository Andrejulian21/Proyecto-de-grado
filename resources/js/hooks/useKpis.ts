import { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '@/lib/utils';
import { FRONTEND_VALIDATION_MODE, mockDelay } from '@/mocks/validationMode';
import { MOCK_KPIS as MOCK_KPIS_DATA } from '@/mocks/proyectosMock';

export interface KpiResponse {
    proyectos_activos: number;
    en_riesgo: number;
    alertas_sin_revisar: number;
    tasa_cumplimiento: number;
}

interface UseKpisResult {
    data: KpiResponse | null;
    loading: boolean;
    error: string | null;
    refetch: () => void;
}

export function useKpis(): UseKpisResult {
    const [data, setData] = useState<KpiResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchKpis = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            if (FRONTEND_VALIDATION_MODE) {
                await mockDelay();
                setData(MOCK_KPIS_DATA);
                return;
            }
            const res = await apiFetch('/api/admin/proyectos/kpis');

            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}: ${res.statusText}`);
            }

            const json: KpiResponse = await res.json();
            setData({
                proyectos_activos: json.proyectos_activos ?? 0,
                en_riesgo: json.en_riesgo ?? 0,
                alertas_sin_revisar: json.alertas_sin_revisar ?? 0,
                tasa_cumplimiento: json.tasa_cumplimiento ?? 0,
            });
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            setError(message);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchKpis();
    }, [fetchKpis]);

    return { data, loading, error, refetch: fetchKpis };
}
