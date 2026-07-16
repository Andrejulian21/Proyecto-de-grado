import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { Loader2, BookOpen, Download, Eye, User, FileText, Gavel, PlaySquare, ChevronRight, AlertCircle } from 'lucide-react';
import { apiFetch } from '@/lib/utils';

type ResourceType = 'reglamento' | 'guia' | 'plantilla' | 'tutorial';

interface ResourceDetail {
    id: number;
    title: string;
    type: ResourceType;
    description: string;
    body: string;
    author: string;
    size: string;
    downloads: number;
    accesses: number;
}

/** Shape returned by GET /api/recursos/{id} */
interface ApiResourceDetail {
    id: number;
    title: string;
    category: string;
    description: string | null;
    body?: string | null;
    file_path: string | null;
    link: string | null;
    access_count: number;
    author: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

function fromApi(r: ApiResourceDetail): ResourceDetail {
    return {
        id: r.id,
        title: r.title,
        type: (['reglamento', 'guia', 'plantilla', 'tutorial'].includes(r.category)
            ? r.category
            : 'reglamento') as ResourceType,
        description: r.description ?? '',
        body: r.body ?? r.description ?? '',
        author: r.author?.name ?? '—',
        size: '—',
        downloads: r.access_count,
        accesses: r.access_count,
    };
}

const typeIcons: Record<ResourceType, typeof BookOpen> = {
    reglamento: Gavel,
    guia: BookOpen,
    plantilla: FileText,
    tutorial: PlaySquare,
};

const typeLabels: Record<ResourceType, string> = {
    reglamento: 'Reglamento',
    guia: 'Guía',
    plantilla: 'Plantilla',
    tutorial: 'Tutorial',
};

export default function RecursoDetalle() {
    const { id } = useParams<{ id: string }>();
    const [recurso, setRecurso] = useState<ResourceDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);
            setRecurso(null);
            try {
                const res = await apiFetch(`/api/recursos/${id}`);
                if (!res.ok) {
                    if (res.status === 404) throw new Error('El recurso no existe o ha sido eliminado.');
                    throw new Error('Error al cargar el recurso');
                }
                const body = await res.json();
                if (!cancelled) {
                    setRecurso(fromApi(body.data));
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

    if (error || !recurso) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-alt">
                    <AlertCircle className="h-6 w-6 text-text-subtle" />
                </div>
                <div className="flex flex-col gap-1">
                    <h3 className="text-base font-semibold text-text">Recurso no encontrado</h3>
                    <p className="text-sm text-text-muted">
                        {error ?? 'El recurso que buscas no existe o ha sido eliminado.'}
                    </p>
                </div>
                <Link
                    to="/recursos"
                    className="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2.5 text-sm font-semibold text-text transition-colors hover:border-primary hover:bg-primary-container hover:text-primary active:scale-[0.98]"
                >
                    Volver a Recursos
                </Link>
            </div>
        );
    }

    const Icon = typeIcons[recurso.type];

    return (
        <div className="flex flex-col gap-6">
            {/* Breadcrumb */}
            <nav aria-label="Breadcrumb" className="flex items-center gap-1.5 text-xs text-text-subtle">
                <Link to="/recursos" className="font-semibold text-text-muted transition-colors hover:text-text">
                    Recursos
                </Link>
                <ChevronRight className="h-3 w-3" />
                <span className="truncate max-w-[300px]" aria-current="page">
                    {recurso.title}
                </span>
            </nav>

            <div className="flex flex-col gap-6 lg:flex-row lg:gap-8">
                {/* Main content */}
                <div className="flex flex-1 flex-col gap-6 min-w-0">
                    {/* Hero card */}
                    <div className="rounded-xl border border-border bg-surface p-6 shadow-warm-sm">
                        <div className="flex flex-col gap-4">
                            <div className="flex items-center gap-2">
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-container">
                                    <Icon className="h-5 w-5 text-primary" />
                                </div>
                                <StatusBadge variant="info">
                                    {typeLabels[recurso.type]}
                                </StatusBadge>
                            </div>

                            <h1 className="text-2xl font-bold text-text text-balance">
                                {recurso.title}
                            </h1>

                            <p className="text-sm text-text-muted leading-relaxed">
                                {recurso.description}
                            </p>
                        </div>
                    </div>

                    {/* Description card */}
                    <div className="rounded-xl border border-border bg-surface p-6 shadow-warm-sm">
                        <h2 className="mb-3 text-base font-bold text-text">Descripción</h2>
                        <p className="text-sm text-text-muted leading-relaxed whitespace-pre-line">
                            {recurso.body}
                        </p>
                    </div>
                </div>

                {/* Sticky sidebar */}
                <aside className="w-full shrink-0 lg:w-64">
                    <div className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5 shadow-warm-sm lg:sticky lg:top-24">
                        <button className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-hover active:scale-[0.98]">
                            <Download className="h-4 w-4" />
                            Descargar
                        </button>

                        <hr className="border-border" />

                        <dl className="flex flex-col gap-3 text-sm">
                            <div className="flex items-center justify-between">
                                <dt className="flex items-center gap-1.5 text-text-muted">
                                    <FileText className="h-3.5 w-3.5" />
                                    Tipo
                                </dt>
                                <dd className="font-semibold text-text">{typeLabels[recurso.type]}</dd>
                            </div>
                            <div className="flex items-center justify-between">
                                <dt className="flex items-center gap-1.5 text-text-muted">
                                    <User className="h-3.5 w-3.5" />
                                    Autor
                                </dt>
                                <dd className="font-semibold text-text text-right max-w-[140px]">{recurso.author}</dd>
                            </div>
                            <div className="flex items-center justify-between">
                                <dt className="flex items-center gap-1.5 text-text-muted">
                                    <Download className="h-3.5 w-3.5" />
                                    Tamaño
                                </dt>
                                <dd className="font-semibold text-text tabular-nums">{recurso.size}</dd>
                            </div>
                            <div className="flex items-center justify-between">
                                <dt className="flex items-center gap-1.5 text-text-muted">
                                    <Eye className="h-3.5 w-3.5" />
                                    Accesos
                                </dt>
                                <dd className="font-semibold text-text tabular-nums">{recurso.accesses.toLocaleString()}</dd>
                            </div>
                        </dl>
                    </div>
                </aside>
            </div>
        </div>
    );
}
