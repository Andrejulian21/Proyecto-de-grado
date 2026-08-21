import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/utils';
import { RetroalimentacionIa } from '@/components/entregas/RetroalimentacionIa';
import type { AnalisisIa, ResultadoAnalisisPreliminar } from '@/types/entregas';
import { AlertTriangle, Brain, Loader2 } from 'lucide-react';

interface Props {
    entregaId: number;
    versionId: number | null;
    versionLabel?: string;
    isConvertible: boolean;
    analisisInicial?: AnalisisIa[];
}

function toAnalisis(payload: Record<string, unknown> | null | undefined): AnalisisIa | null {
    if (!payload || typeof payload.id !== 'number') {
        return null;
    }

    return {
        id: payload.id as number,
        entrega_id: (payload.entrega_id as number) ?? 0,
        documento_id: (payload.documento_id as string | null) ?? null,
        version_id: (payload.version_id as number | null) ?? null,
        temporal: Boolean(payload.temporal),
        tipo: payload.tipo as string | undefined,
        estado: payload.estado as string | undefined,
        resultado: (payload.resultado as ResultadoAnalisisPreliminar | null) ?? null,
        analizado_en: (payload.analizado_en as string | null) ?? null,
    };
}

export function EvaluacionAbetPanel({
    entregaId,
    versionId,
    versionLabel,
    isConvertible,
    analisisInicial = [],
}: Props) {
    const [processing, setProcessing] = useState(false);
    const [loadingLatest, setLoadingLatest] = useState(true);
    const [historial, setHistorial] = useState<AnalisisIa[]>(analisisInicial);
    const [actionError, setActionError] = useState<string | null>(null);
    const [aiUnavailable, setAiUnavailable] = useState(false);

    useEffect(() => {
        setHistorial(analisisInicial);
        setActionError(null);
        setAiUnavailable(false);
        // Reset when the selected version changes; do not depend on array identity.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [versionId]);

    useEffect(() => {
        let cancelled = false;

        async function loadLatest() {
            if (!versionId) {
                setLoadingLatest(false);
                return;
            }

            setLoadingLatest(true);
            try {
                const res = await apiFetch(
                    `/api/director/entregas/${entregaId}/evaluacion-abet?version_id=${versionId}`,
                );
                const payload = await res.json().catch(() => ({}));
                if (!res.ok || cancelled) return;
                const items = Array.isArray(payload?.historial)
                    ? (payload.historial as Record<string, unknown>[])
                        .map((row) => toAnalisis(row))
                        .filter((row): row is AnalisisIa => row !== null)
                    : [];
                const latest = toAnalisis(payload?.data);
                if (items.length > 0) {
                    setHistorial(items);
                } else if (latest) {
                    setHistorial([latest]);
                } else {
                    setHistorial([]);
                }
            } catch {
                // Optional preload — keep analisisInicial
            } finally {
                if (!cancelled) setLoadingLatest(false);
            }
        }

        void loadLatest();
        return () => {
            cancelled = true;
        };
    }, [entregaId, versionId]);

    async function handleEvaluate() {
        if (!versionId) {
            setActionError('Selecciona una versión DOCX o PDF para analizar.');
            return;
        }
        if (!isConvertible) {
            setActionError('Solo se aceptan documentos en formato DOCX o PDF.');
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

            const created = toAnalisis(payload?.data);
            if (created) {
                setHistorial((prev) => [created, ...prev.filter((item) => item.id !== created.id)]);
            }
        } catch {
            setActionError('No fue posible contactar al servicio de análisis. Inténtalo de nuevo.');
        } finally {
            setProcessing(false);
        }
    }

    return (
        <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Brain className="h-5 w-5 text-[#c2410c]" />
                    <div>
                        <h3 className="text-base font-bold text-[#1c1917]">Análisis preliminar de IA</h3>
                        <p className="text-xs text-[#78716c]">
                            Retroalimentación informativa sobre el documento oficial seleccionado
                            {versionLabel ? ` · ${versionLabel}` : ''}. No es una calificación académica.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={() => void handleEvaluate()}
                    disabled={processing || !versionId || !isConvertible}
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

            {!isConvertible && versionId && (
                <p className="mb-4 text-xs text-[#78716c]">
                    La versión seleccionada no es DOCX ni PDF. Selecciona un documento Word o PDF para
                    analizar.
                </p>
            )}

            {loadingLatest && historial.length === 0 && (
                <div className="flex items-center gap-2 text-xs text-[#78716c]">
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    Cargando análisis de esta versión…
                </div>
            )}

            <RetroalimentacionIa analisis={historial} />

            {!loadingLatest && historial.length === 0 && !actionError && !aiUnavailable && (
                <p className="text-xs text-[#78716c]">
                    Ejecuta el análisis para obtener una orientación preliminar de esta versión. No sustituye
                    tu evaluación académica como director.
                </p>
            )}
        </div>
    );
}
