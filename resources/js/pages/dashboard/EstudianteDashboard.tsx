import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { GraduationCap, User, AlertTriangle, Loader2, FileText, Eye } from 'lucide-react';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { PageHeader } from '@/components/ui/PageHeader';
import DeliveryAccordion from '@/components/DeliveryAccordion';
import { PhaseStepper, type PhaseStep } from '@/components/project/PhaseStepper';
import { apiFetch } from '@/lib/utils';
import type { EntregaData } from '@/types/estudiante';

const PHASES = [
    { id: 'anteproyecto', label: 'Anteproyecto' },
    { id: 'presentacion_anteproyecto', label: 'Presentación Anteproyecto' },
    { id: 'desarrollo', label: 'Desarrollo del proyecto' },
    { id: 'presentacion_final', label: 'Presentación Final' },
];

const LABELS: Record<string, string> = {
    anteproyecto: 'Documento de Anteproyecto',
    presentacion_anteproyecto: 'Presentación Anteproyecto',
    desarrollo: 'Informe de Avance',
    presentacion_final: 'Informe Final',
};

function buildPhases(current: string) {
    const idx = PHASES.findIndex((p) => p.id === current);
    return PHASES.map((p, i) => ({ ...p, status: i < idx ? 'done' : i === idx ? 'current' : 'future' }));
}

function toDate(d: string | undefined) {
    return d ? new Date(d).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
}

function toDateTime(d: string | undefined) {
    if (!d) return '—';
    return new Date(d).toLocaleString('es-CO', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const OBSERVATION_PREVIEW_LIMIT = 80;

function previewObservation(text: string | null | undefined): string | null {
    if (!text || !text.trim()) return null;
    const trimmed = text.trim();
    if (trimmed.length <= OBSERVATION_PREVIEW_LIMIT) return trimmed;
    return `${trimmed.slice(0, OBSERVATION_PREVIEW_LIMIT)}…`;
}

function mapStatus(s: string | undefined): EntregaData['status'] {
    if (s === 'aprobada' || s === 'Aprobada') return 'approved';
    if (s === 'enviada' || s === 'Enviada') return 'enviada';
    if (s === 'pendiente' || s === 'Pendiente') return 'pending';
    // Any other status (creacion, solicitada, etc.) → pending, not locked
    // Locked is determined by start_date in the detail view, not by status
    return 'pending';
}

function mapVersionStatus(s: string | undefined): EntregaData['versions'][number]['status'] {
    if (s === 'aprobado' || s === 'aprobada') return 'approved';
    if (s === 'rechazado' || s === 'rechazada' || s === 'revisada') return 'rejected';
    return 'pending';
}

export default function EstudianteDashboard() {
    const navigate = useNavigate();
    const [proyecto, setProyecto] = useState<any>(null);
    const [entregas, setEntregas] = useState<EntregaData[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [selectedPhaseId, setSelectedPhaseId] = useState<string | null>(null);

    useEffect(() => {
        let cancel = false;
        (async () => {
            try {
                const [pr, er] = await Promise.all([apiFetch('/api/estudiante/proyecto'), apiFetch('/api/estudiante/entregas')]);
                if (cancel) return;
                if (!pr.ok || !er.ok) { setError('Error al cargar los datos.'); setLoading(false); return; }
                const pd = await pr.json(), ed = await er.json();
                setProyecto(pd.data);
                setEntregas((ed.data || []).map((e: any) => ({
                    id: e.id,
                    fase: e.fase,
                    label: e.titulo || e.title || LABELS[e.fase] || `Entrega #${e.id}`,
                    status: mapStatus(e.estado || e.status),
                    deadline: toDate(e.fecha_limite || e.due_date),
                    grade: e.nota ?? e.consolidated_grade ?? null,
                    versions: (e.versiones || []).map((v: any) => ({
                        version: v.numero_version ?? v.version_number ?? 0,
                        date: toDateTime(v.subido_en || v.uploaded_at || v.created_at),
                        status: mapVersionStatus(v.estado || v.status),
                        fileName: v.nombre_archivo || v.original_name || (v.ruta_archivo || '').split('/').pop() || 'documento.pdf',
                        observationPreview: previewObservation(v.observacion ?? v.director_notes),
                    })),
                })));
            } catch { if (!cancel) setError('Error de conexion.'); }
            finally { if (!cancel) setLoading(false); }
        })();
        return () => { cancel = true; };
    }, []);

    if (loading) return <div className="flex flex-col items-center justify-center gap-4 py-20"><Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" /><p className="text-sm text-[#78716c]">Cargando tu proyecto...</p></div>;
    if (error) return <div className="flex flex-col items-center justify-center gap-4 py-20"><AlertTriangle className="h-8 w-8 text-[#dc2626]" /><p className="text-sm font-semibold text-[#1c1917]">{error}</p><button onClick={() => window.location.reload()} className="rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white hover:bg-[#9a330a]">Reintentar</button></div>;
    if (!proyecto) return <div className="flex flex-col items-center justify-center gap-4 py-20"><GraduationCap className="h-12 w-12 text-[#d6d3d1]" /><p className="text-sm text-[#78716c]">No tienes un proyecto de grado asignado.</p></div>;

    const phases: PhaseStep[] = buildPhases(proyecto.current_phase);

    const activePhaseId = selectedPhaseId ?? proyecto.current_phase;

    const deliveryCountByPhase = (phaseId: string) =>
        entregas.filter((e) => e.fase === phaseId).length;

    return (
        <div className="flex flex-col gap-6">
            <PageHeader eyebrow="Proyecto Activo" title="Mi Proyecto de Grado" subtitle="Gestiona las entregas y el progreso de tu proyecto de grado" />
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-4">
                        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#fed7aa]"><GraduationCap className="h-7 w-7 text-[#c2410c]" /></div>
                        <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="text-xs font-bold uppercase tracking-[0.05em] text-[#c2410c]">{proyecto.code}</span>
                                <StatusBadge variant="en-curso">En Curso</StatusBadge>
                            </div>
                            <h3 className="text-lg font-bold text-[#1c1917]">{proyecto.title}</h3>
                            <span className="flex items-center gap-1.5 text-sm text-[#57534e]"><User className="h-3.5 w-3.5" /> Director: {proyecto.director?.name}</span>
                        </div>
                    </div>
                </div>
            </div>
            <PhaseStepper
                phases={phases}
                selectedPhaseId={activePhaseId}
                onSelectPhase={setSelectedPhaseId}
                deliveryCountByPhase={deliveryCountByPhase}
            />
            <div className="flex flex-col gap-3">
                    {(() => {
                        const filtered = entregas.filter((e) => e.fase === activePhaseId);
                        return <>
                            <h3 className="text-sm font-bold uppercase tracking-[0.05em] text-[#57534e]">Entregas ({filtered.length})</h3>
                            {filtered.length === 0 ? (
                                <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-[#e5e5e5] bg-white py-12 text-sm text-[#78716c]"><FileText className="h-8 w-8 text-[#d6d3d1]" />No hay entregas para esta fase.</div>
                            ) : filtered.map((d) => (
                                <div key={d.id} className="flex flex-col">
                                    <DeliveryAccordion delivery={d} />
                                    <div className="flex justify-end border-x border-b border-[#e5e5e5] rounded-b-xl bg-white px-4 pb-3 pt-0">
                                        <button
                                            onClick={() => navigate(`/estudiante/entregas/${d.id}`)}
                                            className="inline-flex items-center gap-1.5 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                                        >
                                            <Eye className="h-3.5 w-3.5" />
                                            Ver detalle
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </>;
                    })()}
                </div>
        </div>
    );
}
