import { useEffect, useMemo, useRef, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import DeliveryVersionSelector from '@/components/entregas/DeliveryVersionSelector';
import DeliveryVersionHistory from '@/components/entregas/DeliveryVersionHistory';
import VersionObservationPanel from '@/components/entregas/VersionObservationPanel';
import DeliveryDocumentPreview from '@/components/entregas/DeliveryDocumentPreview';
import {
    ArrowLeft,
    FileText,
    Loader2,
    AlertTriangle,
    Upload,
    Lock,
    CheckCircle2,
} from 'lucide-react';
import { addMockVersion, getEntregaById } from '@/mocks/entregasMock';
import type { EntregaMock } from '@/types/entregas';
import { sortVersionsDesc } from '@/types/entregas';

const MAX_VERSIONS = 4;

const STATUS_MAP: Record<string, { label: string; variant: 'success' | 'warning' | 'error' | 'info' | 'inactivo' }> = {
    aprobada: { label: 'Aprobada', variant: 'success' },
    revisada: { label: 'Necesita ajustes', variant: 'warning' },
    enviada: { label: 'En revisión', variant: 'info' },
    pendiente: { label: 'Sin revisar', variant: 'warning' },
    solicitada: { label: 'Sin entregar', variant: 'inactivo' },
};

const phaseLabels: Record<string, string> = {
    anteproyecto: 'Anteproyecto',
    presentacion_anteproyecto: 'Presentación Anteproyecto',
    desarrollo: 'Desarrollo del proyecto',
    presentacion_final: 'Presentación Final',
};

function formatDate(dateStr: string | null | undefined): string {
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

export default function DetalleEntregaEstudiante() {
    const params = useParams<{ id: string; entregaId: string }>();
    const entregaId = Number(params.entregaId || params.id);
    const navigate = useNavigate();
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [entrega, setEntrega] = useState<EntregaMock | null>(null);
    const [selectedVersionId, setSelectedVersionId] = useState<number | null>(null);
    const [uploading, setUploading] = useState(false);
    const [uploadBanner, setUploadBanner] = useState<string | null>(null);
    const [isLocked, setIsLocked] = useState(false);

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
                if (data.startDate) {
                    const start = new Date(data.startDate);
                    if (data.startTime) {
                        const [h, m] = data.startTime.split(':').map(Number);
                        start.setHours(h || 0, m || 0, 0, 0);
                    }
                    setIsLocked(new Date() < start);
                } else {
                    setIsLocked(false);
                }
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

    async function handleFileUpload(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file || !entrega) return;

        const ext = '.' + (file.name.split('.').pop()?.toLowerCase() ?? '');
        if (ext !== '.pdf' && ext !== '.docx') return;

        setUploading(true);
        setUploadBanner(null);
        await new Promise((r) => setTimeout(r, 800));

        const updated = addMockVersion(entrega, file.name);
        setEntrega(updated);
        const newest = sortVersionsDesc(updated.versiones)[0];
        if (newest) setSelectedVersionId(newest.id);
        setUploadBanner(`Versión ${newest?.versionNumber} subida correctamente (simulado).`);
        setUploading(false);
        if (fileInputRef.current) fileInputRef.current.value = '';
    }

    if (loading) {
        return (
            <div className="flex items-center justify-center py-20" role="status" aria-label="Cargando entrega">
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
                    onClick={() => navigate('/dashboard/estudiante')}
                    className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] hover:bg-[#f5f5f4]"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Volver a Mi Proyecto
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
                        onClick={() => navigate('/dashboard/estudiante')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver a Mi Proyecto
                    </button>
                }
            />

            {uploadBanner && (
                <div className="flex items-center gap-2 rounded-xl border border-[#dcfce7] bg-[#dcfce7] px-4 py-3 text-sm text-[#15803d]">
                    <CheckCircle2 className="h-4 w-4 shrink-0" />
                    {uploadBanner}
                </div>
            )}

            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                {entrega.startDate && (
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <p className="text-xs text-[#78716c]">Fecha de inicio</p>
                        <p className="mt-1 text-sm font-semibold text-[#1c1917]">
                            {formatDate(entrega.startDate)}
                            {entrega.startTime && (
                                <span className="ml-1 font-normal text-[#57534e]">· {entrega.startTime}</span>
                            )}
                        </p>
                    </div>
                )}
                {entrega.dueDate && (
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <p className="text-xs text-[#78716c]">Fecha límite</p>
                        <p className="mt-1 text-sm font-semibold text-[#1c1917]">{formatDate(entrega.dueDate)}</p>
                    </div>
                )}
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
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <p className="text-xs text-[#78716c]">Proyecto</p>
                    <p className="mt-1 truncate text-sm font-semibold text-[#1c1917]" title={entrega.project.title}>
                        {entrega.project.title}
                    </p>
                </div>
            </div>

            {entrega.description && (
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <h3 className="mb-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                        Descripción
                    </h3>
                    <p className="whitespace-pre-wrap text-sm leading-relaxed text-[#1c1917]">
                        {entrega.description}
                    </p>
                </div>
            )}

            {isLocked ? (
                <div className="flex flex-col items-center gap-4 rounded-xl border border-[#e5e5e5] bg-white py-16 text-center shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <Lock className="h-12 w-12 text-[#a8a29e]" />
                    <p className="text-sm font-semibold text-[#57534e]">Entrega bloqueada</p>
                    <p className="text-sm text-[#a8a29e]">
                        Disponible a partir del {formatDate(entrega.startDate)}
                        {entrega.startTime && <> · {entrega.startTime}</>}.
                    </p>
                </div>
            ) : (
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
                                <h3 className="text-base font-bold text-[#1c1917]">Documento</h3>
                            </div>
                            <div className="flex items-center gap-2">
                                {sortedVersions.length > 0 && (
                                    <DeliveryVersionSelector
                                        versions={sortedVersions}
                                        selectedVersionId={selectedVersionId}
                                        onSelect={setSelectedVersionId}
                                    />
                                )}
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
                                            type="button"
                                            onClick={() => fileInputRef.current?.click()}
                                            disabled={uploading}
                                            className="inline-flex min-h-[32px] items-center gap-1.5 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#9a330a] disabled:opacity-50"
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

                        {selectedVersion ? (
                            <div className="grid grid-cols-1 gap-6 p-6 xl:grid-cols-2">
                                <DeliveryDocumentPreview version={selectedVersion} />
                                <VersionObservationPanel version={selectedVersion} />
                            </div>
                        ) : (
                            <div className="flex flex-col items-center gap-3 py-12 text-center text-sm text-[#a8a29e]">
                                <FileText className="h-10 w-10 text-[#d6d3d1]" />
                                <p>No has subido versiones para esta entrega.</p>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
