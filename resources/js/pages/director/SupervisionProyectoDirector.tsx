import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { EmptyState } from '@/components/ui/EmptyState';
import { PhaseStepper, type PhaseStep } from '@/components/project/PhaseStepper';
import { useDirectorProyectos, type DirectorProyecto } from '@/hooks/useDirectorProyectos';
import { apiFetch } from '@/lib/utils';
import {
    ArrowLeft, Search, BookOpen, GraduationCap, FileText,
    Calendar, Clock, User, Award, ChevronDown, ChevronRight,
    Eye, RefreshCw, Loader2, AlertCircle, Users,
} from 'lucide-react';

/* ── Types ── */

interface ProjectDelivery {
    id: number;
    title: string;
    description?: string;
    due_date: string;
    phase: string;
    status: string;
    grade?: string | number | null;
}

interface ProjectDetail {
    id: number;
    code: string;
    title: string;
    description?: string;
    status: string;
    current_phase: string | null;
    estudiantes: { id: number; name: string }[];
    tipo?: string;
    period?: string;
    start_date?: string;
    end_date?: string;
    entregas?: ProjectDelivery[];
}

const deliveryStatusConfig: Record<string, { label: string; variant: 'success' | 'warning' | 'error' | 'info' | 'inactivo' }> = {
    approved: { label: 'Aprobado', variant: 'success' },
    pending: { label: 'Pendiente', variant: 'warning' },
    corrections: { label: 'Correcciones', variant: 'error' },
    rejected: { label: 'Rechazado', variant: 'error' },
};

const projectStatusConfig: Record<string, { label: string; variant: 'success' | 'inactivo' | 'warning' | 'info' }> = {
    active: { label: 'Activo', variant: 'success' },
    completed: { label: 'Completado', variant: 'inactivo' },
    'on-hold': { label: 'En pausa', variant: 'warning' },
};

/* ── Skeleton ── */

function ProjectCardSkeleton() {
    return (
        <div className="h-[200px] animate-pulse rounded-xl border border-[#e5e5e5] bg-white p-5">
            <div className="h-10 w-10 rounded-xl bg-[#f5f5f4] mb-4" />
            <div className="h-3 w-16 rounded bg-[#f5f5f4] mb-2" />
            <div className="h-4 w-full rounded bg-[#f5f5f4] mb-2" />
            <div className="h-4 w-3/4 rounded bg-[#f5f5f4] mb-3" />
            <div className="h-3 w-32 rounded bg-[#f5f5f4]" />
        </div>
    );
}

/* ══════════════════════════════════════
   LIST MODE
   ══════════════════════════════════════ */

function ProjectListView() {
    const navigate = useNavigate();
    const [todas, setTodas] = useState(false);
    const { data: proyectos, loading, error, refetch } = useDirectorProyectos(todas);
    const [search, setSearch] = useState('');

    const filtered = proyectos.filter((p) => {
        const q = search.toLowerCase();
        return (
            p.title.toLowerCase().includes(q) ||
            p.code.toLowerCase().includes(q) ||
            p.estudiantes.some((e) => e.name.toLowerCase().includes(q))
        );
    });

    if (loading && proyectos.length === 0) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Supervisión" title="Proyectos" subtitle="Cargando proyectos asignados..." />
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {Array.from({ length: 6 }).map((_, i) => (
                        <ProjectCardSkeleton key={i} />
                    ))}
                </div>
            </div>
        );
    }

    if (error && proyectos.length === 0) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Supervisión" title="Proyectos" subtitle="Error al cargar los datos" />
                <div className="flex flex-col items-center justify-center gap-4 py-16">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#fee2e2]">
                        <AlertCircle className="h-6 w-6 text-[#dc2626]" />
                    </div>
                    <p className="text-sm text-[#57534e] max-w-md text-center">{error}</p>
                    <button
                        onClick={refetch}
                        className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                    >
                        <RefreshCw className="h-4 w-4" />
                        Reintentar
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Supervisión"
                title="Proyectos"
                subtitle="Seleccione un proyecto para ver su detalle y entregas"
            />

            {/* Toggle inactivos */}
            <div className="flex items-center gap-3">
                <button
                    onClick={() => setTodas(!todas)}
                    className={`inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold transition-colors ${
                        todas
                            ? 'border-[#c2410c] bg-[#fed7aa] text-[#c2410c]'
                            : 'border-[#e5e5e5] bg-white text-[#57534e] hover:border-[#c2410c] hover:text-[#c2410c]'
                    }`}
                >
                    {todas ? '✓ Mostrando todos' : 'Mostrar inactivos'}
                </button>
                {todas && (
                    <span className="text-xs text-[#78716c]">
                        Se muestran proyectos de todos los semestres
                    </span>
                )}
            </div>

            {/* Search */}
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Buscar por código, título o estudiante..."
                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                />
            </div>

            {/* Error inline */}
            {error && (
                <div className="flex items-center gap-3 rounded-lg border border-[#fee2e2] bg-[#fef2f2] p-3 text-sm text-[#dc2626]">
                    <AlertCircle className="h-4 w-4 shrink-0" />
                    <p className="flex-1">{error}</p>
                    <button onClick={refetch} className="text-xs font-semibold text-[#dc2626] underline hover:no-underline">
                        Reintentar
                    </button>
                </div>
            )}

            {/* Cards */}
            {filtered.length === 0 ? (
                <EmptyState
                    icon={BookOpen}
                    title={search ? 'Sin resultados' : 'Sin proyectos asignados'}
                    description={
                        search
                            ? 'No se encontraron proyectos con ese criterio de búsqueda.'
                            : 'No tienes proyectos asignados en semestres activos.'
                    }
                />
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {filtered.map((proj) => (
                        <ProjectCard key={proj.id} project={proj} />
                    ))}
                </div>
            )}
        </div>
    );
}

