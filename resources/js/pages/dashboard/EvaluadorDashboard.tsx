import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { useAuth } from '@/hooks/useAuth';
import { useEvaluadorEvaluaciones } from '@/hooks/useEvaluadorEvaluaciones';
import { datoNoEncontrado } from '@/lib/datoNoEncontrado';
import {
    AlertCircle,
    ArrowRight,
    CheckCircle,
    ClipboardList,
    Clock,
    FolderKanban,
    Loader2,
    Mail,
    Megaphone,
    UserRound,
} from 'lucide-react';

function formatIngreso(iso: string | null | undefined): string {
    if (!iso) return datoNoEncontrado('La fecha de ingreso');
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return datoNoEncontrado('La fecha de ingreso');
    return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'long', year: 'numeric' });
}

function roleLabel(role: string | null | undefined): string {
    if (role === 'EvaluadorExterno') return 'Evaluador Externo';
    if (!role) return datoNoEncontrado('El rol');
    return role;
}

const QUICK_LINKS = [
    {
        to: '/evaluador/evaluaciones',
        label: 'Evaluaciones',
        description: 'Gestionar proyectos asignados',
        icon: ClipboardList,
    },
    {
        to: '/recursos',
        label: 'Recursos',
        description: 'Material de apoyo',
        icon: FolderKanban,
    },
    {
        to: '/anuncios',
        label: 'Anuncios',
        description: 'Comunicados del programa',
        icon: Megaphone,
    },
] as const;

