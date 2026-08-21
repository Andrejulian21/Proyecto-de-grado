import type { AnalisisIa, ResultadoAnalisisPreliminar } from '@/types/entregas';
import { Brain, Calendar } from 'lucide-react';

interface Props {
    analisis: AnalisisIa[];
    /** When false, render nothing (including no empty placeholder). */
    visible?: boolean;
}

function formatDateTime(dateStr: string | null | undefined): string {
    if (!dateStr) return '—';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('es-CO', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateStr;
    }
}

function ListBlock({ title, items }: { title: string; items: string[] }) {
    if (!items.length) return null;
    return (
        <div>
            <p className="mb-1.5 text-xs font-semibold text-[#1c1917]">{title}</p>
            <ul className="list-disc space-y-1 pl-4">
                {items.map((item, index) => (
                    <li key={`${title}-${index}`} className="text-xs text-[#44403c]">
                        {item}
                    </li>
                ))}
            </ul>
        </div>
    );
}

function AspectBlock({ title, text }: { title: string; text?: string }) {
    if (!text) return null;
    return (
        <div>
            <p className="mb-1 text-xs font-semibold text-[#1c1917]">{title}</p>
            <p className="text-sm text-[#44403c]">{text}</p>
        </div>
    );
}

function ResultadoBody({ resultado }: { resultado: ResultadoAnalisisPreliminar }) {
    const resumen = resultado.resumen || resultado.resumen_ejecutivo;

    return (
        <div className="flex flex-col gap-3">
            {resumen && (
                <div>
                    <p className="mb-1 text-xs font-semibold text-[#1c1917]">Resumen</p>
                    <p className="text-sm text-[#44403c]">{resumen}</p>
                </div>
            )}
            <AspectBlock title="Coherencia" text={resultado.coherencia} />
            <AspectBlock title="Claridad" text={resultado.claridad} />
            <AspectBlock title="Estructura" text={resultado.estructura} />
            <AspectBlock title="Completitud aparente" text={resultado.completitud_aparente} />
            <AspectBlock title="Correspondencia con lo solicitado" text={resultado.correspondencia} />
            <ListBlock title="Observaciones" items={resultado.observaciones ?? []} />
            <ListBlock title="Recomendaciones" items={resultado.recomendaciones ?? []} />
            {resultado.conclusion && (
                <div>
                    <p className="mb-1 text-xs font-semibold text-[#1c1917]">Conclusión</p>
                    <p className="text-sm text-[#44403c]">{resultado.conclusion}</p>
                </div>
            )}
        </div>
    );
}

/**
 * Read-only AI feedback for a specific version. Hidden when there is nothing
 * to show — never an empty IA section on a non-analyzable document.
 */
export function RetroalimentacionIa({ analisis, visible = true }: Props) {
    if (!visible || analisis.length === 0) {
        return null;
    }

    const [latest, ...older] = analisis;
    const resultado = latest.resultado;

    if (!resultado) {
        return null;
    }

    return (
        <div className="rounded-md border border-[#ffedd5] bg-white p-3">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.05em] text-[#9a330a]">
                    <Brain className="h-3.5 w-3.5" />
                    Retroalimentación de IA
                </p>
                <p className="flex items-center gap-1 text-[11px] text-[#78716c]">
                    <Calendar className="h-3 w-3" />
                    {formatDateTime(latest.analizado_en)}
                </p>
            </div>
            <ResultadoBody resultado={resultado} />
            {older.length > 0 && (
                <details className="mt-3 border-t border-[#ffedd5] pt-2">
                    <summary className="cursor-pointer text-[11px] font-semibold text-[#9a330a]">
                        Historial ({older.length})
                    </summary>
                    <div className="mt-2 flex flex-col gap-3">
                        {older.map((item) => (
                            item.resultado ? (
                                <div key={item.id} className="rounded-md bg-[#fff7ed] p-2">
                                    <p className="mb-2 flex items-center gap-1 text-[11px] text-[#78716c]">
                                        <Calendar className="h-3 w-3" />
                                        {formatDateTime(item.analizado_en)}
                                    </p>
                                    <ResultadoBody resultado={item.resultado} />
                                </div>
                            ) : null
                        ))}
                    </div>
                </details>
            )}
            <p className="mt-3 text-[11px] leading-relaxed text-[#78350f]">
                Esta retroalimentación es informativa y preliminar. No es una calificación académica
                ni sustituye la observación del director.
            </p>
        </div>
    );
}
