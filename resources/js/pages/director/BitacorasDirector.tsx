import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { FileText, Users, Clock, Search, Eye } from 'lucide-react';

interface BinnacleEntry {
    id: number;
    project: string;
    student: string;
    date: string;
    topic: string;
    status: 'signed' | 'pending' | 'unsigned';
    duration: string;
}

const MOCK_BINNACLES: BinnacleEntry[] = [
    { id: 1, project: 'PG-2026-014', student: 'Carlos Méndez', date: '15/04/2026', topic: 'Revisión de arquitectura', status: 'pending', duration: '1h 30m' },
    { id: 2, project: 'PG-2026-014', student: 'Carlos Méndez', date: '08/04/2026', topic: 'Implementación de módulos', status: 'signed', duration: '2h' },
    { id: 3, project: 'PG-2026-015', student: 'María Rincón', date: '12/04/2026', topic: 'Análisis de sentimientos', status: 'pending', duration: '1h' },
    { id: 4, project: 'PG-2026-015', student: 'María Rincón', date: '05/04/2026', topic: 'Recolección de datos', status: 'signed', duration: '1h 30m' },
    { id: 5, project: 'PG-2026-008', student: 'Andrés Torres', date: '10/04/2026', topic: 'Diseño de dashboard', status: 'unsigned', duration: '2h' },
    { id: 6, project: 'PG-2026-008', student: 'Andrés Torres', date: '03/04/2026', topic: 'Requisitos del dashboard', status: 'signed', duration: '1h' },
    { id: 7, project: 'PG-2026-005', student: 'Diana Rojas', date: '14/04/2026', topic: 'Contenido E-Learning', status: 'pending', duration: '1h 30m' },
    { id: 8, project: 'PG-2026-005', student: 'Diana Rojas', date: '07/04/2026', topic: 'Estructura del curso', status: 'signed', duration: '1h' },
];

const statusConfig: Record<string, { label: string; variant: 'success' | 'warning' | 'inactivo' }> = {
    signed: { label: 'Firmada', variant: 'success' },
    pending: { label: 'Pendiente', variant: 'warning' },
    unsigned: { label: 'Sin firmar', variant: 'inactivo' },
};

const columns: Column<BinnacleEntry>[] = [
    { key: 'date', label: 'Fecha', className: 'whitespace-nowrap' },
    { key: 'project', label: 'Proyecto' },
    { key: 'student', label: 'Estudiante' },
    { key: 'topic', label: 'Tema' },
    { key: 'duration', label: 'Duración', className: 'whitespace-nowrap' },
    {
        key: 'status',
        label: 'Estado',
        render: (row) => {
            const config = statusConfig[row.status];
            return <StatusBadge variant={config.variant}>{config.label}</StatusBadge>;
        },
    },
    {
        key: 'actions',
        label: 'Acciones',
        className: 'text-right',
        render: (row) => (
            <div className="inline-flex gap-0.5">
                <button
                    className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c] active:scale-[0.98]"
                    aria-label={`Ver detalle bitácora ${row.topic}`}
                    title="Ver detalle"
                >
                    <Eye className="h-4 w-4" />
                </button>
                {row.status !== 'signed' && (
                    <button
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#c2410c] hover:text-white active:scale-[0.98]"
                        aria-label={`Firmar bitácora ${row.topic}`}
                        title="Firmar"
                    >
                        <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </button>
                )}
            </div>
        ),
    },
];

export default function BitacorasDirector() {
    const navigate = useNavigate();
    const [search, setSearch] = useState('');
    const [filterStatus, setFilterStatus] = useState<string>('all');

    const filtered = MOCK_BINNACLES.filter((b) => {
        const matchesSearch =
            b.project.toLowerCase().includes(search.toLowerCase()) ||
            b.student.toLowerCase().includes(search.toLowerCase()) ||
            b.topic.toLowerCase().includes(search.toLowerCase());
        const matchesStatus = filterStatus === 'all' || b.status === filterStatus;
        return matchesSearch && matchesStatus;
    });

    const pendingCount = MOCK_BINNACLES.filter((b) => b.status === 'pending').length;
    const signedCount = MOCK_BINNACLES.filter((b) => b.status === 'signed').length;

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Director"
                title="Bitácoras"
                subtitle="Revise y firme las bitácoras de los proyectos que supervisa"
            />

            {/* Stats */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    icon={FileText}
                    label="Total bitácoras"
                    value={MOCK_BINNACLES.length}
                />
                <StatCard
                    icon={Clock}
                    label="Pendientes de firma"
                    value={pendingCount}
                    variant="warning"
                />
                <StatCard
                    icon={Users}
                    label="Firmadas"
                    value={signedCount}
                    variant="success"
                />
            </div>

            {/* Filter */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por proyecto, estudiante o tema..."
                        className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                    />
                </div>
                <select
                    value={filterStatus}
                    onChange={(e) => setFilterStatus(e.target.value)}
                    className="min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                    aria-label="Filtrar por estado"
                >
                    <option value="all">Todos los estados</option>
                    <option value="pending">Pendientes</option>
                    <option value="signed">Firmadas</option>
                    <option value="unsigned">Sin firmar</option>
                </select>
            </div>

            {/* Table */}
            <DataTable
                columns={columns}
                data={filtered}
                getRowKey={(row) => row.id}
                emptyMessage="No se encontraron bitácoras."
            />
        </div>
    );
}
