import { useEffect, useReducer, useCallback, useState } from 'react';
import { apiFetch } from '@/lib/utils';

export interface DirectorProyecto {
    id: number;
    code: string;
    title: string;
    current_phase: string | null;
    status: string;
    estudiantes: { id: number; name: string }[];
    semestre: { id: number; name: string; is_active: boolean } | null;
}

interface State {
    data: DirectorProyecto[];
    loading: boolean;
    error: string | null;
}

type Action =
    | { type: 'FETCH_START' }
    | { type: 'FETCH_SUCCESS'; payload: DirectorProyecto[] }
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

export function useDirectorProyectos() {
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
                const res = await apiFetch('/api/director/proyectos');

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