function ProjectCard({ project }: { project: DirectorProyecto }) {
    const navigate = useNavigate();

    const phaseLabel = project.current_phase
        ? project.current_phase.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
        : 'Sin fase';

    const statusCfg = project.semestre && !project.semestre.is_active
        ? { label: 'Inactivo', variant: 'inactivo' as const }
        : (projectStatusConfig[project.status] ?? projectStatusConfig.active);

    return (
        <div className="group flex flex-col gap-4 rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)] transition-all hover:border-[#c2410c] hover:shadow-[0_4px_12px_rgba(194,65,12,0.1)]">
            {/* Header */}
            <div className="flex items-start justify-between gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#fed7aa] transition-colors group-hover:bg-[#c2410c]">
                    <BookOpen className="h-5 w-5 text-[#c2410c] transition-colors group-hover:text-white" />
                </div>
                <StatusBadge variant={statusCfg.variant}>{statusCfg.label}</StatusBadge>
            </div>

            {/* Info */}
            <div className="flex flex-col gap-1 min-w-0">
                <span className="text-[11px] font-bold uppercase tracking-[0.05em] text-[#c2410c]">
                    {project.code}
                </span>
                <h3 className="text-sm font-bold text-[#1c1917] leading-snug line-clamp-2">
                    {project.title}
                </h3>
                {project.semestre && !project.semestre.is_active && (
                    <span className="mt-0.5 inline-flex items-center gap-1 self-start rounded-full bg-[#e7e5e4] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.03em] text-[#57534e]">
                        Semestre inactivo
                    </span>
                )}
            </div>

            <div className="flex items-center gap-2 text-xs text-[#57534e]">
                <Users className="h-3.5 w-3.5 shrink-0" />
                <span className="truncate">
                    {project.estudiantes.map((e) => e.name).join(', ')}
                </span>
            </div>

            {/* Phase */}
            <span className="inline-flex w-fit items-center rounded-full bg-[#e0e7ff] px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.03em] text-[#1e3a8a]">
                {phaseLabel}
            </span>

            {/* Actions */}
            <div className="flex items-center gap-2 border-t border-[#e5e5e5] pt-3">
                <button
                    onClick={() => navigate(`/supervision/${project.id}`)}
                    className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-xs font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                >
                    <Eye className="h-3.5 w-3.5" />
                    Ver Proyecto
                </button>
                <button
                    onClick={() => navigate(`/supervision/${project.id}/bitacoras`)}
                    className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-xs font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                >
                    <FileText className="h-3.5 w-3.5" />
                    Ver Bitácora
                </button>
            </div>
        </div>
    );
}

/* ══════════════════════════════════════
   DETAIL MODE
   ══════════════════════════════════════ */

