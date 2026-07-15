import { useState, useEffect } from 'react';
import { Plus, Eye, Pencil, FileText, Loader2, AlertTriangle } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { EmptyState } from '@/components/ui/EmptyState';
import { apiFetch } from '@/lib/utils';

/* ── Types ── */

interface Binnacle {
    id: number;
    date: string;
    topic: string;
    description: string;
    duration: string;
    signatureStatus: 'signed' | 'pending' | 'unsigned';
}

interface BitacoraRaw {
    id: number;
    tema?: string;
    topic?: string;
    notes?: string;
    observaciones?: string;
    meeting_date?: string;
    fecha_reunion?: string;
    duration_hours?: number;
    duracion_horas?: number;
    signature_status?: string;
    estado_firma?: string;
    proyecto_id: number;
}

/* ── Signature status config ── */

const signatureConfig: Record<string, { label: string; variant: 'success' | 'warning' | 'inactivo' }> = {
    signed: { label: 'Firmada', variant: 'success' },
    pending: { label: 'Pendiente', variant: 'warning' },
    unsigned: { label: 'No firmado', variant: 'inactivo' },
};

/* ── Helpers ── */

function mapSignStatus(s: string | undefined): 'signed' | 'pending' | 'unsigned' {
    if (!s) return 'unsigned';
    if (s === 'Completada' || s === 'completada') return 'signed';
    if (s === 'Pendiente' || s === 'pendiente') return 'pending';
    if (s === 'FirmadaEstudiante' || s === 'firmada_estudiante') return 'pending';
    return 'unsigned';
}

function formatDate(d: string | undefined): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
}

/* ── Columns ── */

const columns: Column<Binnacle>[] = [
    { key: 'date', label: 'Fecha', className: 'whitespace-nowrap' },
    { key: 'topic', label: 'Tema' },
    {
        key: 'description',
        label: 'Descripcion',
        className: 'max-w-xs truncate',
        render: (row) => (
            <span className="block truncate text-[#57534e]" title={row.description}>
                {row.description}
            </span>
        ),
    },
    { key: 'duration', label: 'Duracion', className: 'whitespace-nowrap' },
    {
        key: 'signatureStatus',
        label: 'Estado firma',
        render: (row) => {
            const config = signatureConfig[row.signatureStatus];
            return <StatusBadge variant={config.variant}>{config.label}</StatusBadge>;
        },
    },
    {
        key: 'actions',
        label: 'Acciones',
        className: 'text-right',
        render: (row) => {
            const isSigned = row.signatureStatus === 'signed';
            return (
                <div className="inline-flex gap-0.5">
                    <button
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c] active:scale-[0.98]"
                        aria-label={`Ver bitacora ${row.topic}`}
                        title="Ver"
                    >
                        <Eye className="h-4 w-4" />
                    </button>
                    {!isSigned && (
                        <button
                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c] active:scale-[0.98]"
                            aria-label={`Editar bitacora ${row.topic}`}
                            title="Editar"
                        >
                            <Pencil className="h-4 w-4" />
                        </button>
                    )}
                </div>
            );
        },
    },
];

/* ── Main component ── */

export default function BitacorasEstudiante() {
    const navigate = useNavigate();
    const [binnacles, setBinnacles] = useState<Binnacle[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [pageState, setPageState] = useState<'loading' | 'empty' | 'data' | 'error'>('loading');

    useEffect(() => {
        let cancelled = false;

        async function fetchData() {
            try {
                // First, get the student's project to know the project_id
                const proyRes = await apiFetch('/api/estudiante/proyecto');
                if (!proyRes.ok) {
                    if (!cancelled) {
                        setPageState('empty');
                        setLoading(false);
                    }
                    return;
                }
                const proyData = await proyRes.json();
                const proyectoId = proyData.data?.id;
                if (!proyectoId) {
                    if (!cancelled) { setPageState('empty'); setLoading(false); }
                    return;
                }

                // Then, fetch bitacoras for that project
                const bitRes = await apiFetch(`/api/bitacoras?proyecto_id=${proyectoId}`);
                if (!bitRes.ok) {
                    const body = await bitRes.json().catch(() => ({}));
                    if (!cancelled) setError(body.error || 'Error al cargar las bitacoras.');
                    return;
                }
                const bitData = await bitRes.json();

                const mapped: Binnacle[] = (bitData.data || []).map((b: BitacoraRaw) => ({
                    id: b.id,
                    date: formatDate(b.meeting_date || b.fecha_reunion),
                    topic: b.tema || b.topic || 'Sin titulo',
                    description: b.notes || b.observaciones || '',
                    duration: b.duration_hours ? `${b.duration_hours}h` : b.duracion_horas ? `${b.duracion_horas}h` : '—',
                    signatureStatus: mapSignStatus(b.signature_status || b.estado_firma),
                }));

                if (!cancelled) {
                    setBinnacles(mapped);
                    setPageState(mapped.length > 0 ? 'data' : 'empty');
                }
            } catch {
                if (!cancelled) setError('Error de conexion.');
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        fetchData();
        return () => { cancelled = true; };
    }, []);

    /* ── Loading ── */
    if (loading) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Bitacora" title="Bitacoras" subtitle="Registro de sesiones y avances de tu proyecto de grado" />
                <div className="flex items-center justify-center py-16">
                    <Loader2 className="h-6 w-6 animate-spin text-[#c2410c]" />
                </div>
            </div>
        );
    }

    /* ── Error ── */
    if (error) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Bitacora" title="Bitacoras" subtitle="Registro de sesiones y avances de tu proyecto de grado" />
                <div className="flex flex-col items-center justify-center gap-4 py-16">
                    <AlertTriangle className="h-8 w-8 text-[#dc2626]" />
                    <p className="text-sm text-[#1c1917]">{error}</p>
                    <button
                        onClick={() => { setLoading(true); setError(null); setPageState('loading'); }}
                        className="rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white hover:bg-[#9a330a]"
                    >
                        Reintentar
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Bitacora"
                title="Bitacoras"
                subtitle="Registro de sesiones y avances de tu proyecto de grado"
                actions={
                    <button
                        onClick={() => navigate('/bitacora/nueva')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                        aria-label="Crear nueva bitacora"
                    >
                        <Plus className="h-4 w-4" />
                        Nueva Bitacora
                    </button>
                }
            />

            {pageState === 'empty' && (
                <EmptyState
                    icon={FileText}
                    title="No has registrado bitacoras"
                    description="Crea una nueva bitacora para comenzar a registrar tus avances."
                    action={{ label: 'Nueva Bitacora', onClick: () => navigate('/bitacora/nueva') }}
                />
            )}

            {pageState === 'data' && (
                <DataTable
                    columns={columns}
                    data={binnacles}
                    getRowKey={(row) => row.id}
                    emptyMessage="No has registrado bitacoras."
                />
            )}
        </div>
    );
}
