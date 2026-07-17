import { useEffect, useReducer, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { apiFetch } from '@/lib/utils';
import {
    ArrowLeft,
    Download,
    FileText,
    Loader2,
    Save,
    CheckCircle,
    XCircle,
} from 'lucide-react';

// ─── Types ────────────────────────────────────────────────────────────

interface Version {
    id: number;
    version_number: number;
    file_path: string;
    file_size: number | null;
    original_name: string;
    director_notes: string | null;
    uploaded_at: string;
}

interface ProyectoInfo {
    id: number;
    code: string;
    title: string;
    director_id: number;
    estudiantes: { id: number; name: string }[];
}

interface EntregaDetail {
    id: number;
    title: string;
    description: string | null;
    due_date: string | null;
    status: string;
    phase: string | null;
    consolidated_grade: number | null;
    evaluation_complete: boolean;
    proyecto: ProyectoInfo | null;
    proyectos: ProyectoInfo[];
    versiones: Version[];
}

interface DetailState {
    data: EntregaDetail | null;
    loading: boolean;
    error: string | null;
}

type DetailAction =
    | { type: 'FETCH_START' }
    | { type: 'FETCH_SUCCESS'; payload: EntregaDetail }
    | { type: 'FETCH_ERROR'; payload: string };

function detailReducer(state: DetailState, action: DetailAction): DetailState {
    switch (action.type) {
        case 'FETCH_START':
            return { ...state, loading: true, error: null };
        case 'FETCH_SUCCESS':
            return { data: action.payload, loading: false, error: null };
        case 'FETCH_ERROR':
            return { ...state, loading: false, error: action.payload };
        default:
            return state;
    }
}

const statusLabels: Record<string, string> = {
    creada: 'Creada',
    solicitada: 'Solicitada',
    pendiente: 'Pendiente',
    enviada: 'Enviada',
    revisada: 'Revisada',
    aprobada: 'Aprobada',
    rechazada: 'Rechazada',
};

const statusVariants: Record<string, 'success' | 'warning' | 'error' | 'info' | 'inactivo'> = {
    aprobada: 'success',
    enviada: 'info',
    pendiente: 'warning',
    rechazada: 'error',
    creada: 'inactivo',
    solicitada: 'warning',
    revisada: 'info',
};

const cardClass = 'rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]';

export default function DetalleEntregaDirector() {
    const navigate = useNavigate();
    const { proyectoId, id } = useParams<{ proyectoId: string; id: string }>();
    const [state, dispatch] = useReducer(detailReducer, {
        data: null,
        loading: true,
        error: null,
    });

    // Review form state
    const [gradeInput, setGradeInput] = useState('');
    const [reviewStatus, setReviewStatus] = useState<string>('aprobada');
    const [directorNotes, setDirectorNotes] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [submitSuccess, setSubmitSuccess] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);

    useEffect(() => {
        if (!id) return;

        let cancelled = false;

        async function fetchDetail() {
            dispatch({ type: 'FETCH_START' });

            try {
                const res = await apiFetch(`/api/admin/entregas/${id}`);

                if (!res.ok) {
                    const body = await res.json().catch(() => null);
                    throw new Error(body?.error ?? `Error ${res.status}`);
                }

                const json = await res.json();
                if (!cancelled) {
                    const data: EntregaDetail = json.data;
                    dispatch({ type: 'FETCH_SUCCESS', payload: data });
                    if (data.consolidated_grade !== null) {
                        setGradeInput(String(data.consolidated_grade));
                    }
                }
            } catch (err) {
                if (!cancelled) {
                    const message = err instanceof Error ? err.message : 'Error desconocido';
                    dispatch({ type: 'FETCH_ERROR', payload: message });
                }
            }
        }

        fetchDetail();
        return () => { cancelled = true; };
    }, [id]);

    async function handleSubmitReview() {
        if (!id) return;

        const parsedGrade = gradeInput ? parseFloat(gradeInput) : null;
        if (parsedGrade !== null && (Number.isNaN(parsedGrade) || parsedGrade < 0 || parsedGrade > 5)) {
            setSubmitError('La nota debe estar entre 0.0 y 5.0.');
            return;
        }

        setSubmitting(true);
        setSubmitError(null);

        try {
            const res = await apiFetch(`/api/admin/entregas/${id}/revisar`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    status: reviewStatus,
                    consolidated_grade: parsedGrade !== null ? parsedGrade * 20 : null, // Convert 0-5 scale to 0-100
                    director_notes: directorNotes || null,
                }),
            });

            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.error ?? `Error ${res.status}`);
            }

            setSubmitSuccess(true);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            setSubmitError(message);
        } finally {
            setSubmitting(false);
        }
    }

    // Build project info string
    const projectInfo = (() => {
        if (state.data?.proyecto) {
            const p = state.data.proyecto;
            return `${p.code} · ${p.estudiantes.map((e) => e.name).join(', ')}`;
        }
        return '';
    })();

    const backPath = proyectoId ? `/supervision/${proyectoId}` : '/supervision';

    if (state.loading) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Entrega" title="Cargando..." subtitle="" />
                <div className="flex items-center justify-center py-20">
                    <Loader2 className="h-6 w-6 animate-spin text-[#c2410c]" />
                </div>
            </div>
        );
    }

    if (state.error || !state.data) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Entrega"
                    title="Entrega no encontrada"
                    subtitle={state.error ?? 'La entrega solicitada no existe.'}
                    actions={
                        <button
                            type="button"
                            onClick={() => navigate(backPath)}
                            className="inline-flex min-h-[40px] items-center gap-2 self-start rounded-lg border border-[#e5e5e5] px-4 py-2 text-sm font-semibold"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver
                        </button>
                    }
                />
            </div>
        );
    }

    const delivery = state.data;
    const config = statusVariants[delivery.status] ?? 'info';
    const alreadyReviewed = delivery.evaluation_complete;

    if (submitSuccess) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Entrega"
                    title={delivery.title}
                    subtitle={projectInfo}
                    actions={
                        <button
                            type="button"
                            onClick={() => navigate(backPath)}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver
                        </button>
                    }
                />
                <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-[#dcfce7] bg-[#dcfce7] py-16 text-center">
                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white">
                        <CheckCircle className="h-8 w-8 text-[#16a34a]" />
                    </div>
                    <div>
                        <h3 className="text-lg font-bold text-[#14532d]">Revisión guardada</h3>
                        <p className="text-sm text-[#14532d] mt-1">
                            La entrega ha sido revisada y el resultado ha sido registrado.
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Entrega"
                title={delivery.title}
                subtitle={projectInfo}
                actions={
                    <button
                        type="button"
                        onClick={() => navigate(backPath)}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            {/* Info */}
            <div className={cardClass}>
                <div className="mb-4 flex items-center gap-2">
                    <FileText className="h-5 w-5 text-[#c2410c]" />
                    <h3 className="text-base font-bold text-[#1c1917]">Información de la entrega</h3>
                    <StatusBadge variant={config}>{statusLabels[delivery.status] ?? delivery.status}</StatusBadge>
                </div>
                <div className="flex flex-col gap-4">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">Título</p>
                        <p className="mt-1 text-sm font-semibold text-[#1c1917]">{delivery.title}</p>
                    </div>
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">Descripción</p>
                        <p className="mt-1 text-sm leading-relaxed text-[#57534e]">{delivery.description ?? 'Sin descripción.'}</p>
                    </div>
                    {delivery.due_date && (
                        <div>
                            <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">Fecha límite</p>
                            <p className="mt-1 text-sm font-semibold text-[#1c1917]">{delivery.due_date}</p>
                        </div>
                    )}
                </div>
            </div>

            {/* Review Form */}
            {!alreadyReviewed && (
                <div className={cardClass}>
                    <div className="mb-4 flex items-center gap-2">
                        <CheckCircle className="h-5 w-5 text-[#c2410c]" />
                        <h3 className="text-base font-bold text-[#1c1917]">Revisar Entrega</h3>
                    </div>

                    <div className="flex flex-col gap-5">
                        {/* Grade: 0.0–5.0 scale */}
                        <div>
                            <label htmlFor="grade-input" className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                Nota (0.0 – 5.0)
                            </label>
                            <input
                                id="grade-input"
                                type="number"
                                step="0.1"
                                min={0}
                                max={5}
                                value={gradeInput}
                                onChange={(e) => setGradeInput(e.target.value)}
                                placeholder="0.0"
                                className="w-28 min-h-[40px] rounded-lg border border-[#e5e5e5] px-3 py-2 text-lg font-bold tabular-nums outline-none focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            />
                        </div>

                        {/* Status selector */}
                        <div>
                            <label htmlFor="review-status" className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                Decisión
                            </label>
                            <div className="flex gap-3">
                                <button
                                    type="button"
                                    onClick={() => setReviewStatus('aprobada')}
                                    className={`flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold transition-colors ${
                                        reviewStatus === 'aprobada'
                                            ? 'border-[#16a34a] bg-[#dcfce7] text-[#15803d]'
                                            : 'border-[#e5e5e5] text-[#57534e] hover:bg-[#f5f5f4]'
                                    }`}
                                >
                                    <CheckCircle className="h-4 w-4" />
                                    Aprobada
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setReviewStatus('rechazada')}
                                    className={`flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold transition-colors ${
                                        reviewStatus === 'rechazada'
                                            ? 'border-[#dc2626] bg-[#fee2e2] text-[#b91c1c]'
                                            : 'border-[#e5e5e5] text-[#57534e] hover:bg-[#f5f5f4]'
                                    }`}
                                >
                                    <XCircle className="h-4 w-4" />
                                    Rechazada
                                </button>
                            </div>
                        </div>

                        {/* Director notes */}
                        <div>
                            <label htmlFor="director-notes" className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                Notas del director
                            </label>
                            <textarea
                                id="director-notes"
                                rows={4}
                                value={directorNotes}
                                onChange={(e) => setDirectorNotes(e.target.value)}
                                placeholder="Escriba sus observaciones sobre esta entrega..."
                                className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            />
                        </div>

                        {submitError && (
                            <div className="rounded-lg border border-[#fee2e2] bg-[#fef2f2] px-4 py-3 text-sm text-[#dc2626]">
                                {submitError}
                            </div>
                        )}

                        <button
                            type="button"
                            onClick={handleSubmitReview}
                            disabled={submitting}
                            className="inline-flex min-h-[40px] w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {submitting ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Save className="h-4 w-4" />
                            )}
                            {submitting ? 'Guardando...' : 'Guardar Revisión'}
                        </button>
                    </div>
                </div>
            )}

            {/* Already reviewed info */}
            {alreadyReviewed && (
                <div className={cardClass}>
                    <div className="mb-4 flex items-center gap-2">
                        <CheckCircle className="h-5 w-5 text-[#16a34a]" />
                        <h3 className="text-base font-bold text-[#1c1917]">Revisión Completada</h3>
                    </div>
                    <div className="flex items-center gap-4">
                        <StatusBadge variant={config}>
                            {statusLabels[delivery.status] ?? delivery.status}
                        </StatusBadge>
                        {delivery.consolidated_grade !== null && (
                            <p className="text-2xl font-bold tabular-nums text-[#1c1917]">
                                {(delivery.consolidated_grade / 20).toFixed(1)}
                                <span className="text-lg text-[#78716c]">/5.0</span>
                            </p>
                        )}
                    </div>
                </div>
            )}

            {/* Versions */}
            <div className={cardClass}>
                <div className="mb-4 flex items-center gap-2">
                    <FileText className="h-5 w-5 text-[#c2410c]" />
                    <h3 className="text-base font-bold text-[#1c1917]">Versiones entregadas</h3>
                </div>

                {delivery.versiones.length > 0 ? (
                    <div className="flex flex-col gap-4">
                        {delivery.versiones.map((v) => (
                            <div key={v.id} className="rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-4">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="flex flex-col gap-1 min-w-0">
                                        <span className="text-sm font-semibold text-[#1c1917]">
                                            Versión {v.version_number}
                                        </span>
                                        <span className="text-xs text-[#57534e]">
                                            {new Date(v.uploaded_at).toLocaleDateString('es-CO', {
                                                day: '2-digit',
                                                month: '2-digit',
                                                year: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </span>
                                        <span className="truncate text-xs text-[#78716c]">{v.original_name}</span>
                                    </div>
                                    <a
                                        href={`/storage/${v.file_path}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex min-h-[36px] shrink-0 items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] hover:bg-[#f5f5f4]"
                                    >
                                        <Download className="h-3.5 w-3.5" />
                                        Descargar
                                    </a>
                                </div>

                                {v.director_notes && (
                                    <div className="mt-3 border-t border-[#e5e5e5] pt-3">
                                        <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e] mb-1">
                                            Comentario del director
                                        </p>
                                        <p className="text-sm text-[#57534e]">{v.director_notes}</p>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-[#78716c]">El estudiante aún no ha enviado versiones para esta entrega.</p>
                )}
            </div>
        </div>
    );
}
