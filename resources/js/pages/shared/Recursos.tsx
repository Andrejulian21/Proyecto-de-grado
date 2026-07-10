import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { cn } from '@/lib/utils';
import { Loader2, Search, BookOpen, Gavel, FileText, PlaySquare, Download, FolderKanban } from 'lucide-react';

type ResourceType = 'reglamento' | 'guia' | 'plantilla' | 'tutorial';
type TabKey = 'todos' | ResourceType;

interface Resource {
    id: number;
    title: string;
    type: ResourceType;
    description: string;
    author: string;
    size: string;
    downloads: number;
}

const MOCK_RECURSOS: Resource[] = [
    {
        id: 1,
        title: 'Reglamento de Proyectos de Grado 2026',
        type: 'reglamento',
        description: 'Normativa vigente que regula la inscripción, desarrollo y evaluación de proyectos de grado en Ingeniería de Sistemas.',
        author: 'Comité de Proyectos',
        size: '1.2 MB',
        downloads: 342,
    },
    {
        id: 2,
        title: 'Guía para la elaboración del anteproyecto',
        type: 'guia',
        description: 'Documento detallado con la estructura, requisitos y recomendaciones para la presentación del anteproyecto de grado.',
        author: 'Coordinación Académica',
        size: '890 KB',
        downloads: 215,
    },
    {
        id: 3,
        title: 'Plantilla de informe final',
        type: 'plantilla',
        description: 'Formato oficial en Word para la presentación del informe final del proyecto de grado, con estilos y estructura predefinidos.',
        author: 'Coordinación de Proyectos',
        size: '450 KB',
        downloads: 178,
    },
    {
        id: 4,
        title: 'Tutorial: Cómo usar el sistema de entregas',
        type: 'tutorial',
        description: 'Video paso a paso que explica el proceso de carga y revisión de entregas en la plataforma de proyectos de grado.',
        author: 'Centro de Innovación',
        size: '15 MB',
        downloads: 89,
    },
];

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

export default function Recursos() {
    const [recursos, setRecursos] = useState<Resource[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [activeTab, setActiveTab] = useState<TabKey>('todos');

    useEffect(() => {
        const timer = setTimeout(() => {
            setRecursos(MOCK_RECURSOS);
            setLoading(false);
        }, 400);
        return () => clearTimeout(timer);
    }, []);

    const filtered = recursos.filter((r) => {
        const matchesSearch =
            r.title.toLowerCase().includes(search.toLowerCase()) ||
            r.description.toLowerCase().includes(search.toLowerCase());
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

            {/* Search + Filter */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-subtle" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar recursos..."
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
                            'rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors active:scale-[0.98]',
                            activeTab === tab.key
                                ? 'bg-primary text-white shadow-sm'
                                : 'bg-surface-alt text-text-muted hover:bg-surface-variant hover:text-text',
                        )}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {/* Content */}
            {loading ? (
                <div className="flex items-center justify-center py-16">
                    <Loader2 className="h-6 w-6 animate-spin text-primary" />
                </div>
            ) : filtered.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-alt">
                        <FolderKanban className="h-6 w-6 text-text-subtle" />
                    </div>
                    <div className="flex flex-col gap-1">
                        <h3 className="text-base font-semibold text-text">Sin resultados</h3>
                        <p className="text-sm text-text-muted max-w-sm">
                            {search
                                ? `No se encontraron recursos que coincidan con "${search}".`
                                : 'No hay recursos disponibles en esta categoría.'}
                        </p>
                    </div>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {filtered.map((recurso) => {
                        const Icon = typeIcons[recurso.type];
                        return (
                            <Link
                                key={recurso.id}
                                to={`/recursos/${recurso.id}`}
                                className="group rounded-xl border border-border bg-surface p-5 shadow-warm-sm transition-colors hover:border-primary/30 hover:shadow-warm-md"
                            >
                                <div className="flex flex-col gap-3">
                                    <div className="flex items-center gap-2">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-container">
                                            <Icon className="h-4.5 w-4.5 text-primary" />
                                        </div>
                                        <span className="rounded-full bg-surface-alt px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.03em] text-text-muted">
                                            {typeLabels[recurso.type]}
                                        </span>
                                    </div>

                                    <h3 className="text-sm font-bold text-text group-hover:text-primary transition-colors text-balance">
                                        {recurso.title}
                                    </h3>

                                    <p className="text-xs text-text-muted leading-relaxed line-clamp-2">
                                        {recurso.description}
                                    </p>

                                    <div className="mt-auto flex items-center justify-between text-[11px] text-text-subtle">
                                        <span>{recurso.size}</span>
                                        <span className="flex items-center gap-1 tabular-nums">
                                            <Download className="h-3 w-3" />
                                            {recurso.downloads}
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
