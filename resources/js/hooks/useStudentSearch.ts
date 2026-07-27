import { useState, useCallback, useRef, useEffect } from 'react';
import { apiFetch } from '@/lib/utils';

export interface StudentUser {
    id: number;
    name: string;
    email: string;
}

const CACHE_KEY = 'student-search-cache';
const DEBOUNCE_MS = 300;

interface CacheEntry {
    [query: string]: { data: StudentUser[]; timestamp: number };
}

const CACHE_TTL = 5 * 60 * 1000; // 5 minutes

function readCache(): CacheEntry {
    try {
        const raw = sessionStorage.getItem(CACHE_KEY);
        return raw ? JSON.parse(raw) : {};
    } catch {
        return {};
    }
}

function writeCache(entries: CacheEntry) {
    try {
        sessionStorage.setItem(CACHE_KEY, JSON.stringify(entries));
    } catch {
        // Silently fail if sessionStorage is full
    }
}

export function useStudentSearch(sinProyecto?: boolean) {
    const [results, setResults] = useState<StudentUser[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    const search = useCallback((query: string) => {
        if (timerRef.current) clearTimeout(timerRef.current);

        if (!query.trim()) {
            setResults([]);
            setLoading(false);
            return;
        }

        // Check cache first
        const cache = readCache();
        const cached = cache[query.trim().toLowerCase()];
        if (cached && Date.now() - cached.timestamp < CACHE_TTL) {
            setResults(cached.data);
            setLoading(false);
            return;
        }

        setLoading(true);

        timerRef.current = setTimeout(async () => {
            // Abort previous request
            if (abortRef.current) abortRef.current.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            try {
                const res = await apiFetch(
                    `/api/admin/usuarios?role=estudiante&search=${encodeURIComponent(query.trim())}${sinProyecto ? '&sin_proyecto=1' : ''}`,
                    { signal: controller.signal },
                );
                if (!res.ok) throw new Error(`Error ${res.status}`);
                const json = await res.json();
                const data: StudentUser[] = json.data ?? json;

                // Update cache
                const newCache = readCache();
                newCache[query.trim().toLowerCase()] = { data, timestamp: Date.now() };
                writeCache(newCache);

                setResults(data);
                setError(null);
            } catch (err) {
                if ((err as Error).name === 'AbortError') return;
                setError(err instanceof Error ? err.message : 'Error desconocido');
                setResults([]);
            } finally {
                setLoading(false);
            }
        }, DEBOUNCE_MS);
    }, [sinProyecto]);

    useEffect(() => {
        return () => {
            if (timerRef.current) clearTimeout(timerRef.current);
            if (abortRef.current) abortRef.current.abort();
        };
    }, []);

    return { results, loading, error, search };
}
