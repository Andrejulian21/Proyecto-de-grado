import { useState, useCallback } from 'react';
import { apiFetch } from '@/lib/utils';

/* ── Types ── */

export interface Director {
    id: number | null;
    name: string;
    email: string;
    areas: string[];
}

export interface DirectorProyecto {
    id: number;
    code: string;
    title: string;
    estudiantes: { id: number; name: string }[];
    current_phase: string;
    status: string;
    semestre?: { id: number; name: string; is_active: boolean } | null;
}

export interface Bitacora {
    id: number;
    fecha: string;
    contenido: string;
    firmada: boolean;
    director_name: string;
}

/* ── Hook ── */

export function useDirectores() {
    const [directores, setDirectores] = useState<Director[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [selectedDirector, setSelectedDirector] = useState<Director | null>(null);
    const [proyectos, setProyectos] = useState<DirectorProyecto[]>([]);
    const [loadingProyectos, setLoadingProyectos] = useState(false);
    const [errorProyectos, setErrorProyectos] = useState<string | null>(null);

    const [selectedProyecto, setSelectedProyecto] = useState<DirectorProyecto | null>(null);
    const [bitacoras, setBitacoras] = useState<Bitacora[]>([]);
    const [loadingBitacoras, setLoadingBitacoras] = useState(false);
    const [errorBitacoras, setErrorBitacoras] = useState<string | null>(null);

    const fetchDirectores = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await apiFetch('/api/admin/directores');
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}: ${res.statusText}`);
            }
            const json = await res.json();
            setDirectores(json.data ?? json);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            setError(message);
        } finally {
            setLoading(false);
        }
    }, []);

    const selectDirector = useCallback(async (director: Director, todas?: boolean) => {
        setSelectedDirector(director);
        setSelectedProyecto(null);
        setBitacoras([]);

        setLoadingProyectos(true);
        setErrorProyectos(null);
        try {
            const params = todas ? '?todas=1' : '';
            const res = await apiFetch(`/api/admin/directores/${director.id}/proyectos${params}`);
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}`);
            }
            const json = await res.json();
            setProyectos(json.data ?? json);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            setErrorProyectos(message);
        } finally {
            setLoadingProyectos(false);
        }
    }, []);

    const selectProyecto = useCallback(async (proyecto: DirectorProyecto) => {
        setSelectedProyecto(proyecto);

        setLoadingBitacoras(true);
        setErrorBitacoras(null);
        try {
            const res = await apiFetch(`/api/admin/proyectos/${proyecto.id}/bitacoras`);
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}`);
            }
            const json = await res.json();
            setBitacoras(json.data ?? json);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            setErrorBitacoras(message);
        } finally {
            setLoadingBitacoras(false);
        }
    }, []);

    const viewProyecto = useCallback((proyecto: DirectorProyecto) => {
        setSelectedProyecto(proyecto);
    }, []);

    const clearProyecto = useCallback(() => {
        setSelectedProyecto(null);
        setBitacoras([]);
        setErrorBitacoras(null);
    }, []);

    const reset = useCallback(() => {
        setSelectedDirector(null);
        setSelectedProyecto(null);
        setProyectos([]);
        setBitacoras([]);
        setErrorProyectos(null);
        setErrorBitacoras(null);
    }, []);

    return {
        directores,
        loading,
        error,
        fetchDirectores,
        selectDirector,
        selectedDirector,
        proyectos,
        loadingProyectos,
        errorProyectos,
        selectProyecto,
        selectedProyecto,
        bitacoras,
        loadingBitacoras,
        errorBitacoras,
        viewProyecto,
        clearProyecto,
        reset,
    };
}
