import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import {
    ArrowLeft, Download, FileText, Calendar, Loader2,
    AlertTriangle, MessageSquareText, Star,
    CheckCircle2, Send,
} from 'lucide-react';
import { apiFetch } from '@/lib/utils';
import type { ArchivoRequeridoConfig } from '@/types/entregas';
import {
    agruparVersionesPorArchivo,
    archivoAceptaObservaciones,
    esDocumentoProyecto,
    type ArchivoConVersiones,
} from '@/lib/entregas';

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
    archivo_requerido_id: string | null;
    /**
     * Director's grade of the per-project delivery this version belongs to
     * (D3-rev). The general delivery template never stores the note.
     */
    director_grade?: number | null;
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
    archivos_requeridos?: ArchivoRequeridoConfig[];
    consolidated_grade: string | number | null;
    director_grade?: number | null;
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
    const [selectedArchivoIdx, setSelectedArchivoIdx] = useState(0);
    const [selectedVersionIdx, setSelectedVersionIdx] = useState(0);

    /* ── Review form state ── */
    const [directorNotes, setDirectorNotes] = useState('');
    const [directorGrade, setDirectorGrade] = useState('');
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
                const data = json.data ?? json;
                setEntrega(data);
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

    /* ── Submit review ── */
    async function handleSubmitReview() {
        if (!entregaId || !decision) return;

        // RF-NOT-02: the note is captured when the review approves the delivery.
        let directorGradePayload: number | undefined;
        if (decision === 'aprobada' && directorGrade.trim() !== '') {
            const nota = Number(directorGrade);
            if (!Number.isFinite(nota) || nota < 0 || nota > 5) {
                setSubmitError('La nota del director debe estar entre 0 y 5.');
                return;
            }
            if (Math.round(nota * 100) / 100 !== nota) {
                setSubmitError('La nota del director debe tener máximo 2 decimales.');
                return;
            }
            directorGradePayload = nota;
        }

        setSubmitting(true);
        setSubmitError(null);

        try {
            const res = await apiFetch(`/api/admin/entregas/${entregaId}/revisar`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    status: decision,
                    consolidated_grade: null,
                    // RF-SUP-01/02: observations are only persisted on the
                    // degree project document (versioned); other files never
                    // carry them, so never send notes for them.
                    director_notes: aceptaObservaciones ? (directorNotes || null) : null,
                    version_id: selectedVersion?.id,
                    archivo_requerido_id: activeArchivo?.config.id ?? null,
                    ...(directorGradePayload !== undefined
                        ? { director_grade: directorGradePayload }
                        : {}),
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

    /* ── Group versions by archivo_requerido_id (slug→id normalization) ── */
    const archivosConVersiones: ArchivoConVersiones<Version>[] = agruparVersionesPorArchivo(
        entrega?.archivos_requeridos ?? [],
        entrega?.versiones ?? [],
    );
    const safeArchivoIdx = Math.min(selectedArchivoIdx, Math.max(0, archivosConVersiones.length - 1));
    const activeArchivo = archivosConVersiones[safeArchivoIdx] ?? null;

    const sortedVersions = activeArchivo?.versiones ?? [];
    const safeVersionIdx = Math.min(selectedVersionIdx, Math.max(0, sortedVersions.length - 1));
    const selectedVersion: Version | null = sortedVersions[safeVersionIdx] ?? null;

    /* RF-SUP-01/02: only the versioned degree project document shows and
       accepts director observations. */
    const esProyecto = activeArchivo ? esDocumentoProyecto(activeArchivo.config) : false;
    const aceptaObservaciones = activeArchivo ? archivoAceptaObservaciones(activeArchivo.config) : false;

    /* ── D3-rev: the grade input follows the selected version's project
       delivery (entrega_proyecto), never the shared template grade. ── */
    useEffect(() => {
        setDirectorGrade(
            selectedVersion?.director_grade != null
                ? String(selectedVersion.director_grade)
                : '',
        );
    }, [selectedVersion?.id]);

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

    /* ── RF-NOT-03: the delivery is closed when its status is terminal
       or its due_date is in the past; note and observations are read-only. */
    const esTerminal = entrega.status === 'aprobada' || entrega.status === 'rechazada';
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const dueInicio = entrega.due_date ? new Date(entrega.due_date) : null;
    if (dueInicio) dueInicio.setHours(0, 0, 0, 0);
    const vencida = dueInicio !== null && dueInicio < hoy;
    const cerrada = esTerminal || vencida;

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

                {/* ── D. Archivos Requeridos ── */}
                {archivosConVersiones.length === 0 ? (
                    <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="border-b border-[#e5e5e5] px-6 py-4">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Archivos Requeridos</h3>
                            </div>
                        </div>
                        <div className="flex flex-col items-center gap-3 py-12 text-center text-sm text-[#a8a29e]">
                            <FileText className="h-10 w-10 text-[#d6d3d1]" />
                            <p>No se han configurado archivos requeridos para esta entrega.</p>
                        </div>
                    </div>
                ) : (
                    /* ── Per-file version tab selector ── */
                    <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="flex items-center border-b border-[#e5e5e5] px-4 py-3">
                            {archivosConVersiones.map((av, idx) => {
                                const isActive = safeArchivoIdx === idx;
                                const isCompleto = av.versiones.length > 0;
                                return (
                                    <button
                                        key={av.config.id}
                                        onClick={() => {
                                            setSelectedArchivoIdx(idx);
                                            setSelectedVersionIdx(0);
                                            setDirectorNotes('');
                                            setDecision(null);
                                            setSubmitError(null);
                                        }}
                                        className={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                                            isActive
                                                ? 'bg-[#c2410c] text-white shadow-sm'
                                                : 'text-[#57534e] hover:bg-[#f5f5f4]'
                                        } ${!isCompleto ? 'opacity-60' : ''}`}
                                    >
                                        <FileText className="h-3.5 w-3.5" />
                                        {av.config.nombre}
                                        {isCompleto && (
                                            <span className="ml-0.5 rounded-full bg-white/20 px-1.5 text-[10px]">
                                                {av.versiones.length}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>

                        {/* Versiones del archivo activo */}
                        {activeArchivo && activeArchivo.versiones.length > 0 && selectedVersion ? (
                            <div className="p-6">
                                <div className="grid grid-cols-1 gap-6 xl:grid-cols-[280px_1fr]">
                                    {/* File info */}
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

                                        {/* Version selector dentro del mismo archivo */}
                                        {activeArchivo.config.versionamiento && activeArchivo.versiones.length > 1 && (
                                            <div className="flex items-center gap-1">
                                                {activeArchivo.versiones.slice(0, 4).map((v, vidx) => (
                                                    <button
                                                        key={v.id}
                                                        onClick={() => setSelectedVersionIdx(vidx)}
                                                        className={`inline-flex min-h-[28px] items-center gap-1 rounded-md px-2 py-1 text-[11px] font-semibold transition-colors ${
                                                            safeVersionIdx === vidx
                                                                ? 'bg-[#c2410c] text-white'
                                                                : 'bg-[#f5f5f4] text-[#57534e] hover:bg-[#e7e5e4]'
                                                        }`}
                                                    >
                                                        v{v.version_number}
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    {/* Observaciones existentes de esta versión (solo documento-proyecto) */}
                                    {aceptaObservaciones ? (
                                        <div className="rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-4">
                                            <div className="mb-3 flex items-center justify-between gap-2">
                                                <span className="text-sm font-bold text-[#1c1917]">
                                                    {activeArchivo.config.nombre} · Versión {selectedVersion.version_number}
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
                                                    Sin observaciones previas del director.
                                                </p>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-4">
                                            <div className="mb-3 flex items-center justify-between gap-2">
                                                <span className="text-sm font-bold text-[#1c1917]">
                                                    {activeArchivo.config.nombre} · Versión {selectedVersion.version_number}
                                                </span>
                                                <StatusBadge variant={getReviewStatus(selectedVersion, entrega.status).variant}>
                                                    {getReviewStatus(selectedVersion, entrega.status).label}
                                                </StatusBadge>
                                            </div>
                                            <p className="text-xs text-[#a8a29e] italic">
                                                {esProyecto
                                                    ? 'Este documento no tiene observaciones del director.'
                                                    : 'Las observaciones del director se guardan únicamente en el documento del proyecto de grado.'}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ) : (
                            /* Sin versiones para este archivo */
                            <div className="flex flex-col items-center gap-3 py-12 text-center text-sm text-[#a8a29e]">
                                <FileText className="h-10 w-10 text-[#d6d3d1]" />
                                <p>El estudiante aún no ha subido el archivo "{activeArchivo?.config.nombre}".</p>
                            </div>
                        )}
                    </div>
                )}

                {/* ── E. Panel de Revisión (DEBAJO de la card de Documento) ── */}
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <div className="mb-6 flex items-center gap-2">
                        <MessageSquareText className="h-5 w-5 text-[#c2410c]" />
                        <h3 className="text-base font-bold text-[#1c1917]">
                            Revisar: {activeArchivo?.config.nombre ?? 'Selecciona un archivo'}
                        </h3>
                    </div>

                    {cerrada && (
                        <div
                            className="mb-6 flex items-center gap-2 rounded-lg border border-[#fecaca] bg-[#fee2e2] px-4 py-3 text-sm text-[#dc2626]"
                            role="alert"
                        >
                            <AlertTriangle className="h-4 w-4 shrink-0" aria-hidden="true" />
                            La entrega está cerrada; la nota y las observaciones no pueden modificarse.
                        </div>
                    )}

                    <div className="flex flex-col gap-6">
                        {/* 1. Observaciones (solo documento-proyecto con versionamiento) */}
                        <div>
                            <label
                                htmlFor="director-notes"
                                className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]"
                            >
                                Observaciones
                            </label>
                            <textarea
                                id="director-notes"
                                rows={5}
                                value={directorNotes}
                                onChange={(e) => setDirectorNotes(e.target.value)}
                                disabled={cerrada || !aceptaObservaciones}
                                placeholder={
                                    aceptaObservaciones
                                        ? 'Escriba sus observaciones sobre la entrega...'
                                        : 'Las observaciones solo se guardan en el documento del proyecto de grado'
                                }
                                className="w-full min-h-[100px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] disabled:bg-[#f5f5f4] disabled:opacity-70 resize-y"
                            />
                            {!aceptaObservaciones && (
                                <p className="mt-1.5 text-xs text-[#a8a29e]">
                                    Las observaciones del director se guardan únicamente en el documento del proyecto de grado (con versionamiento).
                                </p>
                            )}
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
                                    disabled={cerrada}
                                    className={`flex flex-1 items-center gap-3 rounded-lg border p-4 text-left transition-all active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 ${
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
                                    disabled={cerrada}
                                    className={`flex flex-1 items-center gap-3 rounded-lg border p-4 text-left transition-all active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 ${
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

                        {/* RF-NOT-02: nota del director visible al aprobar */}
                        {decision === 'aprobada' && (
                            <div className="flex flex-col gap-1.5">
                                <label
                                    htmlFor="director-grade"
                                    className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]"
                                >
                                    Nota del director (0 – 5)
                                </label>
                                <input
                                    id="director-grade"
                                    type="number"
                                    min={0}
                                    max={5}
                                    step={0.1}
                                    value={directorGrade}
                                    onChange={(e) => setDirectorGrade(e.target.value)}
                                    disabled={cerrada}
                                    placeholder="Ej: 4.5"
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] disabled:bg-[#f5f5f4] disabled:opacity-70 tabular-nums"
                                />
                                <p className="text-xs text-[#a8a29e]">
                                    La nota se guarda al aprobar la entrega (escala 0-5).
                                </p>
                            </div>
                        )}

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
                            disabled={!decision || submitting || cerrada}
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

                {/* ── F. Nota consolidada (si existe) ── */}
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

                {/* ── G. Nota del director (RF-NOT-04 / D3-rev) ── */}
                {selectedVersion?.director_grade != null && (
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="flex items-center gap-2">
                            <Star className="h-4 w-4 text-[#d97706]" aria-hidden="true" />
                            <p className="text-xs text-[#78716c]">Nota del director (este proyecto)</p>
                        </div>
                        <p className="mt-1 text-2xl font-bold text-[#1c1917]">
                            {Number(selectedVersion.director_grade).toFixed(2)}
                            <span className="text-sm font-normal text-[#78716c]"> / 5.00</span>
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}