function ProjectDetailView({ proyectoId }: { proyectoId: number }) {
    const navigate = useNavigate();
    const [project, setProject] = useState<ProjectDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [expandedDelivery, setExpandedDelivery] = useState<number | null>(null);
    const [selectedPhaseId, setSelectedPhaseId] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch(`/api/director/proyectos/${proyectoId}`);

                if (!res.ok) {
                    throw new Error(
                        res.status === 404
                            ? 'Proyecto no encontrado.'
                            : `Error ${res.status}: ${res.statusText}`,
                    );
                }

                const json = await res.json();
                if (!cancelled) {
                    setProject(json.data ?? json);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err instanceof Error ? err.message : 'Error desconocido.');
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        }

        load();
        return () => { cancelled = true; };
    }, [proyectoId]);

    /* ── Loading ── */
    if (loading) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Supervisión" title="Cargando..." />
                <div className="flex flex-col items-center justify-center gap-4 py-16">
                    <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
                    <p className="text-sm text-[#57534e]">Cargando proyecto...</p>
                </div>
            </div>
        );
    }

    /* ── Error ── */
    if (error || !project) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Supervisión"
                    title="Error"
                    subtitle="No se pudo cargar el proyecto"
                    actions={
                        <button
                            onClick={() => navigate('/supervision')}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver
                        </button>
                    }
                />
                <div className="flex flex-col items-center justify-center gap-4 py-16">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#fee2e2]">
                        <AlertCircle className="h-6 w-6 text-[#dc2626]" />
                    </div>
                    <p className="text-sm text-[#57534e] max-w-md text-center">{error ?? 'Proyecto no encontrado.'}</p>
                    <button
                        onClick={() => window.location.reload()}
                        className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                    >
                        <RefreshCw className="h-4 w-4" />
                        Reintentar
                    </button>
                </div>
            </div>
        );
    }

    const estudianteName = project.estudiantes?.map((e) => e.name).join(', ') ?? '';
    const deliveries = project.entregas ?? [];

    const PHASE_IDS = ['anteproyecto', 'presentacion_anteproyecto', 'desarrollo', 'presentacion_final'];
    const PHASE_LABELS: Record<string, string> = {
        anteproyecto: 'Anteproyecto',
        presentacion_anteproyecto: 'Presentación Anteproyecto',
        desarrollo: 'Desarrollo del proyecto',
        presentacion_final: 'Presentación Final',
    };
    const PHASE_STEP_MAP: Record<string, number> = {
        anteproyecto: 0,
        presentacion_anteproyecto: 1,
        desarrollo: 2,
        presentacion_final: 3,
    };
    const currentStep = project.current_phase ? (PHASE_STEP_MAP[project.current_phase] ?? 0) : 0;
    const allPhases: PhaseStep[] = PHASE_IDS.map((id, idx) => ({
        id,
        label: PHASE_LABELS[id],
        status: idx < currentStep ? 'done' : idx === currentStep ? 'current' : 'future',
    }));
    const activePhaseId = selectedPhaseId ?? (project.current_phase ?? PHASE_IDS[0]);
    const filteredDeliveries = deliveries.filter((d) => !activePhaseId || d.phase === activePhaseId);
    const deliveryCountByPhase = (phaseId: string) => deliveries.filter((d) => d.phase === phaseId).length;

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Supervisión"
                title={project.title}
                subtitle={`${project.code} · ${estudianteName}`}
                actions={
                    <button
                        onClick={() => navigate('/supervision')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            {/* Project info card */}
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#fed7aa]">
                            <Award className="h-7 w-7 text-[#c2410c]" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="inline-flex items-center rounded-full bg-[#e7e5e4] px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.03em] text-[#57534e]">
                                    {project.code}
                                </span>
                                {project.period && (
                                    <StatusBadge variant="info">{project.period}</StatusBadge>
                                )}
                                <StatusBadge variant={project.status === 'active' ? 'success' : 'inactivo'}>
                                    {project.status === 'active' ? 'Activo' : 'Completado'}
                                </StatusBadge>
                            </div>
                            <h2 className="mt-1 text-xl font-bold text-[#1c1917]">{project.title}</h2>
                        </div>
                    </div>
                </div>

                <hr className="my-5 border-t border-[#e5e5e5]" />

                {/* Info grid */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                        <User className="h-5 w-5 text-[#c2410c]" />
                        <div>
                            <p className="text-xs text-[#78716c]">Estudiante</p>
                            <p className="text-sm font-semibold text-[#1c1917]">{estudianteName}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                        <FileText className="h-5 w-5 text-[#4f46e5]" />
                        <div>
                            <p className="text-xs text-[#78716c]">Tipo</p>
                            <p className="text-sm font-semibold text-[#1c1917]">{project.tipo ?? '—'}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                        <Calendar className="h-5 w-5 text-[#16a34a]" />
                        <div>
                            <p className="text-xs text-[#78716c]">Inicio</p>
                            <p className="text-sm font-semibold text-[#1c1917]">
                                {project.start_date
                                    ? new Date(project.start_date).toLocaleDateString('es-CO')
                                    : '—'}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                        <Clock className="h-5 w-5 text-[#d97706]" />
                        <div>
                            <p className="text-xs text-[#78716c]">Fin</p>
                            <p className="text-sm font-semibold text-[#1c1917]">
                                {project.end_date
                                    ? new Date(project.end_date).toLocaleDateString('es-CO')
                                    : '—'}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Phase */}
                {project.current_phase && (
                    <div className="mt-4 flex items-center gap-2">
                        <span className="text-xs font-semibold uppercase tracking-[0.05em] text-[#78716c]">Fase actual:</span>
                        <span className="inline-flex items-center rounded-full bg-[#e0e7ff] px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.03em] text-[#1e3a8a]">
                            {project.current_phase.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                        </span>
                    </div>
                )}
            </div>

            {/* Phase Stepper */}
            <PhaseStepper
                phases={allPhases}
                selectedPhaseId={activePhaseId}
                onSelectPhase={setSelectedPhaseId}
                deliveryCountByPhase={deliveryCountByPhase}
                title="Progreso del Proyecto"
            />

            {/* Deliveries */}
            <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="border-b border-[#e5e5e5] px-6 py-4">
                    <h3 className="text-base font-bold text-[#1c1917]">Entregas ({filteredDeliveries.length})</h3>
                </div>

                {filteredDeliveries.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-12 text-center">
                        <FileText className="h-8 w-8 text-[#78716c]" />
                        <p className="text-sm text-[#57534e]">
                            {deliveries.length === 0
                                ? 'Este proyecto aún no tiene entregas registradas.'
                                : 'No hay entregas para esta fase.'}
                        </p>
                    </div>
                ) : (
                    <div className="divide-y divide-[#e5e5e5]">
                        {filteredDeliveries.map((d) => {
                            const config = deliveryStatusConfig[d.status] ?? deliveryStatusConfig.pending;
                            const isExpanded = expandedDelivery === d.id;
                            return (
                                <div key={d.id}>
                                    <button
                                        onClick={() => setExpandedDelivery(isExpanded ? null : d.id)}
                                        className="flex w-full items-center justify-between gap-4 px-6 py-4 text-left transition-colors hover:bg-[#fafaf9]"
                                    >
                                        <div className="flex items-center gap-4 min-w-0">
                                            {isExpanded ? (
                                                <ChevronDown className="h-4 w-4 shrink-0 text-[#78716c]" />
                                            ) : (
                                                <ChevronRight className="h-4 w-4 shrink-0 text-[#78716c]" />
                                            )}
                                            <div className="min-w-0">
                                                <p className="text-sm font-semibold text-[#1c1917] truncate">{d.title}</p>
                                                <p className="text-xs text-[#78716c]">
                                                    {new Date(d.due_date).toLocaleDateString('es-CO')}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3 shrink-0">
                                            <StatusBadge variant={config.variant}>{config.label}</StatusBadge>
                                            {d.grade != null && (
                                                <span className="text-sm font-bold text-[#1c1917] tabular-nums">{d.grade}</span>
                                            )}
                                        </div>
                                    </button>
                                    {isExpanded && (
                                        <div className="border-t border-[#e5e5e5] bg-[#fafaf9] px-6 py-4">
                                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                <p className="text-sm text-[#57534e]">
                                                    {d.status === 'pending'
                                                        ? 'El estudiante aún no ha realizado esta entrega.'
                                                        : d.status === 'corrections'
                                                            ? 'Se solicitaron correcciones. Pendiente de re-entrega.'
                                                            : 'Entrega revisada y aprobada.'}
                                                </p>
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        onClick={() => navigate(`/entregas/${d.id}/revisar`)}
                                                        className="inline-flex min-h-[36px] items-center gap-2 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                                                    >
                                                        Revisar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}

/* ══════════════════════════════════════
   MAIN COMPONENT
   ══════════════════════════════════════ */

export default function SupervisionProyectoDirector() {
    const { proyectoId } = useParams<{ proyectoId: string }>();

    // If no proyectoId in URL, show the list view
    if (!proyectoId) {
        return <ProjectListView />;
    }

    // proyectoId present → show detail
    return <ProjectDetailView proyectoId={Number(proyectoId)} />;
}
