import { useEffect, useCallback, useReducer } from 'react';
import { apiFetch } from '@/lib/utils';
import { FRONTEND_VALIDATION_MODE, mockDelay } from '@/mocks/validationMode';
import { MOCK_ADMIN_PROYECTOS } from '@/mocks/proyectosMock';

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
            if (FRONTEND_VALIDATION_MODE) {
                await mockDelay();
                let data = structuredClone(MOCK_ADMIN_PROYECTOS);
                if (grupoId != null) data = data.filter((p) => p.semester_id === grupoId);
                if (filters?.search) {
                    const q = filters.search.toLowerCase();
                    data = data.filter(
                        (p) =>
                            p.title.toLowerCase().includes(q) ||
                            p.code.toLowerCase().includes(q),
                    );
                }
                dispatch({ type: 'FETCH_SUCCESS', payload: data });
                return;
            }
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
            const qs = params.toString();
            const url = `/api/admin/proyectos${qs ? '?' + qs : ''}`;
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
    }, [grupoId, filters?.search, filters?.semestre_activo]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const crear = useCallback(async (payload: CreateProyectoPayload) => {
        dispatch({ type: 'MUTATION_START' });
        try {
            if (FRONTEND_VALIDATION_MODE) {
                await mockDelay(200);
                const created: Proyecto = {
                    id: Date.now(),
                    code: `PG-2026${String(Date.now()).slice(-3)}`,
                    title: payload.title,
                    estudiantes: payload.student_ids.map((sid) => ({
                        id: sid,
                        name: `Estudiante ${sid}`,
                    })),
                    director: MOCK_ADMIN_PROYECTOS.find((p) => p.director?.id === payload.director_id)?.director ?? {
                        id: payload.director_id,
                        name: 'Director',
                    },
                    current_phase: 'anteproyecto',
                    status: 'active',
                    semester_id: payload.semester_id,
                };
                dispatch({ type: 'CREATE_SUCCESS', payload: created });
                return;
            }
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
                if (FRONTEND_VALIDATION_MODE) {
                    await mockDelay(200);
                    const existing = MOCK_ADMIN_PROYECTOS.find((p) => p.id === id);
                    if (!existing) throw new Error('Proyecto no encontrado');
                    const updated: Proyecto = { ...existing, ...payload };
                    dispatch({ type: 'UPDATE_SUCCESS', payload: updated });
                    return updated;
                }
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
            if (FRONTEND_VALIDATION_MODE) {
                await mockDelay(200);
                dispatch({ type: 'DELETE_SUCCESS', payload: id });
                return;
            }
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
