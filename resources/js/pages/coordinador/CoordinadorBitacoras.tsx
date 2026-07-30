import { useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { Search, ChevronLeft, ChevronRight, Eye } from 'lucide-react';

interface BinnacleEntry {
    id: number;
    project: string;
    student: string;
    director: string;
    date: string;
    semana: number;
    topic: string;
    status: 'signed' | 'pending' | 'unsigned';
}

const ALL_BINNACLES: BinnacleEntry[] = Array.from({ length: 25 }, (_, i) => ({
    id: i + 1,
    project: ['PG-2026-014', 'PG-2026-015', 'PG-2026-008', 'PG-2026-005', 'PG-2026-012'][i % 5],
    student: ['Carlos Méndez', 'María Rincón', 'Andrés Torres', 'Diana Rojas', 'Juan Pérez'][i % 5],
    director: ['Dr. Ricardo Gómez', 'Dra. Laura Martínez', 'Dr. Andrés Vega'][i % 3],
    date: `${String(10 + (i % 20)).padStart(2, '0')}/04/2026`,
    semana: (i % 32) + 1,
    topic: ['Revisión de arquitectura', 'Análisis de requisitos', 'Implementación', 'Pruebas', 'Diseño'][i % 5],
    status: (['signed', 'pending', 'unsigned'] as const)[i % 3],
}));

const statusConfig: Record<string, { label: string; variant: 'success' | 'warning' | 'inactivo' }> = {
    signed: { label: 'Firmada', variant: 'success' },
    pending: { label: 'Pendiente', variant: 'warning' },
    unsigned: { label: 'Sin firmar', variant: 'inactivo' },
};

const columns: Column<BinnacleEntry>[] = [
    { key: 'date', label: 'Fecha', className: 'whitespace-nowrap' },
    {
        key: 'semana',
        label: 'Semana',
        className: 'whitespace-nowrap',
        render: (row) => <span className="text-[#57534e] tabular-nums">Sem {row.semana}</span>,
    },
    { key: 'project', label: 'Proyecto' },
    { key: 'student', label: 'Estudiante' },
    { key: 'director', label: 'Director' },
    { key: 'topic', label: 'Tema' },
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
            <button
                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c] active:scale-[0.98]"
                aria-label={`Ver bitácora ${row.topic}`}
                title="Ver"
            >
                <Eye className="h-4 w-4" />
            </button>
        ),
    },
];

export default function CoordinadorBitacoras() {
    const [currentPage, setCurrentPage] = useState(1);
    const [search, setSearch] = useState('');
    const [filterStatus, setFilterStatus] = useState<string>('all');
    const [filterDirector, setFilterDirector] = useState<string>('all');
    const perPage = 10;

    const filtered = ALL_BINNACLES.filter((b) => {
        const ms = b.project.toLowerCase().includes(search.toLowerCase()) ||
            b.student.toLowerCase().includes(search.toLowerCase()) ||
            b.topic.toLowerCase().includes(search.toLowerCase());
        const mStatus = filterStatus === 'all' || b.status === filterStatus;
        const mDirector = filterDirector === 'all' || b.director === filterDirector;
        return ms && mStatus && mDirector;
    });

    const totalPages = Math.ceil(filtered.length / perPage);
    const paginated = filtered.slice((currentPage - 1) * perPage, currentPage * perPage);

    const directors = [...new Set(ALL_BINNACLES.map((b) => b.director))];

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Coordinación"
                title="Bitácoras"
                subtitle="Gestión de bitácoras de todos los proyectos"
            />

            {/* Filters */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar..."
                        className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                    />
                </div>
                <select
                    value={filterStatus}
                    onChange={(e) => { setFilterStatus(e.target.value); setCurrentPage(1); }}
                    className="min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                    aria-label="Filtrar por estado"
                >
                    <option value="all">Todos los estados</option>
                    <option value="pending">Pendientes</option>
                    <option value="signed">Firmadas</option>
                    <option value="unsigned">Sin firmar</option>
                </select>
                <select
                    value={filterDirector}
                    onChange={(e) => { setFilterDirector(e.target.value); setCurrentPage(1); }}
                    className="min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                    aria-label="Filtrar por director"
                >
                    <option value="all">Todos los directores</option>
                    {directors.map((d) => (
                        <option key={d} value={d}>{d}</option>
                    ))}
                </select>
            </div>

            {/* Table with pagination */}
            <DataTable
                columns={columns}
                data={paginated}
                getRowKey={(row) => row.id}
                emptyMessage="No se encontraron bitácoras."
                pagination={{
                    currentPage,
                    lastPage: totalPages,
                    total: filtered.length,
                    onPageChange: setCurrentPage,
                }}
            />
        </div>
    );
}
