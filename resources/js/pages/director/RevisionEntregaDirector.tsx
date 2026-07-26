import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import {
    ArrowLeft, Download, FileText, Calendar, Loader2,
    AlertTriangle, MessageSquareText, Star, Clock,
    CheckCircle2, XCircle, Send,
} from 'lucide-react';
import { apiFetch } from '@/lib/utils';
import { EvaluacionAbetPanel } from '@/components/director/EvaluacionAbetPanel';

/* ── Types ── */

interface Version {
    id: number;
    version_number: number;
    file_path: string;
    file_size: number | null;
    original_name: string;
    director_notes: string | null;
    uploaded_at: string;
    created_at: string;
}

interface EntregaDetail {
    id: number;
    title: string;
    phase: string;
    status: string;
    description: string | null;
    due_date: string | null;
    start_date: string | null;
    start_time: string | null;
    hora_maxima: string | null;
    acceptance_criteria: string | null;
    consolidated_grade: string | number | null;
    evaluation_complete: boolean;
    proyecto?: { id: number; code: string; title: string };
    proyectos?: { id: number; code: string; title: string }[];
    versiones: Version[];
}

/* ── Helpers ── */

const STATUS_MAP: Record<string, { label: string; variant: 'success' | 'warning' | 'error' | 'info' | 'inactivo' }> = {
    aprobada: { label: 'Aprobada', variant: 'success' },
    aprobado: { label: 'Aprobada', variant: 'success' },
    rechazada: { label: 'Necesita ajustes', variant: 'warning' },
    rechazado: { label: 'Necesita ajustes', variant: 'warning' },
    revisada: { label: 'Necesita ajustes', variant: 'warning' },
    enviada: { label: 'Sin revisar', variant: 'warning' },
    pendiente: { label: 'Sin revisar', variant: 'warning' },
    solicitada: { label: 'Sin entregar', variant: 'inactivo' },
    creacion: { label: 'Sin entregar', variant: 'inactivo' },
};

function statusConfig(status: string) {
    return STATUS_MAP[status] ?? { label: status, variant: 'inactivo' as const };
}

