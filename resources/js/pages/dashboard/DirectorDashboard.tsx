import { useNavigate } from 'react-router-dom';
import { Link } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { DataTable, type Column } from '@/components/ui/DataTable';
import {
    ClipboardCheck,
    FolderKanban,
    FileText,
    CheckCircle,
    Users,
    AlertTriangle,
    ArrowRight,
} from 'lucide-react';
import {
    MOCK_ASSIGNED_PROJECTS,
    phaseLabel,
    type AssignedProject,
    type ReviewStatus,
} from '@/lib/mock/project-data';

/* ── Mock KPIs ── */

const MOCK_KPIS = [
    { icon: FolderKanban, label: 'Proyectos supervisando', value: 8, variant: 'default' as const },
    { icon: FileText, label: 'Entregas por revisar', value: 14, variant: 'warning' as const },
    { icon: AlertTriangle, label: 'Alertas', value: 2, variant: 'warning' as const },
    { icon: CheckCircle, label: 'Aprobadas este mes', value: 12, variant: 'success' as const },
];

interface ProgressProject {
    id: number;
    code: string;
    title: string;
    students: string;
    progress: number;
    color: string;
}

const MOCK_PROGRESS: ProgressProject[] = [
    { id: 1, code: 'PG-2401', title: 'Microgrid solar IoT', students: 'Ana Martínez, Luis Rojas', progress: 85, color: 'bg-success' },
    { id: 2, code: 'PG-2404', title: 'Sistema de deserción ML', students: 'Diana Pardo', progress: 92, color: 'bg-success' },
    { id: 3, code: 'PG-2402', title: 'IoT ambiental', students: 'Pedro Sánchez', progress: 45, color: 'bg-warning' },
];

const reviewStatusConfig: Record<ReviewStatus, { label: string; variant: 'success' | 'warning' }> = {
    pending_review: { label: 'Pendiente por revisar', variant: 'warning' },
    no_pending: { label: 'Sin pendientes', variant: 'success' },
};

/* ── Columns ── */

const projectColumns: Column<AssignedProject>[] = [
    {
        key: 'title',
        label: 'Proyecto',
        render: (row) => (
            <div className="flex flex-col gap-0.5">
                <span className="text-xs font-bold uppercase tracking-[0.05em] text-primary">{row.code}</span>
                <span className="font-medium text-text">{row.title}</span>
            </div>
        ),
    },
    {
        key: 'students',
        label: 'Estudiantes',
        render: (row) => (
            <span className="flex items-center gap-1.5 text-text-muted">
                <Users className="h-3.5 w-3.5 shrink-0" />
                {row.students.join(', ')}
            </span>
        ),
    },
    {
        key: 'currentPhase',
        label: 'Fase actual',
        render: (row) => (
            <span className="text-text-muted">{phaseLabel(row.currentPhase)}</span>
        ),
    },
    {
        key: 'reviewStatus',
        label: 'Estado',
        render: (row) => {
            const config = reviewStatusConfig[row.reviewStatus];
            return <StatusBadge variant={config.variant}>{config.label}</StatusBadge>;
        },
    },
    {
        key: 'actions',
        label: 'Acciones',
        className: 'text-right',
        render: (row) => (
            <Link
                to={`/supervision/${row.id}`}
                className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-text transition-colors hover:border-primary hover:bg-primary-container hover:text-primary"
            >
                Supervisar
                <ArrowRight className="h-3.5 w-3.5" />
            </Link>
        ),
    },
];

function ProgressCard({ code, title, students, progress, color }: ProgressProject) {
    return (
        <div className="rounded-xl border border-border bg-surface p-5 shadow-warm-sm">
            <div className="mb-3 flex items-start justify-between">
                <div className="flex flex-col gap-0.5">
                    <span className="text-xs font-bold uppercase tracking-[0.05em] text-primary">{code}</span>
                    <h4 className="text-sm font-bold text-text">{title}</h4>
                    <span className="flex items-center gap-1.5 text-xs text-text-muted">
                        <Users className="h-3 w-3" />
                        {students}
                    </span>
                </div>
            </div>
            <div className="flex items-center gap-3">
                <div className="flex-1 overflow-hidden rounded-full bg-surface-alt" role="progressbar" aria-valuenow={progress} aria-valuemin={0} aria-valuemax={100} aria-label={`Progreso: ${progress}%`}>
                    <div
                        className={`h-2.5 rounded-full transition-all duration-500 ${color}`}
                        style={{ width: `${progress}%` }}
                    />
                </div>
                <span className="text-sm font-bold tabular-nums text-text">{progress}%</span>
            </div>
        </div>
    );
}

export default function DirectorDashboard() {
    return (
        <div className="flex flex-col gap-6">
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

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {MOCK_KPIS.map((kpi) => (
                    <StatCard key={kpi.label} icon={kpi.icon} label={kpi.label} value={kpi.value} variant={kpi.variant} />
                ))}
            </div>

            <section aria-labelledby="progress-heading">
                <h2 id="progress-heading" className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                    Avance de Proyectos
                </h2>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    {MOCK_PROGRESS.map((proj) => (
                        <ProgressCard key={proj.id} {...proj} />
                    ))}
                </div>
            </section>

            <section aria-labelledby="projects-heading">
                <h2 id="projects-heading" className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                    Proyectos Asignados
                </h2>
                <DataTable<AssignedProject>
                    columns={projectColumns}
                    data={MOCK_ASSIGNED_PROJECTS}
                    getRowKey={(row) => row.id}
                />
            </section>
        </div>
    );
}
