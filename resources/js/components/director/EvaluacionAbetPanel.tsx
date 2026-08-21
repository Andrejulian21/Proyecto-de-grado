import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/utils';
import { AlertTriangle, Brain, Loader2 } from 'lucide-react';

interface ResultadoPreliminar {
    resumen?: string;
    resumen_ejecutivo?: string;
    coherencia?: string;
    claridad?: string;
    estructura?: string;
    completitud_aparente?: string;
    correspondencia?: string;
    observaciones?: string[];
    recomendaciones?: string[];
    conclusion?: string;
}

interface Props {
    entregaId: number;
    versionId: number | null;
    versionLabel?: string;
    isDocx: boolean;
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

export function EvaluacionAbetPanel({ entregaId, versionId, versionLabel, isDocx }: Props) {
    const [processing, setProcessing] = useState(false);
    const [loadingLatest, setLoadingLatest] = useState(true);
    const [resultado, setResultado] = useState<ResultadoPreliminar | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [aiUnavailable, setAiUnavailable] = useState(false);

    useEffect(() => {
        let cancelled = false;

        async function loadLatest() {
            setLoadingLatest(true);
            try {
                const res = await apiFetch(`/api/director/entregas/${entregaId}/evaluacion-abet`);
                const payload = await res.json().catch(() => ({}));
                if (!res.ok || cancelled) return;
                const data = payload?.data;
                if (data?.resultado) {
                    setResultado(data.resultado as ResultadoPreliminar);
                }
            } catch {
                // Optional preload — ignore
            } finally {
                if (!cancelled) setLoadingLatest(false);
            }
        }

        void loadLatest();
        return () => {
            cancelled = true;
        };
    }, [entregaId]);

    async function handleEvaluate() {
        if (!versionId) {
            setActionError('Selecciona una versión DOCX para analizar.');
            return;
        }
        if (!isDocx) {
            setActionError('Solo se pueden analizar documentos en formato DOCX.');
            return;
        }

        setProcessing(true);
        setActionError(null);
        setAiUnavailable(false);

        try {
            const res = await apiFetch(`/api/director/entregas/${entregaId}/evaluacion-abet`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ version_id: versionId }),
            });
            const payload = await res.json().catch(() => ({}));

            if (res.status === 503 || payload?.code === 'ai_unavailable') {
                setAiUnavailable(true);
                setActionError(
                    payload?.error ??
                        'El servicio de Inteligencia Artificial no se encuentra disponible temporalmente.',
                );
                return;
            }

            if (!res.ok) {
                setActionError(payload?.error ?? 'No fue posible completar el análisis preliminar.');
                return;
            }

            const result = payload?.data?.resultado as ResultadoPreliminar | undefined;
            if (result) {
                setResultado(result);
            }
        } catch {
            setActionError('No fue posible contactar al servicio de análisis. Inténtalo de nuevo.');
        } finally {
            setProcessing(false);
        }
    }

    const resumen = resultado?.resumen || resultado?.resumen_ejecutivo;

    return (
        <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Brain className="h-5 w-5 text-[#c2410c]" />
                    <div>
                        <h3 className="text-base font-bold text-[#1c1917]">Análisis preliminar de IA</h3>
                        <p className="text-xs text-[#78716c]">
                            Retroalimentación preliminar sobre el documento oficial seleccionado
                            {versionLabel ? ` · ${versionLabel}` : ''}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={() => void handleEvaluate()}
                    disabled={processing || !versionId || !isDocx}
                    className="inline-flex min-h-[40px] items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Brain className="h-4 w-4" />}
                    {processing ? 'Analizando…' : 'Ejecutar análisis preliminar'}
                </button>
            </div>

            {(aiUnavailable || actionError) && (
                <div
                    className="mb-4 flex items-start gap-3 rounded-lg border border-[#fecaca] bg-[#fef2f2] px-4 py-3 text-sm text-[#991b1b]"
                    role="alert"
                >
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                    <p>
                        {aiUnavailable
                            ? 'El servicio de Inteligencia Artificial no se encuentra disponible temporalmente. Inténtalo más tarde.'
                            : actionError}
                    </p>
                </div>
            )}

            {!isDocx && versionId && (
                <p className="mb-4 text-xs text-[#78716c]">
                    La versión seleccionada no es DOCX. Selecciona un documento Word para analizar.
                </p>
            )}

            {loadingLatest && !resultado && (
                <div className="flex items-center gap-2 text-xs text-[#78716c]">
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    Cargando último análisis…
                </div>
            )}

            {resultado && (
                <div className="flex flex-col gap-4">
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
            )}

            {!loadingLatest && !resultado && !actionError && !aiUnavailable && (
                <p className="text-xs text-[#78716c]">
                    Ejecuta el análisis para obtener una orientación preliminar. No sustituye tu evaluación
                    académica como director.
                </p>
            )}

            <div className="mt-4 rounded-lg border border-[#fef3c7] bg-[#fef3c7] px-3 py-2">
                <p className="text-xs text-[#78350f]">
                    La evaluación de IA es orientativa y no reemplaza la evaluación académica del director.
                </p>
            </div>
        </div>
    );
}
