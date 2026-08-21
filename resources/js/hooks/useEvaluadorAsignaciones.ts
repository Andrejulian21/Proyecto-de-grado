import { useCallback, useEffect, useState } from 'react';
import { apiFetch } from '@/lib/utils';
import { extraerMensajeError } from '@/hooks/useEntregas';
import type { AsignacionEvaluador, DetalleAsignacionEvaluador } from '@/types/entregas';
import type { EvaluadorCalendarioEvento, EvaluadorDashboardData } from '@/types/evaluador';

export function useEvaluadorAsignaciones(filters?: { q?: string; estado?: 'pendiente' | 'evaluada' | '' }) {
    const [data, setData] = useState<AsignacionEvaluador[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const q = filters?.q ?? '';
    const estado = filters?.estado ?? '';

    const refetch = useCallback(async () => {
        setLoading(true);
        setError(null);
        const params = new URLSearchParams();
        if (q) params.set('q', q);
        if (estado) params.set('estado', estado);
        const suffix = params.toString() ? `?${params.toString()}` : '';
        try {
            const res = await apiFetch(`/api/evaluador/mis-asignaciones${suffix}`);
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(extraerMensajeError(body, res.status));
            }
            const json = await res.json();
            setData((json.data ?? json) as AsignacionEvaluador[]);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Error desconocido');
        } finally {
            setLoading(false);
        }
    }, [q, estado]);

    useEffect(() => {
        void refetch();
    }, [refetch]);

    const obtenerDetalle = useCallback(async (id: number): Promise<DetalleAsignacionEvaluador> => {
        const res = await apiFetch(`/api/evaluador/asignaciones/${id}/detalle`);
        if (!res.ok) {
            const body = await res.json().catch(() => null);
            throw new Error(extraerMensajeError(body, res.status));
        }
        const json = await res.json();
        return (json.data ?? json) as DetalleAsignacionEvaluador;
    }, []);

    const enviarEvaluacion = useCallback(
        async (id: number, payload: { nota: number; observaciones: string }) => {
            const res = await apiFetch(`/api/evaluador/asignaciones/${id}/evaluar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(extraerMensajeError(body, res.status));
            }
            await refetch();
            return res.json();
        },
        [refetch],
    );

    return { data, loading, error, refetch, obtenerDetalle, enviarEvaluacion };
}

export function useEvaluadorDashboard() {
    const [data, setData] = useState<EvaluadorDashboardData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const refetch = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await apiFetch('/api/evaluador/dashboard');
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(extraerMensajeError(body, res.status));
            }
            const json = await res.json();
            setData((json.data ?? json) as EvaluadorDashboardData);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Error desconocido');
            setData(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void refetch();
    }, [refetch]);

    return { data, loading, error, refetch };
}

export function useEvaluadorCalendario() {
    const [data, setData] = useState<EvaluadorCalendarioEvento[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const refetch = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await apiFetch('/api/evaluador/calendario');
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(extraerMensajeError(body, res.status));
            }
            const json = await res.json();
            setData((json.data ?? json) as EvaluadorCalendarioEvento[]);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Error desconocido');
            setData([]);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void refetch();
    }, [refetch]);

    return { data, loading, error, refetch };
}
