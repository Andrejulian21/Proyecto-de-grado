import { AlertTriangle } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import { oauthErrorMessage } from '@/lib/auth-routes';

export function LoginInstitucional() {
    const [searchParams] = useSearchParams();
    const error = oauthErrorMessage(searchParams.get('error'));
    return (
        <div
            className="flex min-h-screen flex-col items-center justify-center bg-[#fafaf9] px-4 py-8"
            style={{ fontFamily: "'Open Sans', system-ui, -apple-system, 'Segoe UI', sans-serif" }}
        >
            <div className="w-full max-w-[460px]">
                <div className="rounded-2xl bg-[#e7e5e4] p-[2px]">
                    <div className="rounded-[22px] bg-white px-8 py-10 text-center" style={{ borderRadius: 'calc(24px - 2px)' }}>
                        <div className="mb-6 flex flex-col items-center gap-2">
                            <div className="flex h-14 w-14 items-center justify-center rounded-lg bg-[#c2410c] text-white">
                                <svg viewBox="0 0 24 24" className="h-8 w-8" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                                    <path d="M6 12v5c0 1.1 2.7 3 6 3s6-1.9 6-3v-5" />
                                </svg>
                            </div>
                            <div className="text-[13px] font-bold uppercase tracking-[0.15em] text-[#c2410c]">
                                UNAB
                            </div>
                            <div className="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#57534e]">
                                Ingeniería de Sistemas
                            </div>
                        </div>

                        <div className="mx-auto mb-4 h-[3px] w-12 rounded-full bg-[#c2410c]" aria-hidden="true" />

                        <h1 className="mb-6 text-xl font-extrabold tracking-tight text-[#1c1917]" style={{ textWrap: 'balance', lineHeight: 1.2 }}>
                            Sistema Centralizado de Proyectos de Grado
                        </h1>

                        <a
                            href="/auth/redirect"
                            className="inline-flex w-full items-center justify-center gap-3 rounded-lg bg-[#c2410c] px-5 py-3 text-sm font-semibold text-white hover:bg-[#9a330a] transition-colors"
                        >
                            <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
                                <path fill="#ffffff" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/>
                                <path fill="#ffffff" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.836.86-3.048.86-2.344 0-4.328-1.583-5.036-3.71H.957v2.332A8.997 8.997 0 0 0 9 18z"/>
                                <path fill="#ffffff" d="M3.964 10.707A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.707V4.961H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.039l3.007-2.332z"/>
                                <path fill="#ffffff" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.961L3.964 7.293C4.672 5.166 6.656 3.58 9 3.58z"/>
                            </svg>
                            Iniciar sesión con Google @unab.edu.co
                        </a>

                        {error && (
                            <div className="mt-5 flex items-center justify-center gap-3 rounded-lg border border-[#dc2626]/20 bg-[#fee2e2] px-4 py-3 text-sm text-[#7f1d1d]" role="alert">
                                <AlertTriangle className="h-5 w-5 shrink-0" />
                                <span>{error}</span>
                            </div>
                        )}

                        <hr className="my-6 border-t border-[#e5e5e5]" />

                        <a
                            href="/login/externo"
                            className="inline-flex min-h-[40px] items-center justify-center gap-2 text-[12px] font-bold uppercase tracking-[0.03em] text-[#57534e] no-underline transition-colors hover:text-[#1c1917]"
                        >
                            <span>Login para evaluadores externos</span>
                            <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M5 12h14" />
                                <path d="m12 5 7 7-7 7" />
                            </svg>
                        </a>
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

export default LoginInstitucional;
