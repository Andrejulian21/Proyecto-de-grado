import { useState, useEffect, useMemo } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { apiFetch } from '@/lib/utils';
import {
    ArrowLeft, Eye, PenSquare, FileText, Loader2,
    AlertCircle, RefreshCw, X,
} from 'lucide-react';

/* ── Types ── */

interface ProyectoBitacora {
    id: number;
    topic: string;
    notes: string | null;
    meeting_date: string | null;
    duration_hours: number | null;
    signature_status: string;
    created_at: string;
}

interface ProyectoSimple {
    id: number;
    code: string;
    title: string;
}

const statusConfig: Record<string, { label: string; variant: 'success' | 'warning' | 'inactivo' | 'info' }> = {
    Completada: { label: 'Firmada', variant: 'success' },
    Pendiente: { label: 'Pendiente', variant: 'warning' },
    FirmadaEstudiante: { label: 'Firmada Estudiante', variant: 'info' },
    FirmadaDirector: { label: 'Firmada Director', variant: 'info' },
    Sospechosa: { label: 'Sospechosa', variant: 'inactivo' },
};

export default function BitacorasProyecto() {
    const navigate = useNavigate();
    const { proyectoId } = useParams<{ proyectoId: string }>();

    const [bitacoras, setBitacoras] = useState<ProyectoBitacora[]>([]);
    const [proyecto, setProyecto] = useState<ProyectoSimple | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [filterStatus, setFilterStatus] = useState<string>('all');

    // Confirm dialog state
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [selectedBitacora, setSelectedBitacora] = useState<ProyectoBitacora | null>(null);
    const [signing, setSigning] = useState(false);
    const [signError, setSignError] = useState<string | null>(null);

    useEffect(() => {
        if (!proyectoId) return;

        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                // Fetch bitácoras del proyecto
                const res = await apiFetch(`/api/director/proyectos/${proyectoId}/bitacoras`);

                if (!res.ok) {
                    throw new Error(
                        res.status === 404
                            ? 'Proyecto no encontrado.'
                            : `Error ${res.status}: ${res.statusText}`,
                    );
                }

                const json = await res.json();
                const data: ProyectoBitacora[] = json.data ?? json;

                // Fetch project info for the header
                const projRes = await apiFetch(`/api/director/proyectos/${proyectoId}`);

                let projData: ProyectoSimple | null = null;
                if (projRes.ok) {
                    const projJson = await projRes.json();
                    const pd = projJson.data ?? projJson;
                    projData = { id: pd.id, code: pd.code, title: pd.title };
                }

                if (!cancelled) {
                    setBitacoras(data);
                    setProyecto(projData);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err instanceof Error ? err.message : 'Error desconocido.');
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        }

        load();
        return () => { cancelled = true; };
    }, [proyectoId]);

    const columns: Column<ProyectoBitacora>[] = useMemo(() => [
        {
            key: 'topic',
            label: 'Tema',
            render: (row) => (
                <span className="font-medium text-[#1c1917] max-w-[200px] truncate block" title={row.topic}>
                    {row.topic}
                </span>
            ),
        },
        {
            key: 'notes',
            label: 'Descripción',
            render: (row) => (
                <span className="max-w-[250px] truncate text-[#57534e] block" title={row.notes ?? ''}>
                    {row.notes ?? '—'}
                </span>
            ),
        },
        {
            key: 'meeting_date',
            label: 'Fecha',
            render: (row) => {
                const d = row.meeting_date ? new Date(row.meeting_date) : null;
                return (
                    <span className="whitespace-nowrap text-[#1c1917]">
                        {d ? d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'}
                    </span>
                );
            },
        },
        {
            key: 'duration_hours',
            label: 'Duración',
            render: (row) => (
                <span className="whitespace-nowrap text-[#57534e] tabular-nums">
                    {row.duration_hours != null ? `${row.duration_hours}h` : '—'}
                </span>
            ),
        },
        {
            key: 'signature_status',
            label: 'Estado',
            render: (row) => {
                const config = statusConfig[row.signature_status] ?? { label: 'Sin firmar', variant: 'inactivo' };
                return <StatusBadge variant={config.variant}>{config.label}</StatusBadge>;
            },
        },
        {
            key: 'actions',
            label: 'Acciones',
            className: 'text-right',
            render: (row) => (
                <div className="inline-flex gap-0.5">
                    <button
                        onClick={() => navigate(`/bitacoras/${row.id}/revision`)}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c] active:scale-[0.98]"
                        aria-label={`Ver detalle bitácora ${row.topic}`}
                        title="Ver detalle"
                    >
                        <Eye className="h-4 w-4" />
                    </button>
                    {row.signature_status === 'Pendiente' && (
                        <button
                            onClick={() => {
                                setSelectedBitacora(row);
                                setSignError(null);
                                setConfirmOpen(true);
                            }}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#c2410c] hover:text-white active:scale-[0.98]"
                            aria-label={`Firmar bitácora ${row.topic}`}
                            title="Firmar"
                        >
                            <PenSquare className="h-4 w-4" />
                        </button>
                    )}
                </div>
            ),
        },
    ], [navigate]);

    const filtered = bitacoras.filter((b) => {
        return filterStatus === 'all' || b.signature_status === filterStatus;
    });

    async function handleSign() {
        if (!selectedBitacora) return;
        setSigning(true);
        setSignError(null);

        try {
            const res = await apiFetch(`/api/bitacoras/${selectedBitacora.id}/firmar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
            });

            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.error ?? body?.message ?? 'Error al firmar la bitácora.');
            }

            setConfirmOpen(false);
            setSelectedBitacora(null);

            // Refetch data
            const refreshRes = await apiFetch(`/api/director/proyectos/${proyectoId}/bitacoras`);
            if (refreshRes.ok) {
                const json = await refreshRes.json();
                setBitacoras(json.data ?? json);
            }
        } catch (err) {
            setSignError(err instanceof Error ? err.message : 'Error desconocido.');
        } finally {
            setSigning(false);
        }
    }

    const title = proyecto ? `${proyecto.code} — ${proyecto.title}` : 'Bitácoras del Proyecto';

    /* ── Loading ── */
    if (loading) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Bitácoras"
                    title="Cargando..."
                    actions={
                        <button
                            onClick={() => navigate('/supervision')}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver
                        </button>
                    }
                />
                <div className="flex flex-col items-center justify-center gap-4 py-16">
                    <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
                    <p className="text-sm text-[#57534e]">Cargando bitácoras...</p>
                </div>
            </div>
        );
    }

    /* ── Error ── */
    if (error) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Bitácoras"
                    title="Error"
                    subtitle="No se pudieron cargar las bitácoras"
                    actions={
                        <button
                            onClick={() => navigate('/supervision')}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver
                        </button>
                    }
                />
                <div className="flex flex-col items-center justify-center gap-4 py-16">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#fee2e2]">
                        <AlertCircle className="h-6 w-6 text-[#dc2626]" />
                    </div>
                    <p className="text-sm text-[#57534e] max-w-md text-center">{error}</p>
                    <button
                        onClick={() => window.location.reload()}
                        className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                    >
                        <RefreshCw className="h-4 w-4" />
                        Reintentar
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Bitácoras"
                title={title}
                subtitle={`${bitacoras.length} bitácora${bitacoras.length !== 1 ? 's' : ''} registrada${bitacoras.length !== 1 ? 's' : ''}`}
                actions={
                    <button
                        onClick={() => navigate('/supervision')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            {/* Filter (solo estado) */}
            <div className="flex items-center gap-3">
                <select
                    value={filterStatus}
                    onChange={(e) => setFilterStatus(e.target.value)}
                    className="min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                    aria-label="Filtrar por estado"
                >
                    <option value="all">Todos los estados</option>
                    <option value="Pendiente">Pendientes</option>
                    <option value="FirmadaEstudiante">Firmada Estudiante</option>
                    <option value="Completada">Firmadas</option>
                    <option value="Sospechosa">Sospechosas</option>
                </select>
            </div>

            {/* Table */}
            <DataTable
                columns={columns}
                data={filtered}
                loading={loading}
                getRowKey={(row) => row.id}
                emptyMessage={
                    filterStatus !== 'all'
                        ? 'No se encontraron bitácoras con ese estado.'
                        : 'Este proyecto aún no tiene bitácoras registradas.'
                }
            />

            {/* Sign ConfirmDialog */}
            <ConfirmDialog
                open={confirmOpen}
                title="Firmar Bitácora"
                message={
                    selectedBitacora
                        ? `¿Está seguro de firmar la bitácora "${selectedBitacora.topic}"?`
                        : ''
                }
                confirmLabel={signing ? 'Firmando...' : 'Firmar'}
                cancelLabel="Cancelar"
                onConfirm={handleSign}
                onCancel={() => {
                    if (!signing) {
                        setConfirmOpen(false);
                        setSelectedBitacora(null);
                        setSignError(null);
                    }
                }}
                variant="default"
            />

            {/* Sign error dialog */}
            {signError && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                    onClick={() => { setSignError(null); setConfirmOpen(true); }}
                >
                    <div
                        className="w-full max-w-md rounded-xl bg-white p-6 shadow-[0_20px_60px_rgba(28,25,23,0.15)]"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-start gap-3">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#fee2e2] text-[#dc2626]">
                                <AlertCircle className="h-5 w-5" />
                            </div>
                            <div className="flex flex-col gap-1 flex-1">
                                <h2 className="text-lg font-bold text-[#1c1917]">Error al firmar</h2>
                                <p className="text-sm text-[#57534e]">{signError}</p>
                            </div>
                            <button
                                onClick={() => { setSignError(null); setConfirmOpen(true); }}
                                className="rounded-lg p-1.5 text-[#57534e] transition-colors hover:bg-[#f5f5f4]"
                                aria-label="Cerrar"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="mt-4 flex justify-end">
                            <button
                                onClick={() => { setSignError(null); setConfirmOpen(true); }}
                                className="inline-flex items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a]"
                            >
                                Reintentar
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
