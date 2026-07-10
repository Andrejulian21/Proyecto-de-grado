import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { EmptyState } from '@/components/ui/EmptyState';
import { Loader2, ArrowRight, Megaphone, Calendar } from 'lucide-react';

interface Announcement {
    id: number;
    title: string;
    category: 'importante' | 'recordatorio' | 'informativo';
    date: string;
    excerpt: string;
}

const MOCK_ANUNCIOS: Announcement[] = [
    {
        id: 1,
        title: 'Cronograma de sustentaciones',
        category: 'importante',
        date: '28/06/2026',
        excerpt: 'Se informa a los estudiantes y directores que las sustentaciones de proyectos de grado se realizarán durante la primera semana de agosto. Los plazos para la entrega de documentos finales vencen el 25 de julio.',
    },
    {
        id: 2,
        title: 'Recordatorio: Entrega de informes finales',
        category: 'recordatorio',
        date: '25/06/2026',
        excerpt: 'El plazo para la entrega de informes finales del ciclo 2026-S1 vence el próximo 15 de julio. Asegúrese de completar las correcciones sugeridas por su director y cargar la versión final en el sistema.',
    },
];

const categoryVariant: Record<string, 'error' | 'warning' | 'info'> = {
    importante: 'error',
    recordatorio: 'warning',
    informativo: 'info',
};

const categoryLabels: Record<string, string> = {
    importante: 'Importante',
    recordatorio: 'Recordatorio',
    informativo: 'Informativo',
};

export default function AnunciosPublica() {
    const [anuncios, setAnuncios] = useState<Announcement[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const timer = setTimeout(() => {
            setAnuncios(MOCK_ANUNCIOS);
            setLoading(false);
        }, 400);
        return () => clearTimeout(timer);
    }, []);

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Comunicados oficiales"
                title="Anuncios"
                subtitle="Manténgase informado sobre las novedades del programa de proyectos de grado."
            />

            {loading ? (
                <div className="flex items-center justify-center py-16">
                    <Loader2 className="h-6 w-6 animate-spin text-primary" />
                </div>
            ) : anuncios.length === 0 ? (
                <EmptyState
                    icon={Megaphone}
                    title="No hay anuncios"
                    description="No hay comunicados publicados en este momento."
                />
            ) : (
                <div className="flex flex-col gap-4">
                    {anuncios.map((anuncio) => (
                        <article
                            key={anuncio.id}
                            className="rounded-xl border border-border bg-surface p-6 shadow-warm-sm transition-colors hover:border-primary/20"
                        >
                            <div className="flex flex-col gap-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusBadge variant={categoryVariant[anuncio.category]}>
                                        {categoryLabels[anuncio.category]}
                                    </StatusBadge>
                                    <span className="flex items-center gap-1 text-xs text-text-subtle">
                                        <Calendar className="h-3.5 w-3.5" />
                                        {anuncio.date}
                                    </span>
                                </div>

                                <h3 className="text-lg font-bold text-text text-balance">
                                    {anuncio.title}
                                </h3>

                                <p className="text-sm text-text-muted leading-relaxed">
                                    {anuncio.excerpt}
                                </p>

                                <div>
                                    <Link
                                        to={`/anuncios/${anuncio.id}`}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-border px-4 py-2 text-sm font-semibold text-text transition-colors hover:border-primary hover:bg-primary-container hover:text-primary active:scale-[0.98]"
                                    >
                                        Ver más
                                        <ArrowRight className="h-4 w-4" />
                                    </Link>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </div>
    );
}
