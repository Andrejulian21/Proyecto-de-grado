import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import {
    ArrowLeft, Download, FileText, Calendar, Loader2,
    AlertTriangle, User, MessageSquareText, Star,
    CheckCircle2, XCircle, Clock,
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
    consolidated_grade: string | number | null;
    evaluation_complete: boolean;
    proyecto?: {
        id: number;
        code: string;
        title: string;
        estudiantes?: { id: number; name: string }[];
    };
    proyectos?: { id: number; code: string; title: string }[];
    versiones: Version[];
}

/* ── Helpers ── */

const STATUS_MAP: Record<string, { label: string; variant: 'success' | 'warning' | 'error' | 'info' | 'inactivo' }> = {
    aprobada: { label: 'Aprobado', variant: 'success' },
    aprobado: { label: 'Aprobado', variant: 'success' },
    rechazada: { label: 'Rechazado', variant: 'error' },
    rechazado: { label: 'Rechazado', variant: 'error' },
    revisada: { label: 'Correcciones', variant: 'warning' },
    enviada: { label: 'Enviada', variant: 'info' },
    pendiente: { label: 'Pendiente', variant: 'warning' },
    solicitada: { label: 'Solicitada', variant: 'info' },
    creacion: { label: 'Creada', variant: 'inactivo' },
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

export default function DetalleEntregaCoordinador() {
    const { proyectoId, entregaId } = useParams<{ proyectoId: string; entregaId: string }>();
    const navigate = useNavigate();

    const [entrega, setEntrega] = useState<EntregaDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

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
                    onClick={() => navigate(-1)}
                    className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Volver
                </button>
            </div>
        );
    }

    const statusCfg = statusConfig(entrega.status);
    const latestVersion: Version | undefined = entrega.versiones?.[0];
    const projectCode = entrega.proyecto?.code ?? '—';

    const phaseLabels: Record<string, string> = {
        anteproyecto: 'Anteproyecto',
        presentacion_anteproyecto: 'Presentación Anteproyecto',
        desarrollo: 'Desarrollo del proyecto',
        presentacion_final: 'Presentación Final',
    };

    return (
        <div className="flex flex-col gap-6">
            {/* Header */}
            <PageHeader
                eyebrow="Detalle de Entrega"
                title={entrega.title}
                subtitle={`${projectCode} · ${phaseLabels[entrega.phase] ?? entrega.phase}`}
                actions={
                    <button
                        onClick={() => navigate(-1)}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                {/* Main column: versions table */}
                <div className="lg:col-span-3 flex flex-col gap-6">
                    {/* Versions card */}
                    <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="border-b border-[#e5e5e5] px-6 py-4">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Versiones del Documento</h3>
                            </div>
                        </div>

                        {entrega.versiones.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 py-12 text-center text-sm text-[#a8a29e]">
                                <FileText className="h-10 w-10 text-[#d6d3d1]" />
                                <p>El estudiante aún no ha subido versiones para esta entrega.</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-[#f5f5f4] text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                        <tr>
                                            <th className="px-6 py-3">Versión</th>
                                            <th className="px-6 py-3">Fecha de subida</th>
                                            <th className="px-6 py-3">Archivo</th>
                                            <th className="px-6 py-3">Tamaño</th>
                                            <th className="px-6 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {entrega.versiones.map((v: Version) => (
                                            <tr
                                                key={v.id}
                                                className="border-b border-[#e5e5e5] last:border-none transition-colors hover:bg-[#fafaf9]"
                                            >
                                                <td className="px-6 py-3 font-semibold text-[#1c1917]">
                                                    v{v.version_number}
                                                </td>
                                                <td className="px-6 py-3 text-[#57534e] whitespace-nowrap">
                                                    <div className="flex items-center gap-1.5">
                                                        <Calendar className="h-3.5 w-3.5 shrink-0 text-[#78716c]" />
                                                        {formatDate(v.uploaded_at || v.created_at)}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-3 text-[#57534e] max-w-[240px] truncate">
                                                    {v.original_name || 'documento.pdf'}
                                                </td>
                                                <td className="px-6 py-3 text-[#57534e] whitespace-nowrap">
                                                    {formatFileSize(v.file_size)}
                                                </td>
                                                <td className="px-6 py-3">
                                                    <a
                                                        href={getDownloadUrl(v.file_path)}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c]"
                                                        title="Descargar archivo"
                                                    >
                                                        <Download className="h-4 w-4" />
                                                    </a>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Description card */}
                    {entrega.description && (
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <h3 className="mb-2 text-sm font-semibold text-[#57534e]">Descripción</h3>
                            <p className="text-sm text-[#1c1917] leading-relaxed">{entrega.description}</p>
                        </div>
                    )}
                </div>

                {/* Sidebar */}
                <div className="lg:col-span-2 flex flex-col gap-4">
                    {/* Status card */}
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="flex items-center gap-3">
                            <div className={`flex h-10 w-10 items-center justify-center rounded-xl ${
                                entrega.status === 'aprobada' || entrega.status === 'aprobado'
                                    ? 'bg-[#dcfce7] text-[#16a34a]'
                                    : entrega.status === 'rechazada' || entrega.status === 'rechazado'
                                        ? 'bg-[#fee2e2] text-[#dc2626]'
                                        : entrega.status === 'enviada'
                                            ? 'bg-[#dbeafe] text-[#2563eb]'
                                            : 'bg-[#fef3c7] text-[#d97706]'
                            }`}>
                                {entrega.status === 'aprobada' || entrega.status === 'aprobado' ? (
                                    <CheckCircle2 className="h-5 w-5" />
                                ) : entrega.status === 'rechazada' || entrega.status === 'rechazado' ? (
                                    <XCircle className="h-5 w-5" />
                                ) : entrega.status === 'enviada' ? (
                                    <FileText className="h-5 w-5" />
                                ) : (
                                    <Clock className="h-5 w-5" />
                                )}
                            </div>
                            <div>
                                <p className="text-xs text-[#78716c]">Estado</p>
                                <StatusBadge variant={statusCfg.variant}>{statusCfg.label}</StatusBadge>
                            </div>
                        </div>
                    </div>

                    {/* Due date */}
                    {entrega.due_date && (
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <p className="text-xs text-[#78716c]">Fecha límite</p>
                            <p className="mt-1 text-sm font-semibold text-[#1c1917]">{formatDate(entrega.due_date)}</p>
                        </div>
                    )}

                    {/* Consolidated grade */}
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

                    {/* Director's notes from latest version */}
                    {latestVersion?.director_notes && (
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="flex items-center gap-2 mb-2">
                                <MessageSquareText className="h-4 w-4 text-[#c2410c]" />
                                <p className="text-xs text-[#78716c]">Observaciones del director</p>
                            </div>
                            <p className="text-sm text-[#1c1917] leading-relaxed whitespace-pre-wrap">
                                {latestVersion.director_notes}
                            </p>
                        </div>
                    )}

                    {/* Proyectos vinculados */}
                    {entrega.proyectos && entrega.proyectos.length > 0 && (
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="flex items-center gap-2 mb-2">
                                <User className="h-4 w-4 text-[#4f46e5]" />
                                <p className="text-xs text-[#78716c]">Proyectos vinculados</p>
                            </div>
                            <ul className="space-y-1">
                                {entrega.proyectos.map((p) => (
                                    <li key={p.id} className="text-sm text-[#1c1917]">
                                        {p.code} — {p.title}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
