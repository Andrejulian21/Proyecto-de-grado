import { createContext, useContext, useState, useEffect, useCallback, type ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
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

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const navigate = useNavigate();

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
                    setIsLoading(false);
                    return;
                }
                await new Promise(r => setTimeout(r, 600));
            }

            // API failed — never fall back to browser storage as an identity source.
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

    async function logout() {
        try {
            await apiFetch('/api/auth/logout', {
                method: 'POST',
                headers: { Accept: 'application/json' },
            });
        } catch {
            /* best-effort */
        } finally {
            setUser(null);
            navigate('/login', { replace: true });
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
