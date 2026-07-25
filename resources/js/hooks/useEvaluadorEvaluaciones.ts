import { useEffect, useReducer, useCallback, useState } from 'react';
import { apiFetch } from '@/lib/utils';

export interface EvaluadorEstudiante {
    id: number;
    name: string;
}

export interface EvaluadorDirector {
    id: number;
    name: string;
}

export interface EvaluacionAsignadaEvaluador {
    id: number;
    code: string;
    title: string;
    current_phase: string | null;
    status: string | null;
    fase_asignada: string | null;
    fecha: string | null;
    assigned_at: string | null;
    hora_inicio: string | null;
    hora_fin: string | null;
    director: EvaluadorDirector | null;
    estudiantes: EvaluadorEstudiante[];
    semestre: { id: number; name: string; is_active: boolean } | null;
    evaluation_status: 'pending' | 'evaluated';
    rating: number | null;
}

export interface EvaluadorKpis {
    proyectos_asignados: number;
    evaluaciones_pendientes: number;
    evaluaciones_completadas: number;
}

interface State {
    data: EvaluacionAsignadaEvaluador[];
    kpis: EvaluadorKpis | null;
    loading: boolean;
    error: string | null;
}

type Action =
    | { type: 'FETCH_START' }
    | { type: 'FETCH_SUCCESS'; payload: { data: EvaluacionAsignadaEvaluador[]; kpis: EvaluadorKpis } }
    | { type: 'FETCH_ERROR'; payload: string };

function reducer(state: State, action: Action): State {
    switch (action.type) {
        case 'FETCH_START':
            return { ...state, loading: true, error: null };
        case 'FETCH_SUCCESS':
            return {
                data: action.payload.data,
                kpis: action.payload.kpis,
                loading: false,
                error: null,
            };
        case 'FETCH_ERROR':
            return { ...state, loading: false, error: action.payload };
        default:
            return state;
    }
}

export function useEvaluadorEvaluaciones() {
    const [state, dispatch] = useReducer(reducer, {
        data: [],
        kpis: null,
        loading: true,
        error: null,
    });
    const [fetchId, setFetchId] = useState(0);

    useEffect(() => {
        let cancelled = false;

        async function fetchData() {
            dispatch({ type: 'FETCH_START' });

            try {
                const [resList, resKpis] = await Promise.all([
                    apiFetch('/api/evaluador/evaluaciones'),
                    apiFetch('/api/evaluador/kpis'),
                ]);

                if (!resList.ok) {
                    const body = await resList.json().catch(() => null);
                    throw new Error(body?.message ?? `Error ${resList.status}: ${resList.statusText}`);
                }

                if (!resKpis.ok) {
                    const body = await resKpis.json().catch(() => null);
                    throw new Error(body?.message ?? `Error ${resKpis.status}: ${resKpis.statusText}`);
                }

                const jsonList = await resList.json();
                const jsonKpis = await resKpis.json();

                if (!cancelled) {
                    dispatch({
                        type: 'FETCH_SUCCESS',
                        payload: {
                            data: jsonList.data ?? jsonList,
                            kpis: jsonKpis.data ?? jsonKpis,
                        },
                    });
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
        kpis: state.kpis,
        loading: state.loading,
        error: state.error,
        refetch,
    };
}
