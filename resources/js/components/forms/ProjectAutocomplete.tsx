import { useState, useRef, useEffect, useCallback } from 'react';
import { apiFetch } from '@/lib/utils';
import { X, Search, Loader2 } from 'lucide-react';

export interface ProjectOption {
    id: number;
    code: string;
    title: string;
    /** Identifica el grupo/semestre al que pertenece el proyecto (proyectos.semester_id). */
    semester_id?: number;
    director?: { id: number; name: string } | null;
    estudiantes?: { id: number; name: string }[];
}

interface ProjectAutocompleteProps {
    value: ProjectOption | null;
    onChange: (p: ProjectOption | null) => void;
    error?: string;
    /** Optional: when set, only projects belonging to this group are searched/shown. */
    groupId?: number | null;
}

const CACHE_KEY = 'project-search-cache';
const DEBOUNCE_MS = 300;
const CACHE_TTL = 5 * 60 * 1000;

interface CacheEntry {
    [query: string]: { data: ProjectOption[]; timestamp: number };
}

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
        // silently fail if sessionStorage is full
    }
}

export function ProjectAutocomplete({ value, onChange, error, groupId }: ProjectAutocompleteProps) {
    const [inputValue, setInputValue] = useState('');
    const [open, setOpen] = useState(false);
    const [results, setResults] = useState<ProjectOption[]>([]);
    const [loading, setLoading] = useState(false);
    const wrapperRef = useRef<HTMLDivElement>(null);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        return () => {
            if (timerRef.current) clearTimeout(timerRef.current);
            if (abortRef.current) abortRef.current.abort();
        };
    }, []);

    // When the selected group changes, reset the internal search state so
    // stale results/input from the previous group never leak into the new one.
    useEffect(() => {
        if (timerRef.current) clearTimeout(timerRef.current);
        if (abortRef.current) abortRef.current.abort();
        setInputValue('');
        setResults([]);
        setOpen(false);
    }, [groupId]);

    /** Defensive client-side scope: keeps only projects of the active group. */
    const scopeResults = useCallback(
        (data: ProjectOption[]): ProjectOption[] =>
            groupId != null ? data.filter((p) => p.semester_id === groupId) : data,
        [groupId],
    );

    const search = useCallback((query: string) => {
        if (timerRef.current) clearTimeout(timerRef.current);

        if (!query.trim()) {
            setResults([]);
            setLoading(false);
            return;
        }

        // Cache is scoped per group so switching groups never reuses another group's results.
        const cacheKey = `${groupId ?? 'all'}:${query.trim().toLowerCase()}`;

        // Check cache first
        const cache = readCache();
        const cached = cache[cacheKey];
        if (cached && Date.now() - cached.timestamp < CACHE_TTL) {
            setResults(scopeResults(cached.data));
            setLoading(false);
            return;
        }

        setLoading(true);

        timerRef.current = setTimeout(async () => {
            if (abortRef.current) abortRef.current.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            try {
                const params = new URLSearchParams({ search: query.trim() });
                if (groupId != null) {
                    // Group is the authoritative filter: show every project of the group,
                    // regardless of whether the group is currently marked active.
                    params.set('grupo_id', String(groupId));
                } else {
                    params.set('semestre_activo', '1');
                }
                const res = await apiFetch(
                    `/api/admin/proyectos?${params.toString()}`,
                    { signal: controller.signal },
                );
                if (!res.ok) throw new Error(`Error ${res.status}`);
                const json = await res.json();
                const data: ProjectOption[] = json.data ?? json;

                // Update cache
                const newCache = readCache();
                newCache[cacheKey] = { data, timestamp: Date.now() };
                writeCache(newCache);

                setResults(scopeResults(data));
            } catch (err) {
                if ((err as Error).name === 'AbortError') return;
                setResults([]);
            } finally {
                setLoading(false);
            }
        }, DEBOUNCE_MS);
    }, [groupId, scopeResults]);

    const handleInputChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            const val = e.target.value;
            setInputValue(val);
            search(val);
            setOpen(true);
        },
        [search],
    );

    const handleSelect = useCallback(
        (project: ProjectOption) => {
            onChange(project);
            setInputValue('');
            setOpen(false);
        },
        [onChange],
    );

    const handleClear = useCallback(() => {
        onChange(null);
        setInputValue('');
    }, [onChange]);

    const handleFocus = useCallback(() => {
        if (inputValue.trim()) setOpen(true);
    }, [inputValue]);

    return (
        <div ref={wrapperRef} className="flex flex-col gap-1.5">
            <label className="text-sm font-semibold text-[#1c1917]">
                Proyecto <span className="text-[#dc2626]">*</span>
            </label>

            {/* Selected chip */}
            {value && (
                <div className="flex flex-wrap gap-1.5">
                    <span className="inline-flex items-center gap-1 rounded-full bg-[#fed7aa] px-2.5 py-1 text-xs font-semibold text-[#9a330a]">
                        {value.code} — {value.title}
                        <button
                            type="button"
                            onClick={handleClear}
                            className="ml-0.5 rounded-full p-0.5 transition-colors hover:bg-[#fdba74]"
                            aria-label="Limpiar selección"
                        >
                            <X className="h-3 w-3" />
                        </button>
                    </span>
                </div>
            )}

            {/* Search input */}
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                <input
                    type="text"
                    value={inputValue}
                    onChange={handleInputChange}
                    onFocus={handleFocus}
                    placeholder={value ? 'Cambiar proyecto...' : 'Buscar proyecto por código o título...'}
                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                    aria-label="Buscar proyecto"
                    aria-invalid={!!error}
                    role="combobox"
                    aria-expanded={open}
                    aria-autocomplete="list"
                />
                {loading && (
                    <Loader2 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-[#78716c]" />
                )}
            </div>

            {/* Dropdown */}
            {open && inputValue.trim() && (
                <div className="z-50 max-h-48 overflow-y-auto rounded-lg border border-[#e5e5e5] bg-white shadow-[0_4px_12px_rgba(28,25,23,0.1)]">
                    {loading ? (
                        <div className="flex items-center justify-center py-6">
                            <Loader2 className="h-5 w-5 animate-spin text-[#78716c]" />
                        </div>
                    ) : results.length === 0 ? (
                        <div className="py-6 text-center text-sm text-[#57534e]">
                            Sin resultados
                        </div>
                    ) : (
                        <ul className="py-1" role="listbox">
                            {results.map((project) => (
                                <li key={project.id}>
                                    <button
                                        type="button"
                                        onClick={() => handleSelect(project)}
                                        className="flex w-full items-center gap-3 px-3 py-2 text-left text-sm transition-colors hover:bg-[#f5f5f4]"
                                        role="option"
                                        aria-selected={value?.id === project.id}
                                    >
                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <span className="font-medium text-[#1c1917]">
                                                {project.code} — {project.title}
                                            </span>
                                            {project.director && (
                                                <span className="text-xs text-[#78716c]">
                                                    Director: {project.director.name}
                                                </span>
                                            )}
                                            {project.estudiantes && project.estudiantes.length > 0 && (
                                                <span className="text-xs text-[#78716c]">
                                                    Estudiantes: {project.estudiantes.map((e) => e.name).join(', ')}
                                                </span>
                                            )}
                                        </div>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {error && (
                <span className="text-xs font-medium text-[#dc2626]" role="alert">
                    {error}
                </span>
            )}
        </div>
    );
}
