import { useEffect, useCallback, useReducer } from 'react';
import { apiFetch } from '@/lib/utils';

export interface Proyecto {
    id: number;
    code: string;
    title: string;
    students: { id: number; name: string }[];
    director: { id: number; name: string };
    phase: 'Anteproyecto' | 'Presentacion' | 'Desarrollo' | 'Final';
    status: 'active' | 'at-risk' | 'completed' | 'inscribed';
    grupo_id: number;
}

export interface CreateProyectoPayload {
    title: string;
    grupo_id: number;
    director_id: number;
    student_ids: number[];
}

export interface UpdateProyectoPayload {
    title?: string;
    director_id?: number;
    student_ids?: number[];
    status?: string;
}

interface State {
    data: Proyecto[];
    loading: boolean;
    error: string | null;
    mutationLoading: boolean;
    mutationError: string | null;
}

type Action =
    | { type: 'FETCH_START' }
    | { type: 'FETCH_SUCCESS'; payload: Proyecto[] }
    | { type: 'FETCH_ERROR'; payload: string }
    | { type: 'MUTATION_START' }
    | { type: 'CREATE_SUCCESS'; payload: Proyecto }
    | { type: 'UPDATE_SUCCESS'; payload: Proyecto }
    | { type: 'DELETE_SUCCESS'; payload: number }
    | { type: 'MUTATION_ERROR'; payload: string };

function reducer(state: State, action: Action): State {
    switch (action.type) {
        case 'FETCH_START':
            return { ...state, loading: true, error: null };
        case 'FETCH_SUCCESS':
            return { ...state, data: action.payload, loading: false };
        case 'FETCH_ERROR':
            return { ...state, loading: false, error: action.payload };
        case 'MUTATION_START':
            return { ...state, mutationLoading: true, mutationError: null };
        case 'CREATE_SUCCESS':
            return {
                ...state,
                data: [...state.data, action.payload],
                mutationLoading: false,
            };
        case 'UPDATE_SUCCESS':
            return {
                ...state,
                data: state.data.map((p) =>
                    p.id === action.payload.id ? action.payload : p,
                ),
                mutationLoading: false,
            };
        case 'DELETE_SUCCESS':
            return {
                ...state,
                data: state.data.filter((p) => p.id !== action.payload),
                mutationLoading: false,
            };
        case 'MUTATION_ERROR':
            return { ...state, mutationLoading: false, mutationError: action.payload };
        default:
            return state;
    }
}

export function useProyectos(grupoId?: number | null) {
    const [state, dispatch] = useReducer(reducer, {
        data: [],
        loading: true,
        error: null,
        mutationLoading: false,
        mutationError: null,
    });

    const fetchData = useCallback(async () => {
        dispatch({ type: 'FETCH_START' });
        try {
            const url =
                grupoId != null
                    ? `/api/admin/proyectos?grupo_id=${grupoId}`
                    : '/api/admin/proyectos';
            const res = await apiFetch(url);
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}: ${res.statusText}`);
            }
            const json = await res.json();
            dispatch({ type: 'FETCH_SUCCESS', payload: json.data ?? json });
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            dispatch({ type: 'FETCH_ERROR', payload: message });
        }
    }, [grupoId]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const crear = useCallback(async (payload: CreateProyectoPayload) => {
        dispatch({ type: 'MUTATION_START' });
        try {
            const res = await apiFetch('/api/admin/proyectos', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}`);
            }
            const json = await res.json();
            dispatch({ type: 'CREATE_SUCCESS', payload: json.data ?? json });
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            dispatch({ type: 'MUTATION_ERROR', payload: message });
            throw err;
        }
    }, []);

    const actualizar = useCallback(
        async (id: number, payload: UpdateProyectoPayload) => {
            dispatch({ type: 'MUTATION_START' });
            try {
                const res = await apiFetch(`/api/admin/proyectos/${id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                if (!res.ok) {
                    const body = await res.json().catch(() => null);
                    throw new Error(body?.message ?? `Error ${res.status}`);
                }
                const json = await res.json();
                const updated: Proyecto = json.data ?? json;
                dispatch({ type: 'UPDATE_SUCCESS', payload: updated });
                return updated;
            } catch (err) {
                const message = err instanceof Error ? err.message : 'Error desconocido';
                dispatch({ type: 'MUTATION_ERROR', payload: message });
                throw err;
            }
        },
        [],
    );

    const eliminar = useCallback(async (id: number) => {
        dispatch({ type: 'MUTATION_START' });
        try {
            const res = await apiFetch(`/api/admin/proyectos/${id}`, {
                method: 'DELETE',
            });
            if (!res.ok) throw new Error(`Error ${res.status}`);
            dispatch({ type: 'DELETE_SUCCESS', payload: id });
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            dispatch({ type: 'MUTATION_ERROR', payload: message });
            throw err;
        }
    }, []);

    return {
        data: state.data,
        loading: state.loading,
        error: state.error,
        mutationLoading: state.mutationLoading,
        mutationError: state.mutationError,
        refetch: fetchData,
        crear,
        actualizar,
        eliminar,
    };
}
