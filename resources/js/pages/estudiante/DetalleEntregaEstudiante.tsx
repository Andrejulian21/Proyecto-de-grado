import { useState, useEffect, useRef, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import {
    ArrowLeft, Download, FileText, Calendar, Loader2,
    AlertTriangle, CheckCircle2, XCircle, Clock,
    Upload, Trash2, Lock, ChevronDown, ChevronRight,
} from 'lucide-react';
import { apiFetch } from '@/lib/utils';
import type { ArchivoRequeridoConfig } from '@/types/entregas';

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
}

interface ArchivoConVersiones {
    config: ArchivoRequeridoConfig;
    versiones: Version[];
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
    proyecto?: {
        id: number;
        code: string;
        title: string;
    };
    proyectos?: { id: number; code: string; title: string }[];
    versiones: Version[];
}

/* ── Constants ── */

const MAX_VERSIONS_PER_ARCHIVO = 4;

const STATUS_MAP: Record<string, { label: string; variant: 'success' | 'warning' | 'error' | 'info' | 'inactivo' }> = {
    aprobada: { label: 'Aprobada', variant: 'success' },
    aprobado: { label: 'Aprobada', variant: 'success' },
    rechazada: { label: 'Necesita ajustes', variant: 'warning' },
    rechazado: { label: 'Necesita ajustes', variant: 'warning' },
    revisada: { label: 'Necesita ajustes', variant: 'warning' },
    enviada: { label: 'En revisión', variant: 'info' },
    pendiente: { label: 'Sin revisar', variant: 'warning' },
    solicitada: { label: 'Sin entregar', variant: 'inactivo' },
    creacion: { label: 'Sin entregar', variant: 'inactivo' },
};

/* ── Helpers ── */

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
        });
    } catch {
        return dateStr;
    }
}

