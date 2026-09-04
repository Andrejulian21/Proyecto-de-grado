import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { EmptyState } from '@/components/ui/EmptyState';
import { ArrowRight, Megaphone, Calendar, AlertCircle } from 'lucide-react';
import { apiFetch } from '@/lib/utils';

interface Announcement {
    id: number;
    title: string;
    category: 'importante' | 'recordatorio' | 'informativo';
    date: string;
    excerpt: string;
}

/** Shape returned by GET /api/anuncios */
interface ApiAnnouncement {
    id: number;
    title: string;
    content: string;
    published_at: string | null;
    is_active: boolean;
}

function fromApi(a: ApiAnnouncement): Announcement {
    return {
        id: a.id,
        title: a.title,
        category: 'informativo',
        date: a.published_at
            ? new Date(a.published_at).toLocaleDateString('es-CO')
            : '—',
        excerpt: a.content,
    };
}

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
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);
            try {
                const res = await apiFetch('/api/anuncios');
                if (!res.ok) throw new Error('Error al cargar anuncios');
                const body = await res.json();
                if (!cancelled) {
                    setAnuncios((body.data ?? []).map(fromApi));
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err instanceof Error ? err.message : 'Error desconocido');
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        load();

        return () => {
            cancelled = true;
        };
    }, []);

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Comunicados oficiales"
                title="Anuncios"
                subtitle="Manténgase informado sobre las novedades del programa de proyectos de grado."
            />

            {loading ? (
                <div className="flex flex-col gap-4">
                    {[1, 2].map((i) => (
                        <div key={i} className="animate-pulse rounded-xl border border-border bg-surface p-6">
                            <div className="flex flex-col gap-3">
                                <div className="flex items-center gap-2">
                                    <div className="h-5 w-24 rounded-full bg-[#f5f5f4]" />
                                    <div className="h-3 w-20 rounded bg-[#f5f5f4]" />
                                </div>
                                <div className="h-5 w-3/4 rounded bg-[#f5f5f4]" />
                                <div className="h-3 w-full rounded bg-[#f5f5f4]" />
                                <div className="h-3 w-1/2 rounded bg-[#f5f5f4]" />
                                <div className="h-9 w-24 rounded-lg bg-[#f5f5f4]" />
                            </div>
                        </div>
                    ))}
                </div>
            ) : error ? (
                <div className="rounded-xl border border-[#fee2e2] bg-[#fef2f2] p-6">
                    <div className="flex items-start gap-3">
                        <AlertCircle className="h-5 w-5 shrink-0 text-[#dc2626]" />
                        <div>
                            <p className="text-sm font-semibold text-[#dc2626]">Error al cargar anuncios</p>
                            <p className="mt-1 text-sm text-[#991b1b]">{error}</p>
                        </div>
                    </div>
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
