import { useEffect, useCallback, useReducer } from 'react';
import { apiFetch } from '@/lib/utils';

export interface Recurso {
    id: number;
    title: string;
    description: string;
    type: 'document' | 'spreadsheet' | 'image' | 'video' | 'link' | 'other';
    file_path?: string;
    file_size?: string;
    uploaded_at: string;
    downloads: number;
}

export interface CreateRecursoPayload {
    title: string;
    description: string;
    category: string;
    file?: File | null;
}

export interface UpdateRecursoPayload {
    title?: string;
    description?: string;
    category?: string;
    file?: File | null;
}

interface State {
    data: Recurso[];
    loading: boolean;
    error: string | null;
    mutationLoading: boolean;
    mutationError: string | null;
    uploadProgress: number | null;
}

type Action =
    | { type: 'FETCH_START' }
    | { type: 'FETCH_SUCCESS'; payload: Recurso[] }
    | { type: 'FETCH_ERROR'; payload: string }
    | { type: 'MUTATION_START' }
    | { type: 'CREATE_SUCCESS'; payload: Recurso }
    | { type: 'UPDATE_SUCCESS'; payload: Recurso }
    | { type: 'DELETE_SUCCESS'; payload: number }
    | { type: 'MUTATION_ERROR'; payload: string }
    | { type: 'SET_UPLOAD_PROGRESS'; payload: number };

function reducer(state: State, action: Action): State {
    switch (action.type) {
        case 'FETCH_START':
            return { ...state, loading: true, error: null };
        case 'FETCH_SUCCESS':
            return { ...state, data: action.payload, loading: false };
        case 'FETCH_ERROR':
            return { ...state, loading: false, error: action.payload };
        case 'MUTATION_START':
            return { ...state, mutationLoading: true, mutationError: null, uploadProgress: null };
        case 'CREATE_SUCCESS':
            return {
                ...state,
                data: [action.payload, ...state.data],
                mutationLoading: false,
                uploadProgress: null,
            };
        case 'UPDATE_SUCCESS':
            return {
                ...state,
                data: state.data.map((r) =>
                    r.id === action.payload.id ? action.payload : r,
                ),
                mutationLoading: false,
                uploadProgress: null,
            };
        case 'DELETE_SUCCESS':
            return {
                ...state,
                data: state.data.filter((r) => r.id !== action.payload),
                mutationLoading: false,
            };
        case 'MUTATION_ERROR':
            return {
                ...state,
                mutationLoading: false,
                mutationError: action.payload,
                uploadProgress: null,
            };
        case 'SET_UPLOAD_PROGRESS':
            return { ...state, uploadProgress: action.payload };
        default:
            return state;
    }
}

export function useRecursos() {
    const [state, dispatch] = useReducer(reducer, {
        data: [],
        loading: true,
        error: null,
        mutationLoading: false,
        mutationError: null,
        uploadProgress: null,
    });

    const fetchData = useCallback(async () => {
        dispatch({ type: 'FETCH_START' });
        try {
            const res = await apiFetch('/api/admin/recursos');
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
        fetchData();
    }, [fetchData]);

    const crear = useCallback(async (payload: CreateRecursoPayload) => {
        dispatch({ type: 'MUTATION_START' });
        try {
            const formData = new FormData();
            formData.append('title', payload.title);
            formData.append('description', payload.description);
            formData.append('category', payload.category);
            if (payload.file) {
                formData.append('file', payload.file);
            }

            const res = await apiFetch('/api/admin/recursos', {
                method: 'POST',
                body: formData,
                // No Content-Type — browser sets multipart/form-data with boundary
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}`);
            }
            const json = await res.json();
            dispatch({ type: 'CREATE_SUCCESS', payload: json.data ?? json });
            return json.data ?? json;
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            dispatch({ type: 'MUTATION_ERROR', payload: message });
            throw err;
        }
    }, []);

    const actualizar = useCallback(async (id: number, payload: UpdateRecursoPayload) => {
        dispatch({ type: 'MUTATION_START' });
        try {
            const formData = new FormData();
            if (payload.title !== undefined) formData.append('title', payload.title);
            if (payload.description !== undefined) formData.append('description', payload.description);
            if (payload.category !== undefined) formData.append('category', payload.category);
            if (payload.file) {
                formData.append('file', payload.file);
            }
            formData.append('_method', 'PUT');

            const res = await apiFetch(`/api/admin/recursos/${id}`, {
                method: 'POST', // POST with _method=PUT for multipart
                body: formData,
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}`);
            }
            const json = await res.json();
            const updated: Recurso = json.data ?? json;
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
            const res = await apiFetch(`/api/admin/recursos/${id}`, {
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
        uploadProgress: state.uploadProgress,
        refetch: fetchData,
        crear,
        actualizar,
        eliminar,
    };
}
