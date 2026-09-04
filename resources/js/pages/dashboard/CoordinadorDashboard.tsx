import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { useKpis } from '@/hooks/useKpis';
import { useProyectos, type Proyecto } from '@/hooks/useProyectos';
import { useAlertas, type Alerta } from '@/hooks/useAlertas';
import {
    ClipboardList,
    TrendingDown,
    Bell,
    TrendingUp,
    Eye,
    AlertTriangle,
    RefreshCw,
    Clock,
    AlertCircle,
    FileWarning,
} from 'lucide-react';

/* ── Columns ── */

interface ProjectRow {
    id: number;
    code: string;
    title: string;
    students: string;
    director: string;
    phase: string;
    status: string;
    alertCount: number;
    _navigate: (id: number) => void;
}

const projectColumns: Column<ProjectRow>[] = [
    {
        key: 'code',
        label: 'Código',
        className: 'font-medium text-text',
    },
    {
        key: 'title',
        label: 'Título',
        className: 'max-w-[200px] truncate',
    },
    {
        key: 'students',
        label: 'Estudiantes',
        className: 'text-text-muted',
    },
    {
        key: 'director',
        label: 'Director',
        className: 'text-text-muted',
    },
    {
        key: 'phase',
        label: 'Fase',
        render: (row: ProjectRow) => (
            <StatusBadge variant={row.phase === 'Final' ? 'success' : row.phase === 'Desarrollo' ? 'en-curso' : 'warning'}>
                {row.phase}
            </StatusBadge>
        ),
    },
    {
        key: 'status',
        label: 'Estado',
        render: (row: ProjectRow) => (
            <StatusBadge variant={row.status === 'active' || row.status === 'inscribed' ? 'success' : row.status === 'at-risk' ? 'riesgo' : 'inactivo'}>
                {row.status === 'active' ? 'Activo' : row.status === 'at-risk' ? 'En Riesgo' : row.status === 'completed' ? 'Completado' : 'Inscrito'}
            </StatusBadge>
        ),
    },
    {
        key: 'alertCount',
        label: 'Alertas',
        render: (row: ProjectRow) => (
            <span className={`tabular-nums ${row.alertCount > 0 ? 'font-bold text-error' : 'text-text-muted'}`}>
                {row.alertCount}
            </span>
        ),
    },
    {
        key: 'actions',
        label: 'Acciones',
        className: 'text-right',
        render: (row: ProjectRow) => (
            <button
                onClick={() => row._navigate(row.id)}
                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-text-muted transition-colors hover:bg-surface-alt hover:text-primary"
                aria-label={`Ver proyecto ${row.code}`}
            >
                <Eye className="h-4 w-4" />
            </button>
        ),
    },
];

/* ── Subcomponents ── */

function KpiSkeleton() {
    return (
        <>
            {Array.from({ length: 4 }).map((_, i) => (
                <div
                    key={i}
                    className="animate-pulse rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]"
                >
                    <div className="mb-3 h-10 w-10 rounded-xl bg-[#e5e5e5]" />
                    <div className="mb-1 h-3 w-20 rounded bg-[#e5e5e5]" />
                    <div className="h-7 w-16 rounded bg-[#e5e5e5]" />
                </div>
            ))}
        </>
    );
}

function KpiError({ message, onRetry }: { message: string; onRetry: () => void }) {
    return (
        <div className="col-span-full rounded-xl border border-[#fee2e2] bg-[#fee2e2]/40 p-4">
            <div className="flex items-center gap-3">
                <AlertTriangle className="h-5 w-5 shrink-0 text-[#dc2626]" />
                <p className="text-sm text-[#7f1d1d]">{message}</p>
                <button
                    onClick={onRetry}
                    className="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-[#dc2626]/30 bg-white px-3 py-1.5 text-xs font-semibold text-[#7f1d1d] transition-colors hover:bg-[#fee2e2]"
                    aria-label="Reintentar carga de KPIs"
                >
                    <RefreshCw className="h-3.5 w-3.5" />
                    Reintentar
                </button>
            </div>
        </div>
    );
}

/* ── Alert item type ── */

interface AlertItem {
    icon: React.ComponentType<{ className?: string }>;
    title: string;
    description: string;
    variant: 'error' | 'warning' | 'info';
}

