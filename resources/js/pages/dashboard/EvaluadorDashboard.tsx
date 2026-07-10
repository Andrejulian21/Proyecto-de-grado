import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import {
    ClipboardList,
    Clock,
    CheckCircle,
    Star,
    Calendar,
    Users,
    School,
    ArrowRight,
} from 'lucide-react';

/* ── Mock data ── */

interface Evaluation {
    id: number;
    projectCode: string;
    projectTitle: string;
    students: string;
    director: string;
    date: string;
    status: 'pending' | 'evaluated';
    rating?: number;
}

const MOCK_EVALUATIONS: Evaluation[] = [
    {
        id: 1,
        projectCode: 'PG-2403',
        projectTitle: 'Aplicación móvil para tutorías inteligentes',
        students: 'Laura Jiménez, Carlos Ruiz',
        director: 'Andrés Pérez',
        date: '15/07/2026',
        status: 'pending',
    },
    {
        id: 2,
        projectCode: 'PG-2406',
        projectTitle: 'Blockchain para certificados académicos',
        students: 'Ricardo Mora',
        director: 'Andrés Pérez',
        date: '18/07/2026',
        status: 'pending',
    },
    {
        id: 3,
        projectCode: 'PG-2401',
        projectTitle: 'Sistema predictivo de deserción estudiantil',
        students: 'Ana Martínez, Luis Rojas',
        director: 'Carlos Gómez',
        date: '10/07/2026',
        status: 'evaluated',
        rating: 4.2,
    },
];

/* ── Subcomponents ── */

function StarRating({ rating }: { rating: number }) {
    const fullStars = Math.floor(rating);
    const hasHalf = rating - fullStars >= 0.3;
    const emptyStars = 5 - fullStars - (hasHalf ? 1 : 0);

    return (
        <div className="flex items-center gap-0.5" aria-label={`Calificación: ${rating} de 5 estrellas`}>
            {Array.from({ length: fullStars }).map((_, i) => (
                <Star key={`full-${i}`} className="h-4 w-4 fill-warning text-warning" />
            ))}
            {hasHalf && (
                <Star className="h-4 w-4 fill-warning/30 text-warning" />
            )}
            {Array.from({ length: emptyStars }).map((_, i) => (
                <Star key={`empty-${i}`} className="h-4 w-4 text-border" />
            ))}
            <span className="ml-1.5 text-sm font-bold tabular-nums text-text">{rating}</span>
        </div>
    );
}

function EvaluationCard({ evaluation }: { evaluation: Evaluation }) {
    const isEvaluated = evaluation.status === 'evaluated';

    return (
        <div className="rounded-xl border border-border bg-surface p-5 shadow-warm-sm">
            <div className="mb-3 flex items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-xs font-bold uppercase tracking-[0.05em] text-primary">
                            {evaluation.projectCode}
                        </span>
                        <StatusBadge variant={isEvaluated ? 'success' : 'warning'}>
                            {isEvaluated ? 'Evaluado' : 'Pendiente'}
                        </StatusBadge>
                    </div>
                    <h4 className="text-sm font-bold text-text text-balance">{evaluation.projectTitle}</h4>
                </div>
            </div>

            <div className="mb-4 flex flex-col gap-2 text-xs text-text-muted">
                <span className="flex items-center gap-1.5">
                    <Users className="h-3.5 w-3.5" />
                    {evaluation.students}
                </span>
                <span className="flex items-center gap-1.5">
                    <School className="h-3.5 w-3.5" />
                    Director: {evaluation.director}
                </span>
                <span className="flex items-center gap-1.5">
                    <Calendar className="h-3.5 w-3.5" />
                    {evaluation.date}
                </span>
            </div>

            {isEvaluated && evaluation.rating && (
                <div className="mb-4">
                    <StarRating rating={evaluation.rating} />
                </div>
            )}

            <button
                className="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-border bg-transparent px-4 py-2.5 text-sm font-semibold text-text transition-colors hover:border-primary hover:bg-primary-container hover:text-primary active:scale-[0.98]"
            >
                {isEvaluated ? 'Ver evaluación' : 'Evaluar proyecto'}
                <ArrowRight className="h-4 w-4" />
            </button>
        </div>
    );
}

/* ── Main component ── */

export default function EvaluadorDashboard() {
    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Dashboard"
                title="Panel de Evaluador"
                subtitle="Gestiona las evaluaciones de los proyectos de grado que tienes asignados."
            />

            {/* KPI row */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard icon={ClipboardList} label="Proyectos asignados" value={6} variant="default" />
                <StatCard icon={Clock} label="Evaluaciones pendientes" value={4} variant="warning" />
                <StatCard icon={CheckCircle} label="Evaluaciones completadas" value={2} variant="success" />
            </div>

            {/* Evaluation cards */}
            <section aria-labelledby="evaluations-heading">
                <h2 id="evaluations-heading" className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-text-muted">
                    Mis Evaluaciones
                </h2>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {MOCK_EVALUATIONS.map((evalItem) => (
                        <EvaluationCard key={evalItem.id} evaluation={evalItem} />
                    ))}
                </div>
            </section>
        </div>
    );
}
