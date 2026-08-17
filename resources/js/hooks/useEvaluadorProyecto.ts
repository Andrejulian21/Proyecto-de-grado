import { useState, useEffect, useCallback, useReducer } from 'react';
import { apiFetch } from '@/lib/utils';

/* ── Types ── */

export interface EvaluadorInfo {
    id: number;
    name: string;
    email: string;
    role: string;
    assignment_id: number;
}

export interface EvaluadorProyecto {
    id: number;
    assignment_id: number;
    proyecto_id: number;
    proyecto_codigo: string;
    proyecto_nombre: string;
    proyecto_director_id: number | null;
    proyecto_director_nombre: string;
    estudiantes: { id: number; name: string }[];
    fase: 'presentacion_anteproyecto' | 'presentacion_final';
    fecha: string;
    hora_inicio: string;
    hora_fin: string;
    hora: string; // backward-compat alias
    evaluadores_list: EvaluadorInfo[];
    evaluador_principal_id: number;
    evaluador_principal_nombre: string;
    evaluador_secundario_id: number | null;
    evaluador_secundario_nombre: string | null;
    evaluador_tercero_id: number | null;
    evaluador_tercero_nombre: string | null;
}

export interface EvaluadorUser {
    id: number;
    name: string;
    email: string;
    role: string;
}

export interface CreateEvaluadorPayload {
    proyecto_id: number;
    evaluador_ids: number[];
    fecha: string;
    hora_inicio: string;
    hora_fin: string;
    fase: 'presentacion_anteproyecto' | 'presentacion_final';
}

export type UpdateEvaluadorPayload = Partial<{
    fecha: string;
    hora_inicio: string;
    hora_fin: string;
    fase: 'presentacion_anteproyecto' | 'presentacion_final';
    evaluador_ids: number[];
}>;

/* ── Helpers ── */

/**
 * Extrae un mensaje de error legible de cualquier forma de body de error
 * (`{error}`, `{errors}`, `{message}`) sin asumir su shape. Nunca llama
 * Object.values sobre undefined.
 */
export function extractErrorMessage(body: unknown, status: number): string {
    if (body && typeof body === 'object') {
        const b = body as Record<string, unknown>;
        if (typeof b.error === 'string' && b.error) return b.error;
        if (b.errors && typeof b.errors === 'object' && b.errors !== null) {
            const flat = Object.values(b.errors as Record<string, unknown>)
                .flat()
                .filter((v): v is string => typeof v === 'string');
            if (flat.length > 0) return flat.join('. ');
        }
        if (typeof b.message === 'string' && b.message) return b.message;
    }
    return `Error ${status}`;
}

/* ── State & Reducer ── */

interface State {
    data: EvaluadorProyecto[];
    loading: boolean;
    error: string | null;
    mutationLoading: boolean;
    mutationError: string | null;
}

type Action =
    | { type: 'FETCH_START' }
    | { type: 'FETCH_SUCCESS'; payload: EvaluadorProyecto[] }
    | { type: 'FETCH_ERROR'; payload: string }
    | { type: 'MUTATION_START' }
    | { type: 'CREATE_SUCCESS'; payload: EvaluadorProyecto }
    | { type: 'UPDATE_SUCCESS'; payload: EvaluadorProyecto }
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
                data: state.data.map((a) =>
                    a.id === action.payload.id ? action.payload : a,
                ),
                mutationLoading: false,
            };
        case 'DELETE_SUCCESS':
            return {
                ...state,
                data: state.data.filter((a) => a.id !== action.payload),
                mutationLoading: false,
            };
        case 'MUTATION_ERROR':
            return { ...state, mutationLoading: false, mutationError: action.payload };
        default:
            return state;
    }
}

const initialState: State = {
    data: [],
    loading: true,
    error: null,
    mutationLoading: false,
    mutationError: null,
};

/* ── Hook ── */

export function useEvaluadorProyecto() {
    const [state, dispatch] = useReducer(reducer, initialState);

    const refetch = useCallback(async () => {
        dispatch({ type: 'FETCH_START' });
        try {
            const res = await apiFetch('/api/admin/evaluador-proyecto');
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
    }, []);

    useEffect(() => {
        refetch();
    }, [refetch]);

    const crear = useCallback(async (payload: CreateEvaluadorPayload) => {
        dispatch({ type: 'MUTATION_START' });
        try {
            const res = await apiFetch('/api/admin/evaluador-proyecto', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(extractErrorMessage(body, res.status));
            }
            const json = await res.json();
            const created: EvaluadorProyecto = json.data ?? json;
            dispatch({ type: 'CREATE_SUCCESS', payload: created });
            return created;
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            dispatch({ type: 'MUTATION_ERROR', payload: message });
            throw err;
        }
    }, []);

    const actualizar = useCallback(async (id: number, payload: UpdateEvaluadorPayload) => {
        dispatch({ type: 'MUTATION_START' });
        try {
            const res = await apiFetch(`/api/admin/evaluador-proyecto/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(extractErrorMessage(body, res.status));
            }
            const json = await res.json();
            const updated: EvaluadorProyecto = json.data ?? json;
            dispatch({ type: 'UPDATE_SUCCESS', payload: updated });
            return updated;
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            dispatch({ type: 'MUTATION_ERROR', payload: message });
            throw err;
        }
    }, []);

    const eliminar = useCallback(async (id: number) => {
        dispatch({ type: 'MUTATION_START' });
        try {
            const res = await apiFetch(`/api/admin/evaluador-proyecto/${id}`, {
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
        refetch,
        crear,
        actualizar,
        eliminar,
    };
}

/* ── EvaluadorUsers hook ── */

export interface UseEvaluadorUsersResult {
    data: EvaluadorUser[];
    loading: boolean;
    error: string | null;
}

export function useEvaluadorUsers(): UseEvaluadorUsersResult {
    const [data, setData] = useState<EvaluadorUser[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchUsers = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            // Fetch both evaluadores externos and directores for the selector
            const [evalRes, dirRes] = await Promise.all([
                apiFetch('/api/admin/usuarios?role=evaluadorexterno&per_page=500'),
                apiFetch('/api/admin/usuarios?role=director&per_page=500'),
            ]);

            // Deduplicate by email using a Map
            const resultsMap = new Map<string, EvaluadorUser>();

            if (evalRes.ok) {
                const json = await evalRes.json();
                const raw = Array.isArray(json) ? json : json.data ?? [];
                for (const u of raw) {
                    const email = u.email ?? '';
                    if (!resultsMap.has(email)) {
                        resultsMap.set(email, { id: u.id, name: u.name ?? '', email, role: u.role ?? 'EvaluadorExterno' });
                    }
                }
            }

            if (dirRes.ok) {
                const json = await dirRes.json();
                const raw = Array.isArray(json) ? json : json.data ?? [];
                for (const u of raw) {
                    const email = u.email ?? '';
                    if (!resultsMap.has(email)) {
                        resultsMap.set(email, { id: u.id, name: u.name ?? '', email, role: u.role ?? 'Director' });
                    }
                }
            }

            setData(Array.from(resultsMap.values()));
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            setError(message);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchUsers();
    }, [fetchUsers]);

    return { data, loading, error };
}
