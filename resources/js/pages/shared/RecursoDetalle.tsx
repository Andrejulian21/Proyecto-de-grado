import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { cn } from '@/lib/utils';
import {
    Loader2,
    BookOpen,
    Download,
    Eye,
    User,
    FileText,
    Gavel,
    PlaySquare,
    ChevronRight,
    AlertCircle,
    ArrowLeft,
    ExternalLink,
} from 'lucide-react';
import { FRONTEND_VALIDATION_MODE, mockDelay } from '@/mocks/validationMode';
import { getRecursoById } from '@/mocks/recursosMock';
import { apiFetch } from '@/lib/utils';

type ResourceType = 'reglamento' | 'guia' | 'plantilla' | 'tutorial';

interface ResourceDetail {
    id: number;
    title: string;
    type: ResourceType;
    description: string;
    body: string;
    file_path: string | null;
    link: string | null;
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
    file_size: string | null;
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
        file_path: r.file_path ?? null,
        link: r.link ?? null,
        author: r.author?.name ?? '—',
        size: r.file_size ?? '—',
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

function formatLabel(file_path: string | null, link: string | null): string {
    if (file_path) return 'Documento';
    if (link) return 'Enlace externo';
    return '—';
}

export default function RecursoDetalle() {
    const navigate = useNavigate();
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
                if (FRONTEND_VALIDATION_MODE) {
                    await mockDelay();
                    const r = getRecursoById(Number(id));
                    if (!r) throw new Error('El recurso no existe o ha sido eliminado.');
                    if (!cancelled) {
                        setRecurso({
                            id: r.id,
                            title: r.title,
                            type: (['reglamento', 'guia', 'plantilla', 'tutorial'].includes(r.category)
                                ? r.category
                                : 'guia') as ResourceType,
                            description: r.description,
                            body: r.description,
                            file_path: r.file_path ?? null,
                            link: r.link ?? null,
                            author: r.author,
                            size: r.file_size ? `${r.file_size} KB` : '—',
                            downloads: r.downloads,
                            accesses: r.downloads,
                        });
                    }
                    return;
                }
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
        return () => { cancelled = true; };
    }, [id]);

    if (loading) {
        return (
            <div className="flex items-center justify-center py-16">
                <Loader2 className="h-6 w-6 motion-safe:animate-spin text-primary" />
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
    const fmtLabel = formatLabel(recurso.file_path, recurso.link);
    const hasFile = !!recurso.file_path;
    const hasLink = !!recurso.link;

    const handleDownload = () => {
        if (hasFile) {
            window.open('/storage/' + recurso.file_path, '_blank');
        } else if (hasLink) {
            window.open(recurso.link!, '_blank');
        }
    };

    return (
        <div className="flex flex-col gap-6">
            {/* Breadcrumb + Back */}
            <nav aria-label="Breadcrumb" className="flex items-center gap-1.5 text-xs text-text-subtle">
                <Link to="/recursos" className="font-semibold text-text-muted transition-colors hover:text-text">
                    Recursos
                </Link>
                <ChevronRight className="h-3 w-3" />
                <span className="truncate max-w-[300px]" aria-current="page">
                    {recurso.title}
                </span>
                <button
                    onClick={() => navigate('/recursos')}
                    className="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-text-muted transition-colors hover:border-primary hover:text-primary hover:bg-primary-container"
                >
                    <ArrowLeft className="h-3.5 w-3.5" />
                    Volver
                </button>
            </nav>

            <div className="flex flex-col gap-6 lg:flex-row lg:gap-8">
                {/* Main content */}
                <div className="flex flex-1 flex-col gap-6 min-w-0">
                    {/* Hero card */}
                    <div className="rounded-xl border border-border bg-surface p-6 shadow-warm-sm">
                        <div className="flex flex-col gap-4">
                            <div className="flex items-center gap-2">
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-container">
                                    <Icon className="h-6 w-6 text-primary" />
                                </div>
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <StatusBadge variant="info">
                                        {typeLabels[recurso.type]}
                                    </StatusBadge>
                                    <span
                                        className={cn(
                                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.03em]',
                                            hasFile
                                                ? 'bg-[#dcfce7] text-[#14532d]'
                                                : 'bg-[#dbeafe] text-[#1e3a8a]',
                                        )}
                                    >
                                        {fmtLabel}
                                    </span>
                                </div>
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
                <aside className="w-full shrink-0 lg:w-72">
                    <div className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5 shadow-warm-sm lg:sticky lg:top-24">
                        {/* Main action button */}
                        <button
                            onClick={handleDownload}
                            aria-label={hasFile ? `Descargar ${recurso.title}` : `Abrir ${recurso.title}`}
                            className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-hover active:scale-[0.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        >
                            {hasFile ? (
                                <Download className="h-4 w-4" />
                            ) : (
                                <ExternalLink className="h-4 w-4" />
                            )}
                            {hasFile ? 'Descargar' : 'Abrir enlace'}
                        </button>

                        {/* Direct file link helper */}
                        {hasFile && (
                            <a
                                href={`/storage/${recurso.file_path}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-center text-xs text-text-muted underline transition-colors hover:text-primary"
                            >
                                Ver archivo directamente
                            </a>
                        )}

                        {/* External link shown when no file */}
                        {hasLink && !hasFile && (
                            <a
                                href={recurso.link!}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="block text-center text-xs text-text-muted underline transition-colors hover:text-primary truncate"
                            >
                                {recurso.link}
                            </a>
                        )}

                        <hr className="border-border" />

                        {/* Metadata */}
                        <dl className="flex flex-col gap-3 text-sm">
                            <div className="flex items-center justify-between">
                                <dt className="flex items-center gap-1.5 text-text-muted">
                                    <FileText className="h-3.5 w-3.5" />
                                    Tipo
                                </dt>
                                <dd className="font-semibold text-text text-right">
                                    {typeLabels[recurso.type]}
                                </dd>
                            </div>

                            <div className="flex items-center justify-between">
                                <dt className="flex items-center gap-1.5 text-text-muted">
                                    <FileText className="h-3.5 w-3.5" />
                                    Formato
                                </dt>
                                <dd className="font-semibold text-text text-right">
                                    {fmtLabel}
                                </dd>
                            </div>

                            <div className="flex items-center justify-between">
                                <dt className="flex items-center gap-1.5 text-text-muted">
                                    <User className="h-3.5 w-3.5" />
                                    Autor
                                </dt>
                                <dd className="font-semibold text-text text-right max-w-[140px] break-words">
                                    {recurso.author}
                                </dd>
                            </div>

                            {hasFile && (
                                <div className="flex items-center justify-between">
                                    <dt className="flex items-center gap-1.5 text-text-muted">
                                        <Download className="h-3.5 w-3.5" />
                                        Tamaño
                                    </dt>
                                    <dd className="font-semibold text-text tabular-nums">
                                        {recurso.size}
                                    </dd>
                                </div>
                            )}

                            <div className="flex items-center justify-between">
                                <dt className="flex items-center gap-1.5 text-text-muted">
                                    <Eye className="h-3.5 w-3.5" />
                                    Accesos
                                </dt>
                                <dd className="font-semibold text-text tabular-nums">
                                    {recurso.accesses.toLocaleString()}
                                </dd>
                            </div>

                            {hasLink && (
                                <div className="flex items-center justify-between">
                                    <dt className="flex items-center gap-1.5 text-text-muted">
                                        <ExternalLink className="h-3.5 w-3.5" />
                                        Enlace
                                    </dt>
                                    <dd className="font-semibold text-text text-right max-w-[140px] truncate">
                                        <a
                                            href={recurso.link!}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="underline transition-colors hover:text-primary"
                                        >
                                            Abrir
                                        </a>
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </div>
                </aside>
            </div>
        </div>
    );
}
