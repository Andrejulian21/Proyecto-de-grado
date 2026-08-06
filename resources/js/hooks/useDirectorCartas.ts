import { useEffect, useReducer, useCallback, useState } from 'react';
import { apiFetch } from '@/lib/utils';

/* ── Types (contracto GET /api/director/cartas/proyectos) ── */

export interface EstudianteCartas {
    id: number;
    name: string;
    codigo_estudiante: string | null;
    warnings: string[];
}

export interface ProyectoCartas {
    id: number;
    code: string;
    title: string;
    cartas_habilitadas: boolean;
    cierre_efectivo: string | null;
    estudiantes: EstudianteCartas[];
}

export type TipoCarta = 'aval' | 'jurados';

interface State {
    data: ProyectoCartas[];
    loading: boolean;
    error: string | null;
}

type Action =
    | { type: 'FETCH_START' }
    | { type: 'FETCH_SUCCESS'; payload: ProyectoCartas[] }
    | { type: 'FETCH_ERROR'; payload: string };

function reducer(state: State, action: Action): State {
    switch (action.type) {
        case 'FETCH_START':
            return { ...state, loading: true, error: null };
        case 'FETCH_SUCCESS':
            return { data: action.payload, loading: false, error: null };
        case 'FETCH_ERROR':
            return { ...state, loading: false, error: action.payload };
        default:
            return state;
    }
}

/**
 * Lista los proyectos del director con habilitación de cartas (RF-CA-01).
 * Consume `GET /api/director/cartas/proyectos`.
 */
export function useDirectorCartas() {
    const [state, dispatch] = useReducer(reducer, {
        data: [],
        loading: true,
        error: null,
    });
    const [fetchId, setFetchId] = useState(0);

    useEffect(() => {
        let cancelled = false;

        async function fetchData() {
            dispatch({ type: 'FETCH_START' });

            try {
                const res = await apiFetch('/api/director/cartas/proyectos');

                if (!res.ok) {
                    const body = await res.json().catch(() => null);
                    throw new Error(body?.message ?? `Error ${res.status}: ${res.statusText}`);
                }

                const json = await res.json();
                if (!cancelled) {
                    dispatch({ type: 'FETCH_SUCCESS', payload: json.data ?? json });
                }
            } catch (err) {
                if (!cancelled) {
                    const message = err instanceof Error ? err.message : 'Error desconocido';
                    dispatch({ type: 'FETCH_ERROR', payload: message });
                }
            }
        }

        fetchData();

        return () => {
            cancelled = true;
        };
    }, [fetchId]);

    const refetch = useCallback(() => {
        setFetchId((id) => id + 1);
    }, []);

    return {
        data: state.data,
        loading: state.loading,
        error: state.error,
        refetch,
    };
}

/**
 * Extrae el nombre de archivo del header `Content-Disposition` enviado por
 * el backend (D4). Prioriza `filename*` (UTF-8) y cae a `filename`.
 */
export function parseFilenameFromDisposition(disposition: string | null): string | null {
    if (!disposition) return null;

    const starMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
    if (starMatch) {
        try {
            return decodeURIComponent(starMatch[1]);
        } catch {
            return starMatch[1];
        }
    }

    const plainMatch = disposition.match(/filename="?([^";]+)"?/i);
    return plainMatch ? plainMatch[1].trim() : null;
}

/**
 * Descarga una carta DOCX individual (RF-CA-02/03/04, D4).
 *
 * GET /api/director/cartas/{proyecto}/estudiante/{user}/{aval-sustentacion|carta-jurados}
 * Devuelve un StreamedResponse; el nombre real viene en Content-Disposition.
 */
export async function descargarCarta(
    proyectoId: number,
    estudianteId: number,
    tipo: TipoCarta,
    fallbackName: string,
): Promise<void> {
    const ruta = tipo === 'aval' ? 'aval-sustentacion' : 'carta-jurados';
    const res = await apiFetch(`/api/director/cartas/${proyectoId}/estudiante/${estudianteId}/${ruta}`);

    if (!res.ok) {
        const body = await res.json().catch(() => null);
        throw new Error(body?.error ?? `Error ${res.status}: ${res.statusText}`);
    }

    const blob = await res.blob();
    const filename = parseFilenameFromDisposition(res.headers.get('Content-Disposition')) ?? fallbackName;

    const objectUrl = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(objectUrl);
}
