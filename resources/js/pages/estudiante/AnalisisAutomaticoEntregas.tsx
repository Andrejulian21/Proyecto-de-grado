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
    Copy,
    Check,
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
    const [quotaExceeded, setQuotaExceeded] = useState(false);
    const [resultado, setResultado] = useState<ResultadoAnalisis | null>(null);
    const [avisoTruncado, setAvisoTruncado] = useState<string | null>(null);
    const [copiado, setCopiado] = useState(false);

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

    function resultadoATextoPlano(): string {
        if (!resultado) return '';
        const partes: string[] = [];
        if (resultado.resumen) partes.push(`Resumen\n${resultado.resumen}`);
        if (resultado.coherencia) partes.push(`Coherencia\n${resultado.coherencia}`);
        if (resultado.claridad) partes.push(`Claridad\n${resultado.claridad}`);
        if (resultado.estructura) partes.push(`Estructura\n${resultado.estructura}`);
        if (resultado.completitud_aparente)
            partes.push(`Completitud aparente\n${resultado.completitud_aparente}`);
        if (resultado.correspondencia)
            partes.push(`Correspondencia con lo solicitado\n${resultado.correspondencia}`);
        if ((resultado.observaciones ?? []).length > 0)
            partes.push(`Observaciones\n${(resultado.observaciones ?? []).map((o) => `- ${o}`).join('\n')}`);
        if ((resultado.recomendaciones ?? []).length > 0)
            partes.push(
                `Recomendaciones\n${(resultado.recomendaciones ?? []).map((r) => `- ${r}`).join('\n')}`,
            );
        if (resultado.conclusion) partes.push(`Conclusión\n${resultado.conclusion}`);
        return partes.join('\n\n');
    }

    async function handleCopy() {
        const texto = resultadoATextoPlano();
        if (!texto) return;
        setCopiado(false);
        try {
            await navigator.clipboard.writeText(texto);
            setCopiado(true);
            return;
        } catch {
            // Fallback for contexts without async clipboard access.
        }
        try {
            const area = document.createElement('textarea');
            area.value = texto;
            area.setAttribute('readonly', '');
            area.style.position = 'absolute';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            document.body.removeChild(area);
            setCopiado(true);
        } catch {
            setActionError('No se pudo copiar el texto. Selecciónalo manualmente.');
        }
    }

    async function handleAnalyze() {
        if (!entrega?.documento_analizable_ia) {
            setActionError('Esta entrega no tiene un documento configurado para análisis mediante IA.');
            return;
        }
        if (!entregaId || !file) {
            setActionError('Selecciona un archivo DOCX o PDF temporal para analizar.');
            return;
        }
        if (!/\.(docx|pdf)$/i.test(file.name)) {
            setActionError('Solo se aceptan documentos en formato DOCX o PDF.');
            return;
        }

        setProcessing(true);
        setActionError(null);
        setAiUnavailable(false);
        setQuotaExceeded(false);
        setAvisoTruncado(null);
        setResultado(null);
        setCopiado(false);

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

            if (res.status === 429 || payload?.code === 'ai_quota_exceeded') {
                setQuotaExceeded(true);
                setActionError(
                    payload?.error ?? 'Límite de cuota de IA alcanzado. Inténtalo de nuevo en 60 segundos.',
                );
                return;
            }

            if (res.status === 504 || payload?.code === 'ai_timeout') {
                setActionError(
                    payload?.error ?? 'El análisis tardó demasiado. Inténtalo de nuevo.',
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
            if (typeof payload.data?.aviso_truncado === 'string') {
                setAvisoTruncado(payload.data.aviso_truncado);
            }
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
                        <div>
                            <p className="text-xs text-[#78716c]">Documento analizable</p>
                            <p className="text-sm font-semibold text-[#1c1917]">
                                {entrega.documentos.find((d) => d.id === entrega.documento_analizable_ia)?.nombre
                                    ?? 'Ninguno configurado'}
                            </p>
                        </div>
                        <div className="sm:col-span-2 lg:col-span-4">
                            <p className="text-xs text-[#78716c]">Lo esperado en esta entrega</p>
                            <p className="text-sm text-[#44403c] whitespace-pre-wrap">
                                {entrega.descripcion || 'Esta entrega no tiene una descripción definida.'}
                            </p>
                        </div>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="lg:col-span-1">
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-4 flex items-center gap-2">
                            <FileText className="h-5 w-5 text-[#c2410c]" />
                            <h3 className="text-base font-bold text-[#1c1917]">Archivo temporal para IA</h3>
                        </div>
                        {file ? (
                            <div className="flex flex-col gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-4">
                                <p className="flex items-center gap-2 text-sm font-medium text-[#1c1917]">
                                    <Eye className="h-4 w-4 shrink-0 text-[#78716c]" />
                                    <span className="truncate">{fileLabel}</span>
                                </p>
                                <label className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa]">
                                    <Upload className="h-4 w-4" />
                                    Cambiar archivo
                                    <input
                                        type="file"
                                        accept=".docx,.pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/pdf"
                                        className="hidden"
                                        onChange={(e) => {
                                            const next = e.target.files?.[0] ?? null;
                                            setFile(next);
                                            setResultado(null);
                                            setCopiado(false);
                                            setActionError(null);
                                        }}
                                    />
                                </label>
                            </div>
                        ) : (
                            <div className="flex w-full flex-col items-center justify-center gap-4 rounded-lg border border-dashed border-[#e5e5e5] bg-[#fafaf9] px-4 py-10 text-center">
                                <Eye className="h-10 w-10 text-[#78716c]" />
                                <p className="text-sm font-medium text-[#1c1917]">
                                    Selecciona un borrador DOCX o PDF
                                </p>
                                <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa]">
                                    <Upload className="h-4 w-4" />
                                    Elegir DOCX o PDF
                                    <input
                                        type="file"
                                        accept=".docx,.pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/pdf"
                                        className="hidden"
                                        onChange={(e) => {
                                            const next = e.target.files?.[0] ?? null;
                                            setFile(next);
                                            setResultado(null);
                                            setCopiado(false);
                                            setActionError(null);
                                        }}
                                    />
                                </label>
                            </div>
                        )}
                        <p className="mt-3 text-xs text-[#78716c]">
                            Este archivo no se guarda como versión oficial. La retroalimentación de IA sí
                            se conserva asociada al documento analizable y, si más adelante subes el mismo
                            archivo, a esa versión.
                        </p>
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

                        {quotaExceeded && (
                            <div className="rounded-xl border border-[#fde68a] bg-[#fffbeb] p-4">
                                <div className="flex items-start gap-2.5">
                                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-[#d97706]" />
                                    <div>
                                        <p className="text-xs font-semibold text-[#78350f]">
                                            Límite de cuota de IA alcanzado
                                        </p>
                                        <p className="mt-1 text-xs text-[#78350f]">
                                            {actionError ??
                                                'Límite de cuota de IA alcanzado. Inténtalo de nuevo en 60 segundos.'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {avisoTruncado && (
                            <div className="rounded-xl border border-[#e5e5e5] bg-[#fafaf9] p-4">
                                <p className="text-xs text-[#57534e]">{avisoTruncado}</p>
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

                                <button
                                    type="button"
                                    onClick={() => void handleCopy()}
                                    className="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2.5 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                                >
                                    {copiado ? (
                                        <Check className="h-4 w-4" />
                                    ) : (
                                        <Copy className="h-4 w-4" />
                                    )}
                                    {copiado ? 'Análisis copiado' : 'Copiar análisis'}
                                </button>
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

                        <div className="rounded-xl border border-[#f59e0b] bg-[#fffbeb] p-4" role="alert">
                            <div className="flex items-start gap-2.5">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-[#d97706]" />
                                <div>
                                    <p className="text-xs font-semibold text-[#78350f]">
                                        Este análisis NO se guarda como entrega
                                    </p>
                                    <p className="mt-1 text-xs text-[#78350f]">
                                        Es solo orientación preliminar para mejorar tu borrador. Cuando esté
                                        listo, súbelo por el flujo normal de entregas: este análisis no
                                        reemplaza la evaluación de tu director.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {processing && (
                            <div className="flex items-center gap-2 text-xs text-[#57534e]" role="status">
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                Analizando tu borrador… Recuerda: el resultado no quedará guardado como
                                entrega oficial.
                            </div>
                        )}

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