function AlertCard({ icon: Icon, title, description, variant }: AlertItem) {
    const variantStyles = {
        error: 'bg-error-container/40 border-error/20',
        warning: 'bg-warning-container/40 border-warning/20',
        info: 'bg-secondary-container/40 border-secondary/20',
    };

    const iconStyles = {
        error: 'bg-error-container text-error',
        warning: 'bg-warning-container text-warning',
        info: 'bg-secondary-container text-secondary',
    };

    return (
        <div className={`rounded-xl border p-4 ${variantStyles[variant]}`}>
            <div className="flex items-start gap-3">
                <div className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${iconStyles[variant]}`}>
                    <Icon className="h-4 w-4" />
                </div>
                <div className="flex flex-col gap-1">
                    <h4 className="text-sm font-bold text-text">{title}</h4>
                    <p className="text-xs text-text-muted leading-relaxed">{description}</p>
                </div>
            </div>
        </div>
    );
}

const alertaIconMap: Record<Alerta['tipo'], typeof AlertTriangle> = {
    bitacora_sin_firmar: Clock,
    entrega_vencida: AlertCircle,
    firmas_sospechosas: FileWarning,
};

const alertaVariantMap: Record<Alerta['severidad'], 'error' | 'warning' | 'info'> = {
    alta: 'error',
    media: 'warning',
};

/* ── Main component ── */

export default function CoordinadorDashboard() {
    const navigate = useNavigate();
    const { data: kpis, loading: kpiLoading, error: kpiError, refetch: refetchKpis } = useKpis();
    const { data: proyectos, loading: projLoading, error: projError } = useProyectos();
    const { data: alertas, loading: alertasLoading, error: alertasError, refetch: refetchAlertas } = useAlertas();

    const mapProyectoToRow = (p: Proyecto): ProjectRow => ({
        id: p.id,
        code: p.code,
        title: p.title,
        students: (p.estudiantes ?? []).map((s) => s.name).join(', '),
        director: p.director?.name ?? '—',
        phase: p.current_phase ?? '—',
        status: p.status,
        alertCount: 0,
        _navigate: (id: number) => navigate(`/dashboard/coordinador/proyecto/${id}`),
    });

    const isProjectLoading = projLoading;
    const isAlertasLoading = alertasLoading;

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Dashboard"
                title="Panel de Coordinador"
                subtitle="Administra los proyectos de grado, revisa alertas y da seguimiento al progreso general."
            />

            {/* KPI row */}
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {kpiLoading ? (
                    <KpiSkeleton />
                ) : kpiError ? (
                    <KpiError message={kpiError} onRetry={refetchKpis} />
                ) : (
                    <>
                        <StatCard
                            icon={ClipboardList}
                            label="Proyectos Activos"
                            value={kpis?.proyectos_activos ?? '—'}
                            variant="default"
                        />
                        <StatCard
                            icon={TrendingDown}
                            label="En Riesgo"
                            value={kpis?.en_riesgo ?? '—'}
                            variant="warning"
                        />
                        <StatCard
                            icon={Bell}
                            label="Alertas sin revisar"
                            value={kpis?.alertas_sin_revisar ?? '—'}
                            variant="warning"
                        />
                        <StatCard
                            icon={TrendingUp}
                            label="Tasa de cumplimiento"
                            value={kpis?.tasa_cumplimiento != null ? `${kpis.tasa_cumplimiento}%` : '—'}
                            variant="success"
                        />
                    </>
                )}
            </div>

            {/* Projects table */}
            <section aria-labelledby="projects-heading">
                <h2 id="projects-heading" className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                    Proyectos de Grado
                </h2>
                {isProjectLoading ? (
                    <div className="flex items-center justify-center py-12">
                        <RefreshCw className="h-5 w-5 animate-spin text-text-muted" />
                    </div>
                ) : projError ? (
                    <div className="rounded-xl border border-[#fee2e2] bg-[#fee2e2]/40 p-4 text-sm text-[#7f1d1d]">
                        {projError}
                    </div>
                ) : (
                    <DataTable<ProjectRow>
                        columns={projectColumns}
                        data={proyectos.map(mapProyectoToRow)}
                        getRowKey={(row) => row.id}
                    />
                )}
            </section>

            {/* Alert cards */}
            <section aria-labelledby="alerts-heading">
                <h2 id="alerts-heading" className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                    Alertas Activas
                </h2>
                {isAlertasLoading ? (
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <div key={i} className="animate-pulse rounded-xl border border-[#e5e5e5] bg-white p-4">
                                <div className="mb-2 h-4 w-24 rounded bg-[#e5e5e5]" />
                                <div className="h-3 w-full rounded bg-[#e5e5e5]" />
                            </div>
                        ))}
                    </div>
                ) : alertasError ? (
                    <div className="flex items-center gap-3 rounded-xl border border-[#fee2e2] bg-[#fee2e2]/40 p-4">
                        <AlertTriangle className="h-5 w-5 shrink-0 text-[#dc2626]" />
                        <p className="text-sm text-[#7f1d1d]">{alertasError}</p>
                        <button
                            onClick={refetchAlertas}
                            className="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-[#dc2626]/30 bg-white px-3 py-1.5 text-xs font-semibold text-[#7f1d1d] transition-colors hover:bg-[#fee2e2]"
                        >
                            <RefreshCw className="h-3.5 w-3.5" />
                            Reintentar
                        </button>
                    </div>
                ) : alertas.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-[#e5e5e5] py-12 text-text-muted">
                        <Bell className="h-8 w-8" />
                        <p className="text-sm">Sin alertas activas</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                        {alertas.map((alert) => (
                            <AlertCard
                                key={alert.id}
                                icon={alertaIconMap[alert.tipo]}
                                title={
                                    alert.tipo === 'bitacora_sin_firmar' ? 'Bitácora sin firmar' :
                                    alert.tipo === 'entrega_vencida' ? 'Entrega vencida' :
                                    'Firmas sospechosas'
                                }
                                description={alert.mensaje}
                                variant={alertaVariantMap[alert.severidad]}
                            />
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}
