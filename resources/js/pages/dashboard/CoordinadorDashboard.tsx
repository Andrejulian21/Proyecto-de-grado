import { Link } from 'react-router-dom';
import { LayoutDashboard, Users, FolderKanban, ClipboardCheck, BarChart3, ArrowRight } from 'lucide-react';

const cards = [
    {
        icon: Users,
        title: 'Usuarios',
        description: 'Gestiona los usuarios del sistema',
        status: 'Próximamente',
        link: '/coordinador/usuarios',
        color: 'text-primary',
        bg: 'bg-primary-container/50',
    },
    {
        icon: FolderKanban,
        title: 'Proyectos',
        description: 'Administra los proyectos de grado',
        status: 'Próximamente',
        color: 'text-secondary',
        bg: 'bg-secondary-container/50',
    },
    {
        icon: ClipboardCheck,
        title: 'Evaluaciones',
        description: 'Configura y revisa evaluaciones',
        status: 'Próximamente',
        color: 'text-accent',
        bg: 'bg-cyan-50',
    },
    {
        icon: BarChart3,
        title: 'Estadísticas',
        description: 'Indicadores y reportes del sistema',
        status: 'Próximamente',
        color: 'text-text',
        bg: 'bg-surface-variant',
    },
];

export default function CoordinadorDashboard() {
    return (
        <div className="space-y-6">
            <div className="flex items-center gap-4">
                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-container shadow-warm-sm">
                    <LayoutDashboard className="h-7 w-7 text-primary" />
                </div>
                <div>
                    <h1 className="text-2xl font-bold text-text">Panel de Coordinador</h1>
                    <p className="mt-1 text-sm text-text-muted">
                        Administra el sistema de proyectos de grado
                    </p>
                </div>
            </div>

            <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map((card) => {
                    const content = (
                        <div
                            className="flex h-full flex-col rounded-xl border border-border bg-surface p-5 shadow-warm-sm transition hover:shadow-warm-md"
                        >
                            <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-surface-alt">
                                <card.icon className={`h-5 w-5 ${card.color}`} />
                            </div>
                            <h3 className="font-semibold text-text">{card.title}</h3>
                            <p className="mt-1 flex-1 text-sm text-text-muted">{card.description}</p>
                            <span className="mt-3 inline-block rounded-md bg-surface-alt px-2.5 py-0.5 text-xs font-medium text-text-muted">
                                {card.status}
                            </span>
                        </div>
                    );

                    if (card.link) {
                        return (
                            <Link key={card.title} to={card.link} className="group block">
                                <div className="relative">
                                    {content}
                                    <span className="absolute right-4 top-4 text-text-subtle opacity-0 transition group-hover:opacity-100">
                                        <ArrowRight className="h-4 w-4" />
                                    </span>
                                </div>
                            </Link>
                        );
                    }

                    return <div key={card.title}>{content}</div>;
                })}
            </div>

            <div className="flex items-center justify-center rounded-xl border-2 border-dashed border-border p-12">
                <p className="text-center text-sm text-text-muted">
                    Contenido personalizado — próximamente
                </p>
            </div>
        </div>
    );
}
