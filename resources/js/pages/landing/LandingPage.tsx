import { useEffect, useRef, type ReactNode } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
    GraduationCap,
    UserCheck,
    ClipboardList,
    Star,
    Shield,
    ChevronRight,
    BookOpen,
    Fingerprint,
    FileUp,
    Sparkles,
    ClipboardCheck,
    FileSpreadsheet,
    Check,
    ArrowRight,
    PenLine,
    type LucideIcon,
} from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';

interface RoleCard {
    id: string;
    label: string;
    icon: LucideIcon;
    desc: string;
    features: string[];
    featured: boolean;
}

interface FlowStep {
    index: string;
    title: string;
    desc: string;
    icon: LucideIcon;
    badge: string;
    visual: ReactNode;
}

const ROLES: RoleCard[] = [
    {
        id: 'estudiante',
        label: 'Estudiante',
        icon: GraduationCap,
        desc: 'Tu proyecto, tus entregas y tus bitácoras en un solo panel.',
        features: [
            'Inscribe tu proyecto y conforma tu equipo',
            'Sube entregas versionadas con comentarios',
            'Registra bitácoras y solicita la firma',
        ],
        featured: true,
    },
    {
        id: 'director',
        label: 'Director',
        icon: UserCheck,
        desc: 'Acompaña cada proyecto con seguimiento cercano y firma.',
        features: [
            'Supervisa el avance de tus dirigidos',
            'Revisa entregas y deja retroalimentación',
            'Firma bitácoras con código TOTP',
        ],
        featured: true,
    },
    {
        id: 'coordinador',
        label: 'Coordinador',
        icon: ClipboardList,
        desc: 'Administra proyectos, usuarios y reportes académicos.',
        features: [
            'Gestiona proyectos y usuarios del programa',
            'Publica anuncios a la comunidad',
            'Descarga reportes en Excel',
        ],
        featured: false,
    },
    {
        id: 'evaluador',
        label: 'Evaluador',
        icon: Star,
        desc: 'Evalúa con rúbricas claras y registra el acta.',
        features: [
            'Consulta tus proyectos asignados',
            'Califica con rúbricas por criterio',
            'Registra el acta de sustentación',
        ],
        featured: false,
    },
    {
        id: 'admin',
        label: 'Admin',
        icon: Shield,
        desc: 'Configura el semestre y la operación general.',
        features: [
            'Configura semestres y parámetros',
            'Asigna directores y permisos',
            'Supervisa la operación del sistema',
        ],
        featured: false,
    },
];

const DIFFERENTIALS: { icon: LucideIcon; title: string; desc: string }[] = [
    { icon: Fingerprint, title: 'Bitácoras firmadas', desc: 'Códigos TOTP que respaldan cada asesoría' },
    { icon: Sparkles, title: 'Orientación con IA', desc: 'Acompañamiento para redactar y avanzar' },
    { icon: ClipboardCheck, title: 'Rúbricas por criterio', desc: 'Evaluación transparente y comparable' },
    { icon: FileSpreadsheet, title: 'Reportes en Excel', desc: 'Datos listos para la coordinación' },
];

