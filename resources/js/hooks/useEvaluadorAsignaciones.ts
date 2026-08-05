import { useEffect, useState, useCallback } from 'react';
import { apiFetch } from '@/lib/utils';
import { extraerMensajeError } from '@/hooks/useEntregas';
import type { AsignacionEvaluador, DetalleAsignacionEvaluador } from '@/types/entregas';

/**
 * Data access for the evaluador assignment flow (PR 3 endpoints):
 *  - GET  /api/evaluador/mis-asignaciones            (RF-EVA-01)
 *  - GET  /api/evaluador/asignaciones/{id}/detalle   (RF-EVA-02)
 *  - POST /api/evaluador/asignaciones/{id}/evaluar   (RF-EVA-03)
 *
 * Evaluations are immutable: the POST answers 409 when the assignment was
 * already evaluated; the hook surfaces that as a thrown Error with the
 * Spanish message from the API.
 */
export function useEvaluadorAsignaciones() {
    const [data, setData] = useState<AsignacionEvaluador[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const refetch = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await apiFetch('/api/evaluador/mis-asignaciones');
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
    }, []);

    useEffect(() => {
        refetch();
    }, [refetch]);

    const obtenerDetalle = useCallback(
        async (id: number): Promise<DetalleAsignacionEvaluador> => {
            const res = await apiFetch(`/api/evaluador/asignaciones/${id}/detalle`);
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(extraerMensajeError(body, res.status));
            }
            const json = await res.json();
            return (json.data ?? json) as DetalleAsignacionEvaluador;
        },
        [],
    );

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
