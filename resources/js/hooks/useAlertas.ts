import { useCallback, useEffect, useState } from 'react';
import { apiFetch } from '@/lib/utils';

/**
 * Alerts are owned by the backend. `App\Services\Alertas\AlertaGenerator`
 * upserts rows by `clave` on every read and deletes the ones that stopped
 * applying, so this hook never derives alerts from raw bitacora/entrega
 * payloads — it only renders what the server decided.
 */

export type TipoAlerta =
    | 'bitacora_sin_firmar'
    | 'entrega_vencida'
    | 'firmas_sospechosas';

export type SeveridadAlerta = 'alta' | 'media';

/** Values accepted by the `?revisadas=` query parameter. */
export type FiltroRevisadas = '0' | '1' | 'todas';

export interface AlertaDatosBitacoraSinFirmar {
    bitacora_id: number;
    proyecto_id: number;
    creada_at: string;
    minutos_sin_firmar: number;
}

export interface AlertaDatosEntregaVencida {
    entrega_id: number;
    entrega_proyecto_id: number;
    proyecto_id: number;
    titulo_entrega: string;
    due_date: string | null;
    hora_maxima: string | null;
}

export interface AlertaDatosFirmasSospechosas {
    director_id: number;
    ventana_minutos: number;
    firmas: number;
    bitacora_ids: number[];
    ventana_inicio: string;
    ventana_fin: string;
}

interface AlertaBase<Tipo extends TipoAlerta, Datos> {
    id: number;
    /** Stable identity used by the generator to upsert instead of duplicate. */
    clave: string;
    tipo: Tipo;
    mensaje: string;
    /** `null` for director-scoped alerts (`firmas_sospechosas`). */
    proyecto_id: number | null;
    severidad: SeveridadAlerta;
    datos: Datos;
    reviewed_at: string | null;
    reviewed_by: number | null;
    created_at: string;
    updated_at: string;
}

export type AlertaBitacoraSinFirmar = AlertaBase<
    'bitacora_sin_firmar',
    AlertaDatosBitacoraSinFirmar
>;
export type AlertaEntregaVencida = AlertaBase<
    'entrega_vencida',
    AlertaDatosEntregaVencida
>;
export type AlertaFirmasSospechosas = AlertaBase<
    'firmas_sospechosas',
    AlertaDatosFirmasSospechosas
>;

/**
 * Modelled as a discriminated union on `tipo` so `datos` narrows alongside the
 * alert kind instead of being an untyped bag.
 */
export type Alerta =
    | AlertaBitacoraSinFirmar
    | AlertaEntregaVencida
    | AlertaFirmasSospechosas;

/** True once a human marked the alert as reviewed. */
export function estaRevisada(alerta: Alerta): boolean {
    return alerta.reviewed_at !== null;
}

const FORBIDDEN_MESSAGE =
    'No tiene permisos para gestionar las alertas.';
const LOAD_FAILED_MESSAGE =
    'No se pudieron cargar las alertas. Intente nuevamente.';
const REVIEW_FAILED_MESSAGE =
    'No se pudo marcar la alerta como revisada. Intente nuevamente.';

interface UseAlertasResult {
    data: Alerta[];
    loading: boolean;
    error: string | null;
    refetch: () => void;
    /** Marks one alert as reviewed, then reconciles the list. */
    revisar: (alerta: Alerta) => Promise<void>;
    /** Id of the alert currently being reviewed, or `null`. */
    revisandoId: number | null;
}

export function useAlertas(
    filtroRevisadas: FiltroRevisadas = '0',
): UseAlertasResult {
    const [data, setData] = useState<Alerta[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [revisandoId, setRevisandoId] = useState<number | null>(null);

    const fetchAlertas = useCallback(
        async (options?: { silent?: boolean }) => {
            if (!options?.silent) setLoading(true);
            setError(null);

            try {
                const res = await apiFetch(
                    `/api/admin/alertas?revisadas=${filtroRevisadas}`,
                );

                if (!res.ok) {
                    setError(
                        res.status === 403
                            ? FORBIDDEN_MESSAGE
                            : LOAD_FAILED_MESSAGE,
                    );
                    return;
                }

                const body = (await res.json()) as { data?: unknown };
                setData(
                    Array.isArray(body.data)
                        ? (body.data as Alerta[])
                        : [],
                );
            } catch {
                setError(LOAD_FAILED_MESSAGE);
            } finally {
                setLoading(false);
            }
        },
        [filtroRevisadas],
    );

    useEffect(() => {
        void fetchAlertas();
    }, [fetchAlertas]);

    const refetch = useCallback(() => {
        void fetchAlertas();
    }, [fetchAlertas]);

    const revisar = useCallback(
        async (alerta: Alerta) => {
            setRevisandoId(alerta.id);

            try {
                const res = await apiFetch(
                    `/api/admin/alertas/${alerta.id}/revisar`,
                    { method: 'PATCH' },
                );

                if (!res.ok) {
                    setError(
                        res.status === 403
                            ? FORBIDDEN_MESSAGE
                            : REVIEW_FAILED_MESSAGE,
                    );
                    return;
                }

                // Silent refetch on purpose: the alert drops out of the pending
                // list, and a loading skeleton here would blank the whole panel
                // on every dismissal.
                await fetchAlertas({ silent: true });
            } catch {
                setError(REVIEW_FAILED_MESSAGE);
            } finally {
                setRevisandoId(null);
            }
        },
        [fetchAlertas],
    );

    return {
        data,
        loading,
        error,
        refetch,
        revisar,
        revisandoId,
    };
}
