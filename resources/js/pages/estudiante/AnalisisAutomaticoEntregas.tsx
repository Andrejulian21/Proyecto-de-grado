import { useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { apiFetch } from '@/lib/utils';
import {
    mapEntregaToAnalisisContext,
    type EntregaAnalisisContext,
} from '@/hooks/useEstudianteEntregas';
import {
    ArrowLeft,
    Eye,
    FileText,
    Brain,
    AlertTriangle,
    Loader2,
    Upload,
} from 'lucide-react';

interface ResultadoAnalisis {
    resumen: string;
    coherencia?: string;
    claridad?: string;
    estructura?: string;
    completitud_aparente?: string;
    correspondencia?: string;
    observaciones?: string[];
    recomendaciones?: string[];
    conclusion: string;
}

interface LocationState {
    entrega?: EntregaAnalisisContext;
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

export default function AnalisisAutomaticoEntregas() {
    const navigate = useNavigate();
    const { entregaId: entregaIdParam } = useParams<{ entregaId: string }>();
    const location = useLocation();
    const state = (location.state || {}) as LocationState;

    const [entrega, setEntrega] = useState<EntregaAnalisisContext | null>(state.entrega ?? null);
    const [loading, setLoading] = useState(!state.entrega);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [file, setFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);
    const [aiUnavailable, setAiUnavailable] = useState(false);
    const [resultado, setResultado] = useState<ResultadoAnalisis | null>(null);

    const entregaId = Number(entregaIdParam);

    useEffect(() => {
        if (!entregaIdParam || Number.isNaN(entregaId)) {
            navigate('/analisis-entregas', { replace: true });
            return;
        }
        if (state.entrega && state.entrega.id === entregaId) {
            setEntrega(state.entrega);
            setLoading(false);
            return;
        }

        let cancelled = false;
        (async () => {
            setLoading(true);
            setLoadError(null);
            try {
                const res = await apiFetch('/api/estudiante/entregas');
                if (!res.ok) throw new Error('No se pudo cargar la entrega.');
                const json = await res.json();
                const raw = (json.data ?? []).find((e: any) => e.id === entregaId);
                if (!raw) throw new Error('No se encontró la entrega seleccionada.');
                if (!cancelled) setEntrega(mapEntregaToAnalisisContext(raw));
            } catch (err) {
                if (!cancelled) {
                    setLoadError(err instanceof Error ? err.message : 'Error al cargar la entrega.');
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [entregaId, entregaIdParam, navigate, state.entrega]);

    const observaciones = resultado?.observaciones ?? [];

    const fileLabel = useMemo(() => {
        if (!file) return null;
        return `${file.name} (${Math.max(1, Math.round(file.size / 1024))} KB)`;
    }, [file]);

    async function handleAnalyze() {
        if (!entregaId || !file) {
            setActionError('Selecciona un archivo DOCX temporal para analizar.');
            return;
        }
        if (!file.name.toLowerCase().endsWith('.docx')) {
            setActionError('Solo se admiten archivos DOCX.');
            return;
        }

        setProcessing(true);
        setActionError(null);
        setAiUnavailable(false);
        setResultado(null);

        try {
            const body = new FormData();
            body.append('file', file);

            const res = await apiFetch(`/api/estudiante/entregas/${entregaId}/evaluacion-inteligente`, {
                method: 'POST',
                body,
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
                setActionError(
                    typeof payload?.error === 'string'
                        ? payload.error
                        : 'No se pudo completar el análisis. Inténtalo de nuevo.',
                );
                return;
            }

            setResultado(payload.data?.resultado ?? null);
        } catch {
            setActionError('No se pudo completar el análisis. Verifica tu conexión e inténtalo de nuevo.');
        } finally {
            setProcessing(false);
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="IA"
                title="Análisis preliminar de IA"
                subtitle="Retroalimentación preliminar con un borrador DOCX (no modifica la entrega oficial ni reemplaza al director)"
                actions={
                    <button
                        onClick={() => navigate('/analisis-entregas')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            {loading && (
                <div className="flex items-center gap-2 text-sm text-[#57534e]">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Cargando información de la entrega…
                </div>
            )}

            {loadError && (
                <div className="rounded-xl border border-[#fecaca] bg-[#fef2f2] p-4 text-sm text-[#991b1b]">
                    {loadError}
                </div>
            )}

            {entrega && !loading && (
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p className="text-xs text-[#78716c]">Entrega</p>
                            <p className="text-sm font-semibold text-[#1c1917]">{entrega.titulo}</p>
                        </div>
                        <div>
                            <p className="text-xs text-[#78716c]">Fase</p>
                            <p className="text-sm font-semibold text-[#1c1917]">{entrega.faseLabel}</p>
                        </div>
                        <div className="sm:col-span-2">
                            <p className="text-xs text-[#78716c]">Lo esperado en esta entrega</p>
                            <p className="text-sm text-[#44403c] whitespace-pre-wrap">
                                {entrega.descripcion || 'Esta entrega no tiene una descripción definida.'}
                            </p>
                        </div>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                <div className="lg:col-span-3">
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-4 flex items-center gap-2">
                            <FileText className="h-5 w-5 text-[#c2410c]" />
                            <h3 className="text-base font-bold text-[#1c1917]">Archivo temporal para IA</h3>
                        </div>
                        <div className="flex aspect-[8.5/11] w-full flex-col items-center justify-center gap-4 rounded-lg border border-dashed border-[#e5e5e5] bg-[#fafaf9] px-4 text-center">
                            <Eye className="h-12 w-12 text-[#78716c]" />
                            <p className="text-sm font-medium text-[#1c1917]">
                                {fileLabel ?? 'Selecciona un borrador DOCX'}
                            </p>
                            <p className="max-w-md text-xs text-[#78716c]">
                                Este archivo se usa solo para el análisis. No se guarda como versión oficial ni
                                altera el historial de la entrega.
                            </p>
                            <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa]">
                                <Upload className="h-4 w-4" />
                                Elegir DOCX
                                <input
                                    type="file"
                                    accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                    className="hidden"
                                    onChange={(e) => {
                                        const next = e.target.files?.[0] ?? null;
                                        setFile(next);
                                        setResultado(null);
                                        setActionError(null);
                                    }}
                                />
                            </label>
                        </div>
                    </div>
                </div>

                <div className="lg:col-span-2">
                    <div className="sticky top-20 flex flex-col gap-4">
                        {aiUnavailable && (
                            <div className="rounded-xl border border-[#fde68a] bg-[#fffbeb] p-4">
                                <div className="flex items-start gap-2.5">
                                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-[#d97706]" />
                                    <div>
                                        <p className="text-xs font-semibold text-[#78350f]">
                                            Servicio de Inteligencia Artificial no disponible
                                        </p>
                                        <p className="mt-1 text-xs text-[#78350f]">
                                            {actionError ??
                                                'No fue posible conectarse al servicio de Inteligencia Artificial. Inténtalo más tarde.'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {actionError && !aiUnavailable && (
                            <div className="rounded-xl border border-[#fecaca] bg-[#fef2f2] p-4 text-xs text-[#991b1b]">
                                {actionError}
                            </div>
                        )}

                        {resultado && (
                            <>
                                <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                                    <div className="mb-3 flex items-center gap-2">
                                        <Brain className="h-5 w-5 text-[#c2410c]" />
                                        <h3 className="text-sm font-bold text-[#1c1917]">
                                            Retroalimentación preliminar de IA
                                        </h3>
                                    </div>
                                    {resultado.resumen && (
                                        <p className="text-sm text-[#44403c]">{resultado.resumen}</p>
                                    )}
                                    <div className="mt-4 flex flex-col gap-3">
                                        <AspectBlock title="Coherencia" text={resultado.coherencia} />
                                        <AspectBlock title="Claridad" text={resultado.claridad} />
                                        <AspectBlock title="Estructura" text={resultado.estructura} />
                                        <AspectBlock
                                            title="Completitud aparente"
                                            text={resultado.completitud_aparente}
                                        />
                                        <AspectBlock
                                            title="Correspondencia con lo solicitado"
                                            text={resultado.correspondencia}
                                        />
                                    </div>
                                </div>

                                {observaciones.length > 0 && (
                                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                                        <h3 className="mb-2 text-sm font-bold text-[#1c1917]">Observaciones</h3>
                                        <ul className="list-disc space-y-1 pl-4 text-xs text-[#57534e]">
                                            {observaciones.map((item, index) => (
                                                <li key={`o-${index}`}>{item}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                {(resultado.recomendaciones ?? []).length > 0 && (
                                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                                        <h3 className="mb-2 text-sm font-bold text-[#1c1917]">Recomendaciones</h3>
                                        <ul className="list-disc space-y-1 pl-4 text-xs text-[#57534e]">
                                            {(resultado.recomendaciones ?? []).map((item, index) => (
                                                <li key={`r-${index}`}>{item}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                {resultado.conclusion && (
                                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                                        <h3 className="mb-2 text-sm font-bold text-[#1c1917]">Conclusión</h3>
                                        <p className="text-xs text-[#57534e]">{resultado.conclusion}</p>
                                    </div>
                                )}
                            </>
                        )}

                        <div className="rounded-xl border border-[#fef3c7] bg-[#fef3c7] p-4">
                            <div className="flex items-start gap-2.5">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-[#d97706]" />
                                <div>
                                    <p className="text-xs font-semibold text-[#78350f]">
                                        La evaluación de IA es orientativa
                                    </p>
                                    <p className="mt-1 text-xs text-[#78350f]">
                                        Este análisis preliminar no reemplaza la evaluación académica del director.
                                        El archivo analizado no se convierte en entrega oficial.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <button
                            onClick={() => void handleAnalyze()}
                            disabled={processing || loading || !file || !entrega}
                            className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:opacity-60"
                        >
                            {processing ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Brain className="h-4 w-4" />
                            )}
                            {processing ? 'Analizando…' : 'Analizar borrador'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
