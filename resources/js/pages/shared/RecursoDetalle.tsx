import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { Loader2, BookOpen, Download, Eye, User, FileText, Gavel, PlaySquare, ChevronRight } from 'lucide-react';

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

const MOCK_DETAIL: Record<number, ResourceDetail> = {
    1: {
        id: 1,
        title: 'Reglamento de Proyectos de Grado 2026',
        type: 'reglamento',
        description: 'Normativa vigente que regula la inscripción, desarrollo y evaluación de proyectos de grado en Ingeniería de Sistemas.',
        body: 'Este reglamento establece las disposiciones generales para el desarrollo de proyectos de grado del programa de Ingeniería de Sistemas. Incluye los requisitos de inscripción, las modalidades de proyecto, los roles y responsabilidades de los participantes, los criterios de evaluación y el cronograma general del proceso.\n\nAplica a todos los estudiantes que cursen proyectos de grado a partir del ciclo 2026-S1. Las disposiciones aquí contenidas reemplazan cualquier normativa anterior.',
        author: 'Comité de Proyectos',
        size: '1.2 MB',
        downloads: 342,
        accesses: 1205,
    },
    2: {
        id: 2,
        title: 'Guía para la elaboración del anteproyecto',
        type: 'guia',
        description: 'Documento detallado con la estructura, requisitos y recomendaciones para la presentación del anteproyecto de grado.',
        body: 'Esta guía contiene las instrucciones detalladas para la elaboración del anteproyecto de grado. Cubre la estructura sugerida, los contenidos mínimos de cada sección, el formato de presentación y los criterios que evaluará el comité para su aprobación.',
        author: 'Coordinación Académica',
        size: '890 KB',
        downloads: 215,
        accesses: 876,
    },
    3: {
        id: 3,
        title: 'Plantilla de informe final',
        type: 'plantilla',
        description: 'Formato oficial en Word para la presentación del informe final del proyecto de grado.',
        body: 'Plantilla oficial en formato .docx con estilos predefinidos para la elaboración del informe final. Incluye portada, tabla de contenidos, numeración de páginas y estilos para títulos, párrafos y tablas.',
        author: 'Coordinación de Proyectos',
        size: '450 KB',
        downloads: 178,
        accesses: 654,
    },
    4: {
        id: 4,
        title: 'Tutorial: Cómo usar el sistema de entregas',
        type: 'tutorial',
        description: 'Video paso a paso que explica el proceso de carga y revisión de entregas.',
        body: 'Video tutorial que guía a los estudiantes a través del proceso completo de carga de entregas en la plataforma: inicio de sesión, navegación al módulo de entregas, carga de archivos, verificación de estado y consulta de retroalimentación.',
        author: 'Centro de Innovación',
        size: '15 MB',
        downloads: 89,
        accesses: 312,
    },
};

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
    const [notFound, setNotFound] = useState(false);

    useEffect(() => {
        const timer = setTimeout(() => {
            const data = MOCK_DETAIL[Number(id)];
            if (data) {
                setRecurso(data);
            } else {
                setNotFound(true);
            }
            setLoading(false);
        }, 400);
        return () => clearTimeout(timer);
    }, [id]);

    if (loading) {
        return (
            <div className="flex items-center justify-center py-16">
                <Loader2 className="h-6 w-6 animate-spin text-primary" />
            </div>
        );
    }

    if (notFound || !recurso) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-alt">
                    <BookOpen className="h-6 w-6 text-text-subtle" />
                </div>
                <div className="flex flex-col gap-1">
                    <h3 className="text-base font-semibold text-text">Recurso no encontrado</h3>
                    <p className="text-sm text-text-muted">
                        El recurso que buscas no existe o ha sido eliminado.
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
