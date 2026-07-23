import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { useDirectorProyectos } from '@/hooks/useDirectorProyectos';
import { useDirectorKpis } from '@/hooks/useDirectorKpis';
import { useDirectorEntregas, type DirectorEntrega } from '@/hooks/useDirectorEntregas';
import {
    ClipboardCheck,
    FileText,
    CheckCircle,
    Eye,
    Users,
    AlertTriangle,
    ClipboardList,
    RefreshCw,
    AlertCircle,
} from 'lucide-react';

/* ── Columns for deliveries table ── */

const deliveryColumns: Column<DirectorEntrega>[] = [
    {
        key: 'codigo',
        label: 'Código',
        className: 'font-medium text-text',
    },
    {
        key: 'proyecto',
        label: 'Proyecto',
        className: 'text-text-muted',
    },
    {
        key: 'estudiante',
        label: 'Estudiante',
        className: 'text-text-muted',
    },
    {
        key: 'title',
        label: 'Entrega',
        className: 'text-text-muted',
    },
    {
        key: 'due_date',
        label: 'Fecha',
        className: 'text-text-muted tabular-nums',
    },
    {
        key: 'status',
        label: 'Estado',
        render: (row: DirectorEntrega) => (
            <StatusBadge variant="warning">
                Pendiente
            </StatusBadge>
        ),
    },
    {
        key: 'actions',
        label: 'Acciones',
        className: 'text-right',
        render: () => (
            <button
                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-text-muted transition-colors hover:bg-surface-alt hover:text-primary"
                aria-label="Revisar entrega"
            >
                <Eye className="h-4 w-4" />
            </button>
        ),
    },
];

/* ── Subcomponents ── */

function ProjectCard({ code, title, estudiantes, current_phase }: {
    code: string;
    title: string;
    estudiantes: { id: number; name: string }[];
    current_phase: string | null;
}) {
    const phaseLabel = current_phase
        ? current_phase.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
        : 'Sin fase';

    return (
        <div className="w-[280px] shrink-0 rounded-xl border border-border bg-surface p-5 shadow-warm-sm">
            <div className="mb-3 flex flex-col gap-0.5">
                <span className="text-xs font-bold uppercase tracking-[0.05em] text-primary">{code}</span>
                <h4 className="text-sm font-bold text-text line-clamp-2">{title}</h4>
                <span className="flex items-center gap-1.5 text-xs text-text-muted mt-1">
                    <Users className="h-3 w-3 shrink-0" />
                    {estudiantes.map((e) => e.name).join(', ')}
                </span>
            </div>
            <div className="mt-2">
                <span className="inline-flex items-center rounded-full bg-info/10 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.03em] text-info">
                    {phaseLabel}
                </span>
            </div>
        </div>
    );
}

function SkeletonCard() {
    return (
        <div className="h-[120px] w-[280px] shrink-0 animate-pulse rounded-xl border border-border bg-surface p-5">
            <div className="h-3 w-16 rounded bg-surface-alt mb-3" />
            <div className="h-4 w-40 rounded bg-surface-alt mb-2" />
            <div className="h-3 w-32 rounded bg-surface-alt" />
        </div>
    );
}

function SkeletonStatCard() {
    return (
        <div className="h-[110px] animate-pulse rounded-xl border border-border bg-surface p-5">
            <div className="h-10 w-10 rounded-xl bg-surface-alt mb-3" />
            <div className="h-3 w-24 rounded bg-surface-alt mb-1" />
            <div className="h-7 w-12 rounded bg-surface-alt" />
        </div>
    );
}

function ErrorBanner({ message, onRetry }: { message: string; onRetry: () => void }) {
    return (
        <div className="flex items-center gap-3 rounded-lg border border-error/20 bg-error/5 p-4 text-sm text-error">
            <AlertCircle className="h-5 w-5 shrink-0" />
            <p className="flex-1">{message}</p>
            <button
                onClick={onRetry}
                className="inline-flex items-center gap-1.5 rounded-lg border border-error/30 px-3 py-1.5 text-xs font-semibold text-error transition-colors hover:bg-error/10"
            >
                <RefreshCw className="h-3.5 w-3.5" />
                Reintentar
            </button>
        </div>
    );
}

