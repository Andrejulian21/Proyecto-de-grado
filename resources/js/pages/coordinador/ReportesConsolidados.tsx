import { useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Download, FileText, BarChart3, Filter, Calendar, Search, Loader2 } from 'lucide-react';

interface ReportRow {
    project: string;
    student: string;
    director: string;
    avgGrade: string;
    deliveries: number;
    completed: number;
    status: string;
}

const MOCK_DATA: ReportRow[] = [
    { project: 'PG-2026-014', student: 'Carlos Méndez', director: 'Dr. Ricardo Gómez', avgGrade: '85.0', deliveries: 4, completed: 2, status: 'En progreso' },
    { project: 'PG-2026-015', student: 'María Rincón', director: 'Dr. Ricardo Gómez', avgGrade: '88.0', deliveries: 4, completed: 1, status: 'En progreso' },
    { project: 'PG-2026-012', student: 'Juan Pérez', director: 'Dra. Laura Martínez', avgGrade: '92.0', deliveries: 4, completed: 4, status: 'Finalizado' },
    { project: 'PG-2026-010', student: 'Laura Gómez', director: 'Dr. Andrés Vega', avgGrade: '70.0', deliveries: 3, completed: 1, status: 'En pausa' },
    { project: 'PG-2026-008', student: 'Andrés Torres', director: 'Dra. Laura Martínez', avgGrade: '78.5', deliveries: 4, completed: 2, status: 'En progreso' },
    { project: 'PG-2026-005', student: 'Diana Rojas', director: 'Dr. Andrés Vega', avgGrade: '82.0', deliveries: 3, completed: 2, status: 'En progreso' },
];

const columns: Column<ReportRow>[] = [
    { key: 'project', label: 'Proyecto' },
    { key: 'student', label: 'Estudiante' },
    { key: 'director', label: 'Director' },
    { key: 'avgGrade', label: 'Promedio', className: 'tabular-nums', render: (row) => (
        <span className={`font-semibold ${Number(row.avgGrade) >= 80 ? 'text-[#16a34a]' : Number(row.avgGrade) >= 60 ? 'text-[#d97706]' : 'text-[#dc2626]'}`}>
            {row.avgGrade}
        </span>
    )},
    { key: 'deliveries', label: 'Entregas', className: 'tabular-nums text-center' },
    { key: 'completed', label: 'Completadas', className: 'tabular-nums text-center' },
    { key: 'status', label: 'Estado' },
];

export default function ReportesConsolidados() {
    const [search, setSearch] = useState('');
    const [period, setPeriod] = useState('2026-01');
    const [exporting, setExporting] = useState(false);

    const filtered = MOCK_DATA.filter((r) =>
        r.project.toLowerCase().includes(search.toLowerCase()) ||
        r.student.toLowerCase().includes(search.toLowerCase())
    );

    const promedioGeneral = MOCK_DATA.reduce((s, r) => s + Number(r.avgGrade), 0) / MOCK_DATA.length;
    const totalEntregas = MOCK_DATA.reduce((s, r) => s + r.deliveries, 0);
    const completadas = MOCK_DATA.reduce((s, r) => s + r.completed, 0);

    async function handleExport() {
        setExporting(true);
        try {
            await new Promise((r) => setTimeout(r, 1200));
        } finally {
            setExporting(false);
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Reportes"
                title="Reportes Consolidados"
                subtitle="Indicadores y estadísticas generales del programa"
                actions={
                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleExport}
                            disabled={exporting}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98] disabled:opacity-60"
                        >
                            {exporting ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Download className="h-4 w-4" />
                            )}
                            Exportar Excel
                        </button>
                        <button
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                        >
                            <FileText className="h-4 w-4" />
                            Exportar PDF
                        </button>
                    </div>
                }
            />

            {/* Filter card */}
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div className="flex-1 flex flex-col gap-1.5">
                        <label htmlFor="report-period" className="text-xs font-semibold text-[#57534e] uppercase tracking-wide">Período</label>
                        <div className="relative">
                            <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                            <select
                                id="report-period"
                                value={period}
                                onChange={(e) => setPeriod(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            >
                                <option value="2026-01">2026-01 (Ene — Jun)</option>
                                <option value="2025-02">2025-02 (Jul — Dic)</option>
                                <option value="2025-01">2025-01 (Ene — Jun)</option>
                            </select>
                        </div>
                    </div>
                    <div className="flex-1 flex flex-col gap-1.5">
                        <label htmlFor="report-search" className="text-xs font-semibold text-[#57534e] uppercase tracking-wide">Buscar</label>
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                            <input
                                id="report-search"
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Proyecto, estudiante..."
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            />
                        </div>
                    </div>
                    <button className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]">
                        <Filter className="h-4 w-4" />
                        Aplicar
                    </button>
                </div>
            </div>

            {/* Summary cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <p className="text-xs text-[#57534e]">Proyectos activos</p>
                    <p className="text-2xl font-bold text-[#1c1917] tabular-names">{filtered.length}</p>
                </div>
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <p className="text-xs text-[#57534e]">Promedio general</p>
                    <p className="text-2xl font-bold text-[#1c1917] tabular-nums">{promedioGeneral.toFixed(1)}</p>
                </div>
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <p className="text-xs text-[#57534e]">Total entregas</p>
                    <p className="text-2xl font-bold text-[#1c1917] tabular-nums">{totalEntregas}</p>
                </div>
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <p className="text-xs text-[#57534e]">Completadas</p>
                    <p className="text-2xl font-bold text-[#16a34a] tabular-nums">{completadas}</p>
                </div>
            </div>

            {/* Bar chart mock */}
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="mb-4 flex items-center gap-2">
                    <BarChart3 className="h-5 w-5 text-[#c2410c]" />
                    <h3 className="text-base font-bold text-[#1c1917]">Distribución de Calificaciones</h3>
                </div>
                <div className="flex items-end gap-3 h-40">
                    {[
                        { label: '0-59', value: 1, color: '#dc2626' },
                        { label: '60-69', value: 2, color: '#d97706' },
                        { label: '70-79', value: 3, color: '#fef3c7' },
                        { label: '80-89', value: 4, color: '#16a34a' },
                        { label: '90-100', value: 3, color: '#14532d' },
                    ].map((bar) => (
                        <div key={bar.label} className="flex flex-1 flex-col items-center gap-2">
                            <span className="text-xs font-semibold text-[#1c1917] tabular-nums">{bar.value}</span>
                            <div
                                className="w-full rounded-t-lg transition-all"
                                style={{
                                    height: `${(bar.value / 4) * 100}%`,
                                    backgroundColor: bar.color,
                                    minHeight: '20px',
                                }}
                            />
                            <span className="text-xs text-[#78716c]">{bar.label}</span>
                        </div>
                    ))}
                </div>
            </div>

            {/* Data table */}
            <DataTable
                columns={columns}
                data={filtered}
                getRowKey={(row) => row.project}
                emptyMessage="No se encontraron datos para el período seleccionado."
            />
        </div>
    );
}
