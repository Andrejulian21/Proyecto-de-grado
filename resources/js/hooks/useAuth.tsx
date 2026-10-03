import { createContext, useContext, useState, useEffect, useCallback, type ReactNode } from 'react';
import { apiFetch } from '@/lib/utils';

interface User {
    id: number;
    name: string;
    email: string;
    role: 'Coordinador' | 'Director' | 'Estudiante' | 'EvaluadorExterno';
}

interface AuthContextValue {
    user: User | null;
    isAuthenticated: boolean;
    role: string | null;
    isLoading: boolean;
    login: (email: string, password: string) => Promise<{ success: boolean; error?: string }>;
    logout: () => Promise<void>;
    sessionCheck: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

/* Render hint, NOT an identity source.
   The server stays authoritative: every data call goes to the API and a revoked
   session still 401s. This only exists so a refresh inside an active session
   paints the right screen on the first frame instead of flashing the landing
   page while /api/auth/user is retried. Bounded by CACHE_TTL_MS, cleared on
   logout, and always overwritten by the real response in sessionCheck(). */
const CACHE_KEY = 'auth_user';
const CACHE_TS_KEY = 'auth_user_ts';
const CACHE_TTL_MS = 2 * 60 * 1000;

function readCachedUser(): User | null {
    try {
        const raw = sessionStorage.getItem(CACHE_KEY);
        const ts = Number(sessionStorage.getItem(CACHE_TS_KEY) ?? '0');
        if (!raw || !ts) return null;
        if (Date.now() - ts > CACHE_TTL_MS) {
            clearCachedUser();
            return null;
        }
        return JSON.parse(raw) as User;
    } catch {
        return null;
    }
}

function cacheUser(user: User): void {
    try {
        sessionStorage.setItem(CACHE_KEY, JSON.stringify(user));
        sessionStorage.setItem(CACHE_TS_KEY, String(Date.now()));
    } catch {
        /* storage unavailable — the app still works, it just flashes */
    }
}

function clearCachedUser(): void {
    try {
        sessionStorage.removeItem(CACHE_KEY);
        sessionStorage.removeItem(CACHE_TS_KEY);
    } catch {
        /* ignore */
    }
}

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(() => readCachedUser());
    const [isLoading, setIsLoading] = useState<boolean>(() => readCachedUser() === null);

    const isAuthenticated = user !== null;
    const role = user?.role ?? null;

    const fetchUser = useCallback(async (): Promise<User | null> => {
        try {
            const res = await fetch('/api/auth/user', {
                credentials: 'include',
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                return await res.json();
            }
        } catch {
            // ignore
        }
        return null;
    }, []);

    const sessionCheck = useCallback(async (): Promise<void> => {
        try {
            // Bootstrap Sanctum CSRF cookie — required for SPA auth.
            await fetch('/sanctum/csrf-cookie', {
                method: 'GET',
                credentials: 'include',
            });

            // Try the API with retries (the Sanctum cookie may take a moment).
            for (let attempt = 0; attempt < 6; attempt++) {
                const data = await fetchUser();
                if (data) {
                    setUser(data);
                    cacheUser(data);
                    setIsLoading(false);
                    return;
                }
                await new Promise(r => setTimeout(r, 600));
            }

            // API failed — the server is the only authority on who the user is,
            // so a failed check always drops the session and the render hint.
            clearCachedUser();
            setUser(null);
        } catch {
            setUser(null);
        } finally {
            setIsLoading(false);
        }
    }, [fetchUser]);

    useEffect(() => {
        sessionCheck();
    }, [sessionCheck]);

    // Stub — external login is handled by LoginExterno component,
    // Google OAuth is handled server-side. This keeps the interface
    // happy for any component that destructures `login` from context.
    const login = useCallback(async (_email: string, _password: string) => {
        return { success: false, error: 'Usa el formulario de inicio de sesión.' } as const;
    }, []);

    // RF-AUTH-LOGOUT-01: await POST /api/auth/logout (204), clear
    // every client-side identity remnant, then hard-navigate to /login
    // so a refresh can never restore the session from memory state.
    async function logout() {
        try {
            await apiFetch('/api/auth/logout', {
                method: 'POST',
                headers: { Accept: 'application/json' },
            });
        } catch {
            /* best-effort: still wipe local identity below */
        } finally {
            setUser(null);
            clearCachedUser();
            localStorage.removeItem('user_role');
            window.location.href = '/login';
        }
    }

    return (
        <AuthContext.Provider value={{ user, isAuthenticated, role, isLoading, login, logout, sessionCheck }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth(): AuthContextValue {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth must be used within an AuthProvider');
    return ctx;
}
