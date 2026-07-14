import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { useDirectores, type Director, type DirectorProyecto, type Bitacora } from '@/hooks/useDirectores';
import SupervisionReadOnly from '@/components/supervision/SupervisionReadOnly';
import {
    UserCheck,
    BookText,
    FolderKanban,
    ArrowLeft,
    AlertTriangle,
    RefreshCw,
    FileText,
    ChevronRight,
    ScrollText,
    ExternalLink,
    Calendar,
    Loader2,
} from 'lucide-react';

/* ── Types ── */

type NivelView = 1 | 2 | 3;
type DrillMode = 'bitacoras' | 'proyectos' | null;

/* ── Subcomponents ── */

function SkeletonCard() {
    return (
        <div className="animate-pulse rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <div className="mb-3 h-10 w-10 rounded-xl bg-[#e5e5e5]" />
            <div className="mb-1 h-4 w-32 rounded bg-[#e5e5e5]" />
            <div className="mb-2 h-3 w-24 rounded bg-[#e5e5e5]" />
            <div className="mt-3 flex gap-2">
                <div className="h-8 w-24 rounded-lg bg-[#e5e5e5]" />
                <div className="h-8 w-24 rounded-lg bg-[#e5e5e5]" />
            </div>
        </div>
    );
}

function ErrorBanner({ message, onRetry }: { message: string; onRetry: () => void }) {
    return (
        <div className="rounded-xl border border-[#fee2e2] bg-[#fee2e2]/40 p-4">
            <div className="flex items-center gap-3">
                <AlertTriangle className="h-5 w-5 shrink-0 text-[#dc2626]" />
                <p className="text-sm text-[#7f1d1d]">{message}</p>
                <button
                    onClick={onRetry}
                    className="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-[#dc2626]/30 bg-white px-3 py-1.5 text-xs font-semibold text-[#7f1d1d] transition-colors hover:bg-[#fee2e2]"
                    aria-label="Reintentar"
                >
                    <RefreshCw className="h-3.5 w-3.5" />
                    Reintentar
                </button>
            </div>
        </div>
    );
}

function EmptyState({ icon: Icon, message }: { icon: React.ComponentType<{ className?: string }>; message: string }) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-[#e5e5e5] bg-white py-16">
            <Icon className="mb-3 h-10 w-10 text-[#d6d3d1]" />
            <p className="text-sm font-medium text-[#a8a29e]">{message}</p>
        </div>
    );
}

/* ── Level 1: Director Cards ── */

interface DirectorCardProps {
    director: Director;
    onViewBitacoras: (d: Director) => void;
    onViewProyectos: (d: Director) => void;
}

function DirectorCard({ director, onViewBitacoras, onViewProyectos }: DirectorCardProps) {
    return (
        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)] transition-shadow hover:shadow-[0_4px_12px_rgba(28,25,23,0.08)]">
            <div className="mb-3 flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#e0e7ff]">
                    <UserCheck className="h-5 w-5 text-[#4f46e5]" />
                </div>
                <div className="min-w-0">
                    <h3 className="text-sm font-bold text-[#1c1917] truncate">{director.name}</h3>
                    <p className="text-xs text-[#78716c]">{director.email}</p>
                </div>
            </div>

            {director.areas.length > 0 && (
                <div className="mb-4 flex flex-wrap gap-1.5">
                    {director.areas.map((area) => (
                        <StatusBadge key={area.id} variant="info">{area.name}</StatusBadge>
                    ))}
                </div>
            )}

            <div className="flex gap-2">
                <button
                    onClick={() => onViewBitacoras(director)}
                    className="inline-flex min-h-[36px] flex-1 items-center justify-center gap-1.5 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:border-[#4f46e5] hover:bg-[#e0e7ff] hover:text-[#4f46e5] active:scale-[0.98]"
                    aria-label={`Ver bitácoras de ${director.name}`}
                >
                    <ScrollText className="h-3.5 w-3.5" />
                    Ver bitácoras
                </button>
                <button
                    onClick={() => onViewProyectos(director)}
                    className="inline-flex min-h-[36px] flex-1 items-center justify-center gap-1.5 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    aria-label={`Ver proyectos de ${director.name}`}
                >
                    <FolderKanban className="h-3.5 w-3.5" />
                    Ver proyectos
                </button>
            </div>
        </div>
    );
}

/* ── Level 2: Project Cards ── */

interface ProjectCardProps {
    proyecto: DirectorProyecto;
    mode: DrillMode;
    onSelect: (p: DirectorProyecto) => void;
}

