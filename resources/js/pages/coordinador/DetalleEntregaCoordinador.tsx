import { useEffect, useMemo, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import DeliveryVersionSelector from '@/components/entregas/DeliveryVersionSelector';
import DeliveryVersionHistory from '@/components/entregas/DeliveryVersionHistory';
import VersionObservationPanel from '@/components/entregas/VersionObservationPanel';
import DeliveryDocumentPreview from '@/components/entregas/DeliveryDocumentPreview';
import { ArrowLeft, FileText, Loader2, AlertTriangle } from 'lucide-react';
import { getEntregaById } from '@/mocks/entregasMock';
import type { EntregaMock } from '@/types/entregas';
import { sortVersionsDesc } from '@/types/entregas';

const STATUS_MAP: Record<string, { label: string; variant: 'success' | 'warning' | 'error' | 'info' | 'inactivo' }> = {
    aprobada: { label: 'Aprobada', variant: 'success' },
    revisada: { label: 'Necesita ajustes', variant: 'warning' },
    enviada: { label: 'Sin revisar', variant: 'warning' },
    pendiente: { label: 'Sin revisar', variant: 'warning' },
    solicitada: { label: 'Sin entregar', variant: 'inactivo' },
};

const phaseLabels: Record<string, string> = {
    anteproyecto: 'Anteproyecto',
    presentacion_anteproyecto: 'Presentación Anteproyecto',
    desarrollo: 'Desarrollo del proyecto',
    presentacion_final: 'Presentación Final',
};

function formatDateShort(dateStr: string | null | undefined): string {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('es-CO', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return dateStr;
    }
}

export default function DetalleEntregaCoordinador() {
    const { proyectoId, entregaId: entregaIdParam } = useParams<{
        proyectoId: string;
        entregaId: string;
    }>();
    const navigate = useNavigate();
    const entregaId = Number(entregaIdParam);

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [entrega, setEntrega] = useState<EntregaMock | null>(null);
    const [selectedVersionId, setSelectedVersionId] = useState<number | null>(null);

    useEffect(() => {
        setLoading(true);
        setError(null);
        const timer = setTimeout(() => {
            const data = getEntregaById(entregaId);
            if (!data) {
                setError('No se encontró la entrega.');
                setEntrega(null);
            } else {
                setEntrega(data);
                const sorted = sortVersionsDesc(data.versiones);
                setSelectedVersionId(sorted[0]?.id ?? null);
            }
            setLoading(false);
        }, 400);
        return () => clearTimeout(timer);
    }, [entregaId]);

    const sortedVersions = useMemo(
        () => (entrega ? sortVersionsDesc(entrega.versiones) : []),
        [entrega],
    );

    const selectedVersion = sortedVersions.find((v) => v.id === selectedVersionId) ?? null;

    const backPath = proyectoId
        ? `/coordinador/entregas?proyecto=${proyectoId}`
        : '/coordinador/entregas';

    if (loading) {
        return (
            <div className="flex items-center justify-center py-20" role="status">
                <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
            </div>
        );
    }

    if (error || !entrega) {
        return (
            <div className="flex flex-col items-center gap-4 py-20">
                <AlertTriangle className="h-10 w-10 text-[#dc2626]" />
                <p className="text-sm text-[#dc2626]">{error ?? 'No se encontró la entrega.'}</p>
                <button
                    type="button"
                    onClick={() => navigate('/coordinador/entregas')}
                    className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Volver a Entregas
                </button>
            </div>
        );
    }

    const statusCfg = STATUS_MAP[entrega.status] ?? { label: entrega.status, variant: 'inactivo' as const };

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Entrega"
                title={entrega.title}
                subtitle={`${entrega.project.code} · ${phaseLabels[entrega.phase] ?? entrega.phase}`}
                actions={
                    <button
                        type="button"
                        onClick={() => navigate(backPath)}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver a Entregas
                    </button>
                }
            />

            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <p className="text-xs text-[#78716c]">Estado</p>
                    <div className="mt-1.5">
                        <StatusBadge variant={statusCfg.variant}>{statusCfg.label}</StatusBadge>
                    </div>
                </div>
                {entrega.dueDate && (
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <p className="text-xs text-[#78716c]">Fecha límite</p>
                        <p className="mt-1 text-sm font-semibold text-[#1c1917]">
                            {formatDateShort(entrega.dueDate)}
                        </p>
                    </div>
                )}
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <p className="text-xs text-[#78716c]">Director</p>
                    <p className="mt-1 text-sm font-semibold text-[#1c1917]">
                        {entrega.project.directorName ?? '—'}
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[280px_1fr]">
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <DeliveryVersionHistory
                        versions={sortedVersions}
                        selectedVersionId={selectedVersionId}
                        onSelect={setSelectedVersionId}
                    />
                </div>

                <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[#e5e5e5] px-6 py-4">
                        <div className="flex items-center gap-2">
                            <FileText className="h-5 w-5 text-[#c2410c]" />
                            <h3 className="text-base font-bold text-[#1c1917]">Documento (solo lectura)</h3>
                        </div>
                        {sortedVersions.length > 0 && (
                            <DeliveryVersionSelector
                                versions={sortedVersions}
                                selectedVersionId={selectedVersionId}
                                onSelect={setSelectedVersionId}
                            />
                        )}
                    </div>

                    {selectedVersion ? (
                        <div className="grid grid-cols-1 gap-6 p-6 xl:grid-cols-2">
                            <DeliveryDocumentPreview version={selectedVersion} />
                            <VersionObservationPanel version={selectedVersion} />
                        </div>
                    ) : (
                        <div className="py-12 text-center text-sm text-[#78716c]">
                            El estudiante aún no ha subido versiones.
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
