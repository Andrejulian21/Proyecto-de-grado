import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import {
    ArrowLeft, Download, FileText, Calendar, Loader2,
    AlertTriangle, CheckCircle2, XCircle, Clock,
    Upload, Trash2, Lock,
} from 'lucide-react';
import { apiFetch } from '@/lib/utils';

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
    proyecto?: {
        id: number;
        code: string;
        title: string;
    };
    proyectos?: { id: number; code: string; title: string }[];
    versiones: Version[];
}

/* ── Constants ── */

const MAX_VERSIONS = 4;

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
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [entrega, setEntrega] = useState<EntregaDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [selectedVersionIdx, setSelectedVersionIdx] = useState(0);

    /* Upload state */
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);


    /* Delete state */
    const [deletingVersionId, setDeletingVersionId] = useState<number | null>(null);

    /* Locked state */
    const [isLocked, setIsLocked] = useState(false);

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

                /* Check if locked by start_date */
                if (data.start_date) {
                    const startDate = new Date(data.start_date);
                    const now = new Date();
                    if (data.start_time) {
                        const [hours, minutes] = data.start_time.split(':').map(Number);
                        startDate.setHours(hours || 0, minutes || 0, 0, 0);
                    }
                    setIsLocked(now < startDate);
                }
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

    /* Reset version index when entrega data changes */
    useEffect(() => {
        setSelectedVersionIdx(0);
    }, [entrega?.id]);

    /* ── Upload handler ── */
    async function handleFileUpload(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file || !entregaId) return;

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

        setUploading(true);
        setUploadError(null);

        try {
            const formData = new FormData();
            formData.append('file', file);

            // Read XSRF token for Sanctum CSRF protection
            const xsrfMatch = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
            const xsrfToken = xsrfMatch ? decodeURIComponent(xsrfMatch[1]) : '';

            const res = await fetch(`/api/entregas/${entregaId}/versiones`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken,
                },
                body: formData,
            });

            if (!res.ok) {
                const text = await res.text().catch(() => 'Error desconocido');
                console.error('Upload error:', res.status, text);
                throw new Error(text ? (() => { try { return JSON.parse(text).message || JSON.parse(text).error || text; } catch { return text; } })() : `Error ${res.status}`);
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
            setUploading(false);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
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
    const proyectoDesdeUrl = null; // student view doesn't use URL params for this
    const mainProyecto = entrega.proyecto ?? (entrega.proyectos?.[0] ?? null);
    const projectCode = mainProyecto?.code ?? '';
    const projectTitle = mainProyecto?.title ?? '';

    const sortedVersions = [...(entrega.versiones ?? [])].sort(
        (a, b) => b.version_number - a.version_number,
    );
    const safeVersionIdx = Math.min(selectedVersionIdx, Math.max(0, sortedVersions.length - 1));
    const selectedVersion: Version | null = sortedVersions[safeVersionIdx] ?? null;

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

                {/* ── D. Documento / Versiones ── */}
                {isLocked ? (
                    /* ── Locked state ── */
                    <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="border-b border-[#e5e5e5] px-6 py-4">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Documento</h3>
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
                    /* ── Normal document view ── */
                    <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        {/* Header + version selector + upload */}
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[#e5e5e5] px-6 py-4">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Documento</h3>
                            </div>

                            <div className="flex items-center gap-2">
                                {/* Version selector */}
                                {sortedVersions.length > 0 && (
                                    <div className="flex items-center gap-1">
                                        {sortedVersions.length <= 4 ? (
                                            sortedVersions.map((v, idx) => (
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
                                            ))
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
                                )}

                                {/* Upload button */}
                                {sortedVersions.length < MAX_VERSIONS && (
                                    <>
                                        <input
                                            ref={fileInputRef}
                                            type="file"
                                            accept=".pdf,.docx"
                                            className="hidden"
                                            onChange={handleFileUpload}
                                        />
                                        <button
                                            onClick={() => fileInputRef.current?.click()}
                                            disabled={uploading}
                                            className="inline-flex min-h-[32px] items-center gap-1.5 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            {uploading ? (
                                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                            ) : (
                                                <Upload className="h-3.5 w-3.5" />
                                            )}
                                            Subir nueva versión
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>

                        {/* Upload error */}
                        {uploadError && (
                            <div className="mx-6 mt-4 flex items-center gap-2 rounded-lg bg-[#fee2e2] px-4 py-2 text-xs text-[#dc2626]">
                                <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                                {uploadError}
                            </div>
                        )}

                        {/* Body: document + observations */}
                        {sortedVersions.length > 0 && selectedVersion ? (
                            <div className="p-6">
                                <div className="grid grid-cols-1 gap-6 xl:grid-cols-[280px_1fr]">
                                    {/* Documento */}
                                    <div className="flex flex-col items-center justify-center gap-4 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] py-16">
                                        <FileText className="h-16 w-16 text-[#d6d3d1]" />
                                        <div className="text-center">
                                            <p className="text-sm font-semibold text-[#1c1917]">
                                                {selectedVersion.original_name || `documento_v${selectedVersion.version_number}.pdf`}
                                            </p>
                                            <p className="mt-1 flex items-center justify-center gap-1 text-xs text-[#78716c]">
                                                <Calendar className="h-3 w-3" />
                                                {formatDateTime(selectedVersion.uploaded_at || selectedVersion.created_at)}
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

                                    {/* Observaciones de esta versión */}
                                    <div className="rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-4">
                                        <div className="mb-3 flex items-center justify-between gap-2">
                                            <span className="text-sm font-bold text-[#1c1917]">
                                                Versión {selectedVersion.version_number}
                                            </span>
                                            <div className="flex items-center gap-1.5">
                                                <StatusBadge variant={getReviewStatus(selectedVersion).variant}>
                                                    {getReviewStatus(selectedVersion).label}
                                                </StatusBadge>
                                                {canDeleteVersion(selectedVersion) && (
                                                    <button
                                                        onClick={() => {
                                                            if (window.confirm(`¿Estás seguro de eliminar la versión ${selectedVersion.version_number}? Esta acción no se puede deshacer.`)) {
                                                                handleDeleteVersion(selectedVersion.id);
                                                            }
                                                        }}
                                                        disabled={deletingVersionId === selectedVersion.id}
                                                        className="inline-flex h-7 w-7 items-center justify-center rounded-md text-[#a8a29e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626] disabled:opacity-50"
                                                        title="Eliminar versión"
                                                    >
                                                        {deletingVersionId === selectedVersion.id ? (
                                                            <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                        ) : (
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        )}
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                        <div className="mb-3 space-y-1">
                                            <p className="flex items-center gap-1 text-xs text-[#78716c]">
                                                <Calendar className="h-3 w-3" />
                                                {formatDateTime(selectedVersion.uploaded_at || selectedVersion.created_at)}
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
                        ) : (
                            /* Sin versiones */
                            <div className="flex flex-col items-center gap-3 py-12 text-center text-sm text-[#a8a29e]">
                                <FileText className="h-10 w-10 text-[#d6d3d1]" />
                                <p>No has subido versiones para esta entrega.</p>
                                <p className="text-xs text-[#d6d3d1]">
                                    Usa el botón "Subir nueva versión" para agregar tu primer archivo.
                                </p>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