function ProjectCard({ proyecto, mode, onSelect }: ProjectCardProps) {
    const navigate = useNavigate();
    const isBitacoraMode = mode === 'bitacoras';

    return (
        <div
            className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)] transition-shadow hover:shadow-[0_4px_12px_rgba(28,25,23,0.08)]"
        >
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="inline-flex items-center rounded-full bg-[#e7e5e4] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.03em] text-[#57534e]">
                            {proyecto.code}
                        </span>
                        <StatusBadge variant={proyecto.status === 'active' ? 'success' : proyecto.status === 'at-risk' ? 'riesgo' : 'inactivo'}>
                            {proyecto.status === 'active' ? 'Activo' : proyecto.status === 'at-risk' ? 'En Riesgo' : 'Completado'}
                        </StatusBadge>
                    </div>
                    <h3 className="mt-2 text-sm font-bold text-[#1c1917]">{proyecto.title}</h3>
                    <p className="mt-1 text-xs text-[#78716c]">
                        {proyecto.students.map((s) => s.name).join(', ')}
                    </p>
                </div>

                <div className="flex shrink-0 items-start gap-2">
                    {isBitacoraMode ? (
                        <button
                            onClick={() => onSelect(proyecto)}
                            className="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:border-[#4f46e5] hover:bg-[#e0e7ff] hover:text-[#4f46e5] active:scale-[0.98]"
                            aria-label={`Ver bitácoras de ${proyecto.title}`}
                        >
                            <ScrollText className="h-3.5 w-3.5" />
                            Bitácoras
                        </button>
                    ) : (
                        <button
                            onClick={() => navigate(`/dashboard/coordinador/proyecto/${proyecto.id}`)}
                            className="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                            aria-label={`Ver supervisión de ${proyecto.title}`}
                        >
                            <ExternalLink className="h-3.5 w-3.5" />
                            Supervisión
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

/* ── Level 3: Bitácora List ── */

function BitacoraItem({ bitacora }: { bitacora: Bitacora }) {
    const [expanded, setExpanded] = useState(false);

    return (
        <div className="border-b border-[#e5e5e5] last:border-b-0">
            <button
                onClick={() => setExpanded(!expanded)}
                className="flex w-full items-center justify-between gap-4 px-6 py-4 text-left transition-colors hover:bg-[#fafaf9]"
                aria-expanded={expanded}
                aria-label={`Bitácora del ${bitacora.fecha}`}
            >
                <div className="flex items-center gap-3 min-w-0">
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#fef3c7]">
                        <FileText className="h-4 w-4 text-[#d97706]" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-sm font-semibold text-[#1c1917]">{bitacora.fecha}</p>
                        <p className="text-xs text-[#78716c] truncate">
                            {bitacora.director_name}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-3 shrink-0">
                    <StatusBadge variant={bitacora.firmada ? 'success' : 'warning'}>
                        {bitacora.firmada ? 'Firmada' : 'Pendiente'}
                    </StatusBadge>
                    {expanded ? (
                        <ChevronRight className="h-4 w-4 rotate-90 text-[#78716c] transition-transform" />
                    ) : (
                        <ChevronRight className="h-4 w-4 text-[#78716c] transition-transform" />
                    )}
                </div>
            </button>
            {expanded && (
                <div className="border-t border-[#e5e5e5] bg-[#fafaf9] px-6 py-4">
                    <p className="text-sm text-[#57534e] whitespace-pre-line">{bitacora.contenido}</p>
                </div>
            )}
        </div>
    );
}

/* ── Main Component ── */

export default function DirectoresPage() {
    const navigate = useNavigate();
    const {
        directores,
        loading,
        error,
        fetchDirectores,
        selectDirector,
        selectedDirector,
        proyectos,
        loadingProyectos,
        errorProyectos,
        selectProyecto,
        selectedProyecto,
        bitacoras,
        loadingBitacoras,
        errorBitacoras,
        reset,
    } = useDirectores();

    const [nivel, setNivel] = useState<NivelView>(1);
    const [drillMode, setDrillMode] = useState<DrillMode>(null);

    useEffect(() => {
        fetchDirectores();
    }, [fetchDirectores]);

    /* ── Navigation helpers ── */

    function handleViewBitacoras(director: Director) {
        setDrillMode('bitacoras');
        selectDirector(director);
        setNivel(2);
    }

    function handleViewProyectos(director: Director) {
        setDrillMode('proyectos');
        selectDirector(director);
        setNivel(2);
    }

    function handleSelectProyecto(proyecto: DirectorProyecto) {
        selectProyecto(proyecto);
        setNivel(3);
    }

    function handleBack() {
        if (nivel === 3) {
            setNivel(2);
            setSelectedProyecto(null);
        } else if (nivel === 2) {
            setNivel(1);
            reset();
        }
    }

    /* ── Breadcrumb aria-label ── */

    const nivelLabel =
        nivel === 1
            ? 'Lista de directores'
            : nivel === 2
              ? `Proyectos de ${selectedDirector?.name ?? ''}`
              : `Bitácoras de ${selectedProyecto?.title ?? ''}`;

    /* ── Render ── */

    return (
        <div className="flex flex-col gap-6" aria-label={nivelLabel}>
            {/* Header */}
            <PageHeader
                eyebrow="Coordinación"
                title={
                    nivel === 1
                        ? 'Directores'
                        : nivel === 2
                          ? `Proyectos de ${selectedDirector?.name ?? ''}`
                          : `Bitácoras — ${selectedProyecto?.title ?? ''}`
                }
                subtitle={
                    nivel === 1
                        ? 'Consulta la información de directores, sus proyectos y bitácoras.'
                        : undefined
                }
                actions={
                    nivel > 1 ? (
                        <button
                            onClick={handleBack}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver
                        </button>
                    ) : undefined
                }
            />

            {/* Level 1: Director Cards */}
            {nivel === 1 && (
                <>
                    {loading ? (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3" role="status" aria-label="Cargando directores">
                            {Array.from({ length: 6 }).map((_, i) => (
                                <SkeletonCard key={i} />
                            ))}
                        </div>
                    ) : error ? (
                        <ErrorBanner message={error} onRetry={fetchDirectores} />
                    ) : directores.length === 0 ? (
                        <EmptyState icon={UserCheck} message="Sin directores registrados" />
                    ) : (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {directores.map((director) => (
                                <DirectorCard
                                    key={director.id}
                                    director={director}
                                    onViewBitacoras={handleViewBitacoras}
                                    onViewProyectos={handleViewProyectos}
                                />
                            ))}
                        </div>
                    )}
                </>
            )}

            {/* Level 2: Projects */}
            {nivel === 2 && (
                <>
                    {loadingProyectos ? (
                        <div className="flex items-center justify-center py-16" role="status" aria-label="Cargando proyectos">
                            <Loader2 className="h-6 w-6 animate-spin text-[#c2410c]" />
                        </div>
                    ) : errorProyectos ? (
                        <ErrorBanner message={errorProyectos} onRetry={() => selectedDirector && selectDirector(selectedDirector)} />
                    ) : proyectos.length === 0 ? (
                        <EmptyState icon={FolderKanban} message="Este director no tiene proyectos asignados" />
                    ) : (
                        <div className="flex flex-col gap-3">
                            {proyectos.map((proyecto) => (
                                <ProjectCard
                                    key={proyecto.id}
                                    proyecto={proyecto}
                                    mode={drillMode}
                                    onSelect={handleSelectProyecto}
                                />
                            ))}
                        </div>
                    )}
                </>
            )}

            {/* Level 3: Bitácoras or SupervisionReadOnly */}
            {nivel === 3 && drillMode === 'bitacoras' && (
                <>
                    {loadingBitacoras ? (
                        <div className="flex items-center justify-center py-16" role="status" aria-label="Cargando bitácoras">
                            <Loader2 className="h-6 w-6 animate-spin text-[#c2410c]" />
                        </div>
                    ) : errorBitacoras ? (
                        <ErrorBanner message={errorBitacoras} onRetry={() => selectedProyecto && selectProyecto(selectedProyecto)} />
                    ) : bitacoras.length === 0 ? (
                        <EmptyState icon={ScrollText} message="No hay bitácoras registradas para este proyecto" />
                    ) : (
                        <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="border-b border-[#e5e5e5] px-6 py-4">
                                <div className="flex items-center gap-2">
                                    <Calendar className="h-4 w-4 text-[#78716c]" />
                                    <h3 className="text-sm font-bold text-[#1c1917]">
                                        Bitácoras
                                    </h3>
                                </div>
                            </div>
                            <div className="divide-y divide-[#e5e5e5]">
                                {bitacoras.map((bitacora) => (
                                    <BitacoraItem key={bitacora.id} bitacora={bitacora} />
                                ))}
                            </div>
                        </div>
                    )}
                </>
            )}

            {nivel === 3 && drillMode === 'proyectos' && selectedProyecto && (
                <SupervisionReadOnly
                    projectCode={selectedProyecto.code}
                    projectTitle={selectedProyecto.title}
                />
            )}
        </div>
    );
}