const STEPS: FlowStep[] = [
    {
        index: '01',
        title: 'Inscribe tu proyecto',
        desc: 'Registra el título, el equipo y la propuesta inicial. La coordinación revisa la inscripción y asigna un director para iniciar el acompañamiento.',
        icon: PenLine,
        badge: 'Inscripción guiada',
        visual: (
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-warm-sm">
                <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-[#78716c]">Nueva inscripción</p>
                <div className="mt-3 space-y-2.5">
                    <div className="rounded-lg border border-[#e5e5e5] bg-[#fafaf9] px-3 py-2">
                        <p className="text-[11px] text-[#78716c]">Título del proyecto</p>
                        <p className="text-sm font-semibold text-[#1c1917]">Sistema de riego automatizado</p>
                    </div>
                    <div className="flex gap-2">
                        <span className="rounded-full bg-[#fed7aa] px-3 py-1 text-xs font-semibold text-[#c2410c]">Ana</span>
                        <span className="rounded-full bg-[#fed7aa] px-3 py-1 text-xs font-semibold text-[#c2410c]">Luis</span>
                        <span className="rounded-full border border-dashed border-[#d6d3d1] px-3 py-1 text-xs font-semibold text-[#78716c]">Agregar</span>
                    </div>
                    <div className="flex items-center gap-2 rounded-lg bg-[#dcfce7] px-3 py-2">
                        <Check className="h-4 w-4 shrink-0 text-[#14532d]" />
                        <p className="text-xs font-semibold text-[#14532d]">Propuesta enviada a coordinación</p>
                    </div>
                </div>
            </div>
        ),
    },
    {
        index: '02',
        title: 'Entrega versiones con trazabilidad',
        desc: 'Sube cada entrega con número de versión y comentarios. El historial completo queda disponible para tu director y para los evaluadores.',
        icon: FileUp,
        badge: 'Versionado automático',
        visual: (
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-warm-sm">
                <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-[#78716c]">Historial de entregas</p>
                <ul className="mt-3 space-y-2">
                    {[
                        { v: 'v3', label: 'Capítulo final corregido', current: true },
                        { v: 'v2', label: 'Marco teórico ampliado', current: false },
                        { v: 'v1', label: 'Anteproyecto inicial', current: false },
                    ].map((item) => (
                        <li
                            key={item.v}
                            className={`flex items-center gap-3 rounded-lg border px-3 py-2 ${
                                item.current ? 'border-[#c2410c]/40 bg-[#fed7aa]/30' : 'border-[#e5e5e5] bg-[#fafaf9]'
                            }`}
                        >
                            <span
                                className={`rounded-md px-2 py-0.5 text-xs font-bold ${
                                    item.current ? 'bg-[#c2410c] text-white' : 'bg-[#e7e5e4] text-[#57534e]'
                                }`}
                            >
                                {item.v}
                            </span>
                            <span className="text-sm text-[#1c1917]">{item.label}</span>
                        </li>
                    ))}
                </ul>
            </div>
        ),
    },
    {
        index: '03',
        title: 'Firma bitácoras con código TOTP',
        desc: 'Cada asesoría queda registrada en una bitácora que tu director firma con un código temporal. Nada se pierde y todo queda respaldado.',
        icon: Fingerprint,
        badge: 'Firma TOTP',
        visual: (
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-warm-sm">
                <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-[#78716c]">Bitácora de asesoría</p>
                <div className="mt-3 flex items-center justify-center gap-2">
                    {['4', '8', '1', '5', '9', '2'].map((digit) => (
                        <span
                            key={digit}
                            className="flex h-10 w-8 items-center justify-center rounded-lg border border-[#e5e5e5] bg-[#fafaf9] text-base font-bold text-[#1c1917]"
                        >
                            {digit}
                        </span>
                    ))}
                </div>
                <div className="mt-3 flex items-center gap-2 rounded-lg bg-[#dcfce7] px-3 py-2">
                    <Fingerprint className="h-4 w-4 shrink-0 text-[#14532d]" />
                    <p className="text-xs font-semibold text-[#14532d]">Código verificado, bitácora firmada</p>
                </div>
            </div>
        ),
    },
    {
        index: '04',
        title: 'Recibe evaluación por rúbrica',
        desc: 'Los evaluadores califican con rúbricas por criterio y la nota final queda registrada en el sistema, lista para los reportes de coordinación.',
        icon: ClipboardCheck,
        badge: 'Rúbricas por criterio',
        visual: (
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-warm-sm">
                <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-[#78716c]">Rúbrica de sustentación</p>
                <div className="mt-3 space-y-3">
                    {[
                        { label: 'Contenido técnico', width: 'w-4/5' },
                        { label: 'Presentación', width: 'w-3/5' },
                        { label: 'Documento final', width: 'w-11/12' },
                    ].map((row) => (
                        <div key={row.label}>
                            <p className="text-xs font-semibold text-[#57534e]">{row.label}</p>
                            <div className="mt-1 h-2 overflow-hidden rounded-full bg-[#f5f5f4]">
                                <div className={`h-full rounded-full bg-[#c2410c] ${row.width}`} />
                            </div>
                        </div>
                    ))}
                    <div className="flex items-center gap-2 rounded-lg bg-[#fed7aa]/40 px-3 py-2">
                        <Check className="h-4 w-4 shrink-0 text-[#c2410c]" />
                        <p className="text-xs font-semibold text-[#c2410c]">Nota registrada en el sistema</p>
                    </div>
                </div>
            </div>
        ),
    },
];

/**
 * Reveal on scroll with IntersectionObserver and CSS opacity/transform only.
 * SSR safe: the hidden state applies only after JS adds `reveal-on` to the
 * root, so server output and no-JS output stay fully visible. Reduced motion
 * users see everything immediately with no animation.
 */
function useLandingReveal() {
    const rootRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const root = rootRef.current;
        if (!root) return;
        const targets = Array.from(root.querySelectorAll<HTMLElement>('[data-reveal]'));
        if (
            window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
            typeof IntersectionObserver === 'undefined'
        ) {
            targets.forEach((el) => el.classList.add('is-visible'));
            return;
        }
        root.classList.add('reveal-on');
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const el = entry.target as HTMLElement;
                        const delay = el.getAttribute('data-reveal-delay');
                        if (delay) el.style.transitionDelay = `${delay}ms`;
                        el.classList.add('is-visible');
                        observer.unobserve(el);
                    }
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -6% 0px' },
        );
        targets.forEach((el) => observer.observe(el));
        return () => observer.disconnect();
    }, []);

    return rootRef;
}

