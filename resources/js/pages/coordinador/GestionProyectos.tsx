import { useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Plus, Search, Trash2, Users, Calendar, GraduationCap, Loader2 } from 'lucide-react';

interface Project {
    id: number;
    code: string;
    title: string;
    students: string[];
    director: string;
    status: 'active' | 'completed' | 'on-hold' | 'inscribed';
    period: string;
}

interface Cupo {
    id: number;
    name: string;
    total: number;
    used: number;
    available: number;
}

const MOCK_PROJECTS: Project[] = [
    { id: 1, code: 'PG-2026-014', title: 'Sistema Centralizado de Proyectos de Grado', students: ['Carlos Méndez', 'Ana Torres'], director: 'Dr. Ricardo Gómez', status: 'active', period: '2026-01' },
    { id: 2, code: 'PG-2026-015', title: 'Plataforma de Análisis de Sentimientos', students: ['María Rincón'], director: 'Dr. Ricardo Gómez', status: 'active', period: '2026-01' },
    { id: 3, code: 'PG-2026-012', title: 'App Móvil Gestión Hospitalaria', students: ['Juan Pérez', 'Luis Ramírez', 'Camila Rojas'], director: 'Dra. Laura Martínez', status: 'completed', period: '2025-02' },
    { id: 4, code: 'PG-2026-010', title: 'Sistema de Recomendación de Rutas', students: ['Laura Gómez'], director: 'Dr. Andrés Vega', status: 'on-hold', period: '2025-02' },
    { id: 5, code: 'PG-2026-008', title: 'Dashboard Indicadores Académicos', students: ['Andrés Torres', 'Paula Medina'], director: 'Dra. Laura Martínez', status: 'active', period: '2026-01' },
    { id: 6, code: 'PG-2026-005', title: 'Plataforma E-Learning Programación', students: ['Diana Rojas', 'Sofía Peña', 'Mateo Cruz'], director: 'Dr. Andrés Vega', status: 'active', period: '2026-01' },
    { id: 7, code: 'PG-2026-003', title: 'Sistema de Gestión de Biblioteca', students: ['Pedro Ramírez'], director: 'Dr. Ricardo Gómez', status: 'inscribed', period: '2026-01' },
];

const MOCK_CUPOS: Cupo[] = [
    { id: 1, name: 'Dr. Ricardo Gómez', total: 8, used: 5, available: 3 },
    { id: 2, name: 'Dra. Laura Martínez', total: 6, used: 4, available: 2 },
    { id: 3, name: 'Dr. Andrés Vega', total: 6, used: 3, available: 3 },
    { id: 4, name: 'Dr. Felipe Rojas', total: 4, used: 4, available: 0 },
];

const PERIODS = ['2026-01', '2025-02', '2025-01'];

const statusConfig: Record<string, { label: string; variant: 'success' | 'info' | 'inactivo' | 'warning' | 'en-curso' }> = {
    active: { label: 'Activo', variant: 'success' },
    completed: { label: 'Completado', variant: 'inactivo' },
    'on-hold': { label: 'En pausa', variant: 'warning' },
    inscribed: { label: 'Inscrito', variant: 'en-curso' },
};

const columns: Column<Project>[] = [
    { key: 'code', label: 'Código', className: 'whitespace-nowrap font-mono text-xs' },
    { key: 'title', label: 'Título', className: 'max-w-xs', render: (row) => <span className="line-clamp-2">{row.title}</span> },
    { key: 'students', label: 'Estudiantes', render: (row) => (
        <span className="text-xs text-text-muted">{row.students.join(', ')}</span>
    ) },
    { key: 'director', label: 'Director' },
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
        label: '',
        className: 'text-right',
        render: (row) => (
            <button
                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626] active:scale-[0.98]"
                aria-label={`Eliminar proyecto ${row.code}`}
                title="Eliminar"
                onClick={(e) => { e.stopPropagation(); }}
            >
                <Trash2 className="h-4 w-4" />
            </button>
        ),
    },
];

