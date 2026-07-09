import { ClipboardCheck, FileText, Clock } from 'lucide-react';

const cards = [
    {
        icon: FileText,
        title: 'Evaluaciones Asignadas',
        description: 'Proyectos asignados para evaluar',
        status: 'Próximamente',
        color: 'text-primary',
        bg: 'bg-primary-container/50',
    },
    {
        icon: Clock,
        title: 'Próximas Evaluaciones',
        description: 'Evaluaciones con fecha próxima',
        status: 'Próximamente',
        color: 'text-secondary',
        bg: 'bg-secondary-container/50',
    },
];

export default function EvaluadorDashboard() {
    return (
        <div className="space-y-6">
            <div className="flex items-center gap-4">
                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-container shadow-warm-sm">
                    <ClipboardCheck className="h-7 w-7 text-primary" />
                </div>
                <div>
                    <h1 className="text-2xl font-bold text-text">Panel de Evaluador</h1>
                    <p className="mt-1 text-sm text-text-muted">Evalúa proyectos de grado</p>
                </div>
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
                {cards.map((card) => (
                    <div
                        key={card.title}
                        className="rounded-xl border border-border bg-surface p-5 shadow-warm-sm transition hover:shadow-warm-md"
                    >
                        <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-surface-alt">
                            <card.icon className={`h-5 w-5 ${card.color}`} />
                        </div>
                        <h3 className="font-semibold text-text">{card.title}</h3>
                        <p className="mt-1 text-sm text-text-muted">{card.description}</p>
                        <span className="mt-3 inline-block rounded-md bg-surface-alt px-2.5 py-0.5 text-xs font-medium text-text-muted">
                            {card.status}
                        </span>
                    </div>
                ))}
            </div>

            <div className="flex items-center justify-center rounded-xl border-2 border-dashed border-border p-12">
                <p className="text-center text-sm text-text-muted">
                    Contenido personalizado — próximamente
                </p>
            </div>
        </div>
    );
}