function formatDate(dateStr: string | null | undefined): string {
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

function formatDateShort(dateStr: string | null | undefined): string {
    if (!dateStr) return '—';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('es-CO', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return dateStr;
    }
}

function formatFileSize(bytes: number | null): string {
    if (bytes === null || bytes === undefined) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function getDownloadUrl(filePath: string): string {
    return `/storage/${filePath}`;
}

function getReviewStatus(
    version: Version,
    entregaStatus: string,
): { label: string; variant: 'success' | 'warning' | 'info' } {
    const hasNotes = version.director_notes && version.director_notes.trim().length > 0;
    if (!hasNotes) {
        return { label: 'Sin revisar', variant: 'warning' };
    }
    if (entregaStatus === 'aprobada' || entregaStatus === 'aprobado') {
        return { label: 'Aprobada', variant: 'success' };
    }
    return { label: 'Necesita ajustes', variant: 'warning' };
}

const phaseLabels: Record<string, string> = {
    anteproyecto: 'Anteproyecto',
    presentacion_anteproyecto: 'Presentación Anteproyecto',
    desarrollo: 'Desarrollo del proyecto',
    presentacion_final: 'Presentación Final',
};

/* ── Component ── */

export default function RevisionEntregaDirector() {
    const { id: entregaId } = useParams<{ id: string }>();
    const navigate = useNavigate();

    const [entrega, setEntrega] = useState<EntregaDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [selectedVersionIdx, setSelectedVersionIdx] = useState(0);

    /* ── Review form state ── */
    const [directorNotes, setDirectorNotes] = useState('');
    const [decision, setDecision] = useState<'aprobada' | 'revisada' | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);

    /* ── Fetch entrega ── */
    useEffect(() => {
        if (!entregaId) return;
        let cancelled = false;

        (async () => {
            setLoading(true);
            setError(null);
            try {
                const res = await apiFetch(`/api/admin/entregas/${entregaId}`);
                if (cancelled) return;
                if (!res.ok) {
                    const body = await res.json().catch(() => null);
                    throw new Error(body?.message ?? `Error ${res.status}`);
                }
                const json = await res.json();
                setEntrega(json.data ?? json);
            } catch (err) {
                if (!cancelled) {
                    setError(err instanceof Error ? err.message : 'Error al cargar la entrega');
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        })();

        return () => { cancelled = true; };
    }, [entregaId]);

    /* ── Reset version index on data change ── */
    useEffect(() => {
        setSelectedVersionIdx(0);
    }, [entrega?.id]);

    const sortedVersions = [...(entrega?.versiones ?? [])].sort(
        (a, b) => b.version_number - a.version_number,
    );
    const safeVersionIdx = Math.min(selectedVersionIdx, Math.max(0, sortedVersions.length - 1));
    const selectedVersion: Version | null = sortedVersions[safeVersionIdx] ?? null;

    /* ── Sync editable notes with the selected version ── */
    useEffect(() => {
        setDirectorNotes(selectedVersion?.director_notes ?? '');
    }, [selectedVersion?.id]);

    /* ── Submit review ── */
    async function handleSubmitReview() {
        if (!entregaId || !decision) return;

        setSubmitting(true);
        setSubmitError(null);

        try {
            const res = await apiFetch(`/api/admin/entregas/${entregaId}/revisar`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    status: decision,
                    consolidated_grade: null,
                    director_notes: directorNotes || null,
                    version_id: selectedVersion?.id ?? null,
                }),
            });

            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.error ?? `Error ${res.status}`);
            }

            setSubmitted(true);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            setSubmitError(message);
        } finally {
            setSubmitting(false);
        }
    }

    /* ── Derived data ── */
    const mainProyecto = entrega?.proyecto ?? entrega?.proyectos?.[0] ?? null;
    const proyectoId = mainProyecto?.id;
    const projectCode = mainProyecto?.code ?? '';
    const projectTitle = mainProyecto?.title ?? '';
    const backPath = proyectoId ? `/supervision/${proyectoId}` : '/supervision';

    /* ══════════════════════════════════════════════════
       Loading state
       ══════════════════════════════════════════════════ */
    if (loading) {
        return (
            <div className="flex items-center justify-center py-20" role="status" aria-label="Cargando entrega">
                <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
            </div>
        );
    }

    /* ══════════════════════════════════════════════════
       Error state
       ══════════════════════════════════════════════════ */
    if (error || !entrega) {
        return (
            <div className="flex flex-col items-center gap-4 py-20">
                <AlertTriangle className="h-10 w-10 text-[#dc2626]" />
                <p className="text-sm text-[#dc2626]">{error ?? 'No se encontró la entrega.'}</p>
                <button
                    onClick={() => navigate('/supervision')}
                    className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Volver
                </button>
            </div>
        );
    }

    const statusCfg = statusConfig(entrega.status);

    /* ══════════════════════════════════════════════════
       Submitted (success screen)
       ══════════════════════════════════════════════════ */
    if (submitted) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Revisión"
                    title={entrega.title}
                    subtitle={
                        projectCode
                            ? `${projectCode} · ${phaseLabels[entrega.phase] ?? entrega.phase}`
                            : phaseLabels[entrega.phase] ?? entrega.phase
                    }
                    actions={
                        <button
                            onClick={() => navigate(backPath)}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver
                        </button>
                    }
                />
                <div className="flex flex-col items-center gap-4 rounded-xl border border-[#dcfce7] bg-[#dcfce7] py-16 text-center">
                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white">
                        <CheckCircle2 className="h-8 w-8 text-[#16a34a]" />
                    </div>
                    <div>
                        <h3 className="text-lg font-bold text-[#1c1917]">Revisión guardada</h3>
                        <p className="mt-1 text-sm text-[#57534e]">
                            {decision === 'aprobada'
                                ? 'La entrega ha sido aprobada exitosamente.'
                                : 'Se han registrado las observaciones. El estudiante deberá realizar los ajustes correspondientes.'}
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    /* ══════════════════════════════════════════════════
       Main content
       ══════════════════════════════════════════════════ */
    return (
        <div className="flex flex-col gap-6">
            {/* ── Header ── */}
            <PageHeader
                eyebrow="Revisión"
                title={entrega.title}
                subtitle={
                    projectCode
                        ? `${projectCode} · ${phaseLabels[entrega.phase] ?? entrega.phase}`
                        : phaseLabels[entrega.phase] ?? entrega.phase
                }
                actions={
                    <button
                        onClick={() => navigate(backPath)}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            {/* ── Full-width layout ── */}
            <div className="flex flex-col gap-6">

                {/* ── A. Metadata cards ── */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    {/* Fecha de inicio */}
                    {(entrega.start_date || entrega.start_time) && (
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <p className="text-xs text-[#78716c]">Fecha de inicio</p>
                            <p className="mt-1 text-sm font-semibold text-[#1c1917]">
                                {entrega.start_date ? formatDateShort(entrega.start_date) : '—'}
                                {entrega.start_time && (
                                    <span className="ml-1 font-normal text-[#57534e]">
                                        · {entrega.start_time}
                                    </span>
                                )}
                            </p>
                        </div>
                    )}

                    {/* Fecha límite */}
                    {entrega.due_date && (
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <p className="text-xs text-[#78716c]">Fecha límite</p>
                            <p className="mt-1 text-sm font-semibold text-[#1c1917]">
                                {formatDateShort(entrega.due_date)}
                                {entrega.hora_maxima && (
                                    <span className="ml-1 font-normal text-[#57534e]">
                                        · {entrega.hora_maxima}
                                    </span>
                                )}
                            </p>
                        </div>
                    )}

                    {/* Estado */}
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <p className="text-xs text-[#78716c]">Estado</p>
                        <div className="mt-1.5">
                            <StatusBadge variant={statusCfg.variant}>{statusCfg.label}</StatusBadge>
                        </div>
                    </div>

                    {/* Proyecto */}
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <p className="text-xs text-[#78716c]">Proyecto</p>
                        <p className="mt-1 text-sm font-semibold text-[#1c1917] truncate" title={projectTitle}>
                            {projectTitle || projectCode}
                        </p>
                    </div>
                </div>

                {/* ── B. Descripción ── */}
                {entrega.description && (
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <h3 className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                            Descripción
                        </h3>
                        <p className="text-sm leading-relaxed text-[#1c1917] whitespace-pre-wrap">
                            {entrega.description}
                        </p>
                    </div>
                )}

                {/* ── C. Criterios de aceptación ── */}
                {entrega.acceptance_criteria && (
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <h3 className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                            Criterios de Aceptación
                        </h3>
                        <p className="text-sm leading-relaxed text-[#1c1917] whitespace-pre-wrap">
                            {entrega.acceptance_criteria}
                        </p>
                    </div>
                )}

                {/* ── D. Documento / Versiones ── */}
                {sortedVersions.length > 0 && selectedVersion ? (
                    <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        {/* Header + version selector */}
                        <div className="flex items-center justify-between border-b border-[#e5e5e5] px-6 py-4">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Documento</h3>
                            </div>

                            {sortedVersions.length <= 4 ? (
                                <div className="flex items-center gap-1">
                                    {sortedVersions.map((v, idx) => (
                                        <button
                                            key={v.id}
                                            onClick={() => setSelectedVersionIdx(idx)}
                                            className={`inline-flex min-h-[32px] items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                                                safeVersionIdx === idx
                                                    ? 'bg-[#c2410c] text-white shadow-sm'
                                                    : 'bg-[#f5f5f4] text-[#57534e] hover:bg-[#e7e5e4]'
                                            }`}
                                        >
                                            v{v.version_number}
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <select
                                    value={safeVersionIdx}
                                    onChange={(e) => setSelectedVersionIdx(Number(e.target.value))}
                                    className="rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] focus:border-[#c2410c] focus:outline-none focus:ring-1 focus:ring-[#c2410c]"
                                >
                                    {sortedVersions.map((v, idx) => (
                                        <option key={v.id} value={idx}>
                                            Versión {v.version_number}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </div>

                        {/* Body: left (file info) + right (observations) */}
                        <div className="p-6">
                            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[280px_1fr]">
                                {/* Izquierda: info del archivo */}
                                <div className="flex flex-col items-center justify-center gap-4 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] py-16">
                                    <FileText className="h-16 w-16 text-[#d6d3d1]" />
                                    <div className="text-center">
                                        <p className="text-sm font-semibold text-[#1c1917]">
                                            {selectedVersion.original_name || `documento_v${selectedVersion.version_number}.pdf`}
                                        </p>
                                        <p className="mt-1 flex items-center justify-center gap-1 text-xs text-[#78716c]">
                                            <Calendar className="h-3 w-3" />
                                            {formatDate(selectedVersion.uploaded_at || selectedVersion.created_at)}
                                        </p>
                                    </div>
                                    <a
                                        href={getDownloadUrl(selectedVersion.file_path)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a]"
                                    >
                                        <Download className="h-4 w-4" />
                                        Abrir documento
                                    </a>
                                </div>

                                {/* Derecha: observaciones de la versión seleccionada */}
                                <div className="rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-4">
                                    <div className="mb-3 flex items-center justify-between gap-2">
                                        <span className="text-sm font-bold text-[#1c1917]">
                                            Versión {selectedVersion.version_number}
                                        </span>
                                        <StatusBadge variant={getReviewStatus(selectedVersion, entrega.status).variant}>
                                            {getReviewStatus(selectedVersion, entrega.status).label}
                                        </StatusBadge>
                                    </div>
                                    <div className="mb-3 space-y-1">
                                        <p className="flex items-center gap-1 text-xs text-[#78716c]">
                                            <Calendar className="h-3 w-3" />
                                            {formatDate(selectedVersion.uploaded_at || selectedVersion.created_at)}
                                        </p>
                                    </div>
                                    {selectedVersion.director_notes ? (
                                        <div className="rounded-md bg-white p-3">
                                            <p className="whitespace-pre-wrap text-xs leading-relaxed text-[#1c1917]">
                                                {selectedVersion.director_notes}
                                            </p>
                                        </div>
                                    ) : (
                                        <p className="text-xs text-[#a8a29e] italic">
                                            Sin observaciones del director.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                ) : (
                    /* Sin versiones */
                    <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="border-b border-[#e5e5e5] px-6 py-4">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Documento</h3>
                            </div>
                        </div>
                        <div className="flex flex-col items-center gap-3 py-12 text-center text-sm text-[#a8a29e]">
                            <FileText className="h-10 w-10 text-[#d6d3d1]" />
                            <p>El estudiante aún no ha subido versiones para esta entrega.</p>
                        </div>
                    </div>
                )}

                {/* ── E. Evaluación Inteligente ABET ── */}
                {selectedVersion && (
                    <EvaluacionAbetPanel
                        entregaId={entrega.id}
                        versionId={selectedVersion.id}
                        versionLabel={`Versión ${selectedVersion.version_number}`}
                        isDocx={(selectedVersion.original_name || selectedVersion.file_path || '')
                            .toLowerCase()
                            .endsWith('.docx')}
                    />
                )}

                {/* ── F. Panel de Revisión (DEBAJO de la card de Documento) ── */}
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <div className="mb-6 flex items-center gap-2">
                        <MessageSquareText className="h-5 w-5 text-[#c2410c]" />
                        <h3 className="text-base font-bold text-[#1c1917]">Panel de Revisión</h3>
                    </div>

                    <div className="flex flex-col gap-6">
                        {/* 1. Observaciones */}
                        <div>
                            <label
                                htmlFor="director-notes"
                                className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]"
                            >
                                Observaciones
                                {selectedVersion ? ` · Versión ${selectedVersion.version_number}` : ''}
                            </label>
                            <textarea
                                id="director-notes"
                                rows={5}
                                value={directorNotes}
                                onChange={(e) => setDirectorNotes(e.target.value)}
                                placeholder={
                                    selectedVersion
                                        ? `Escriba sus observaciones sobre la versión ${selectedVersion.version_number}...`
                                        : 'Escriba sus observaciones sobre la entrega...'
                                }
                                className="w-full min-h-[100px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y"
                            />
                        </div>

                        {/* 2. Decisión */}
                        <div>
                            <p className="mb-3 text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                Decisión
                            </p>
                            <div className="flex flex-col gap-3 sm:flex-row">
                                <button
                                    type="button"
                                    onClick={() => setDecision('aprobada')}
                                    className={`flex flex-1 items-center gap-3 rounded-lg border p-4 text-left transition-all active:scale-[0.98] ${
                                        decision === 'aprobada'
                                            ? 'border-[#16a34a] bg-[#dcfce7]'
                                            : 'border-[#e5e5e5] hover:bg-[#fafaf9]'
                                    }`}
                                >
                                    <CheckCircle2
                                        className={`h-5 w-5 ${
                                            decision === 'aprobada' ? 'text-[#16a34a]' : 'text-[#78716c]'
                                        }`}
                                    />
                                    <div>
                                        <p className="text-sm font-semibold text-[#1c1917]">Aprobada</p>
                                        <p className="text-xs text-[#57534e]">
                                            La entrega cumple con los criterios establecidos
                                        </p>
                                    </div>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setDecision('revisada')}
                                    className={`flex flex-1 items-center gap-3 rounded-lg border p-4 text-left transition-all active:scale-[0.98] ${
                                        decision === 'revisada'
                                            ? 'border-[#d97706] bg-[#fef3c7]'
                                            : 'border-[#e5e5e5] hover:bg-[#fafaf9]'
                                    }`}
                                >
                                    <AlertTriangle
                                        className={`h-5 w-5 ${
                                            decision === 'revisada' ? 'text-[#d97706]' : 'text-[#78716c]'
                                        }`}
                                    />
                                    <div>
                                        <p className="text-sm font-semibold text-[#1c1917]">Necesita ajustes</p>
                                        <p className="text-xs text-[#57534e]">
                                            Se requieren correcciones antes de aprobar
                                        </p>
                                    </div>
                                </button>
                            </div>
                        </div>

                        {/* Error message */}
                        {submitError && (
                            <div className="rounded-lg border border-[#fee2e2] bg-[#fef2f2] px-4 py-3 text-sm text-[#dc2626]">
                                {submitError}
                            </div>
                        )}

                        {/* 3. Botón Guardar revisión */}
                        <button
                            type="button"
                            onClick={handleSubmitReview}
                            disabled={!decision || submitting}
                            className="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {submitting ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Send className="h-4 w-4" />
                            )}
                            {submitting ? 'Guardando...' : 'Guardar revisión'}
                        </button>
                    </div>
                </div>

                {/* ── G. Nota consolidada (si existe) ── */}
                {entrega.consolidated_grade !== null && entrega.consolidated_grade !== undefined && (
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="flex items-center gap-2">
                            <Star className="h-4 w-4 text-[#d97706]" />
                            <p className="text-xs text-[#78716c]">Nota consolidada</p>
                        </div>
                        <p className="mt-1 text-2xl font-bold text-[#1c1917]">
                            {Number(entrega.consolidated_grade).toFixed(2)}
                            <span className="text-sm font-normal text-[#78716c]"> / 5.00</span>
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}