function formatDateTime(dateStr: string | null | undefined): string {
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

function formatFileSize(bytes: number | null): string {
    if (bytes === null || bytes === undefined) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function getDownloadUrl(filePath: string): string {
    return `/storage/${filePath}`;
}

/* ── Component ── */

export default function DetalleEntregaEstudiante() {
    const params = useParams<{ id: string; entregaId: string }>();
    const entregaId = params.entregaId || params.id;
    const navigate = useNavigate();

    const [entrega, setEntrega] = useState<EntregaDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    /* Upload state per slug */
    const [uploadingSlug, setUploadingSlug] = useState<string | null>(null);
    const [uploadError, setUploadError] = useState<string | null>(null);

    /* Delete state */
    const [deletingVersionId, setDeletingVersionId] = useState<number | null>(null);

    /* File input refs per slug */
    const fileInputRefs = useRef<Record<string, HTMLInputElement | null>>({});

    function setFileInputRef(slug: string, el: HTMLInputElement | null) {
        fileInputRefs.current[slug] = el;
    }

    /* Expanded version history */
    const [expandedSlugs, setExpandedSlugs] = useState<Set<string>>(new Set());

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
                const data: EntregaDetail = json.data ?? json;
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

    /* ── Group versions by archivo_requerido_id ── */
    const allVersions = entrega?.versiones ?? [];
    const hasArchivoIds = allVersions.some((v) => v.archivo_requerido_id);
    const archivosConVersiones: ArchivoConVersiones[] = (entrega?.archivos_requeridos ?? []).map((raw, idx) => {
        // Persisted items use `slug`; normalize to the builder identity (`id`).
        const config = { ...raw, id: raw.id || (raw as unknown as { slug?: string }).slug || '' };
        return {
            config,
            versiones: allVersions
                .filter((v) => {
                    if (hasArchivoIds) return v.archivo_requerido_id === config.id;
                    // Fallback: first config gets all versions (legacy data)
                    return idx === 0;
                })
                .sort((a, b) => b.version_number - a.version_number),
        };
    });

    /* ── Upload handler per slug ── */
    async function handleFileUpload(slug: string, file: File) {
        if (!entregaId) return;

        /* Validate file type */
        const ext = '.' + (file.name.split('.').pop()?.toLowerCase() ?? '');
        if (ext !== '.pdf' && ext !== '.docx') {
            setUploadError('Solo se permiten archivos PDF y DOCX.');
            return;
        }

        /* Validate file size (50 MB) */
        if (file.size > 50 * 1024 * 1024) {
            setUploadError('El archivo no puede superar los 50 MB.');
            return;
        }

        setUploadingSlug(slug);
        setUploadError(null);

        try {
            const formData = new FormData();
            formData.append('file', file);

            const res = await apiFetch(`/api/entregas/${entregaId}/archivos/${slug}`, {
                method: 'POST',
                body: formData,
            });

            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? body?.error ?? `Error ${res.status}`);
            }

            /* Refresh entrega data */
            const refreshRes = await apiFetch(`/api/admin/entregas/${entregaId}`);
            if (refreshRes.ok) {
                const json = await refreshRes.json();
                setEntrega(json.data ?? json);
            }
        } catch (err) {
            setUploadError(err instanceof Error ? err.message : 'Error al subir el archivo.');
        } finally {
            setUploadingSlug(null);
        }
    }

    /* ── Delete handler ── */
    async function handleDeleteVersion(versionId: number) {
        if (!entregaId) return;
        setDeletingVersionId(versionId);
        try {
            const res = await apiFetch(`/api/entregas/${entregaId}/versiones/${versionId}`, {
                method: 'DELETE',
            });

            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? 'Error al eliminar la versión.');
            }

            /* Refresh entrega data */
            const refreshRes = await apiFetch(`/api/admin/entregas/${entregaId}`);
            if (refreshRes.ok) {
                const json = await refreshRes.json();
                setEntrega(json.data ?? json);
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Error al eliminar la versión.');
        } finally {
            setDeletingVersionId(null);
        }
    }

    /* Business window (mirrors EntregaEstudianteController::verificarVentanaTiempo):
       - before start_date (+ start_time): locked — cannot view/upload.
       - after due_date (+ hora_maxima): can view, cannot upload. */
    function fechaLocalISO(date: Date): string {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function parsearFechaLocal(fecha: string, hora?: string | null): Date {
        const [y, m, d] = fecha.slice(0, 10).split('-').map(Number);
        const date = new Date(y, (m || 1) - 1, d || 1);
        if (hora) {
            const [hh, mm] = hora.split(':').map(Number);
            date.setHours(hh || 0, mm || 0, 0, 0);
        }
        return date;
    }

    const isLocked = useMemo(() => {
        if (!entrega?.start_date) return false;
        return new Date() < parsearFechaLocal(entrega.start_date, entrega.start_time);
    }, [entrega]);

    const vencida = useMemo(() => {
        if (!entrega?.due_date) return false;
        const now = new Date();
        const hoy = fechaLocalISO(now);
        const dueDia = String(entrega.due_date).slice(0, 10);
        if (hoy > dueDia) return true;
        if (hoy === dueDia && entrega.hora_maxima) {
            const horaActual = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
            if (horaActual > entrega.hora_maxima) return true;
        }
        return false;
    }, [entrega]);

    /* ── Loading state ── */
    if (loading) {
        return (
            <div className="flex items-center justify-center py-20" role="status" aria-label="Cargando entrega">
                <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
            </div>
        );
    }

    /* ── Error state ── */
    if (error || !entrega) {
        return (
            <div className="flex flex-col items-center gap-4 py-20">
                <AlertTriangle className="h-10 w-10 text-[#dc2626]" />
                <p className="text-sm text-[#dc2626]">{error ?? 'No se encontró la entrega.'}</p>
                <button
                    onClick={() => navigate('/dashboard/estudiante')}
                    className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Volver
                </button>
            </div>
        );
    }

    /* ── Derived data ── */
    const statusCfg = statusConfig(entrega.status);
    const mainProyecto = entrega.proyecto ?? (entrega.proyectos?.[0] ?? null);
    const projectCode = mainProyecto?.code ?? '';
    const projectTitle = mainProyecto?.title ?? '';

    const phaseLabels: Record<string, string> = {
        anteproyecto: 'Anteproyecto',
        presentacion_anteproyecto: 'Presentación Anteproyecto',
        desarrollo: 'Desarrollo del proyecto',
        presentacion_final: 'Presentación Final',
    };

    function canDeleteVersion(v: Version): boolean {
        return !v.director_notes || v.director_notes.trim().length === 0;
    }

    function getReviewStatus(v: Version): { label: string; variant: 'success' | 'warning' | 'info' } {
        const hasNotes = v.director_notes && v.director_notes.trim().length > 0;
        if (!hasNotes) return { label: 'Sin revisar', variant: 'warning' };
        if (entrega.status === 'aprobada' || entrega.status === 'aprobado') {
            return { label: 'Aprobada', variant: 'success' };
        }
        return { label: 'Necesita ajustes', variant: 'warning' };
    }



    return (
        <div className="flex flex-col gap-6">
            {/* ── Header ── */}
            <PageHeader
                eyebrow="Entrega"
                title={entrega.title}
                subtitle={
                    projectCode
                        ? `${projectCode} · ${phaseLabels[entrega.phase] ?? entrega.phase}`
                        : phaseLabels[entrega.phase] ?? entrega.phase
                }
                actions={
                    <button
                        onClick={() => navigate('/dashboard/estudiante')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            {/* ── Full width layout ── */}
            <div className="flex flex-col gap-6">
                {/* ── A. Metadata cards ── */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    {/* Fecha de inicio */}
                    {(entrega.start_date || entrega.start_time) && (
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <p className="text-xs text-[#78716c]">Fecha de inicio</p>
                            <p className="mt-1 text-sm font-semibold text-[#1c1917]">
                                {entrega.start_date ? formatDate(entrega.start_date) : '—'}
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
                                {formatDate(entrega.due_date)}
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
                            {isLocked ? (
                                <StatusBadge variant="inactivo">Bloqueada</StatusBadge>
                            ) : (
                                <StatusBadge variant={statusCfg.variant}>{statusCfg.label}</StatusBadge>
                            )}
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
                {isLocked ? (
                    /* ── Locked state ── */
                    <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="border-b border-[#e5e5e5] px-6 py-4">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Archivos Requeridos</h3>
                            </div>
                        </div>
                        <div className="flex flex-col items-center gap-4 py-16 text-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4]">
                                <Lock className="h-8 w-8 text-[#a8a29e]" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-[#57534e]">Entrega Bloqueada</p>
                                <p className="mt-1 text-sm text-[#a8a29e]">
                                    Esta entrega estará disponible a partir del{' '}
                                    {formatDate(entrega.start_date)}
                                    {entrega.start_time && <> {entrega.start_time}</>}.
                                </p>
                            </div>
                        </div>
                    </div>
                ) : (
                    /* ── Per-file cards ── */
                    <div className="flex flex-col gap-4">
                        {vencida && (
                            <div className="flex items-center gap-2 rounded-lg bg-[#fef3c7] px-4 py-2 text-sm text-[#78350f]">
                                <AlertTriangle className="h-4 w-4 shrink-0" />
                                La fecha límite de la entrega ya pasó. Puedes ver los archivos, pero no subir nuevas versiones.
                            </div>
                        )}

                        {archivosConVersiones.length === 0 ? (
                            <div className="rounded-xl border border-[#e5e5e5] bg-white p-8 text-center shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                                <p className="text-sm text-[#a8a29e]">
                                    No se han configurado archivos requeridos para esta entrega.
                                </p>
                            </div>
                        ) : (
                            archivosConVersiones.map(({ config, versiones }) => {
                                const isCompleto = versiones.length > 0;
                                const isExpanded = expandedSlugs.has(config.id);

                                return (
                                    <div
                                        key={config.id}
                                        className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]"
                                    >
                                        {/* ── Card header ── */}
                                        <div className="flex items-center justify-between gap-3 border-b border-[#e5e5e5] px-5 py-3.5">
                                            <div className="flex items-center gap-3">
                                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                                <div>
                                                    <h4 className="text-sm font-bold text-[#1c1917]">
                                                        {config.nombre}
                                                    </h4>
                                                    <span className="text-xs text-[#57534e]">
                                                        {config.versionamiento
                                                            ? `Con versionamiento · ${versiones.length} versión${versiones.length !== 1 ? 'es' : ''}`
                                                            : 'Sin versionamiento'}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <StatusBadge
                                                    variant={isCompleto ? 'success' : 'warning'}
                                                >
                                                    {isCompleto ? 'Completo' : 'Pendiente'}
                                                </StatusBadge>

                                                {/* Upload button */}
                                                <input
                                                    ref={(el) => setFileInputRef(config.id, el)}
                                                    type="file"
                                                    accept=".pdf,.docx"
                                                    className="hidden"
                                                    onChange={(e) => {
                                                        const file = e.target.files?.[0];
                                                        if (file) {
                                                            handleFileUpload(config.id, file);
                                                        }
                                                        e.target.value = '';
                                                    }}
                                                />

                                                {config.versionamiento && versiones.length < MAX_VERSIONS_PER_ARCHIVO && (
                                                    <button
                                                        onClick={() => fileInputRefs.current[config.id]?.click()}
                                                        disabled={uploadingSlug === config.id || vencida}
                                                        title={vencida ? 'La fecha límite ya pasó' : undefined}
                                                        className="inline-flex min-h-[32px] items-center gap-1.5 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-50"
                                                    >
                                                        {uploadingSlug === config.id ? (
                                                            <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                        ) : (
                                                            <Upload className="h-3.5 w-3.5" />
                                                        )}
                                                        Subir
                                                    </button>
                                                )}

                                                {!config.versionamiento && (
                                                    <button
                                                        onClick={() => fileInputRefs.current[config.id]?.click()}
                                                        disabled={uploadingSlug === config.id || vencida}
                                                        title={vencida ? 'La fecha límite ya pasó' : undefined}
                                                        className="inline-flex min-h-[32px] items-center gap-1.5 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-50"
                                                    >
                                                        {uploadingSlug === config.id ? (
                                                            <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                        ) : (
                                                            <Upload className="h-3.5 w-3.5" />
                                                        )}
                                                        {versiones.length === 0 ? 'Subir' : 'Reemplazar'}
                                                    </button>
                                                )}
                                            </div>
                                        </div>

                                        {/* ── Card body ── */}
                                        {config.versionamiento ? (
                                            /* ── Versionamiento: historial de versiones ── */
                                            <div className="px-5 py-3.5">
                                                {versiones.length === 0 ? (
                                                    <div className="flex flex-col items-center gap-2 py-6 text-center text-sm text-[#a8a29e]">
                                                        <FileText className="h-8 w-8 text-[#d6d3d1]" />
                                                        <p>No has subido archivos para este requisito.</p>
                                                    </div>
                                                ) : (
                                                    <>
                                                        {/* Última versión (destacada) */}
                                                        <div className="mb-2 flex items-center justify-between rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3">
                                                            <div className="flex items-center gap-3">
                                                                <FileText className="h-8 w-8 text-[#c2410c]" />
                                                                <div>
                                                                    <p className="text-sm font-semibold text-[#1c1917]">
                                                                        {versiones[0].original_name || `documento_v${versiones[0].version_number}.pdf`}
                                                                    </p>
                                                                    <p className="flex items-center gap-1 text-xs text-[#78716c]">
                                                                        <Calendar className="h-3 w-3" />
                                                                        {formatDateTime(versiones[0].uploaded_at || versiones[0].created_at)}
                                                                        <span className="mx-1">·</span>
                                                                        v{versiones[0].version_number}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div className="flex items-center gap-2">
                                                                <StatusBadge variant={getReviewStatus(versiones[0]).variant}>
                                                                    {getReviewStatus(versiones[0]).label}
                                                                </StatusBadge>
                                                                <a
                                                                    href={getDownloadUrl(versiones[0].file_path)}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#e7e5e4]"
                                                                    title="Descargar"
                                                                >
                                                                    <Download className="h-4 w-4" />
                                                                </a>
                                                            </div>
                                                        </div>

                                                        {/* Expandir historial */}
                                                        {versiones.length > 1 && (
                                                            <>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        const next = new Set(expandedSlugs);
                                                                        if (isExpanded) next.delete(config.id);
                                                                        else next.add(config.id);
                                                                        setExpandedSlugs(next);
                                                                    }}
                                                                    className="mb-2 inline-flex items-center gap-1 text-xs font-semibold text-[#c2410c] transition-colors hover:text-[#9a330a]"
                                                                >
                                                                    {isExpanded ? (
                                                                        <ChevronDown className="h-3.5 w-3.5" />
                                                                    ) : (
                                                                        <ChevronRight className="h-3.5 w-3.5" />
                                                                    )}
                                                                    Versiones anteriores ({versiones.length - 1})
                                                                </button>

                                                                {isExpanded && (
                                                                    <div className="flex flex-col gap-1.5">
                                                                        {versiones.slice(1).map((v) => (
                                                                            <div
                                                                                key={v.id}
                                                                                className="flex items-center justify-between rounded-lg border border-[#e5e5e5] bg-white px-3 py-2"
                                                                            >
                                                                                <div className="flex items-center gap-2">
                                                                                    <FileText className="h-4 w-4 text-[#a8a29e]" />
                                                                                    <div>
                                                                                        <p className="text-xs font-medium text-[#1c1917]">
                                                                                            v{v.version_number} · {v.original_name || `documento_v${v.version_number}.pdf`}
                                                                                        </p>
                                                                                        <p className="text-[11px] text-[#a8a29e]">
                                                                                            {formatDateTime(v.uploaded_at || v.created_at)}
                                                                                        </p>
                                                                                    </div>
                                                                                </div>
                                                                                <div className="flex items-center gap-1.5">
                                                                                    <StatusBadge variant={getReviewStatus(v).variant}>
                                                                                        {getReviewStatus(v).label}
                                                                                    </StatusBadge>
                                                                                    <a
                                                                                        href={getDownloadUrl(v.file_path)}
                                                                                        target="_blank"
                                                                                        rel="noopener noreferrer"
                                                                                        className="inline-flex h-7 w-7 items-center justify-center rounded-md text-[#a8a29e] hover:text-[#c2410c]"
                                                                                        title="Descargar"
                                                                                    >
                                                                                        <Download className="h-3.5 w-3.5" />
                                                                                    </a>
                                                                                    {canDeleteVersion(v) && (
                                                                                        <button
                                                                                            onClick={() => {
                                                                                                if (window.confirm(`¿Eliminar versión ${v.version_number} del archivo "${config.nombre}"?`)) {
                                                                                                    handleDeleteVersion(v.id);
                                                                                                }
                                                                                            }}
                                                                                            disabled={deletingVersionId === v.id}
                                                                                            className="inline-flex h-7 w-7 items-center justify-center rounded-md text-[#a8a29e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626] disabled:opacity-50"
                                                                                            title="Eliminar versión"
                                                                                        >
                                                                                            {deletingVersionId === v.id ? (
                                                                                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                                                            ) : (
                                                                                                <Trash2 className="h-3.5 w-3.5" />
                                                                                            )}
                                                                                        </button>
                                                                                    )}
                                                                                </div>
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                )}
                                                            </>
                                                        )}

                                                        {versiones[0].director_notes && (
                                                            <div className="mt-2 rounded-md bg-[#fef3c7] px-3 py-2">
                                                                <p className="text-xs font-semibold text-[#78350f]">
                                                                    Observaciones del director:
                                                                </p>
                                                                <p className="mt-0.5 text-xs text-[#451a03] whitespace-pre-wrap">
                                                                    {versiones[0].director_notes}
                                                                </p>
                                                            </div>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        ) : (
                                            /* ── Sin versionamiento: subida única ── */
                                            <div className="px-5 py-3.5">
                                                {versiones.length === 0 ? (
                                                    <div className="flex flex-col items-center gap-2 py-6 text-center text-sm text-[#a8a29e]">
                                                        <FileText className="h-8 w-8 text-[#d6d3d1]" />
                                                        <p>Sube el archivo requerido.</p>
                                                    </div>
                                                ) : (
                                                    <div className="flex items-center justify-between rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3">
                                                        <div className="flex items-center gap-3">
                                                            <FileText className="h-8 w-8 text-[#c2410c]" />
                                                            <div>
                                                                <p className="text-sm font-semibold text-[#1c1917]">
                                                                    {versiones[0].original_name || `documento_v${versiones[0].version_number}.pdf`}
                                                                </p>
                                                                <p className="flex items-center gap-1 text-xs text-[#78716c]">
                                                                    <Calendar className="h-3 w-3" />
                                                                    {formatDateTime(versiones[0].uploaded_at || versiones[0].created_at)}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <StatusBadge variant={getReviewStatus(versiones[0]).variant}>
                                                                {getReviewStatus(versiones[0]).label}
                                                            </StatusBadge>
                                                            <a
                                                                href={getDownloadUrl(versiones[0].file_path)}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#e7e5e4]"
                                                                title="Descargar"
                                                            >
                                                                <Download className="h-4 w-4" />
                                                            </a>
                                                        </div>
                                                    </div>
                                                )}

                                                {versiones[0]?.director_notes && (
                                                    <div className="mt-2 rounded-md bg-[#fef3c7] px-3 py-2">
                                                        <p className="text-xs font-semibold text-[#78350f]">
                                                            Observaciones del director:
                                                        </p>
                                                        <p className="mt-0.5 text-xs text-[#451a03] whitespace-pre-wrap">
                                                            {versiones[0].director_notes}
                                                        </p>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })
                        )}

                        {/* Upload error */}
                        {uploadError && (
                            <div className="flex items-center gap-2 rounded-lg bg-[#fee2e2] px-4 py-2 text-sm text-[#dc2626]">
                                <AlertTriangle className="h-4 w-4 shrink-0" />
                                {uploadError}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
