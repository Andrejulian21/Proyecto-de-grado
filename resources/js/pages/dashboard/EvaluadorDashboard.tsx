import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { useEvaluadorEvaluaciones, type EvaluacionAsignadaEvaluador } from '@/hooks/useEvaluadorEvaluaciones';
import { datoNoEncontrado } from '@/lib/datoNoEncontrado';
import {
    ClipboardList,
    Clock,
    CheckCircle,
    Star,
    Calendar,
    Users,
    School,
    ArrowRight,
    AlertCircle,
    Loader2,
} from 'lucide-react';

function formatDate(iso: string | null | undefined): string {
    if (!iso) return datoNoEncontrado('La fecha de asignación');
    const d = new Date(iso.includes('T') ? iso : `${iso}T00:00:00`);
    if (Number.isNaN(d.getTime())) return datoNoEncontrado('La fecha de asignación');
    return d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function studentsLabel(evaluation: EvaluacionAsignadaEvaluador): string {
    const names = evaluation.estudiantes?.map((e) => e.name).filter(Boolean) ?? [];
    if (names.length === 0) return datoNoEncontrado('El nombre del estudiante');
    return names.join(', ');
}

function directorLabel(evaluation: EvaluacionAsignadaEvaluador): string {
    if (!evaluation.director?.name) return datoNoEncontrado('El director');
    return evaluation.director.name;
}

function StarRating({ rating }: { rating: number }) {
    const fullStars = Math.floor(rating);
    const hasHalf = rating - fullStars >= 0.3;
    const emptyStars = 5 - fullStars - (hasHalf ? 1 : 0);

    return (
        <div className="flex items-center gap-0.5" aria-label={`Calificación: ${rating} de 5 estrellas`}>
            {Array.from({ length: fullStars }).map((_, i) => (
                <Star key={`full-${i}`} className="h-4 w-4 fill-warning text-warning" />
            ))}
            {hasHalf && <Star className="h-4 w-4 fill-warning/30 text-warning" />}
            {Array.from({ length: emptyStars }).map((_, i) => (
                <Star key={`empty-${i}`} className="h-4 w-4 text-border" />
            ))}
            <span className="ml-1.5 text-sm font-bold tabular-nums text-text">{rating}</span>
        </div>
    );
}

function EvaluationCard({ evaluation }: { evaluation: EvaluacionAsignadaEvaluador }) {
    const navigate = useNavigate();
    const isEvaluated = evaluation.evaluation_status === 'evaluated';
    const code = evaluation.code || datoNoEncontrado('El código del proyecto');
    const title = evaluation.title || datoNoEncontrado('El título del proyecto');
    const dateLabel = formatDate(evaluation.assigned_at ?? evaluation.fecha);

    function handleNavigate() {
        if (isEvaluated) {
            navigate(`/evaluaciones/${evaluation.id}/calificar`);
        } else {
            navigate(`/evaluaciones/${evaluation.id}`);
        }
    }

    return (
        <div className="rounded-xl border border-border bg-surface p-5 shadow-warm-sm">
            <div className="mb-3 flex items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-xs font-bold uppercase tracking-[0.05em] text-primary">
                            {code}
                        </span>
                        <StatusBadge variant={isEvaluated ? 'success' : 'warning'}>
                            {isEvaluated ? 'Evaluado' : 'Pendiente'}
                        </StatusBadge>
                    </div>
                    <h4 className="text-sm font-bold text-text text-balance">{title}</h4>
                </div>
            </div>

            <div className="mb-4 flex flex-col gap-2 text-xs text-text-muted">
                <span className="flex items-center gap-1.5">
                    <Users className="h-3.5 w-3.5 shrink-0" />
                    {studentsLabel(evaluation)}
                </span>
                <span className="flex items-center gap-1.5">
                    <School className="h-3.5 w-3.5 shrink-0" />
                    Director: {directorLabel(evaluation)}
                </span>
                <span className="flex items-center gap-1.5">
                    <Calendar className="h-3.5 w-3.5 shrink-0" />
                    {dateLabel}
                </span>
            </div>

            {isEvaluated && evaluation.rating != null && (
                <div className="mb-4">
                    <StarRating rating={evaluation.rating} />
                </div>
            )}

            <button
                type="button"
                onClick={handleNavigate}
                className="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-border bg-transparent px-4 py-2.5 text-sm font-semibold text-text transition-colors hover:border-primary hover:bg-primary-container hover:text-primary active:scale-[0.98]"
            >
                {isEvaluated ? 'Ver evaluación' : 'Evaluar proyecto'}
                <ArrowRight className="h-4 w-4" />
            </button>
        </div>
    );
}

export default function EvaluadorDashboard() {
    const { data, kpis, loading, error, refetch } = useEvaluadorEvaluaciones();

    if (loading) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Dashboard"
                    title="Panel de Evaluador"
                    subtitle="Gestiona las evaluaciones de los proyectos de grado que tienes asignados."
                />
                <div className="flex items-center justify-center py-20">
                    <Loader2 className="h-8 w-8 animate-spin text-primary" aria-label="Cargando" />
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Dashboard"
                    title="Panel de Evaluador"
                    subtitle="Gestiona las evaluaciones de los proyectos de grado que tienes asignados."
                />
                <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-[#fee2e2] bg-[#fee2e2] py-16 text-center">
                    <AlertCircle className="h-12 w-12 text-[#dc2626]" />
                    <div>
                        <h3 className="text-lg font-bold text-[#7f1d1d]">Error al cargar</h3>
                        <p className="mt-1 text-sm text-[#7f1d1d]">{error}</p>
                    </div>
                    <button
                        type="button"
                        onClick={refetch}
                        className="inline-flex items-center gap-2 rounded-lg bg-[#dc2626] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#b91c1c] active:scale-[0.98]"
                    >
                        Reintentar
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Dashboard"
                title="Panel de Evaluador"
                subtitle="Gestiona las evaluaciones de los proyectos de grado que tienes asignados."
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    icon={ClipboardList}
                    label="Proyectos asignados"
                    value={kpis?.proyectos_asignados ?? 0}
                    variant="default"
                />
                <StatCard
                    icon={Clock}
                    label="Evaluaciones pendientes"
                    value={kpis?.evaluaciones_pendientes ?? 0}
                    variant="warning"
                />
                <StatCard
                    icon={CheckCircle}
                    label="Evaluaciones completadas"
                    value={kpis?.evaluaciones_completadas ?? 0}
                    variant="success"
                />
            </div>

            <section aria-labelledby="evaluations-heading">
                <h2 id="evaluations-heading" className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                    Mis Evaluaciones
                </h2>
                {data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-border bg-surface py-16 text-center">
                        <ClipboardList className="h-10 w-10 text-text-muted" />
                        <p className="text-sm font-semibold text-text">Sin proyectos asignados</p>
                        <p className="text-xs text-text-muted">
                            Cuando el coordinador te asigne un proyecto, aparecerá aquí.
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {data.map((evalItem) => (
                            <EvaluationCard key={evalItem.id} evaluation={evalItem} />
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}
