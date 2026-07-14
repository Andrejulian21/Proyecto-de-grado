import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { useKpis } from '@/hooks/useKpis';
import {
    ClipboardList,
    TrendingDown,
    Bell,
    TrendingUp,
    Eye,
    AlertTriangle,
    UserX,
    AlertCircle,
    Loader2,
    RefreshCw,
} from 'lucide-react';

/* ── Mock data (preserved until PR6) ── */

interface Project {
    id: number;
    code: string;
    title: string;
    students: string;
    director: string;
    phase: string;
    status: 'active' | 'at-risk' | 'completed';
    alertCount: number;
}

const MOCK_PROJECTS: Project[] = [
    { id: 1, code: 'PG-2401', title: 'Sistema predictivo de deserción estudiantil', students: 'Ana Martínez, Luis Rojas', director: 'Carlos Gómez', phase: 'Presentación', status: 'active', alertCount: 0 },
    { id: 2, code: 'PG-2402', title: 'Plataforma IoT para monitoreo ambiental', students: 'Pedro Sánchez', director: 'María Torres', phase: 'Desarrollo', status: 'at-risk', alertCount: 2 },
    { id: 3, code: 'PG-2403', title: 'Aplicación móvil para tutorías inteligentes', students: 'Laura Jiménez, Carlos Ruiz', director: 'Andrés Pérez', phase: 'Anteproyecto', status: 'active', alertCount: 0 },
    { id: 4, code: 'PG-2404', title: 'Dashboard de indicadores académicos', students: 'Diana Pardo', director: 'Sofía Medina', phase: 'Desarrollo', status: 'at-risk', alertCount: 1 },
    { id: 5, code: 'PG-2405', title: 'Sistema de recomendación de cursos', students: 'Miguel Ángel Díaz, Olga Luna', director: 'Carlos Gómez', phase: 'Final', status: 'completed', alertCount: 0 },
    { id: 6, code: 'PG-2406', title: 'Blockchain para certificados académicos', students: 'Ricardo Mora', director: 'Andrés Pérez', phase: 'Presentación', status: 'active', alertCount: 0 },
    { id: 7, code: 'PG-2407', title: 'Chatbot institucional con IA generativa', students: 'Camila Rangel, David Peña', director: 'María Torres', phase: 'Desarrollo', status: 'at-risk', alertCount: 3 },
    { id: 8, code: 'PG-2408', title: 'Plataforma de realidad virtual para laboratorios', students: 'Fernando Gil', director: 'Sofía Medina', phase: 'Anteproyecto', status: 'active', alertCount: 0 },
];

interface AlertItem {
    icon: typeof AlertTriangle;
    title: string;
    description: string;
    variant: 'error' | 'warning' | 'info';
}

const MOCK_ALERTS: AlertItem[] = [
    {
        icon: AlertTriangle,
        title: 'Entrega vencida',
        description: 'El proyecto PG-2402 no ha realizado la entrega de desarrollo. La fecha límite venció hace 3 días.',
        variant: 'error',
    },
    {
        icon: UserX,
        title: 'Proyecto sin director',
        description: 'El proyecto PG-2409 (Sistema de gestión de egresados) no tiene director asignado esta semana.',
        variant: 'warning',
    },
    {
        icon: AlertCircle,
        title: 'Bajo rendimiento',
        description: '3 proyectos tienen menos del 50% de avance en el semestre. Revise los reportes para más detalles.',
        variant: 'info',
    },
];

/* ── Columns ── */

interface ProjectRow extends Project {
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
            <StatusBadge variant={row.status === 'active' ? 'success' : row.status === 'at-risk' ? 'riesgo' : 'inactivo'}>
                {row.status === 'active' ? 'Activo' : row.status === 'at-risk' ? 'En Riesgo' : 'Completado'}
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

/* ── Main component ── */

export default function CoordinadorDashboard() {
    const navigate = useNavigate();
    const { data: kpis, loading, error, refetch } = useKpis();

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Dashboard"
                title="Panel de Coordinador"
                subtitle="Administra los proyectos de grado, revisa alertas y da seguimiento al progreso general."
            />

            {/* KPI row */}
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {loading ? (
                    <KpiSkeleton />
                ) : error ? (
                    <KpiError message={error} onRetry={refetch} />
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
                <DataTable<ProjectRow>
                    columns={projectColumns}
                    data={MOCK_PROJECTS.map((p) => ({ ...p, _navigate: (id: number) => navigate(`/dashboard/coordinador/proyecto/${id}`) }))}
                    getRowKey={(row) => row.id}
                />
            </section>

            {/* Alert cards */}
            <section aria-labelledby="alerts-heading">
                <h2 id="alerts-heading" className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                    Alertas Activas
                </h2>
                <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                    {MOCK_ALERTS.map((alert) => (
                        <AlertCard key={alert.title} {...alert} />
                    ))}
                </div>
            </section>
        </div>
    );
}