export default function EvaluadorDashboard() {
    const navigate = useNavigate();
    const { user } = useAuth();
    const { kpis, loading, error, refetch } = useEvaluadorEvaluaciones();

    const asignados = kpis?.proyectos_asignados ?? 0;
    const pendientes = kpis?.evaluaciones_pendientes ?? 0;
    const completadas = kpis?.evaluaciones_completadas ?? 0;
    const progresoPct = asignados > 0 ? Math.round((completadas / asignados) * 100) : 0;

    const displayName = user?.name?.trim() || datoNoEncontrado('El nombre completo');
    const displayEmail = user?.email?.trim() || datoNoEncontrado('El correo electrónico');

    if (loading) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Dashboard"
                    title="Panel de Evaluador"
                    subtitle="Tu resumen de actividad como evaluador externo."
                />
                <div className="flex items-center justify-center py-20">
                    <Loader2 className="h-8 w-8 animate-spin text-primary" aria-label="Cargando" />
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Dashboard"
                    title="Panel de Evaluador"
                    subtitle="Tu resumen de actividad como evaluador externo."
                />
                <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-[#fee2e2] bg-[#fee2e2] py-16 text-center">
                    <AlertCircle className="h-12 w-12 text-[#dc2626]" />
                    <div>
                        <h3 className="text-lg font-bold text-[#7f1d1d]">Error al cargar</h3>
                        <p className="mt-1 text-sm text-[#7f1d1d]">{error}</p>
                    </div>
                    <button
                        type="button"
                        onClick={refetch}
                        className="inline-flex items-center gap-2 rounded-lg bg-[#dc2626] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#b91c1c] active:scale-[0.98]"
                    >
                        Reintentar
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Dashboard"
                title={`Hola, ${user?.name?.trim() || 'evaluador'}`}
                subtitle="Resumen de tu perfil y carga de trabajo. La gestión detallada está en Evaluaciones."
                actions={
                    <button
                        type="button"
                        onClick={() => navigate('/evaluador/evaluaciones')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                    >
                        Ir a Evaluaciones
                        <ArrowRight className="h-4 w-4" />
                    </button>
                }
            />

            {/* Profile */}
            <section
                aria-labelledby="perfil-heading"
                className="rounded-xl border border-border bg-surface p-5 shadow-warm-sm"
            >
                <div className="mb-4 flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-container text-primary">
                        <UserRound className="h-5 w-5" />
                    </div>
                    <div>
                        <h2 id="perfil-heading" className="text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                            Tu perfil
                        </h2>
                        <p className="text-base font-bold text-text">{displayName}</p>
                    </div>
                </div>
                <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div className="flex items-start gap-2 text-sm">
                        <Mail className="mt-0.5 h-4 w-4 shrink-0 text-text-muted" />
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-[0.05em] text-text-muted">Correo</dt>
                            <dd className="text-text">{displayEmail}</dd>
                        </div>
                    </div>
                    <div className="flex items-start gap-2 text-sm">
                        <ClipboardList className="mt-0.5 h-4 w-4 shrink-0 text-text-muted" />
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-[0.05em] text-text-muted">Rol</dt>
                            <dd className="text-text">{roleLabel(user?.role)}</dd>
                        </div>
                    </div>
                    <div className="flex items-start gap-2 text-sm sm:col-span-2">
                        <Clock className="mt-0.5 h-4 w-4 shrink-0 text-text-muted" />
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-[0.05em] text-text-muted">
                                Fecha de ingreso
                            </dt>
                            <dd className="text-text">{formatIngreso(user?.created_at)}</dd>
                        </div>
                    </div>
                </dl>
            </section>

            {/* Activity KPIs */}
            <section aria-labelledby="actividad-heading">
                <h2 id="actividad-heading" className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                    Resumen de actividad
                </h2>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard icon={ClipboardList} label="Proyectos asignados" value={asignados} />
                    <StatCard
                        icon={CheckCircle}
                        label="Proyectos evaluados"
                        value={completadas}
                        variant="success"
                    />
                    <StatCard
                        icon={Clock}
                        label="Pendientes por evaluar"
                        value={pendientes}
                        variant="warning"
                    />
                </div>
            </section>

            {/* Workload progress */}
            <section
                aria-labelledby="carga-heading"
                className="rounded-xl border border-border bg-surface p-5 shadow-warm-sm"
            >
                <div className="mb-3 flex items-center justify-between gap-3">
                    <h2 id="carga-heading" className="text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                        Carga de trabajo
                    </h2>
                    <span className="text-sm font-bold tabular-nums text-text">{progresoPct}%</span>
                </div>
                {asignados === 0 ? (
                    <p className="text-sm text-text-muted">
                        No tienes proyectos asignados. Cuando el coordinador te asigne uno, aparecerá en Evaluaciones.
                    </p>
                ) : (
                    <>
                        <div
                            className="h-2.5 w-full overflow-hidden rounded-full bg-[#f5f5f4]"
                            role="progressbar"
                            aria-valuenow={progresoPct}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label="Porcentaje de proyectos evaluados"
                        >
                            <div
                                className="h-full rounded-full bg-primary transition-[width] duration-300"
                                style={{ width: `${progresoPct}%` }}
                            />
                        </div>
                        <p className="mt-2 text-xs text-text-muted">
                            {completadas} de {asignados} proyectos evaluados
                            {pendientes > 0 ? ` · ${pendientes} pendientes` : ''}
                        </p>
                    </>
                )}
            </section>

            {/* Quick access */}
            <section aria-labelledby="accesos-heading">
                <h2 id="accesos-heading" className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                    Accesos rápidos
                </h2>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    {QUICK_LINKS.map((link) => (
                        <button
                            key={link.to}
                            type="button"
                            onClick={() => navigate(link.to)}
                            className="flex items-start gap-3 rounded-xl border border-border bg-surface p-4 text-left shadow-warm-sm transition-colors hover:border-primary hover:bg-primary-container/40 active:scale-[0.99]"
                        >
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-container text-primary">
                                <link.icon className="h-5 w-5" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-bold text-text">{link.label}</p>
                                <p className="text-xs text-text-muted">{link.description}</p>
                            </div>
                            <ArrowRight className="mt-1 h-4 w-4 shrink-0 text-text-muted" />
                        </button>
                    ))}
                </div>
            </section>
        </div>
    );
}
