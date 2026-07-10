import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { DataTable, type Column } from '@/components/ui/DataTable';
import {
    ClipboardCheck,
    FolderKanban,
    FileText,
    CheckCircle,
    Eye,
    Users,
    AlertTriangle,
} from 'lucide-react';

/* ── Mock data ── */

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

interface Delivery {
    id: number;
    student: string;
    project: string;
    type: string;
    date: string;
    status: 'approved' | 'pending' | 'rejected';
}

const MOCK_DELIVERIES: Delivery[] = [
    { id: 1, student: 'Ana Martínez', project: 'Microgrid solar IoT', type: 'Informe de Avance 1', date: '12/06/2026', status: 'pending' },
    { id: 2, student: 'Pedro Sánchez', project: 'IoT ambiental', type: 'Presentación', date: '08/06/2026', status: 'approved' },
    { id: 3, student: 'Diana Pardo', project: 'Sistema de deserción ML', type: 'Informe de Avance 2', date: '01/06/2026', status: 'pending' },
];

/* ── Columns ── */

const deliveryColumns: Column<Delivery>[] = [
    {
        key: 'student',
        label: 'Estudiante',
        className: 'font-medium text-text',
    },
    {
        key: 'project',
        label: 'Proyecto',
        className: 'text-text-muted',
    },
    {
        key: 'type',
        label: 'Tipo',
        className: 'text-text-muted',
    },
    {
        key: 'date',
        label: 'Fecha',
        className: 'text-text-muted tabular-nums',
    },
    {
        key: 'status',
        label: 'Estado',
        render: (row: Delivery) => (
            <StatusBadge
                variant={row.status === 'approved' ? 'success' : row.status === 'pending' ? 'warning' : 'error'}
            >
                {row.status === 'approved' ? 'Aprobado' : row.status === 'pending' ? 'Pendiente' : 'Rechazado'}
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

/* ── Main component ── */

export default function DirectorDashboard() {
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

            {/* KPI row */}
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {MOCK_KPIS.map((kpi) => (
                    <StatCard key={kpi.label} icon={kpi.icon} label={kpi.label} value={kpi.value} variant={kpi.variant} />
                ))}
            </div>

            {/* Progress cards */}
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

            {/* Deliveries table */}
            <section aria-labelledby="deliveries-heading">
                <h2 id="deliveries-heading" className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                    Últimas Entregas
                </h2>
                <DataTable<Delivery>
                    columns={deliveryColumns}
                    data={MOCK_DELIVERIES}
                    getRowKey={(row) => row.id}
                />
            </section>
        </div>
    );
}
