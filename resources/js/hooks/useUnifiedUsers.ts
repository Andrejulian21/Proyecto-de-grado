import { useState, useEffect, useCallback, useRef } from 'react';
import { apiFetch } from '@/lib/utils';

export type UnifiedRole = 'Estudiante' | 'Director' | 'Coordinador' | 'EvaluadorExterno' | 'Pendiente';
export type UnifiedSource = 'usuarios' | 'whitelist' | 'evaluadores';

export interface UnifiedUser {
    id: number | null;
    email: string;
    name: string;
    role: UnifiedRole;
    last_access: string | null;
    source: UnifiedSource;
}

interface RawApiUser {
    id: number;
    email: string;
    name?: string;
    role?: string;
    last_access?: string | null;
    created_at?: string;
}

export interface UseUnifiedUsersResult {
    data: UnifiedUser[];
    loading: boolean;
    error: string | null;
    refetch: () => void;
    addToWhitelist: (email: string, name?: string) => Promise<void>;
    updateRole: (userId: number, newRole: string) => Promise<void>;
    deleteUser: (user: UnifiedUser) => Promise<void>;
}

/**
 * Merge three raw sources into a single UnifiedUser array.
 *
 * Priority by email:
 *   1. usuarios   — highest priority (exact DB user)
 *   2. evaluadores — medium priority (same DB user, different filter)
 *   3. whitelist   — lowest priority (email-only, not yet registered)
 */
function mergeUsers(
    usuarios: RawApiUser[],
    whitelist: RawApiUser[],
    evaluadores: RawApiUser[],
): UnifiedUser[] {
    const map = new Map<string, UnifiedUser>();

    // 1. Process usuarios — highest priority
    for (const u of usuarios) {
        const key = u.email.toLowerCase();
        map.set(key, {
            id: u.id,
            email: u.email,
            name: u.name || '',
            role: u.role as UnifiedRole,
            last_access: u.last_access ?? null,
            source: 'usuarios',
        });
    }

    // 2. Process evaluadores — only add if not already in map
    for (const e of evaluadores) {
        const key = e.email.toLowerCase();
        if (!map.has(key)) {
            map.set(key, {
                id: e.id,
                email: e.email,
                name: e.name || '',
                role: 'EvaluadorExterno',
                last_access: e.last_access ?? null,
                source: 'evaluadores',
            });
        }
    }

    // 3. Process whitelist — only add if not already present
    for (const w of whitelist) {
        const key = w.email.toLowerCase();
        if (!map.has(key)) {
            map.set(key, {
                id: null,
                email: w.email,
                name: w.name || '',
                role: 'Pendiente',
                last_access: null,
                source: 'whitelist',
            });
        }
    }

    return Array.from(map.values());
}

function extractArray(raw: unknown): RawApiUser[] {
    if (Array.isArray(raw)) return raw;
    if (raw && typeof raw === 'object' && 'data' in raw) {
        const d = (raw as Record<string, unknown>).data;
        if (Array.isArray(d)) return d;
    }
    return [];
}

export function useUnifiedUsers(): UseUnifiedUsersResult {
    const [data, setData] = useState<UnifiedUser[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const optimisticSeq = useRef(0);
    const mountedRef = useRef(true);

    const fetchAll = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const [usuariosRes, whitelistRes, evaluadoresRes] = await Promise.all([
                apiFetch('/api/admin/usuarios?per_page=500'),
                apiFetch('/api/admin/whitelist?per_page=500'),
                apiFetch('/api/admin/evaluadores?per_page=500'),
            ]);

            if (!usuariosRes.ok) {
                const body = await usuariosRes.json().catch(() => null);
                throw new Error(body?.message ?? 'Error al cargar usuarios');
            }

            const [usuariosJson, whitelistJson, evaluadoresJson] = await Promise.all([
                usuariosRes.json(),
                whitelistRes.ok ? whitelistRes.json() : Promise.resolve([]),
                evaluadoresRes.ok ? evaluadoresRes.json() : Promise.resolve([]),
            ]);

            const usuarios = extractArray(usuariosJson);
            const whitelist = extractArray(whitelistJson);
            const evaluadores = extractArray(evaluadoresJson);

            const merged = mergeUsers(usuarios, whitelist, evaluadores);

            if (mountedRef.current) {
                setData(merged);
            }
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            if (mountedRef.current) {
                setError(message);
            }
        } finally {
            if (mountedRef.current) {
                setLoading(false);
            }
        }
    }, []);

    useEffect(() => {
        mountedRef.current = true;
        fetchAll();
        return () => {
            mountedRef.current = false;
        };
    }, [fetchAll]);

    const addToWhitelist = useCallback(
        async (email: string, name?: string) => {
            const seq = ++optimisticSeq.current;

            // Optimistic entry
            const optimistic: UnifiedUser & { __opt: number } = {
                id: null,
                email,
                name: name || '',
                role: 'Pendiente',
                last_access: null,
                source: 'whitelist',
                __opt: seq,
            };

            setData((prev) => [optimistic, ...prev]);

            try {
                const res = await apiFetch('/api/admin/whitelist', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: email.trim(),
                        name: name?.trim() || null,
                        role: 'Estudiante',
                    }),
                });

                if (!res.ok) {
                    const err = await res.json().catch(() => null);
                    throw new Error(err?.message ?? 'Error al agregar a la whitelist');
                }

                // Full refetch to get the real server-side entry
                await fetchAll();
            } catch (err) {
                // Rollback optimistic entry
                setData((prev) => prev.filter((u) => (u as any).__opt !== seq));
                const message = err instanceof Error ? err.message : 'Error al agregar usuario';
                throw new Error(message);
            }
        },
        [fetchAll],
    );

    const updateRole = useCallback(
        async (userId: number, newRole: string) => {
            const res = await apiFetch(`/api/admin/usuarios/${userId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role: newRole }),
            });

            if (!res.ok) {
                const err = await res.json().catch(() => null);
                throw new Error(err?.message ?? 'Error al actualizar rol');
            }

            await fetchAll();
        },
        [fetchAll],
    );

    const deleteUser = useCallback(
        async (user: UnifiedUser) => {
            if (user.id === null) {
                throw new Error('No se puede eliminar este usuario');
            }

            const endpoint =
                user.source === 'whitelist'
                    ? `/api/admin/whitelist/${user.id}`
                    : `/api/admin/usuarios/${user.id}`;

            const res = await apiFetch(endpoint, { method: 'DELETE' });

            if (!res.ok) {
                const err = await res.json().catch(() => null);
                throw new Error(err?.message ?? 'Error al eliminar');
            }

            await fetchAll();
        },
        [fetchAll],
    );

    return {
        data,
        loading,
        error,
        refetch: fetchAll,
        addToWhitelist,
        updateRole,
        deleteUser,
    };
}
