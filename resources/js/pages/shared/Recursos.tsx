import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { cn } from '@/lib/utils';
import {
    Search,
    BookOpen,
    Gavel,
    FileText,
    PlaySquare,
    Download,
    FolderKanban,
    AlertCircle,
    ExternalLink,
} from 'lucide-react';
import { apiFetch } from '@/lib/utils';

type ResourceType = 'reglamento' | 'guia' | 'plantilla' | 'tutorial';
type TabKey = 'todos' | ResourceType;

interface Resource {
    id: number;
    title: string;
    type: ResourceType;
    description: string;
    file_path: string | null;
    link: string | null;
    author: string;
    size: string;
    downloads: number;
}

/** Shape returned by GET /api/recursos */
interface ApiResource {
    id: number;
    title: string;
    category: string;
    description: string | null;
    file_path: string | null;
    link: string | null;
    access_count: number;
    file_size: string | null;
    author: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

function fromApi(r: ApiResource): Resource {
    return {
        id: r.id,
        title: r.title,
        type: (['reglamento', 'guia', 'plantilla', 'tutorial'].includes(r.category)
            ? r.category
            : 'reglamento') as ResourceType,
        description: r.description ?? '',
        file_path: r.file_path ?? null,
        link: r.link ?? null,
        author: r.author?.name ?? '—',
        size: r.file_size ?? '—',
        downloads: r.access_count,
    };
}

const TABS: { key: TabKey; label: string }[] = [
    { key: 'todos', label: 'Todos' },
    { key: 'reglamento', label: 'Reglamento' },
    { key: 'guia', label: 'Guías' },
    { key: 'plantilla', label: 'Plantillas' },
    { key: 'tutorial', label: 'Tutoriales' },
];

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

/** Colored left border per category */
const categoryBorders: Record<ResourceType, string> = {
    reglamento: 'border-l-primary',
    guia: 'border-l-secondary',
    plantilla: 'border-l-accent',
    tutorial: 'border-l-success',
};

function formatLabel(file_path: string | null, link: string | null): string {
    if (file_path) return 'Documento';
    if (link) return 'Enlace externo';
    return '—';
}

function formatBadgeStyle(file_path: string | null, link: string | null): string {
    if (file_path) return 'bg-[#dcfce7] text-[#14532d]';
    if (link) return 'bg-[#dbeafe] text-[#1e3a8a]';
    return 'bg-[#e7e5e4] text-[#57534e]';
}

/* ---------- Skeleton ---------- */
function ResourceSkeleton() {
    return (
        <div className="rounded-xl border border-border bg-surface p-5 shadow-warm-sm motion-safe:animate-pulse">
            <div className="flex flex-col gap-3">
                <div className="flex items-center gap-2">
                    <div className="h-10 w-10 rounded-xl bg-surface-variant" />
                    <div className="h-4 w-24 rounded bg-surface-variant" />
                </div>
                <div className="mt-1 h-5 w-3/4 rounded bg-surface-variant" />
                <div className="h-4 w-full rounded bg-surface-variant" />
                <div className="h-4 w-2/3 rounded bg-surface-variant" />
                <div className="mt-2 flex items-center justify-between pt-2">
                    <div className="h-6 w-20 rounded bg-surface-variant" />
                    <div className="h-3 w-12 rounded bg-surface-variant" />
                </div>
            </div>
        </div>
    );
}

export default function Recursos() {
    const [recursos, setRecursos] = useState<Resource[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [activeTab, setActiveTab] = useState<TabKey>('todos');

    /* Debounce search 300 ms */
    useEffect(() => {
        const timer = setTimeout(() => setDebouncedSearch(search), 300);
        return () => clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);
            try {
                const res = await apiFetch('/api/recursos');
                if (!res.ok) throw new Error('Error al cargar recursos');
                const body = await res.json();
                if (!cancelled) {
                    setRecursos((body.data ?? []).map(fromApi));
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
    }, []);

    const filtered = recursos.filter((r) => {
        const q = debouncedSearch.toLowerCase();
        const matchesSearch = !q || r.title.toLowerCase().includes(q) || r.description.toLowerCase().includes(q);
        const matchesTab = activeTab === 'todos' || r.type === activeTab;
        return matchesSearch && matchesTab;
    });

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Biblioteca de recursos"
                title="Recursos"
                subtitle="Acceda a reglamentos, guías, plantillas y tutoriales para el desarrollo de su proyecto de grado."
            />

            {/* Search */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-subtle" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar recursos…"
                        className="w-full min-h-[40px] rounded-lg border border-border bg-surface pl-9 pr-3 py-2 text-sm text-text outline-none transition-colors placeholder:text-text-subtle focus:border-primary focus:shadow-[0_0_0_3px_#fed7aa]"
                        aria-label="Buscar recursos"
                    />
                </div>
            </div>

            {/* Tabs */}
            <div className="flex flex-wrap gap-1" role="tablist" aria-label="Categorías">
                {TABS.map((tab) => (
                    <button
                        key={tab.key}
                        role="tab"
                        aria-selected={activeTab === tab.key}
                        onClick={() => setActiveTab(tab.key)}
                        className={cn(
                            'rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors active:scale-[0.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
                            activeTab === tab.key
                                ? 'bg-primary text-white shadow-sm'
                                : 'bg-surface-alt text-text-muted hover:bg-surface-variant hover:text-text',
                        )}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {/* Loading */}
            {loading && (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {Array.from({ length: 6 }).map((_, i) => (
                        <ResourceSkeleton key={i} />
                    ))}
                </div>
            )}

            {/* Error */}
            {!loading && error && (
                <div className="rounded-xl border border-[#fee2e2] bg-[#fef2f2] p-6">
                    <div className="flex items-start gap-3">
                        <AlertCircle className="h-5 w-5 shrink-0 text-[#dc2626]" />
                        <div>
                            <p className="text-sm font-semibold text-[#dc2626]">Error al cargar recursos</p>
                            <p className="mt-1 text-sm text-[#991b1b]">{error}</p>
                        </div>
                    </div>
                </div>
            )}

            {/* Empty */}
            {!loading && !error && filtered.length === 0 && (
                <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-alt">
                        <FolderKanban className="h-6 w-6 text-text-subtle" />
                    </div>
                    <div className="flex flex-col gap-1">
                        <h3 className="text-base font-semibold text-text">Sin resultados</h3>
                        <p className="text-sm text-text-muted max-w-sm">
                            {debouncedSearch
                                ? `No se encontraron recursos que coincidan con "${debouncedSearch}".`
                                : 'No hay recursos disponibles en esta categoría.'}
                        </p>
                    </div>
                </div>
            )}

            {/* Cards */}
            {!loading && !error && filtered.length > 0 && (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {filtered.map((recurso) => {
                        const Icon = typeIcons[recurso.type];
                        const fmtLabel = formatLabel(recurso.file_path, recurso.link);
                        const fmtStyle = formatBadgeStyle(recurso.file_path, recurso.link);
                        const borderColor = categoryBorders[recurso.type];
                        const hasFile = !!recurso.file_path;
                        const hasLink = !!recurso.link;

                        return (
                            <article
                                key={recurso.id}
                                role="article"
                                className={cn(
                                    'group relative flex flex-col rounded-xl border border-border bg-surface p-5 shadow-warm-sm transition-all duration-200 hover:shadow-warm-md focus-within:ring-2 focus-within:ring-primary/50',
                                    'border-l-4',
                                    borderColor,
                                )}
                            >
                                <div className="flex flex-col gap-3 h-full">
                                    {/* Icon + badges */}
                                    <div className="flex items-center gap-2">
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-container">
                                            <Icon className="h-5 w-5 text-primary" />
                                        </div>
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <span className="rounded-full bg-surface-alt px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.03em] text-text-muted">
                                                {typeLabels[recurso.type]}
                                            </span>
                                            <span
                                                className={cn(
                                                    'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.03em]',
                                                    fmtStyle,
                                                )}
                                            >
                                                {fmtLabel}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Title */}
                                    <h3 className="text-sm font-bold text-text group-hover:text-primary transition-colors text-balance">
                                        {recurso.title}
                                    </h3>

                                    {/* Description */}
                                    <p className="text-xs text-text-muted leading-relaxed line-clamp-2">
                                        {recurso.description}
                                    </p>

                                    {/* Metadata */}
                                    <div className="flex items-center gap-3 text-[11px] text-text-subtle">
                                        <span className="flex items-center gap-1 truncate">
                                            <span>Por {recurso.author}</span>
                                        </span>
                                        {recurso.file_path && (
                                            <span className="shrink-0">Documento</span>
                                        )}
                                        {!recurso.file_path && recurso.link && (
                                            <span className="shrink-0">Enlace</span>
                                        )}
                                    </div>

                                    {/* Spacer */}
                                    <div className="flex-1 min-h-0" />

                                    {/* Footer */}
                                    <div className="flex items-center justify-between gap-2 pt-2 border-t border-border/50">
                                        <div className="flex items-center gap-2 text-[11px]">
                                            {hasFile && (
                                                <a
                                                    href={`/storage/${recurso.file_path}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    download
                                                    onClick={(e) => e.stopPropagation()}
                                                    aria-label={`Descargar ${recurso.title}`}
                                                    className="inline-flex items-center gap-1 rounded-md bg-primary/10 px-2 py-1 font-semibold text-primary transition-colors hover:bg-primary/20"
                                                >
                                                    <Download className="h-3 w-3" />
                                                    Descargar
                                                </a>
                                            )}
                                            {!hasFile && hasLink && (
                                                <a
                                                    href={recurso.link!}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    onClick={(e) => e.stopPropagation()}
                                                    aria-label={`Abrir ${recurso.title}`}
                                                    className="inline-flex items-center gap-1 rounded-md bg-accent/10 px-2 py-1 font-semibold text-accent transition-colors hover:bg-accent/20"
                                                >
                                                    <ExternalLink className="h-3 w-3" />
                                                    Abrir enlace
                                                </a>
                                            )}
                                            {!hasFile && !hasLink && (
                                                <span className="text-text-subtle">—</span>
                                            )}
                                        </div>

                                        <span className="flex items-center gap-1 tabular-nums text-[11px] text-text-subtle shrink-0">
                                            <Download className="h-3 w-3" />
                                            {recurso.downloads}
                                        </span>
                                    </div>
                                </div>

                                {/* Card overlay link */}
                                <Link
                                    to={`/recursos/${recurso.id}`}
                                    className="absolute inset-0 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                                    aria-label={`Ver detalle de ${recurso.title}`}
                                >
                                    <span className="sr-only">Ver detalle de {recurso.title}</span>
                                </Link>
                            </article>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
