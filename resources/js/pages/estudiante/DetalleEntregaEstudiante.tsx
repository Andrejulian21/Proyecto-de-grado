import { useState, useEffect, useRef, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import {
    ArrowLeft, Download, FileText, Calendar, Loader2,
    AlertTriangle, Trash2, Lock, Upload,
} from 'lucide-react';
import { apiFetch } from '@/lib/utils';
import type { DocumentoSolicitado } from '@/types/entregas';
import {
    agruparVersionesPorArchivo,
    type DocumentoConVersiones,
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
    archivos_requeridos?: DocumentoSolicitado[];
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

function getDownloadUrl(filePath: string): string {
    return `/storage/${filePath}`;
}

function getReviewStatus(
    version: Version,
    entregaStatus: string,
): { label: string; variant: 'success' | 'warning' | 'info' } {
    const hasNotes = version.director_notes && version.director_notes.trim().length > 0;
    if (!hasNotes) return { label: 'Sin revisar', variant: 'warning' };
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

export default function DetalleEntregaEstudiante() {
    const params = useParams<{ id: string; entregaId: string }>();
    const entregaId = params.entregaId || params.id;
    const navigate = useNavigate();

    const [entrega, setEntrega] = useState<EntregaDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    /* Selected version index per requested document */
    const [selectedVersionByDoc, setSelectedVersionByDoc] = useState<Record<string, number>>({});

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

    /* Reset selection when a fresh entrega payload arrives */
    useEffect(() => {
        setSelectedVersionByDoc({});
        setUploadError(null);
    }, [entrega?.id]);

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
                const data: EntregaDetail = json.data ?? json;
                setEntrega(data);
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

    /* Group versions per requested document (normalizing slug→id, legacy-safe) */
    const documentosConVersiones: DocumentoConVersiones<Version>[] = agruparVersionesPorArchivo(
        entrega.archivos_requeridos ?? [],
        entrega.versiones ?? [],
    );

    function canDeleteVersion(v: Version): boolean {
        return !v.director_notes || v.director_notes.trim().length === 0;
    }

    function selectVersion(docId: string, idx: number) {
        setSelectedVersionByDoc((prev) => ({ ...prev, [docId]: idx }));
        setUploadError(null);
    }

    function uploadLabel(doc: DocumentoConVersiones<Version>): string {
        if (doc.config.versionamiento) return 'Subir nueva versión';
        return doc.versiones.length === 0 ? 'Subir' : 'Reemplazar';
    }

    function canUpload(doc: DocumentoConVersiones<Version>): boolean {
        if (vencida) return false;
        if (doc.config.versionamiento) {
            return doc.versiones.length < MAX_VERSIONS_PER_ARCHIVO;
        }
        return true;
    }

    function versionIdx(doc: DocumentoConVersiones<Version>): number {
        const raw = selectedVersionByDoc[doc.config.id] ?? 0;
        return Math.min(raw, Math.max(0, doc.versiones.length - 1));
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
                {/* ── D. Documentos solicitados (sección independiente por documento) ── */}
                {isLocked ? (
                    <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="border-b border-[#e5e5e5] px-6 py-4">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Documentos solicitados</h3>
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
                    <div className="flex flex-col gap-4">
                        {vencida && (
                            <div className="flex items-center gap-2 rounded-lg bg-[#fef3c7] px-4 py-2 text-sm text-[#78350f]">
                                <AlertTriangle className="h-4 w-4 shrink-0" />
                                La fecha límite de la entrega ya pasó. Puedes ver los archivos, pero no subir nuevas versiones.
                            </div>
                        )}

                        {documentosConVersiones.length === 0 ? (
                            <div className="rounded-xl border border-[#e5e5e5] bg-white p-8 text-center shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                                <p className="text-sm text-[#a8a29e]">
                                    No se han configurado documentos solicitados para esta entrega.
                                </p>
                            </div>
                        ) : (
                            documentosConVersiones.map((doc) => {
                                const idx = versionIdx(doc);
                                const selectedVersion = doc.versiones[idx] ?? null;
                                const reviewStatus = selectedVersion
                                    ? getReviewStatus(selectedVersion, entrega.status)
                                    : null;

                                return (
                                    <div
                                        key={doc.config.id}
                                        className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]"
                                    >
                                        <div className="flex flex-col gap-1 border-b border-[#e5e5e5] px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="flex items-center gap-2">
                                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                                <h3 className="text-base font-bold text-[#1c1917]">
                                                    Documento: {doc.config.nombre}
                                                </h3>
                                            </div>
                                            {doc.config.analizable_ia && (
                                                <span className="rounded-full bg-[#ffedd5] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.03em] text-[#9a330a]">
                                                    Analizable con IA
                                                </span>
                                            )}
                                        </div>

                                        {selectedVersion ? (
                                            <div className="p-6">
                                                <div className="grid grid-cols-1 gap-6 xl:grid-cols-[280px_1fr]">
                                                    <div className="flex flex-col items-center justify-center gap-4 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] py-10">
                                                        <FileText className="h-16 w-16 text-[#d6d3d1]" />
                                                        <div className="text-center">
                                                            <p className="text-sm font-semibold text-[#1c1917]">
                                                                {selectedVersion.original_name || `documento_v${selectedVersion.version_number}.pdf`}
                                                            </p>
                                                            <p className="mt-1 flex items-center justify-center gap-1 text-xs text-[#78716c]">
                                                                <Calendar className="h-3 w-3" />
                                                                {formatDateTime(selectedVersion.uploaded_at || selectedVersion.created_at)}
                                                                {doc.config.versionamiento && (
                                                                    <>
                                                                        <span className="mx-1">·</span>
                                                                        <span>v{selectedVersion.version_number}</span>
                                                                    </>
                                                                )}
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

                                                        {doc.config.versionamiento && doc.versiones.length > 1 && (
                                                            <div className="flex flex-wrap items-center justify-center gap-1">
                                                                {doc.versiones.map((v, vidx) => (
                                                                    <button
                                                                        key={v.id}
                                                                        onClick={() => selectVersion(doc.config.id, vidx)}
                                                                        className={`inline-flex min-h-[28px] items-center gap-1 rounded-md px-2.5 py-1 text-[11px] font-semibold transition-colors ${
                                                                            idx === vidx
                                                                                ? 'bg-[#c2410c] text-white shadow-sm'
                                                                                : 'bg-white text-[#57534e] ring-1 ring-[#e5e5e5] hover:bg-[#e7e5e4]'
                                                                        }`}
                                                                    >
                                                                        v{v.version_number}
                                                                    </button>
                                                                ))}
                                                            </div>
                                                        )}

                                                        <div className="flex flex-wrap items-center justify-center gap-2">
                                                            <input
                                                                ref={(el) => setFileInputRef(doc.config.id, el)}
                                                                type="file"
                                                                accept=".pdf,.docx"
                                                                className="hidden"
                                                                onChange={(e) => {
                                                                    const file = e.target.files?.[0];
                                                                    if (file) handleFileUpload(doc.config.id, file);
                                                                    e.target.value = '';
                                                                }}
                                                            />
                                                            {canUpload(doc) && (
                                                                <button
                                                                    onClick={() => fileInputRefs.current[doc.config.id]?.click()}
                                                                    disabled={uploadingSlug === doc.config.id}
                                                                    className="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg bg-[#c2410c] px-3.5 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-50"
                                                                >
                                                                    {uploadingSlug === doc.config.id ? (
                                                                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                                    ) : (
                                                                        <Upload className="h-3.5 w-3.5" />
                                                                    )}
                                                                    {uploadLabel(doc)}
                                                                </button>
                                                            )}
                                                            {canDeleteVersion(selectedVersion) && (
                                                                <button
                                                                    onClick={() => {
                                                                        if (window.confirm(`¿Eliminar versión ${selectedVersion.version_number} de "${doc.config.nombre}"?`)) {
                                                                            handleDeleteVersion(selectedVersion.id);
                                                                        }
                                                                    }}
                                                                    disabled={deletingVersionId === selectedVersion.id}
                                                                    className="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg border border-[#e5e5e5] bg-white px-3.5 py-1.5 text-xs font-semibold text-[#57534e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626] disabled:opacity-50"
                                                                >
                                                                    {deletingVersionId === selectedVersion.id ? (
                                                                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                                    ) : (
                                                                        <Trash2 className="h-3.5 w-3.5" />
                                                                    )}
                                                                    Eliminar
                                                                </button>
                                                            )}
                                                        </div>
                                                    </div>

                                                    <div className="rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-4">
                                                        <div className="mb-3 flex items-center justify-between gap-2">
                                                            <span className="text-sm font-bold text-[#1c1917]">
                                                                {doc.config.nombre} · Versión {selectedVersion.version_number}
                                                            </span>
                                                            {reviewStatus && (
                                                                <StatusBadge variant={reviewStatus.variant}>
                                                                    {reviewStatus.label}
                                                                </StatusBadge>
                                                            )}
                                                        </div>
                                                        <p className="mb-3 flex items-center gap-1 text-xs text-[#78716c]">
                                                            <Calendar className="h-3 w-3" />
                                                            {formatDateTime(selectedVersion.uploaded_at || selectedVersion.created_at)}
                                                        </p>
                                                        {selectedVersion.director_notes ? (
                                                            <div className="rounded-md bg-white p-3">
                                                                <p className="mb-1 text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                                                    Observación del director
                                                                </p>
                                                                <p className="whitespace-pre-wrap text-xs leading-relaxed text-[#1c1917]">
                                                                    {selectedVersion.director_notes}
                                                                </p>
                                                            </div>
                                                        ) : (
                                                            <p className="text-xs text-[#a8a29e] italic">
                                                                Sin observación del director.
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="flex flex-col items-center gap-4 py-12 text-center">
                                                <FileText className="h-10 w-10 text-[#d6d3d1]" />
                                                <p className="text-sm text-[#a8a29e]">
                                                    No has subido el documento "{doc.config.nombre}".
                                                </p>
                                                {canUpload(doc) && (
                                                    <>
                                                        <input
                                                            ref={(el) => setFileInputRef(doc.config.id, el)}
                                                            type="file"
                                                            accept=".pdf,.docx"
                                                            className="hidden"
                                                            onChange={(e) => {
                                                                const file = e.target.files?.[0];
                                                                if (file) handleFileUpload(doc.config.id, file);
                                                                e.target.value = '';
                                                            }}
                                                        />
                                                        <button
                                                            onClick={() => fileInputRefs.current[doc.config.id]?.click()}
                                                            disabled={uploadingSlug === doc.config.id}
                                                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-50"
                                                        >
                                                            {uploadingSlug === doc.config.id ? (
                                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                            ) : (
                                                                <Upload className="h-4 w-4" />
                                                            )}
                                                            {uploadLabel(doc)}
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })
                        )}

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
