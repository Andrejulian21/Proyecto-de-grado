import { useState, useMemo, useCallback } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { useDirectorBitacoras, type BitacoraEntry } from '@/hooks/useDirectorBitacoras';
import { apiFetch } from '@/lib/utils';
import { SignatureCodeInput } from '@/components/bitacoras/SignatureCode';
import {
    FileText, Users, Clock, Eye, Loader2,
    AlertCircle, RefreshCw, PenSquare, X, ArrowLeft,
} from 'lucide-react';

const statusConfig: Record<string, { label: string; variant: 'success' | 'warning' | 'inactivo' | 'info' }> = {
    Completada: { label: 'Firmada', variant: 'success' },
    Pendiente: { label: 'Pendiente', variant: 'warning' },
    FirmadaEstudiante: { label: 'Firmada Estudiante', variant: 'info' },
    FirmadaDirector: { label: 'Firmada Director', variant: 'info' },
    Sospechosa: { label: 'Sospechosa', variant: 'inactivo' },
};

export default function BitacorasDirector() {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const proyectoId = searchParams.get('proyectoId');
    const { data: bitacoras, loading, error, refetch } = useDirectorBitacoras();

    const [filterStatus, setFilterStatus] = useState<string>('all');

    // Confirm dialog state
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [selectedBitacora, setSelectedBitacora] = useState<BitacoraEntry | null>(null);
    const openConfirmDialog = useCallback((b: BitacoraEntry) => {
        setSelectedBitacora(b);
        setConfirmOpen(true);
    }, []);

    const columns: Column<BitacoraEntry>[] = useMemo(() => [
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
            key: 'project_code',
            label: 'Proyecto',
            render: (row) => (
                <span className="font-medium text-[#1c1917]">{row.project_code ?? '—'}</span>
            ),
        },
        {
            key: 'estudiante_name',
            label: 'Estudiante',
            render: (row) => (
                <span className="text-[#57534e]">{row.estudiante_name ?? '—'}</span>
            ),
        },
        {
            key: 'topic',
            label: 'Tema',
            render: (row) => (
                <span className="max-w-[200px] truncate text-[#57534e]" title={row.topic}>
                    {row.topic}
                </span>
            ),
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
                const config = statusConfig[row.signature_status] ?? { label: 'Sin firmar', variant: 'inactivo' as const };
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
                            onClick={() => openConfirmDialog(row)}
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
    ], [navigate, openConfirmDialog]);

    const filtered = bitacoras.filter((b) => {
        const matchesProject = !proyectoId || b.project_id === Number(proyectoId);
        const matchesStatus = filterStatus === 'all' || b.signature_status === filterStatus;
        return matchesProject && matchesStatus;
    });

    const totalCount = filtered.length;
    const pendingCount = filtered.filter((b) => b.signature_status === 'Pendiente').length;
    const signedCount = filtered.filter((b) => b.signature_status === 'Completada').length;

    /* ── Loading state (initial only) ── */
    if (loading && bitacoras.length === 0) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Director" title="Bitácoras" subtitle="Cargando bitácoras..." />
                <div className="flex flex-col items-center justify-center gap-4 py-16">
                    <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
                    <p className="text-sm text-[#57534e]">Cargando bitácoras...</p>
                </div>
            </div>
        );
    }

    /* ── Error state (no cached data) ── */
    if (error && bitacoras.length === 0) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Director" title="Bitácoras" subtitle="Error al cargar los datos" />
                <div className="flex flex-col items-center justify-center gap-4 py-16">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#fee2e2]">
                        <AlertCircle className="h-6 w-6 text-[#dc2626]" />
                    </div>
                    <p className="text-sm text-[#57534e] max-w-md text-center">{error}</p>
                    <button
                        onClick={refetch}
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
                eyebrow="Director"
                title={proyectoId ? 'Bitácoras del Proyecto' : 'Bitácoras'}
                subtitle={proyectoId ? 'Bitácoras registradas para este proyecto' : 'Revise y firme las bitácoras de los proyectos que supervisa'}
                actions={
                    proyectoId ? (
                        <button
                            onClick={() => navigate('/supervision')}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver
                        </button>
                    ) : error ? (
                        <button
                            onClick={refetch}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                        >
                            <RefreshCw className="h-4 w-4" />
                            Reintentar
                        </button>
                    ) : undefined
                }
            />

            {/* Stats */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard icon={FileText} label="Total bitácoras" value={totalCount} />
                <StatCard icon={Clock} label="Pendientes de firma" value={pendingCount} variant="warning" />
                <StatCard icon={Users} label="Firmadas" value={signedCount} variant="success" />
            </div>

            {/* Filter by status */}
            <div className="flex justify-end">
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

            {/* Error toast inline (when there's cached data) */}
            {error && (
                <div className="flex items-center gap-3 rounded-lg border border-[#fee2e2] bg-[#fef2f2] p-3 text-sm text-[#dc2626]">
                    <AlertCircle className="h-4 w-4 shrink-0" />
                    <p className="flex-1">{error}</p>
                    <button
                        onClick={refetch}
                        className="text-xs font-semibold text-[#dc2626] underline hover:no-underline"
                    >
                        Reintentar
                    </button>
                </div>
            )}

            {/* Table */}
            <DataTable
                columns={columns}
                data={filtered}
                loading={loading}
                getRowKey={(row) => row.id}
                emptyMessage={
                    filterStatus !== 'all'
                        ? 'No se encontraron bitácoras con el filtro seleccionado.'
                        : 'Aún no hay bitácoras registradas.'
                }
            />

            {/* Sign code input modal */}
            {confirmOpen && selectedBitacora && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                    onClick={() => { setConfirmOpen(false); setSelectedBitacora(null); }}>
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-[0_20px_60px_rgba(28,25,23,0.15)]"
                        onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-bold text-[#1c1917]">Firmar Bitácora</h2>
                            <button onClick={() => { setConfirmOpen(false); setSelectedBitacora(null); }}
                                className="rounded-lg p-1.5 text-[#57534e] hover:bg-[#f5f5f4]">
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <p className="mb-4 text-sm text-[#57534e]">
                            Ingrese el código de 6 dígitos que el estudiante le compartió para firmar la bitácora:
                        </p>
                        <SignatureCodeInput
                            bitacoraId={selectedBitacora.id}
                            onSuccess={() => { setConfirmOpen(false); setSelectedBitacora(null); refetch(); }}
                        />
                    </div>
                </div>
            )}

            {/* Sign error dialog - replaced by SignatureCodeInput's internal error handling */}
                </div>
            )}
        </div>
    );
}
