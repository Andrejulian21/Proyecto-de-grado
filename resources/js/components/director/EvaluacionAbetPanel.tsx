import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/utils';
import { formatFechaHora } from '@/lib/fechas';
import { RetroalimentacionIa } from '@/components/entregas/RetroalimentacionIa';
import type { AnalisisIa, ResultadoAnalisisPreliminar } from '@/types/entregas';
import { Brain, Loader2 } from 'lucide-react';

interface Props {
    entregaId: number;
    versionId: number | null;
    proyectoId?: number | null;
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
        truncado: Boolean(payload.truncado),
        aviso_truncado: (payload.aviso_truncado as string | null) ?? null,
    };
}

/** Only student-requested analyses are shown to the director. */
function soloAnalisisEstudiante(items: AnalisisIa[]): AnalisisIa[] {
    return items.filter((item) => item.tipo === undefined || item.tipo === 'pre_submission');
}

export function EvaluacionAbetPanel({
    entregaId,
    versionId,
    proyectoId = null,
    versionLabel,
    isConvertible,
    analisisInicial = [],
}: Props) {
    const [loadingLatest, setLoadingLatest] = useState(true);
    const [historial, setHistorial] = useState<AnalisisIa[]>(() =>
        soloAnalisisEstudiante(analisisInicial),
    );
    const [loadError, setLoadError] = useState<string | null>(null);

    useEffect(() => {
        setHistorial(soloAnalisisEstudiante(analisisInicial));
        setLoadError(null);
        // Reset when the selected version changes; do not depend on array identity.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [versionId, proyectoId]);

    useEffect(() => {
        let cancelled = false;

        async function loadLatest() {
            if (!versionId) {
                setLoadingLatest(false);
                return;
            }

            setLoadingLatest(true);
            setLoadError(null);
            try {
                const params = new URLSearchParams({ version_id: String(versionId) });
                if (proyectoId !== null) {
                    params.set('proyecto_id', String(proyectoId));
                }
                const res = await apiFetch(
                    `/api/director/entregas/${entregaId}/evaluacion-abet?${params.toString()}`,
                );
                const payload = await res.json().catch(() => ({}));
                if (!res.ok || cancelled) {
                    if (!res.ok && !cancelled) {
                        setLoadError(
                            typeof payload?.error === 'string'
                                ? payload.error
                                : 'No se pudo cargar el análisis de IA.',
                        );
                    }
                    return;
                }
                const items = Array.isArray(payload?.historial)
                    ? (payload.historial as Record<string, unknown>[])
                        .map((row) => toAnalisis(row))
                        .filter((row): row is AnalisisIa => row !== null)
                    : [];
                const latest = toAnalisis(payload?.data);
                const estudiante = soloAnalisisEstudiante(
                    items.length > 0 ? items : latest ? [latest] : [],
                );
                setHistorial(estudiante);
            } catch {
                if (!cancelled) {
                    setLoadError('No se pudo cargar el análisis de IA. Inténtalo de nuevo.');
                }
            } finally {
                if (!cancelled) setLoadingLatest(false);
            }
        }

        void loadLatest();
        return () => {
            cancelled = true;
        };
    }, [entregaId, versionId]);

    return (
        <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <div className="mb-4 flex items-center gap-2">
                <Brain className="h-5 w-5 text-[#c2410c]" />
                <div>
                    <h3 className="text-base font-bold text-[#1c1917]">
                        Observaciones de la IA
                    </h3>
                    <p className="text-xs text-[#78716c]">
                        Análisis preliminar solicitado por el estudiante
                        {versionLabel ? ` · ${versionLabel}` : ''}. Es orientación
                        informativa, no una calificación académica.
                    </p>
                </div>
            </div>

            {loadError && (
                <div
                    className="mb-4 rounded-lg border border-[#fecaca] bg-[#fef2f2] px-4 py-3 text-sm text-[#991b1b]"
                    role="alert"
                >
                    {loadError}
                </div>
            )}

            {!isConvertible && versionId && (
                <p className="mb-4 text-xs text-[#78716c]">
                    La versión seleccionada no es DOCX ni PDF. El análisis de IA solo está
                    disponible para documentos Word o PDF.
                </p>
            )}

            {loadingLatest && historial.length === 0 && (
                <div className="flex items-center gap-2 text-xs text-[#78716c]">
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    Cargando análisis de esta versión…
                </div>
            )}

            <RetroalimentacionIa analisis={historial} />

            {historial[0]?.temporal && historial[0]?.analizado_en && (
                <p className="mt-3 text-xs text-[#78716c]">
                    Análisis sobre borrador, analizado el {formatFechaHora(historial[0].analizado_en)}.
                </p>
            )}

            {historial[0]?.aviso_truncado && (
                <p className="mt-3 text-xs text-[#78716c]">{historial[0].aviso_truncado}</p>
            )}

            {!loadingLatest && historial.length === 0 && !loadError && (
                <p className="text-xs text-[#78716c]">
                    El estudiante aún no ha solicitado un análisis de IA para esta versión.
                    Cuando lo pida desde su panel, el resultado aparecerá aquí.
                </p>
            )}
        </div>
    );
}