export default function LandingPage() {
    const { isAuthenticated, isLoading, role } = useAuth();
    const navigate = useNavigate();
    const rootRef = useLandingReveal();

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

    // The landing is public: it must render as soon as the bundle parses. Gating
    // it on the session check made every anonymous visitor stare at a spinner
    // while useAuth retried /api/auth/user with 600ms sleeps. The redirect for an
    // already-authenticated visitor still happens in the effect above.
    if (isAuthenticated) return null;

    return (
        <div ref={rootRef} className="flex min-h-screen flex-col bg-[#fafaf9]">
            <style>{`
                .reveal-on [data-reveal] {
                    opacity: 0;
                    transform: translateY(22px);
                    transition: opacity 0.55s ease-out, transform 0.65s cubic-bezier(0.22, 1, 0.36, 1);
                    will-change: opacity, transform;
                }
                .reveal-on [data-reveal].is-visible {
                    opacity: 1;
                    transform: translateY(0);
                }
                @media (prefers-reduced-motion: reduce) {
                    .reveal-on [data-reveal] {
                        opacity: 1 !important;
                        transform: none !important;
                        transition: none !important;
                    }
                }
            `}</style>

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

            {/* ── Hero: editorial asymmetric ── */}
            <section className="relative overflow-hidden">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0"
                    style={{
                        background:
                            'radial-gradient(52rem 30rem at 82% 18%, rgba(254,215,170,0.55), transparent 62%), radial-gradient(36rem 24rem at 8% 90%, rgba(254,215,170,0.35), transparent 60%)',
                    }}
                />
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 opacity-60"
                    style={{
                        backgroundImage: 'radial-gradient(circle, #e7e5e4 1px, transparent 1px)',
                        backgroundSize: '26px 26px',
                        maskImage: 'radial-gradient(46rem 26rem at 70% 20%, black 30%, transparent 75%)',
                        WebkitMaskImage: 'radial-gradient(46rem 26rem at 70% 20%, black 30%, transparent 75%)',
                    }}
                />
                <div className="relative mx-auto grid max-w-6xl items-center gap-12 px-6 pb-16 pt-14 lg:grid-cols-12 lg:gap-8 lg:px-12 lg:pb-24 lg:pt-20">
                    <div className="lg:col-span-6" data-reveal>
                        <span className="inline-flex w-fit items-center gap-1 rounded-full bg-[#fed7aa] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.05em] text-[#c2410c]">
                            Plataforma oficial UNAB
                        </span>
                        <h1 className="mt-5 max-w-xl text-balance text-[clamp(2rem,4.6vw,3.25rem)] font-extrabold leading-[1.08] tracking-tight text-[#1c1917]">
                            Del anteproyecto a la sustentación, en un solo lugar
                        </h1>
                        <p className="mt-5 max-w-xl text-balance text-base leading-relaxed text-[#57534e] lg:text-lg">
                            La plataforma de Ingeniería de Sistemas para inscribir proyectos, entregar versiones,
                            firmar bitácoras y evaluar resultados, con acompañamiento en cada etapa.
                        </p>
                        <div className="mt-8 flex flex-wrap items-center gap-3">
                            <Link
                                to="/login"
                                className="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-[#c2410c] px-6 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                            >
                                <GraduationCap className="h-4 w-4" />
                                Ingresar al sistema
                            </Link>
                            <a
                                href="#como-funciona"
                                className="inline-flex min-h-[44px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-6 py-2.5 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:text-[#c2410c] active:scale-[0.98]"
                            >
                                <BookOpen className="h-4 w-4" />
                                Cómo funciona
                            </a>
                        </div>
                        <p className="mt-5 max-w-md text-xs leading-relaxed text-[#78716c]">
                            Accede con tu cuenta institucional. La plataforma te lleva a tu panel según tu rol.
                        </p>
                    </div>

                    {/* Dashboard mock built with system divs */}
                    <div className="relative lg:col-span-6" data-reveal data-reveal-delay="120">
                        <div className="relative overflow-hidden rounded-xl border border-[#e5e5e5] bg-white shadow-warm-lg">
                            <div className="flex items-center gap-2 border-b border-[#e5e5e5] bg-[#fafaf9] px-4 py-2.5">
                                <span className="h-2.5 w-2.5 rounded-full bg-[#e7e5e4]" />
                                <span className="h-2.5 w-2.5 rounded-full bg-[#e7e5e4]" />
                                <span className="h-2.5 w-2.5 rounded-full bg-[#fed7aa]" />
                                <span className="ml-2 hidden rounded-full bg-white px-3 py-1 text-[11px] font-semibold text-[#78716c] sm:inline">
                                    Panel del estudiante
                                </span>
                            </div>
                            <div className="flex gap-4 p-4 sm:p-5">
                                <div aria-hidden="true" className="hidden w-14 shrink-0 flex-col items-center gap-2.5 pt-1 sm:flex">
                                    <span className="h-8 w-8 rounded-lg bg-[#c2410c]" />
                                    <span className="h-8 w-8 rounded-lg bg-[#f5f5f4]" />
                                    <span className="h-8 w-8 rounded-lg bg-[#f5f5f4]" />
                                    <span className="h-8 w-8 rounded-lg bg-[#fed7aa]" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-sm font-bold text-[#1c1917]">Proyecto PG-2026-014</p>
                                        <span className="rounded-full bg-[#fed7aa] px-2.5 py-0.5 text-[11px] font-bold text-[#c2410c]">
                                            En curso
                                        </span>
                                    </div>
                                    <div className="mt-3 h-2 overflow-hidden rounded-full bg-[#f5f5f4]">
                                        <div className="h-full w-2/3 rounded-full bg-[#c2410c]" />
                                    </div>
                                    <ul className="mt-4 space-y-2">
                                        <li className="flex items-center gap-2.5 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] px-3 py-2">
                                            <FileUp className="h-4 w-4 shrink-0 text-[#c2410c]" />
                                            <p className="truncate text-xs font-semibold text-[#1c1917]">Anteproyecto aprobado por tu director</p>
                                        </li>
                                        <li className="flex items-center gap-2.5 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] px-3 py-2">
                                            <Fingerprint className="h-4 w-4 shrink-0 text-[#c2410c]" />
                                            <p className="truncate text-xs font-semibold text-[#1c1917]">Bitácora de asesoría firmada</p>
                                        </li>
                                        <li className="flex items-center gap-2.5 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] px-3 py-2">
                                            <ClipboardCheck className="h-4 w-4 shrink-0 text-[#c2410c]" />
                                            <p className="truncate text-xs font-semibold text-[#1c1917]">Sustentación calificada por jurados</p>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div className="mt-3 hidden grid-cols-2 gap-3 md:grid">
                        <div className="flex flex-1 items-center gap-2.5 rounded-xl border border-[#e5e5e5] bg-white px-4 py-3 shadow-warm-md">
                            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#dcfce7]">
                                <Check className="h-5 w-5 text-[#14532d]" />
                            </span>
                            <span>
                                <span className="block text-xs font-bold text-[#1c1917]">Código TOTP verificado</span>
                                <span className="block text-[11px] text-[#78716c]">Firma registrada hoy</span>
                            </span>
                        </div>
                        <div className="flex flex-1 items-center gap-2.5 rounded-xl border border-[#e5e5e5] bg-white px-4 py-3 shadow-warm-md">
                            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#fed7aa]">
                                <Sparkles className="h-5 w-5 text-[#c2410c]" />
                            </span>
                            <span>
                                <span className="block text-xs font-bold text-[#1c1917]">Orientación IA</span>
                                <span className="block text-[11px] text-[#78716c]">Sugerencia lista para ti</span>
                            </span>
                        </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ── How it works: alternating numbered steps ── */}
            <section id="como-funciona" className="scroll-mt-6 px-6 py-16 lg:px-12 lg:py-24">
                <div className="mx-auto max-w-6xl">
                    <div className="max-w-2xl" data-reveal>
                        <p className="text-xs font-bold uppercase tracking-[0.08em] text-[#c2410c]">Cómo funciona</p>
                        <h2 className="mt-3 text-balance text-3xl font-extrabold tracking-tight text-[#1c1917] lg:text-4xl">
                            Un flujo claro, de principio a fin
                        </h2>
                        <p className="mt-4 max-w-xl text-base leading-relaxed text-[#57534e]">
                            Cuatro etapas conectadas que acompañan al estudiante y al director durante todo el proyecto.
                        </p>
                    </div>
                    <ol className="mt-12 space-y-12 lg:mt-16 lg:space-y-20">
                        {STEPS.map((step, position) => {
                            const StepIcon = step.icon;
                            const textFirst = position % 2 === 0;
                            return (
                                <li
                                    key={step.index}
                                    data-reveal
                                    className="relative grid items-center gap-8 lg:grid-cols-12 lg:gap-12"
                                >
                                    <span
                                        aria-hidden="true"
                                        className="pointer-events-none absolute -top-10 select-none text-[5rem] font-extrabold leading-none text-[#f5f5f4] lg:text-[7rem]"
                                    >
                                        {step.index}
                                    </span>
                                    <div className={`relative lg:col-span-6 ${textFirst ? '' : 'lg:order-2'}`}>
                                        <span className="inline-flex items-center gap-2 rounded-full bg-[#fed7aa]/50 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.06em] text-[#c2410c]">
                                            <StepIcon className="h-3.5 w-3.5" />
                                            {step.badge}
                                        </span>
                                        <h3 className="mt-4 text-balance text-2xl font-bold tracking-tight text-[#1c1917]">
                                            {step.title}
                                        </h3>
                                        <p className="mt-3 max-w-lg text-base leading-relaxed text-[#57534e]">{step.desc}</p>
                                    </div>
                                    <div className={`relative lg:col-span-5 ${textFirst ? 'lg:col-start-8' : 'lg:order-1'}`}>
                                        {step.visual}
                                    </div>
                                </li>
                            );
                        })}
                    </ol>

                    {/* Differentials strip integrated in the flow */}
                    <div
                        data-reveal
                        className="mt-14 grid grid-cols-1 overflow-hidden rounded-xl border border-[#e5e5e5] bg-white shadow-warm-sm sm:grid-cols-2 sm:divide-x sm:divide-[#e5e5e5] lg:mt-20 lg:grid-cols-4"
                    >
                        {DIFFERENTIALS.map((item) => {
                            const ItemIcon = item.icon;
                            return (
                                <div key={item.title} className="flex items-start gap-3 border-t border-[#e5e5e5] p-5 first:border-t-0 sm:border-t-0 sm:[&:nth-child(n+3)]:border-t lg:[&:nth-child(n+3)]:border-t-0">
                                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#fed7aa]">
                                        <ItemIcon className="h-5 w-5 text-[#c2410c]" />
                                    </span>
                                    <span>
                                        <span className="block text-sm font-bold text-[#1c1917]">{item.title}</span>
                                        <span className="mt-0.5 block text-xs leading-relaxed text-[#57534e]">{item.desc}</span>
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* ── Roles with hierarchy ── */}
            <section id="roles" className="scroll-mt-6 bg-white px-6 py-16 lg:px-12 lg:py-24">
                <div className="mx-auto max-w-6xl">
                    <div className="max-w-2xl" data-reveal>
                        <p className="text-xs font-bold uppercase tracking-[0.08em] text-[#c2410c]">Roles</p>
                        <h2 className="mt-3 text-balance text-3xl font-extrabold tracking-tight text-[#1c1917] lg:text-4xl">
                            Cada rol tiene su espacio
                        </h2>
                        <p className="mt-4 max-w-xl text-base leading-relaxed text-[#57534e]">
                            Ingresa con tu cuenta y la plataforma te lleva a tu panel automáticamente.
                        </p>
                    </div>
                    <div className="mt-10 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-6 lg:gap-5">
                        {ROLES.map((roleItem) => {
                            const Icon = roleItem.icon;
                            return (
                                <Link
                                    key={roleItem.id}
                                    to="/login"
                                    data-reveal
                                    aria-label={`Ingresar como ${roleItem.label}`}
                                    className={`group flex flex-col rounded-xl border bg-[#fafaf9] transition-all hover:-translate-y-0.5 hover:shadow-warm-md active:scale-[0.99] ${
                                        roleItem.featured
                                            ? 'border-[#c2410c]/30 p-7 shadow-warm-sm md:col-span-1 lg:col-span-3'
                                            : 'border-[#e5e5e5] p-6 hover:border-[#c2410c]/40 lg:col-span-2'
                                    }`}
                                >
                                    <div className="flex items-center gap-3">
                                        <span
                                            className={`flex items-center justify-center rounded-xl ${
                                                roleItem.featured
                                                    ? 'h-12 w-12 bg-[#c2410c]'
                                                    : 'h-10 w-10 bg-[#fed7aa]'
                                            }`}
                                        >
                                            <Icon className={`h-5 w-5 ${roleItem.featured ? 'text-white' : 'text-[#c2410c]'}`} />
                                        </span>
                                        <h3 className={`${roleItem.featured ? 'text-lg' : 'text-base'} font-bold text-[#1c1917]`}>
                                            {roleItem.label}
                                        </h3>
                                    </div>
                                    <p className="mt-3 text-sm leading-relaxed text-[#57534e]">{roleItem.desc}</p>
                                    <ul className="mt-4 space-y-2">
                                        {roleItem.features.map((feature) => (
                                            <li key={feature} className="flex items-start gap-2 text-[13px] leading-relaxed text-[#57534e]">
                                                <Check className="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#c2410c]" />
                                                {feature}
                                            </li>
                                        ))}
                                    </ul>
                                    <span className="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-[#c2410c]">
                                        Ingresar como {roleItem.label}
                                        <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                    </span>
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* ── Final CTA ── */}
            <section className="px-6 py-16 lg:px-12 lg:py-24">
                <div
                    data-reveal
                    className="mx-auto grid max-w-6xl items-center gap-8 rounded-2xl bg-[#1c1917] px-6 py-12 sm:px-10 lg:grid-cols-12 lg:px-14 lg:py-16"
                >
                    <div className="lg:col-span-8">
                        <h2 className="max-w-xl text-balance text-3xl font-extrabold tracking-tight text-[#fafaf9] lg:text-4xl">
                            Empieza hoy con tu proyecto de grado
                        </h2>
                        <p className="mt-4 max-w-xl text-base leading-relaxed text-[#d6d3d1]">
                            Ingresa con tu cuenta institucional y continúa donde quedó tu proyecto, o inscribe
                            uno nuevo con tu equipo.
                        </p>
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row lg:col-span-4 lg:flex-col lg:items-stretch">
                        <Link
                            to="/login"
                            className="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-6 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                        >
                            <GraduationCap className="h-4 w-4" />
                            Ingresar al sistema
                        </Link>
                        <a
                            href="#como-funciona"
                            className="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg border border-[#57534e] px-6 py-2.5 text-sm font-semibold text-[#fafaf9] transition-colors hover:border-[#fed7aa] hover:text-[#fed7aa] active:scale-[0.98]"
                        >
                            Ver cómo funciona
                        </a>
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
