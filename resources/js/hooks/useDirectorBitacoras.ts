import { useEffect, useReducer, useCallback, useState } from 'react';
import { apiFetch } from '@/lib/utils';

export interface BitacoraEntry {
    id: number;
    topic: string;
    notes: string | null;
    meeting_date: string | null;
    duration_hours: number | null;
    signature_status: string;
    created_at: string;
    /** Enriched with project info after aggregation */
    project_code?: string;
    project_title?: string;
    project_id?: number;
    estudiante_name?: string;
}

interface DirectorProyecto {
    id: number;
    code: string;
    title: string;
    estudiantes: { id: number; name: string }[];
}

interface State {
    data: BitacoraEntry[];
    loading: boolean;
    error: string | null;
}

type Action =
    | { type: 'FETCH_START' }
    | { type: 'FETCH_SUCCESS'; payload: BitacoraEntry[] }
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

export function useDirectorBitacoras() {
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
                // 1. Fetch director's projects
                const projRes = await apiFetch('/api/director/proyectos');

                if (!projRes.ok) {
                    const body = await projRes.json().catch(() => null);
                    throw new Error(body?.message ?? `Error ${projRes.status}: ${projRes.statusText}`);
                }

                const projJson = await projRes.json();
                const proyectos: DirectorProyecto[] = projJson.data ?? projJson;

                // 2. For each project, fetch its bitácoras
                const allBitacoras: BitacoraEntry[] = [];

                for (const proyecto of proyectos) {
                    const bitRes = await apiFetch(`/api/director/proyectos/${proyecto.id}/bitacoras`);

                    if (!bitRes.ok) continue;

                    const bitJson = await bitRes.json();
                    const bitacoras: BitacoraEntry[] = bitJson.data ?? bitJson;

                    const estudianteName = proyecto.estudiantes
                        .map((e) => e.name)
                        .join(', ');

                    for (const b of bitacoras) {
                        allBitacoras.push({
                            ...b,
                            project_code: proyecto.code,
                            project_title: proyecto.title,
                            project_id: proyecto.id,
                            estudiante_name: estudianteName,
                        });
                    }
                }

                // Sort by created_at descending
                allBitacoras.sort(
                    (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
                );

                if (!cancelled) {
                    dispatch({ type: 'FETCH_SUCCESS', payload: allBitacoras });
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
