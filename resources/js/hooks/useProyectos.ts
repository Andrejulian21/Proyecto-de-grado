import { useEffect, useCallback, useReducer } from 'react';
import { apiFetch } from '@/lib/utils';

export interface Proyecto {
    id: number;
    code: string;
    title: string;
    estudiantes: { id: number; name: string; email?: string }[];
    director: { id: number; name: string; email?: string } | null;
    current_phase: string | null;
    status: string;
    semester_id: number;
    created_at?: string;
}

export interface CreateProyectoPayload {
    title: string;
    semester_id: number;
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

export function useProyectos(grupoId?: number | null, filters?: { search?: string; semestre_activo?: boolean }) {
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
            // Issue #53: /api/admin/proyectos ahora pagina en el servidor.
            // Este hook acumula todas las páginas (per_page máximo 200) para
            // que los consumidores sigan trabajando con el listado completo
            // sin truncar a partir del proyecto 201 (misma clase de defecto
            // que el truncamiento de usuarios a 200).
            const params = new URLSearchParams();
            if (grupoId != null) {
                params.set('grupo_id', String(grupoId));
            }
            if (filters?.search) {
                params.set('search', filters.search);
            }
            if (filters?.semestre_activo) {
                params.set('semestre_activo', 'true');
            }
            params.set('per_page', '200');

            const all: Proyecto[] = [];
            let page = 1;
            let lastPage = 1;

            do {
                const pageParams = new URLSearchParams(params);
                pageParams.set('page', String(page));
                const res = await apiFetch(`/api/admin/proyectos?${pageParams.toString()}`);
                if (!res.ok) {
                    const body = await res.json().catch(() => null);
                    throw new Error(body?.message ?? `Error ${res.status}: ${res.statusText}`);
                }
                const json = await res.json();
                const items: Proyecto[] = Array.isArray(json.data) ? json.data : [];
                all.push(...items);
                lastPage = json.last_page ?? 1;
                page += 1;
            } while (page <= lastPage);

            dispatch({ type: 'FETCH_SUCCESS', payload: all });
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            dispatch({ type: 'FETCH_ERROR', payload: message });
        }
    }, [grupoId, filters?.search, filters?.semestre_activo]);

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
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.error ?? `Error ${res.status}`);
            }
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
