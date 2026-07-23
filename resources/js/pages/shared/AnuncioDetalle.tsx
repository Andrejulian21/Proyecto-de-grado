import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { Loader2, ArrowLeft, Calendar, User, Paperclip, FileDown, FileText, AlertCircle } from 'lucide-react';
import { FRONTEND_VALIDATION_MODE, mockDelay } from '@/mocks/validationMode';
import { getAnuncioById } from '@/mocks/anunciosMock';
import { apiFetch } from '@/lib/utils';

interface Attachment {
    name: string;
    size: string;
}

interface AnnouncementDetail {
    id: number;
    title: string;
    category: 'importante' | 'recordatorio' | 'informativo';
    date: string;
    author: string;
    body: string;
    attachments?: Attachment[];
}

/** Shape returned by GET /api/anuncios/{id} */
interface ApiAnnouncement {
    id: number;
    title: string;
    content: string;
    published_at: string | null;
    is_active: boolean;
    author: { id: number; name: string } | null;
}

function fromApi(a: ApiAnnouncement): AnnouncementDetail {
    return {
        id: a.id,
        title: a.title,
        category: 'informativo',
        date: a.published_at
            ? new Date(a.published_at).toLocaleDateString('es-CO')
            : '—',
        author: a.author?.name ?? '—',
        body: a.content,
        attachments: [],
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

export default function AnuncioDetalle() {
    const { id } = useParams<{ id: string }>();
    const [anuncio, setAnuncio] = useState<AnnouncementDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);
            setAnuncio(null);
            try {
                if (FRONTEND_VALIDATION_MODE) {
                    await mockDelay();
                    const a = getAnuncioById(Number(id));
                    if (!a) throw new Error('El anuncio no existe o ha sido eliminado.');
                    if (!cancelled) {
                        setAnuncio({
                            id: a.id,
                            title: a.title,
                            category: a.category,
                            date: new Date(a.published_at).toLocaleDateString('es-CO'),
                            author: a.author,
                            body: a.content,
                            attachments: a.attachments,
                        });
                    }
                    return;
                }
                const res = await apiFetch(`/api/anuncios/${id}`);
                if (!res.ok) {
                    if (res.status === 404) throw new Error('El anuncio no existe o ha sido eliminado.');
                    throw new Error('Error al cargar el anuncio');
                }
                const body = await res.json();
                if (!cancelled) {
                    setAnuncio(fromApi(body.data));
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
    }, [id]);

    if (loading) {
        return (
            <div className="flex items-center justify-center py-16">
                <Loader2 className="h-6 w-6 animate-spin text-primary" />
            </div>
        );
    }

    if (error || !anuncio) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-alt">
                    <AlertCircle className="h-6 w-6 text-text-subtle" />
                </div>
                <div className="flex flex-col gap-1">
                    <h3 className="text-base font-semibold text-text">Anuncio no encontrado</h3>
                    <p className="text-sm text-text-muted">
                        {error ?? 'El anuncio que buscas no existe o ha sido eliminado.'}
                    </p>
                </div>
                <Link
                    to="/anuncios"
                    className="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2.5 text-sm font-semibold text-text transition-colors hover:border-primary hover:bg-primary-container hover:text-primary active:scale-[0.98]"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Volver a Anuncios
                </Link>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <Link
                to="/anuncios"
                className="inline-flex w-fit items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold text-text-muted transition-colors hover:bg-surface-alt hover:text-text active:scale-[0.98]"
            >
                <ArrowLeft className="h-4 w-4" />
                Volver a Anuncios
            </Link>

            <article className="rounded-xl border border-border bg-surface p-6 shadow-warm-sm">
                <div className="flex flex-col gap-4">
                    <div>
                        <StatusBadge variant={categoryVariant[anuncio.category]}>
                            {categoryLabels[anuncio.category]}
                        </StatusBadge>
                    </div>

                    <h1 className="text-2xl font-bold text-text text-balance">{anuncio.title}</h1>

                    <div className="flex flex-wrap items-center gap-4 text-xs text-text-subtle">
                        <span className="flex items-center gap-1.5">
                            <Calendar className="h-3.5 w-3.5" />
                            {anuncio.date}
                        </span>
                        <span className="flex items-center gap-1.5">
                            <User className="h-3.5 w-3.5" />
                            {anuncio.author}
                        </span>
                    </div>

                    <hr className="border-border" />

                    <div className="text-sm text-text-muted leading-relaxed whitespace-pre-line">
                        {anuncio.body}
                    </div>

                    {anuncio.attachments && anuncio.attachments.length > 0 && (
                        <>
                            <hr className="border-border" />
                            <div className="flex flex-col gap-3">
                                <h3 className="flex items-center gap-2 text-sm font-bold text-text">
                                    <Paperclip className="h-4 w-4" />
                                    Adjuntos ({anuncio.attachments.length})
                                </h3>
                                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                    {anuncio.attachments.map((att, i) => (
                                        <button
                                            key={i}
                                            type="button"
                                            className="inline-flex items-center gap-2 rounded-lg border border-border bg-surface-alt px-3 py-2 text-xs font-semibold text-text transition-colors hover:border-primary hover:bg-primary-container hover:text-primary active:scale-[0.98]"
                                        >
                                            <FileDown className="h-3.5 w-3.5 shrink-0" />
                                            <span className="truncate max-w-[180px]">{att.name}</span>
                                            <span className="text-text-subtle font-normal">({att.size})</span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </article>
        </div>
    );
}
