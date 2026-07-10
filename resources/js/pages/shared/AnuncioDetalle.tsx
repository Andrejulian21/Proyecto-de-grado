import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { Loader2, ArrowLeft, Calendar, User, Paperclip, FileDown, FileText } from 'lucide-react';

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

const MOCK_DETAIL: Record<number, AnnouncementDetail> = {
    1: {
        id: 1,
        title: 'Cronograma de sustentaciones',
        category: 'importante',
        date: '28/06/2026',
        author: 'Coordinación de Proyectos de Grado',
        body: 'Se informa a la comunidad académica del programa de Ingeniería de Sistemas que las sustentaciones de proyectos de grado correspondientes al ciclo 2026-S1 se realizarán durante la primera semana de agosto de 2026.\n\nLos estudiantes deben asegurarse de haber cargado la versión final de su proyecto y contar con la aprobación de su director antes de inscribirse en la agenda de sustentaciones.\n\nLas fechas específicas por proyecto serán publicadas en los próximos días. Cualquier cambio será notificado oportunamente a través de este medio.',
        attachments: [
            { name: 'calendario_sustentaciones_2026.pdf', size: '245 KB' },
            { name: 'formato_acta_sustentacion.docx', size: '120 KB' },
        ],
    },
    2: {
        id: 2,
        title: 'Recordatorio: Entrega de informes finales',
        category: 'recordatorio',
        date: '25/06/2026',
        author: 'Coordinación de Proyectos de Grado',
        body: 'Se recuerda a todos los estudiantes de proyectos de grado que el plazo para la entrega de informes finales del ciclo 2026-S1 vence el próximo 15 de julio de 2026.\n\nLa entrega debe realizarse a través de la plataforma, adjuntando el documento en formato PDF junto con los anexos correspondientes. Es responsabilidad del estudiante verificar que el archivo cargado sea legible y esté completo.\n\nLos informes que no cumplan con los requisitos de formato establecidos en la guía serán devueltos para corrección.',
        attachments: [
            { name: 'guia_informe_final_2026.pdf', size: '310 KB' },
        ],
    },
};

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
    const [notFound, setNotFound] = useState(false);

    useEffect(() => {
        const timer = setTimeout(() => {
            const data = MOCK_DETAIL[Number(id)];
            if (data) {
                setAnuncio(data);
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

    if (notFound || !anuncio) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-alt">
                    <FileText className="h-6 w-6 text-text-subtle" />
                </div>
                <div className="flex flex-col gap-1">
                    <h3 className="text-base font-semibold text-text">Anuncio no encontrado</h3>
                    <p className="text-sm text-text-muted">
                        El anuncio que buscas no existe o ha sido eliminado.
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
