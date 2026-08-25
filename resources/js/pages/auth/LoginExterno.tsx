import { useState, type FormEvent } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import { AlertTriangle, Eye, EyeOff } from 'lucide-react';
import { apiFetch } from '@/lib/utils';

interface FieldError {
    email?: string;
    password?: string;
    general?: string;
}

/* Same role → route mapping as DashboardRouter (issue #54). */
const DASHBOARD_ROUTE_BY_ROLE: Record<string, string> = {
    estudiante: '/dashboard/estudiante',
    director: '/dashboard/director',
    coordinador: '/dashboard/coordinador',
    evaluadorexterno: '/dashboard/evaluador-externo',
};

function dashboardRouteForRole(role: unknown): string {
    const key = typeof role === 'string' ? role.toLowerCase() : 'estudiante';
    return DASHBOARD_ROUTE_BY_ROLE[key] ?? '/dashboard/estudiante';
}

export function LoginExterno() {
    const [searchParams] = useSearchParams();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [errors, setErrors] = useState<FieldError>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const redirectError = searchParams.get('error');

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setErrors({});

        const newErrors: FieldError = {};
        if (!email.trim()) newErrors.email = 'El correo electrónico es obligatorio.';
        if (!password) newErrors.password = 'La contraseña es obligatoria.';

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setIsSubmitting(true);

        try {
            const res = await apiFetch('/api/auth/externo/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ email: email.trim(), password }),
            });

            if (res.ok) {
                const data = await res.json();
                sessionStorage.setItem('auth_user', JSON.stringify(data.user));
                window.location.href = dashboardRouteForRole(data?.user?.role);
                return;
            }

            if (res.status === 401) {
                setErrors({ general: 'Credenciales inválidas. Verifica tu correo y contraseña.' });
            } else if (res.status === 423) {
                setErrors({ general: 'Cuenta bloqueada. Contacta al administrador del sistema.' });
            } else {
                const body = await res.json().catch(() => null);
                setErrors({
                    general: body?.message || 'Ocurrió un error al iniciar sesión. Intenta de nuevo.',
                });
            }
        } catch {
            setErrors({ general: 'Error de conexión. Verifica tu red e intenta de nuevo.' });
        } finally {
            setIsSubmitting(false);
        }
    }

    return (
        <div
            className="flex min-h-screen flex-col items-center justify-center bg-[#fafaf9] px-4 py-8"
            style={{ fontFamily: "'Open Sans', system-ui, -apple-system, 'Segoe UI', sans-serif" }}
        >
            <div className="w-full max-w-[460px]">
                <div className="rounded-2xl bg-[#e7e5e4] p-[2px]">
                    <div className="rounded-[22px] bg-white px-8 py-10 text-center" style={{ borderRadius: 'calc(24px - 2px)' }}>
                        <div className="mb-5 flex flex-col items-center gap-2">
                            <div className="flex h-14 w-14 items-center justify-center rounded-lg bg-[#4f46e5] text-white">
                                <svg viewBox="0 0 24 24" className="h-8 w-8" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M6 9a6 6 0 0 1 12 0v6a6 6 0 0 1-12 0V9Z" />
                                    <path d="M12 3v3" />
                                    <path d="M8 21h8" />
                                </svg>
                            </div>
                            <div className="text-[13px] font-bold uppercase tracking-[0.15em] text-[#c2410c]">
                                UNAB
                            </div>
                            <div className="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#57534e]">
                                Ingeniería de Sistemas
                            </div>
                        </div>

                        <div className="mx-auto mb-4 h-[3px] w-12 rounded-full bg-[#4f46e5]" aria-hidden="true" />

                        <h1 className="mb-1 text-xl font-extrabold tracking-tight text-[#1c1917]" style={{ lineHeight: 1.2, textWrap: 'balance' }}>
                            Evaluadores Externos
                        </h1>
                        <p className="mb-7 text-sm text-[#57534e]">
                            Acceso al Sistema Centralizado de Proyectos
                        </p>

                        {redirectError && (
                            <div className="mb-4 flex items-start gap-3 rounded-lg border border-[#dc2626]/20 bg-[#fee2e2] p-4 text-left text-sm text-[#7f1d1d]" role="alert">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{decodeURIComponent(redirectError)}</span>
                            </div>
                        )}

                        {errors.general && (
                            <div className="mb-4 flex items-start gap-3 rounded-lg border border-[#dc2626]/20 bg-[#fee2e2] p-4 text-left text-sm text-[#7f1d1d]" role="alert">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{errors.general}</span>
                            </div>
                        )}

                        <form onSubmit={handleSubmit} className="flex flex-col gap-6 text-left" noValidate>
                            <div className="flex flex-col gap-1.5">
                                <label htmlFor="username" className="text-sm font-semibold text-[#1c1917]">
                                    Usuario o Correo Electrónico <span className="text-[#dc2626]">*</span>
                                </label>
                                <div className="relative">
                                    <svg viewBox="0 0 24 24" className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-[#57534e]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                                        <circle cx="12" cy="7" r="4" />
                                    </svg>
                                    <input
                                        id="username"
                                        name="username"
                                        type="text"
                                        autoComplete="username"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        className="w-full rounded-lg border border-[#e5e5e5] bg-white py-2.5 pl-10 pr-3 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                        placeholder="usuario@institucion.edu"
                                    />
                                </div>
                                {errors.email && (
                                    <p className="text-xs font-semibold text-[#dc2626]">{errors.email}</p>
                                )}
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <label htmlFor="password" className="text-sm font-semibold text-[#1c1917]">
                                    Contraseña <span className="text-[#dc2626]">*</span>
                                </label>
                                <div className="relative">
                                    <svg viewBox="0 0 24 24" className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-[#57534e]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                    </svg>
                                    <input
                                        id="password"
                                        name="password"
                                        type={showPassword ? 'text' : 'password'}
                                        autoComplete="current-password"
                                        value={password}
                                        onChange={(e) => setPassword(e.target.value)}
                                        className="w-full rounded-lg border border-[#e5e5e5] bg-white py-2.5 pl-10 pr-10 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                        placeholder="••••••••"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-[#57534e] hover:text-[#1c1917] transition-colors"
                                        tabIndex={-1}
                                    >
                                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                </div>
                                {errors.password && (
                                    <p className="text-xs font-semibold text-[#dc2626]">{errors.password}</p>
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={isSubmitting}
                                className="btn--primary btn--block btn--lg inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-5 py-3 text-sm font-semibold text-white hover:bg-[#9a330a] transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                                    <polyline points="10 17 15 12 10 7" />
                                    <line x1="15" y1="12" x2="3" y2="12" />
                                </svg>
                                {isSubmitting ? 'Ingresando…' : 'Iniciar Sesión'}
                            </button>
                        </form>

                        <hr className="my-6 border-t border-[#e5e5e5]" />

                        <Link
                            to="/login"
                            className="inline-flex min-h-[40px] items-center justify-center gap-2 text-[12px] font-bold uppercase tracking-[0.03em] text-[#57534e] no-underline transition-colors hover:text-[#1c1917]"
                        >
                            <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M19 12H5" />
                                <path d="m12 19-7-7 7-7" />
                            </svg>
                            <span>Volver al inicio de sesión institucional</span>
                        </Link>
                    </div>
                </div>

                <footer className="mt-8 max-w-[460px] text-center">
                    <p className="text-xs leading-relaxed text-[#57534e]" style={{ textWrap: 'pretty' }}>
                        Programa de Ingeniería de Sistemas — Universidad Autónoma de Bucaramanga.<br />
                        Uso exclusivo para gestión académica institucional.
                    </p>
                </footer>
            </div>
        </div>
    );
}

export default LoginExterno;