/* ── Main component ── */

export default function DirectorDashboard() {
    const {
        data: proyectos,
        loading: loadingProyectos,
        error: errorProyectos,
        refetch: refetchProyectos,
    } = useDirectorProyectos();

    const {
        data: kpis,
        loading: loadingKpis,
        error: errorKpis,
        refetch: refetchKpis,
    } = useDirectorKpis();

    const {
        data: entregas,
        loading: loadingEntregas,
        error: errorEntregas,
        refetch: refetchEntregas,
    } = useDirectorEntregas();

    const handleRetry = () => {
        refetchProyectos();
        refetchKpis();
        refetchEntregas();
    };

    const hasError = errorProyectos || errorKpis || errorEntregas;

    return (
        <div className="flex flex-col gap-6">
            {/* Bezel header */}
            <div className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5 shadow-warm-sm sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-start gap-4">
                    <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary-container">
                        <ClipboardCheck className="h-7 w-7 text-primary" />
                    </div>
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold text-text">Proyectos Asignados</h1>
                            <span className="flex h-2.5 w-2.5 rounded-full bg-success animate-pulse" aria-label="Activo" />
                        </div>
                        <p className="text-sm text-text-muted">
                            Supervisa y da seguimiento a los proyectos de grado bajo tu dirección.
                        </p>
                    </div>
                </div>
            </div>

            {/* Error banner */}
            {hasError && (
                <ErrorBanner
                    message={errorProyectos || errorKpis || errorEntregas || 'Error al cargar los datos'}
                    onRetry={handleRetry}
                />
            )}

            {/* KPI row */}
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {loadingKpis ? (
                    <>
                        <SkeletonStatCard />
                        <SkeletonStatCard />
                        <SkeletonStatCard />
                        <SkeletonStatCard />
                    </>
                ) : kpis ? (
                    <>
                        <StatCard
                            icon={ClipboardList}
                            label="Proyectos supervisando"
                            value={kpis.proyectos_supervisando}
                        />
                        <StatCard
                            icon={FileText}
                            label="Entregas pendientes"
                            value={kpis.entregas_pendientes}
                            variant="warning"
                        />
                        <StatCard
                            icon={AlertTriangle}
                            label="Alertas"
                            value={kpis.alertas}
                            variant={kpis.alertas > 0 ? 'warning' : 'success'}
                        />
                        <StatCard
                            icon={CheckCircle}
                            label="Aprobadas este mes"
                            value={kpis.aprobadas_mes}
                            variant="success"
                        />
                    </>
                ) : null}
            </div>

            {/* Project carousel */}
            <section aria-labelledby="projects-heading">
                <h2 id="projects-heading" className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                    Mis Proyectos
                </h2>

                {loadingProyectos ? (
                    <div className="flex gap-4 overflow-x-auto pb-2">
                        <SkeletonCard />
                        <SkeletonCard />
                        <SkeletonCard />
                    </div>
                ) : errorProyectos ? null : proyectos.length === 0 ? (
                    <p className="py-8 text-center text-sm text-text-muted">
                        No tienes proyectos asignados en semestres activos.
                    </p>
                ) : (
                    <div className="flex gap-4 overflow-x-auto pb-2">
                        {proyectos.map((proj) => (
                            <ProjectCard
                                key={proj.id}
                                code={proj.code}
                                title={proj.title}
                                estudiantes={proj.estudiantes}
                                current_phase={proj.current_phase}
                            />
                        ))}
                    </div>
                )}
            </section>

            {/* Deliveries table */}
            <section aria-labelledby="deliveries-heading">
                <h2 id="deliveries-heading" className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                    Últimas Entregas
                </h2>
                <DataTable<DirectorEntrega>
                    columns={deliveryColumns}
                    data={entregas}
                    loading={loadingEntregas}
                    emptyMessage={errorEntregas ? 'Error al cargar las entregas.' : 'No hay entregas pendientes por revisar.'}
                    getRowKey={(row) => row.id}
                />
            </section>
        </div>
    );
}
