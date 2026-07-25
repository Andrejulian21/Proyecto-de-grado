import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { apiFetch } from '@/lib/utils';
import { datoNoEncontrado } from '@/lib/datoNoEncontrado';
import {
    DEFAULT_EVALUADOR_RUBRIC,
    extractComment,
    hasStoredGrades,
    hydrateCriteriaFromSaved,
    type EvaluacionCriterion,
    type SavedEvaluacionRow,
} from '@/lib/evaluacionCriteria';
import type { EvaluacionAsignadaEvaluador } from '@/hooks/useEvaluadorEvaluaciones';
import { ArrowLeft, Eye, Download, FileText, Send, Loader2, AlertCircle } from 'lucide-react';

interface EntregaInfo {
    id: number;
    title: string;
    phase: string;
    status: string;
    versiones: Array<{
        id: number;
        version_number: number;
        original_name: string;
        file_path: string;
        uploaded_at: string;
    }>;
}

function buildSubtitle(proyecto: EvaluacionAsignadaEvaluador | null): string {
    if (!proyecto) return datoNoEncontrado('El proyecto');
    const code = proyecto.code || datoNoEncontrado('El código del proyecto');
    const title = proyecto.title || datoNoEncontrado('El título del proyecto');
    const student =
        proyecto.estudiantes?.map((e) => e.name).filter(Boolean).join(', ') ||
        datoNoEncontrado('El nombre del estudiante');
    return `${code} — ${title} — ${student}`;
}

function formatAssignedDate(proyecto: EvaluacionAsignadaEvaluador | null): string {
    const raw = proyecto?.assigned_at ?? proyecto?.fecha;
    if (!raw) return datoNoEncontrado('La fecha de asignación');
    const d = new Date(raw.includes('T') ? raw : `${raw}T00:00:00`);
    if (Number.isNaN(d.getTime())) return datoNoEncontrado('La fecha de asignación');
    return d.toLocaleDateString('es-CO');
}