export default function GestionProyectos() {
    const [search, setSearch] = useState('');
    const [periodFilter, setPeriodFilter] = useState('all');
    const [deleteTarget, setDeleteTarget] = useState<Project | null>(null);

    const [showCrearGrupo, setShowCrearGrupo] = useState(false);
    const [newGroupName, setNewGroupName] = useState('');
    const [creating, setCreating] = useState(false);

    const filtered = MOCK_PROJECTS.filter((p) => {
        const ms = p.title.toLowerCase().includes(search.toLowerCase()) ||
            p.code.toLowerCase().includes(search.toLowerCase()) ||
            p.students.some((s) => s.toLowerCase().includes(search.toLowerCase())) || p.code.toLowerCase().includes(search.toLowerCase());
        const mp = periodFilter === 'all' || p.period === periodFilter;
        return ms && mp;
    });

    const activeProjects = MOCK_PROJECTS.filter((p) => p.status === 'active').length;

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Coordinación"
                title="Gestión de Proyectos"
                subtitle="Administre los proyectos de grado, grupos y cupos de dirección"
                actions={
                    <button
                        onClick={() => setShowCrearGrupo(!showCrearGrupo)}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                    >
                        <Plus className="h-4 w-4" />
                        Nuevo Grupo
                    </button>
                }
            />

            {/* Crear grupo form */}
            {showCrearGrupo && (
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <h3 className="mb-4 text-base font-bold text-[#1c1917]">Crear Nuevo Grupo</h3>
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
                        <div className="flex-1 flex flex-col gap-1.5">
                            <label htmlFor="group-name" className="text-sm font-semibold text-[#1c1917]">Nombre del grupo</label>
                            <input
                                id="group-name"
                                type="text"
                                value={newGroupName}
                                onChange={(e) => setNewGroupName(e.target.value)}
                                placeholder="Ej: Grupo 01 — 2026-01"
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            />
                        </div>
                        <button
                            disabled={!newGroupName.trim() || creating}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {creating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                            Crear Grupo
                        </button>
                    </div>
                </div>
            )}

            {/* Period filter bar */}
            <div className="flex items-center gap-2 overflow-x-auto pb-1">
                <Calendar className="h-4 w-4 shrink-0 text-[#78716c]" />
                <button
                    onClick={() => setPeriodFilter('all')}
                    className={`whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                        periodFilter === 'all' ? 'bg-[#c2410c] text-white' : 'bg-[#f5f5f4] text-[#57534e] hover:bg-[#e7e5e4]'
                    }`}
                >
                    Todos
                </button>
                {PERIODS.map((p) => (
                    <button
                        key={p}
                        onClick={() => setPeriodFilter(p)}
                        className={`whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                            periodFilter === p ? 'bg-[#c2410c] text-white' : 'bg-[#f5f5f4] text-[#57534e] hover:bg-[#e7e5e4]'
                        }`}
                    >
                        {p}
                    </button>
                ))}
                <span className="ml-auto text-xs text-[#78716c] tabular-nums">
                    {filtered.length} proyectos · {activeProjects} activos
                </span>
            </div>

            {/* Search */}
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Buscar proyectos..."
                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                />
            </div>

            {/* Table */}
            <DataTable
                columns={columns}
                data={filtered}
                getRowKey={(row) => row.id}
                emptyMessage="No se encontraron proyectos."
            />

            {/* Cupos */}
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="mb-4 flex items-center gap-2">
                    <Users className="h-5 w-5 text-[#c2410c]" />
                    <h3 className="text-base font-bold text-[#1c1917]">Cupos de Dirección</h3>
                </div>
                <p className="mb-4 text-sm text-[#57534e]">
                    Distribución de estudiantes por director en el semestre actual.
                </p>
                <div className="w-full overflow-x-auto rounded-lg border border-[#e5e5e5]">
                    <table className="w-full text-left text-sm tabular-nums">
                        <thead className="bg-[#f5f5f4] text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                            <tr>
                                <th className="px-4 py-3">Director</th>
                                <th className="px-4 py-3 text-center">Cupo total</th>
                                <th className="px-4 py-3 text-center">Asignados</th>
                                <th className="px-4 py-3 text-center">Disponibles</th>
                                <th className="px-4 py-3">Ocupación</th>
                            </tr>
                        </thead>
                        <tbody>
                            {MOCK_CUPOS.map((c) => {
                                const pct = Math.round((c.used / c.total) * 100);
                                return (
                                    <tr key={c.id} className="border-b border-[#e5e5e5] last:border-none">
                                        <td className="px-4 py-3 font-medium text-[#1c1917]">{c.name}</td>
                                        <td className="px-4 py-3 text-center text-[#57534e]">{c.total}</td>
                                        <td className="px-4 py-3 text-center text-[#1c1917] font-semibold">{c.used}</td>
                                        <td className="px-4 py-3 text-center">
                                            <span className={`font-semibold ${c.available === 0 ? 'text-[#dc2626]' : 'text-[#16a34a]'}`}>
                                                {c.available}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <div className="h-2 w-full max-w-[100px] overflow-hidden rounded-full bg-[#e7e5e4]">
                                                    <div
                                                        className={`h-full rounded-full ${
                                                            pct >= 100 ? 'bg-[#dc2626]' : pct >= 75 ? 'bg-[#d97706]' : 'bg-[#16a34a]'
                                                        }`}
                                                        style={{ width: `${pct}%` }}
                                                    />
                                                </div>
                                                <span className="text-xs text-[#78716c] tabular-nums">{pct}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                title="Eliminar proyecto"
                message={`¿Está seguro de eliminar el proyecto ${deleteTarget?.code}? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                variant="danger"
                onConfirm={() => { setDeleteTarget(null); }}
                onCancel={() => { setDeleteTarget(null); }}
            />
        </div>
    );
}
