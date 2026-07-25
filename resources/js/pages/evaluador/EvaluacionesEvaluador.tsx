import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { useEvaluadorEvaluaciones, type EvaluacionAsignadaEvaluador } from '@/hooks/useEvaluadorEvaluaciones';
import { datoNoEncontrado } from '@/lib/datoNoEncontrado';
import {
    AlertCircle,
    ArrowDownUp,
    CheckCircle,
    ClipboardList,
    Clock,
    Search,
} from 'lucide-react';

type StatusFilter = 'all' | 'pending' | 'evaluated';
type SortDir = 'desc' | 'asc';

const PAGE_SIZE = 20;

function formatDate(iso: string | null | undefined): string {
    if (!iso) return datoNoEncontrado('La fecha de asignación');
    const d = new Date(iso.includes('T') ? iso : `${iso}T00:00:00`);
    if (Number.isNaN(d.getTime())) return datoNoEncontrado('La fecha de asignación');
    return d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function assignmentTimestamp(row: EvaluacionAsignadaEvaluador): number {
    const raw = row.assigned_at ?? row.fecha;
    if (!raw) return 0;
    const d = new Date(raw.includes('T') ? raw : `${raw}T00:00:00`);
    return Number.isNaN(d.getTime()) ? 0 : d.getTime();
}

function faseLabel(fase: string | null): string {
    if (!fase) return datoNoEncontrado('La fase actual');
    const labels: Record<string, string> = {
        anteproyecto: 'Anteproyecto',
        presentacion_anteproyecto: 'Presentación Anteproyecto',
        desarrollo: 'Desarrollo',
        presentacion_final: 'Presentación Final',
        Anteproyecto: 'Anteproyecto',
        Final: 'Presentación Final',
        Desarrollo: 'Desarrollo',
    };
    return labels[fase] ?? fase;
}

function studentsLabel(row: EvaluacionAsignadaEvaluador): string {
    const names = row.estudiantes?.map((e) => e.name).filter(Boolean) ?? [];
    if (names.length === 0) return datoNoEncontrado('El nombre del estudiante');
    return names.join(', ');
}

function directorLabel(row: EvaluacionAsignadaEvaluador): string {
    return row.director?.name || datoNoEncontrado('El director');
}

function matchesSearch(row: EvaluacionAsignadaEvaluador, query: string): boolean {
    if (!query) return true;
    const q = query.toLowerCase();
    const haystack = [
        row.title ?? '',
        row.code ?? '',
        directorLabel(row),
        ...(row.estudiantes?.map((e) => e.name) ?? []),
    ]
        .join(' ')
        .toLowerCase();
    return haystack.includes(q);
}

export default function EvaluacionesEvaluador() {
    const navigate = useNavigate();
    const { data, kpis, loading, error, refetch } = useEvaluadorEvaluaciones();
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
    const [sortDir, setSortDir] = useState<SortDir>('desc');
    const [page, setPage] = useState(1);

    const filtered = useMemo(() => {
        const list = data
            .filter((row) => matchesSearch(row, search.trim()))
            .filter((row) => statusFilter === 'all' || row.evaluation_status === statusFilter)
            .slice()
            .sort((a, b) => {
                const diff = assignmentTimestamp(a) - assignmentTimestamp(b);
                return sortDir === 'asc' ? diff : -diff;
            });
        return list;
    }, [data, search, statusFilter, sortDir]);

    const pendingCount = useMemo(
        () => data.filter((r) => r.evaluation_status === 'pending').length,
        [data],
    );
    const evaluatedCount = useMemo(
        () => data.filter((r) => r.evaluation_status === 'evaluated').length,
        [data],
    );

    const lastPage = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    const currentPage = Math.min(page, lastPage);
    const pageData = filtered.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);

    const columns: Column<EvaluacionAsignadaEvaluador>[] = [
        {
            key: 'proyecto',
            label: 'Proyecto',
            render: (row) => (
                <div className="min-w-[180px]">
                    <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#c2410c]">
                        {row.code || datoNoEncontrado('El código del proyecto')}
                    </p>
                    <p className="font-semibold text-[#1c1917]">
                        {row.title || datoNoEncontrado('El título del proyecto')}
                    </p>
                </div>
            ),
        },
        {
            key: 'estudiantes',
            label: 'Estudiantes',
            render: (row) => <span className="text-[#57534e]">{studentsLabel(row)}</span>,
        },
        {
            key: 'director',
            label: 'Director',
            render: (row) => <span className="text-[#57534e]">{directorLabel(row)}</span>,
        },
        {
            key: 'modalidad',
            label: 'Modalidad',
            render: () => (
                <span className="text-[#57534e]">{datoNoEncontrado('La modalidad')}</span>
            ),
        },
        {
            key: 'fase',
            label: 'Fase',
            render: (row) => (
                <span className="text-[#57534e]">
                    {faseLabel(row.fase_asignada || row.current_phase)}
                </span>
            ),
        },
        {
            key: 'fecha',
            label: 'Asignación',
            render: (row) => (
                <span className="tabular-nums text-[#57534e]">
                    {formatDate(row.assigned_at ?? row.fecha)}
                </span>
            ),
        },
        {
            key: 'estado',
            label: 'Estado',
            render: (row) => {
                const evaluated = row.evaluation_status === 'evaluated';
                return (
                    <StatusBadge variant={evaluated ? 'success' : 'warning'}>
                        {evaluated ? 'Evaluada' : 'Pendiente'}
                    </StatusBadge>
                );
            },
        },
        {
            key: 'acciones',
            label: 'Acciones',
            render: (row) => {
                const evaluated = row.evaluation_status === 'evaluated';
                return (
                    <button
                        type="button"
                        onClick={() =>
                            navigate(
                                evaluated
                                    ? `/evaluaciones/${row.id}/calificar`
                                    : `/evaluaciones/${row.id}`,
                            )
                        }
                        className="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-[#e5e5e5] bg-transparent px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        {evaluated ? 'Ver evaluación' : 'Evaluar'}
                    </button>
                );
            },
        },
    ];

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Gestión"
                title="Evaluaciones"
                subtitle="Proyectos de grado asignados para tu evaluación."
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    icon={ClipboardList}
                    label="Asignadas"
                    value={kpis?.proyectos_asignados ?? data.length}
                />
                <StatCard
                    icon={Clock}
                    label="Pendientes"
                    value={kpis?.evaluaciones_pendientes ?? pendingCount}
                    variant="warning"
                />
                <StatCard
                    icon={CheckCircle}
                    label="Realizadas"
                    value={kpis?.evaluaciones_completadas ?? evaluatedCount}
                    variant="success"
                />
            </div>

            {error && (
                <div className="flex items-center gap-3 rounded-lg border border-[#fee2e2] bg-[#fef2f2] p-3 text-sm text-[#dc2626]">
                    <AlertCircle className="h-4 w-4 shrink-0" />
                    <p className="flex-1">{error}</p>
                    <button
                        type="button"
                        onClick={refetch}
                        className="text-xs font-semibold text-[#dc2626] underline hover:no-underline"
                    >
                        Reintentar
                    </button>
                </div>
            )}

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative w-full sm:max-w-md">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            setPage(1);
                        }}
                        placeholder="Buscar por proyecto, estudiante o director..."
                        className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white py-2 pl-9 pr-3 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                        aria-label="Buscar evaluaciones"
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <select
                        value={statusFilter}
                        onChange={(e) => {
                            setStatusFilter(e.target.value as StatusFilter);
                            setPage(1);
                        }}
                        className="min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                        aria-label="Filtrar por estado"
                    >
                        <option value="all">Todos</option>
                        <option value="pending">Pendientes</option>
                        <option value="evaluated">Evaluadas</option>
                    </select>

                    <button
                        type="button"
                        onClick={() => setSortDir((d) => (d === 'desc' ? 'asc' : 'desc'))}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                        aria-label="Ordenar por fecha de asignación"
                    >
                        <ArrowDownUp className="h-4 w-4" />
                        Fecha {sortDir === 'desc' ? '↓' : '↑'}
                    </button>
                </div>
            </div>

            <p className="text-xs text-[#57534e]">
                {filtered.length === 1
                    ? '1 resultado encontrado'
                    : `${filtered.length} resultados encontrados`}
            </p>

            <DataTable
                columns={columns}
                data={pageData}
                loading={loading}
                getRowKey={(row) => row.id}
                emptyMessage={
                    data.length === 0
                        ? 'No tienes proyectos asignados como evaluador.'
                        : 'No se encontraron evaluaciones con los filtros seleccionados.'
                }
                pagination={
                    filtered.length > PAGE_SIZE
                        ? {
                              currentPage,
                              lastPage,
                              total: filtered.length,
                              onPageChange: setPage,
                          }
                        : undefined
                }
            />
        </div>
    );
}