export default function EvaluarProyecto() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const [proyecto, setProyecto] = useState<EvaluacionAsignadaEvaluador | null>(null);
    const [entrega, setEntrega] = useState<EntregaInfo | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [rubric, setRubric] = useState<EvaluacionCriterion[]>(DEFAULT_EVALUADOR_RUBRIC);
    const [globalComment, setGlobalComment] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [readOnly, setReadOnly] = useState(false);

    const totalScore = rubric.reduce((s, r) => s + r.score, 0);
    const totalMax = rubric.reduce((s, r) => s + r.maxScore, 0);
    const latestVersion = entrega?.versiones?.[0] ?? null;

    useEffect(() => {
        let cancelled = false;

        async function load() {
            if (!id) {
                setError(datoNoEncontrado('El proyecto'));
                setLoading(false);
                return;
            }

            setLoading(true);
            setError(null);

            try {
                const resList = await apiFetch('/api/evaluador/evaluaciones');
                if (!resList.ok) {
                    const body = await resList.json().catch(() => null);
                    throw new Error(body?.error ?? `Error ${resList.status}`);
                }
                const jsonList = await resList.json();
                const items: EvaluacionAsignadaEvaluador[] = jsonList.data ?? jsonList;
                const found = items.find((p) => String(p.id) === String(id)) ?? null;

                if (!found) {
                    if (!cancelled) {
                        setError(datoNoEncontrado('El proyecto'));
                        setLoading(false);
                    }
                    return;
                }

                if (!cancelled) {
                    setProyecto(found);
                    setReadOnly(found.evaluation_status === 'evaluated');
                }

                const fase = found.fase_asignada || found.current_phase || 'Anteproyecto';
                const resEntrega = await apiFetch(
                    `/api/evaluador/proyectos/${found.id}/entrega-fase?fase=${encodeURIComponent(fase)}`,
                );

                if (resEntrega.status === 404) {
                    if (!cancelled) {
                        setEntrega(null);
                        setLoading(false);
                    }
                    return;
                }

                if (!resEntrega.ok) {
                    const body = await resEntrega.json().catch(() => null);
                    throw new Error(body?.error ?? `Error ${resEntrega.status}`);
                }

                const jsonEntrega = await resEntrega.json();
                const entregaData: EntregaInfo = jsonEntrega.data ?? jsonEntrega;
                if (!cancelled) setEntrega(entregaData);

                const resGrades = await apiFetch(`/api/evaluaciones?entrega_id=${entregaData.id}`);
                if (resGrades.ok) {
                    const jsonGrades = await resGrades.json();
                    const saved: SavedEvaluacionRow[] = jsonGrades.data ?? [];
                    if (saved.length > 0 && !cancelled) {
                        setRubric(hydrateCriteriaFromSaved(saved, DEFAULT_EVALUADOR_RUBRIC));
                        setGlobalComment(extractComment(saved));
                        if (hasStoredGrades(saved)) setReadOnly(true);
                    }
                }

                if (!cancelled) setLoading(false);
            } catch (err) {
                if (!cancelled) {
                    setError(err instanceof Error ? err.message : 'Error al cargar la evaluación');
                    setLoading(false);
                }
            }
        }

        load();
        return () => {
            cancelled = true;
        };
    }, [id]);

    function handleScoreChange(itemId: string, value: number) {
        if (readOnly) return;
        setRubric((prev) =>
            prev.map((r) =>
                r.id === itemId ? { ...r, score: Math.min(Math.max(0, value), r.maxScore) } : r,
            ),
        );
    }

    async function handleSubmit() {
        if (totalScore === 0 || !entrega || readOnly) return;
        setSubmitting(true);
        setSubmitError(null);

        try {
            for (const item of rubric) {
                const res = await apiFetch('/api/evaluaciones', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        entrega_id: entrega.id,
                        criterio: item.name,
                        percentage: item.percentage,
                        grade: item.score,
                        comment: globalComment || null,
                    }),
                });
                if (!res.ok) {
                    const body = await res.json().catch(() => null);
                    throw new Error(
                        body?.errors?.percentage?.[0] ??
                            body?.errors?.grade?.[0] ??
                            body?.error ??
                            `Error ${res.status}`,
                    );
                }
            }
            setSubmitted(true);
        } catch (err) {
            setSubmitError(err instanceof Error ? err.message : 'Error al enviar la evaluación');
        } finally {
            setSubmitting(false);
        }
    }

    if (loading) {
        return (
            <div className="flex flex-col items-center justify-center gap-3 py-20">
                <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" aria-label="Cargando" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Evaluación"
                    title="Evaluar Proyecto"
                    actions={
                        <button
                            type="button"
                            onClick={() => navigate('/dashboard/evaluador-externo')}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver
                        </button>
                    }
                />
                <div className="flex flex-col items-center gap-3 rounded-xl border border-[#fee2e2] bg-[#fee2e2] py-16 text-center">
                    <AlertCircle className="h-10 w-10 text-[#dc2626]" />
                    <p className="text-sm font-semibold text-[#7f1d1d]">{error}</p>
                </div>
            </div>
        );
    }

    if (submitted) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Evaluación"
                    title="Evaluación Enviada"
                    subtitle="La evaluación del proyecto ha sido registrada exitosamente"
                    actions={
                        <button
                            type="button"
                            onClick={() => navigate('/dashboard/evaluador-externo')}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver al inicio
                        </button>
                    }
                />
                <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-[#dcfce7] bg-[#dcfce7] py-16 text-center">
                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white">
                        <svg viewBox="0 0 24 24" className="h-8 w-8 text-[#16a34a]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div>
                        <h3 className="text-lg font-bold text-[#14532d]">Evaluación registrada</h3>
                        <p className="text-sm text-[#14532d] mt-1">
                            Calificación: <span className="font-bold tabular-nums">{totalScore}/{totalMax}</span>
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Evaluación"
                title="Evaluar Proyecto"
                subtitle={buildSubtitle(proyecto)}
                actions={
                    <button
                        type="button"
                        onClick={() => navigate('/dashboard/evaluador-externo')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            <div className="rounded-lg border border-[#e5e5e5] bg-white px-4 py-3 text-sm text-[#57534e]">
                <p>Director: {proyecto?.director?.name || datoNoEncontrado('El director')}</p>
                <p>Modalidad: {datoNoEncontrado('La modalidad')}</p>
                <p>Fecha de asignación: {formatAssignedDate(proyecto)}</p>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                <div className="lg:col-span-3">
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-4 flex items-center justify-between gap-3 flex-wrap">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Documento del Proyecto</h3>
                                <StatusBadge variant={readOnly ? 'success' : 'warning'}>
                                    {readOnly ? 'Evaluado' : 'Por evaluar'}
                                </StatusBadge>
                            </div>
                            {latestVersion?.file_path ? (
                                <a
                                    href={`/storage/${latestVersion.file_path}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                                >
                                    <Download className="h-3.5 w-3.5" />
                                    Descargar
                                </a>
                            ) : null}
                        </div>
                        <div className="flex aspect-[8.5/11] w-full items-center justify-center rounded-lg border border-[#e5e5e5] bg-[#fafaf9]">
                            <div className="flex flex-col items-center gap-3 text-center px-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4]">
                                    <Eye className="h-8 w-8 text-[#78716c]" />
                                </div>
                                {latestVersion ? (
                                    <>
                                        <p className="text-sm font-medium text-[#1c1917]">
                                            {latestVersion.original_name || datoNoEncontrado('El documento')}
                                        </p>
                                        {latestVersion.file_path ? (
                                            <a
                                                href={`/storage/${latestVersion.file_path}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                                            >
                                                <Eye className="h-4 w-4" />
                                                Abrir documento
                                            </a>
                                        ) : (
                                            <p className="text-sm text-[#57534e]">{datoNoEncontrado('El documento')}</p>
                                        )}
                                    </>
                                ) : (
                                    <p className="text-sm text-[#57534e]">{datoNoEncontrado('El documento')}</p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <div className="lg:col-span-2">
                    <div className="sticky top-20 flex flex-col gap-4">
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-semibold text-[#57534e]">Calificación Total</span>
                                <span className="text-2xl font-bold text-[#1c1917] tabular-nums">
                                    {totalScore} <span className="text-sm font-normal text-[#78716c]">/ {totalMax}</span>
                                </span>
                            </div>
                        </div>

                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <h3 className="mb-4 text-sm font-bold text-[#1c1917]">Rúbrica de Evaluación</h3>
                            <div className="space-y-5">
                                {rubric.map((item) => (
                                    <div key={item.id}>
                                        <div className="flex items-center justify-between gap-2 mb-1">
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-semibold text-[#1c1917]">{item.name}</p>
                                                <p className="text-xs text-[#57534e]">{item.description}</p>
                                            </div>
                                            <span className="text-xs text-[#78716c] tabular-nums shrink-0">
                                                {item.score}/{item.maxScore}
                                            </span>
                                        </div>
                                        <input
                                            type="range"
                                            min={0}
                                            max={item.maxScore}
                                            step={0.1}
                                            value={item.score}
                                            disabled={readOnly}
                                            onChange={(e) => handleScoreChange(item.id, Number(e.target.value))}
                                            className="w-full accent-[#c2410c] disabled:opacity-60"
                                            aria-label={`Puntaje para ${item.name}`}
                                        />
                                        <div className="flex items-center gap-2 mt-1">
                                            <input
                                                type="number"
                                                min={0}
                                                max={item.maxScore}
                                                step={0.1}
                                                value={item.score}
                                                disabled={readOnly}
                                                onChange={(e) => handleScoreChange(item.id, Number(e.target.value))}
                                                className="w-16 min-h-[32px] rounded-lg border border-[#e5e5e5] bg-white px-2 py-1 text-xs font-semibold text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] tabular-nums disabled:opacity-60"
                                            />
                                            <span className="text-xs text-[#78716c]">/ {item.maxScore}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <p className="mb-3 text-sm font-semibold text-[#57534e]">Comentarios Generales</p>
                            <textarea
                                rows={4}
                                value={globalComment}
                                disabled={readOnly}
                                onChange={(e) => setGlobalComment(e.target.value)}
                                placeholder="Escriba sus observaciones sobre el proyecto..."
                                className="w-full min-h-[80px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y disabled:opacity-60"
                            />
                        </div>

                        {submitError && (
                            <p className="text-sm text-[#dc2626]" role="alert">{submitError}</p>
                        )}

                        {!readOnly && (
                            <button
                                type="button"
                                onClick={handleSubmit}
                                disabled={totalScore === 0 || submitting || !entrega}
                                className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {submitting ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Send className="h-4 w-4" />
                                )}
                                Enviar Evaluación
                            </button>
                        )}
                        {!entrega && (
                            <p className="text-xs text-center text-[#57534e]">
                                {datoNoEncontrado('El documento')} No es posible enviar sin entrega aprobada.
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
