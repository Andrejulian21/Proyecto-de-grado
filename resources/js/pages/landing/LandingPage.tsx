import { useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
    GraduationCap,
    UserCheck,
    ClipboardList,
    Star,
    Shield,
    ChevronRight,
    BookOpen,
    Loader2,
} from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';

const ROLES = [
    { id: 'estudiante', label: 'Estudiante', icon: GraduationCap, desc: 'Gestiona tu proyecto de grado, entregas y bitácoras' },
    { id: 'director', label: 'Director', icon: UserCheck, desc: 'Supervisa proyectos, revisa entregas y firma bitácoras' },
    { id: 'coordinador', label: 'Coordinador', icon: ClipboardList, desc: 'Administra proyectos, usuarios y reportes académicos' },
    { id: 'evaluador', label: 'Evaluador', icon: Star, desc: 'Evalúa proyectos y genera calificaciones' },
    { id: 'admin', label: 'Admin', icon: Shield, desc: 'Gestiona el semestre, directores y configuraciones' },
];

export default function LandingPage() {
    const { isAuthenticated, isLoading, role } = useAuth();
    const navigate = useNavigate();

    useEffect(() => {
        if (isLoading) return;
        if (isAuthenticated && role) {
            const redirectMap: Record<string, string> = {
                Estudiante: '/dashboard/estudiante',
                Director: '/dashboard/director',
                Coordinador: '/dashboard/coordinador',
                EvaluadorExterno: '/dashboard/evaluador-externo',
            };
            navigate(redirectMap[role] ?? '/', { replace: true });
        }
    }, [isAuthenticated, isLoading, role, navigate]);

    if (isLoading) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-[#fafaf9]">
                <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
            </div>
        );
    }

    if (isAuthenticated) return null;

    return (
        <div className="flex min-h-screen flex-col bg-[#fafaf9]">
            {/* ── Top bar ── */}
            <header className="flex items-center justify-between border-b border-[#e5e5e5] bg-white px-6 py-3 lg:px-12">
                <div className="flex items-center gap-3">
                    <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#c2410c]">
                        <svg viewBox="0 0 40 40" className="h-5 w-5 text-white" fill="none" stroke="currentColor" strokeWidth="2">
                            <path d="M20 4L4 12v6c0 8 6 16 16 20 10-4 16-12 16-20v-6L20 4z" />
                            <path d="M14 18l4 4 8-8" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                    </div>
                    <div>
                        <p className="text-sm font-bold leading-tight text-[#1c1917]">UNAB</p>
                        <p className="text-[10px] leading-tight text-[#57534e]">Sistema de Proyectos de Grado</p>
                    </div>
                </div>
                <Link
                    to="/login"
                    className="inline-flex min-h-[40px] items-center gap-1.5 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                >
                    Iniciar sesión
                    <ChevronRight className="h-4 w-4" />
                </Link>
            </header>

            {/* ── Hero ── */}
            <section className="flex flex-col items-center px-6 pb-12 pt-16 text-center lg:px-12 lg:pt-24">
                <span className="inline-flex w-fit items-center gap-1 rounded-full bg-[#fed7aa] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.05em] text-[#c2410c]">
                    Plataforma oficial UNAB
                </span>
                <h1 className="mt-5 max-w-3xl text-balance text-[clamp(1.75rem,5vw,3rem)] font-extrabold leading-[1.1] tracking-tight text-[#1c1917]">
                    Sistema Centralizado de Proyectos de Grado
                </h1>
                <p className="mt-4 max-w-xl text-balance text-base leading-relaxed text-[#57534e]">
                    Gestiona el ciclo de vida completo de los proyectos de grado de Ingeniería de Sistemas: desde la inscripción hasta la sustentación final.
                </p>
                <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <Link
                        to="/login"
                        className="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-[#c2410c] px-6 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                    >
                        <GraduationCap className="h-4 w-4" />
                        Ingresar al sistema
                    </Link>
                    <a
                        href="#roles"
                        className="inline-flex min-h-[44px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-6 py-2.5 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <BookOpen className="h-4 w-4" />
                        Conocer más
                    </a>
                </div>
            </section>

            {/* ── Role cards ── */}
            <section id="roles" className="px-6 pb-16 lg:px-12">
                <div className="mx-auto max-w-6xl">
                    <h2 className="text-center text-sm font-bold uppercase tracking-[0.05em] text-[#57534e]">
                        ¿Cuál es tu rol?
                    </h2>
                    <p className="mt-2 text-center text-base text-[#57534e]">
                        Selecciona tu perfil para acceder a las funcionalidades correspondientes
                    </p>
                    <div className="mt-8 grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-5">
                        {ROLES.map((role) => {
                            const Icon = role.icon;
                            return (
                                <Link
                                    key={role.id}
                                    to="/login"
                                    className="flex flex-col items-center gap-3 rounded-xl border border-[#e5e5e5] bg-white p-6 text-center shadow-[0_1px_2px_rgba(28,25,23,0.05)] transition-colors hover:border-[#c2410c] hover:shadow-[0_4px_16px_rgba(28,25,23,0.10)] active:scale-[0.98]"
                                    aria-label={`Ingresar como ${role.label}`}
                                >
                                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#fed7aa]">
                                        <Icon className="h-6 w-6 text-[#c2410c]" />
                                    </div>
                                    <div>
                                        <h3 className="text-sm font-bold text-[#1c1917]">{role.label}</h3>
                                        <p className="mt-1 text-xs leading-relaxed text-[#78716c] text-pretty">
                                            {role.desc}
                                        </p>
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* ── Footer ── */}
            <footer className="mt-auto border-t border-[#e5e5e5] bg-white px-6 py-8 lg:px-12">
                <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 text-center sm:flex-row sm:text-left">
                    <div className="flex flex-col gap-1">
                        <p className="text-sm font-bold text-[#1c1917]">Universidad Autónoma de Bucaramanga</p>
                        <p className="text-xs text-[#78716c]">
                            Facultad de Ingeniería — Programa de Ingeniería de Sistemas
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-4 text-xs text-[#57534e]">
                        <a href="#" className="transition-colors hover:text-[#c2410c]">Términos de uso</a>
                        <a href="#" className="transition-colors hover:text-[#c2410c]">Política de privacidad</a>
                        <a href="#" className="transition-colors hover:text-[#c2410c]">Contacto</a>
                    </div>
                </div>
                <div className="mx-auto mt-4 max-w-6xl border-t border-[#e5e5e5] pt-4 text-center text-[11px] text-[#78716c]">
                    &copy; {new Date().getFullYear()} Universidad Autónoma de Bucaramanga — Todos los derechos reservados.
                </div>
            </footer>
        </div>
    );
}
