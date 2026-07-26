import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/utils';
import { AlertTriangle, Brain, CheckCircle2, Loader2 } from 'lucide-react';

interface CriterioEvaluado {
    id: string;
    nombre: string;
    cumplimiento: string;
    evidencias: string[];
    observaciones: string;
}

interface ResultadoAbet {
    resumen_ejecutivo: string;
    criterios_evaluados: CriterioEvaluado[];
    fortalezas: string[];
    oportunidades_mejora: string[];
    observaciones: string[];
    recomendaciones: string[];
    riesgos: string[];
    conclusion: string;
    perfil_metricas?: string;
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

function cumplimientoLabel(value: string): string {
    const map: Record<string, string> = {
        alto: 'Alto',
        medio: 'Medio',
        bajo: 'Bajo',
        no_evidencia: 'Sin evidencia',
    };
    return map[value] ?? value;
}

export function EvaluacionAbetPanel({ entregaId, versionId, versionLabel, isDocx }: Props) {
    const [processing, setProcessing] = useState(false);
    const [loadingLatest, setLoadingLatest] = useState(true);
    const [resultado, setResultado] = useState<ResultadoAbet | null>(null);
    const [perfil, setPerfil] = useState<string | null>(null);
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
                    setResultado(data.resultado as ResultadoAbet);
                    setPerfil(data.perfil_metricas ?? data.resultado.perfil_metricas ?? null);
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
            setActionError('Selecciona una versión DOCX para evaluar.');
            return;
        }
        if (!isDocx) {
            setActionError('Solo se pueden evaluar documentos en formato DOCX.');
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
                setActionError(payload?.error ?? 'No fue posible completar la evaluación ABET.');
                return;
            }

            const result = payload?.data?.resultado as ResultadoAbet | undefined;
            if (result) {
                setResultado(result);
                setPerfil(payload?.data?.perfil_metricas ?? result.perfil_metricas ?? null);
            }
        } catch {
            setActionError('No fue posible contactar al servicio de evaluación. Inténtalo de nuevo.');
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
                        <h3 className="text-base font-bold text-[#1c1917]">Evaluación Inteligente ABET</h3>
                        <p className="text-xs text-[#78716c]">
                            Usa únicamente el documento oficial seleccionado (sin carga manual)
                            {versionLabel ? ` · ${versionLabel}` : ''}
                            {perfil ? ` · ${perfil}` : ''}
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
                    {processing ? 'Evaluando…' : 'Ejecutar evaluación ABET'}
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
                    La versión seleccionada no es DOCX. Selecciona un documento Word para evaluar.
                </p>
            )}

            {loadingLatest && !resultado && (
                <div className="flex items-center gap-2 text-xs text-[#78716c]">
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    Cargando última evaluación…
                </div>
            )}

            {resultado && (
                <div className="flex flex-col gap-4">
                    {resultado.resumen_ejecutivo && (
                        <div>
                            <p className="mb-1 text-xs font-semibold text-[#1c1917]">Resumen ejecutivo</p>
                            <p className="text-sm text-[#44403c]">{resultado.resumen_ejecutivo}</p>
                        </div>
                    )}

                    {(resultado.criterios_evaluados ?? []).length > 0 && (
                        <div>
                            <p className="mb-2 text-xs font-semibold text-[#1c1917]">Criterios evaluados</p>
                            <div className="flex flex-col gap-2">
                                {resultado.criterios_evaluados.map((c) => (
                                    <div key={`${c.id}-${c.nombre}`} className="rounded-lg border border-[#e5e5e5] p-3">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-xs font-semibold text-[#1c1917]">
                                                {c.id ? `[${c.id}] ` : ''}
                                                {c.nombre}
                                            </p>
                                            <span className="inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-[#57534e]">
                                                <CheckCircle2 className="h-3 w-3 text-[#c2410c]" />
                                                {cumplimientoLabel(c.cumplimiento)}
                                            </span>
                                        </div>
                                        {c.observaciones && (
                                            <p className="mt-1 text-[11px] text-[#57534e]">{c.observaciones}</p>
                                        )}
                                        {(c.evidencias ?? []).length > 0 && (
                                            <ul className="mt-1 list-disc space-y-0.5 pl-4">
                                                {c.evidencias.map((ev, idx) => (
                                                    <li key={idx} className="text-[11px] text-[#78716c]">
                                                        {ev}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <ListBlock title="Fortalezas" items={resultado.fortalezas ?? []} />
                    <ListBlock title="Oportunidades de mejora" items={resultado.oportunidades_mejora ?? []} />
                    <ListBlock title="Observaciones" items={resultado.observaciones ?? []} />
                    <ListBlock title="Recomendaciones" items={resultado.recomendaciones ?? []} />
                    <ListBlock title="Riesgos" items={resultado.riesgos ?? []} />

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
                    Ejecuta la evaluación para obtener un análisis orientativo por criterios ABET
                    (perfil placeholder). No sustituye tu criterio como Director.
                </p>
            )}
        </div>
    );
}
