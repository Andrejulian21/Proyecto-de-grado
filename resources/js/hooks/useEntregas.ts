import { useEffect, useCallback, useReducer } from 'react';
import { apiFetch } from '@/lib/utils';

export const FASE_SEQUENCE = [
    'anteproyecto',
    'presentacion_anteproyecto',
    'desarrollo',
    'presentacion_final',
] as const;

export type Fase = (typeof FASE_SEQUENCE)[number];

export interface ProyectoResumen {
    id: number;
    code: string;
    title: string;
    semester_id: number;
}

export interface Entrega {
    id: number;
    proyecto_id: number | null;
    semester_id?: number | null;
    grupo_id: number;
    phase: string;
    title: string;
    description: string;
    due_date: string;
    acceptance_criteria?: string;
    hora_maxima?: string;
    status?: string;
    created_at?: string;
    semestre_nombre?: string;
    proyecto?: ProyectoResumen;
    proyectos?: ProyectoResumen[];
    proyectos_count?: number;
    proyectos_list?: string[];
}

export interface CreateEntregaPayload {
    grupo_id: number;
    fase: string;
    titulo: string;
    descripcion: string;
    fecha_limite: string;
    criterios?: string;
    hora_maxima?: string;
}

export interface UpdateEntregaPayload {
    due_date?: string;
    description?: string;
    titulo?: string;
    acceptance_criteria?: string | null;
    hora_maxima?: string | null;
    phase?: string;
    proyecto_id?: number;
}

export interface EntregasFilters {
    grupo_id?: number | null;
    fase?: string | null;
    entrega_id?: number | null;
}

interface State {
    data: Entrega[];
    loading: boolean;
    error: string | null;
    mutationLoading: boolean;
    mutationError: string | null;
}

type Action =
    | { type: 'FETCH_START' }
    | { type: 'FETCH_SUCCESS'; payload: Entrega[] }
    | { type: 'FETCH_ERROR'; payload: string }
    | { type: 'MUTATION_START' }
    | { type: 'MUTATION_END' }
    | { type: 'CREATE_SUCCESS'; payload: Entrega[] }
    | { type: 'UPDATE_SUCCESS'; payload: Entrega }
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
        case 'MUTATION_END':
            return { ...state, mutationLoading: false };
        case 'CREATE_SUCCESS':
            return {
                ...state,
                data: [...state.data, ...action.payload],
                mutationLoading: false,
            };
        case 'UPDATE_SUCCESS':
            return {
                ...state,
                data: state.data.map((e) =>
                    e.id === action.payload.id ? action.payload : e,
                ),
                mutationLoading: false,
            };
        case 'DELETE_SUCCESS':
            return {
                ...state,
                data: state.data.filter((e) => e.id !== action.payload),
                mutationLoading: false,
            };
        case 'MUTATION_ERROR':
            return { ...state, mutationLoading: false, mutationError: action.payload };
        default:
            return state;
    }
}

export function useEntregas(filters?: EntregasFilters) {
    const [state, dispatch] = useReducer(reducer, {
        data: [],
        loading: true,
        error: null,
        mutationLoading: false,
        mutationError: null,
    });

    const buildUrl = useCallback(() => {
        const params = new URLSearchParams();
        if (filters?.grupo_id != null) params.set('grupo_id', String(filters.grupo_id));
        if (filters?.fase) params.set('fase', filters.fase);
        if (filters?.entrega_id != null) params.set('entrega_id', String(filters.entrega_id));
        const qs = params.toString();
        return `/api/admin/entregas${qs ? `?${qs}` : ''}`;
    }, [filters?.grupo_id, filters?.fase, filters?.entrega_id]);

    const fetchData = useCallback(async () => {
        dispatch({ type: 'FETCH_START' });
        try {
            const res = await apiFetch(buildUrl());
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
    }, [buildUrl]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const crear = useCallback(async (payload: CreateEntregaPayload) => {
        dispatch({ type: 'MUTATION_START' });
        try {
            const res = await apiFetch('/api/admin/entregas', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}`);
            }
            // Refetch to get fresh data including the new entrega
            await fetchData();
            dispatch({ type: 'MUTATION_END' });
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            dispatch({ type: 'MUTATION_ERROR', payload: message });
            throw err;
        }
    }, [fetchData]);

    const actualizar = useCallback(async (id: number, payload: UpdateEntregaPayload) => {
        dispatch({ type: 'MUTATION_START' });
        try {
            const res = await apiFetch(`/api/admin/entregas/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}`);
            }
            const json = await res.json();
            dispatch({ type: 'UPDATE_SUCCESS', payload: json.data ?? json });
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            dispatch({ type: 'MUTATION_ERROR', payload: message });
            throw err;
        }
    }, []);

    const eliminar = useCallback(async (id: number) => {
        dispatch({ type: 'MUTATION_START' });
        try {
            const res = await apiFetch(`/api/admin/entregas/${id}`, {
                method: 'DELETE',
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}`);
            }
            dispatch({ type: 'DELETE_SUCCESS', payload: id });
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            dispatch({ type: 'MUTATION_ERROR', payload: message });
            throw err;
        }
    }, []);

    const getNextFase = useCallback(
        (grupoId: number): Fase => {
            const groupEntregas = state.data.filter((e) => e.grupo_id === grupoId);
            if (groupEntregas.length === 0) return 'anteproyecto';

            // Find the highest index in the sequence among completed entregas
            let highestIdx = -1;
            for (const entrega of groupEntregas) {
                const idx = FASE_SEQUENCE.indexOf(entrega.phase as Fase);
                if (idx > highestIdx) highestIdx = idx;
            }

            const nextIdx = highestIdx + 1;
            if (nextIdx >= FASE_SEQUENCE.length) {
                return FASE_SEQUENCE[FASE_SEQUENCE.length - 1];
            }
            return FASE_SEQUENCE[nextIdx];
        },
        [state.data],
    );

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
        getNextFase,
    };
}
