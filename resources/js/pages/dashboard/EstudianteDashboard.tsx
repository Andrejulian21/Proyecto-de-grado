import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { GraduationCap, User, AlertTriangle, Loader2, Pencil, Check, X } from 'lucide-react';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { PageHeader } from '@/components/ui/PageHeader';
import ReadOnlyDeliveryList, { type ReadOnlyDelivery } from '@/components/supervision/ReadOnlyDeliveryList';
import { PhaseStepper, type PhaseStep } from '@/components/project/PhaseStepper';
import { apiFetch } from '@/lib/utils';
import { entregaLayoutStatus } from '@/lib/entregas';

const PHASES = [
    { id: 'anteproyecto', label: 'Anteproyecto' },
    { id: 'presentacion_anteproyecto', label: 'Presentación Anteproyecto' },
    { id: 'desarrollo', label: 'Desarrollo del proyecto' },
    { id: 'presentacion_final', label: 'Presentación Final' },
] as const;

function buildPhases(current: string): PhaseStep[] {
    const idx = PHASES.findIndex((p) => p.id === current);
    return PHASES.map((p, i) => ({ ...p, status: (i < idx ? 'done' : i === idx ? 'current' : 'future') as 'done' | 'current' | 'future' }));
}

function toDate(d: string | undefined) {
    return d ? new Date(d).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
}

export default function EstudianteDashboard() {
    const navigate = useNavigate();
    const [proyecto, setProyecto] = useState<any>(null);
    const [entregas, setEntregas] = useState<ReadOnlyDelivery[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [selectedPhaseId, setSelectedPhaseId] = useState<string | null>(null);
    const [editingTitle, setEditingTitle] = useState(false);
    const [editTitleValue, setEditTitleValue] = useState('');
    const [savingTitle, setSavingTitle] = useState(false);

    useEffect(() => {
        let cancel = false;
        (async () => {
            try {
                const [pr, er] = await Promise.all([apiFetch('/api/estudiante/proyecto'), apiFetch('/api/estudiante/entregas')]);
                if (cancel) return;
                if (!pr.ok || !er.ok) { setError('Error al cargar los datos.'); setLoading(false); return; }
                const pd = await pr.json(), ed = await er.json();
                setProyecto(pd.data);
                setEntregas((ed.data || []).map((e: any) => {
                    const apiStatus = e.estado ?? e.status ?? null;
                    const grade = e.nota ?? e.consolidated_grade ?? null;
                    return {
                        id: e.id,
                        name: e.titulo || e.title || `Entrega #${e.id}`,
                        date: toDate(e.fecha_limite || e.due_date),
                        phase: e.fase ?? '',
                        // The raw status decides the badge label through the
                        // canonical map; the layout state only picks the
                        // contextual sentence. Keeping both is what stops a
                        // never-submitted delivery from reading as submitted.
                        apiStatus,
                        status: entregaLayoutStatus(apiStatus),
                        grade: grade != null ? String(grade) : '—',
                    };
                }));
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
        entregas.filter((e) => e.phase === phaseId).length;

    // The list renders already-filtered rows: phase scoping belongs to the
    // caller so the shared component stays a pure read-only renderer.
    const visibleDeliveries = entregas.filter((e) => e.phase === activePhaseId);

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
                            <div className="flex items-center gap-2">
                                {editingTitle ? (
                                    <>
                                        <input
                                            type="text"
                                            value={editTitleValue}
                                            onChange={(e) => setEditTitleValue(e.target.value)}
                                            className="flex-1 min-h-[36px] rounded-lg border border-[#c2410c] bg-white px-3 py-1.5 text-base font-bold text-[#1c1917] outline-none focus:shadow-[0_0_0_3px_#fed7aa]"
                                            autoFocus
                                            disabled={savingTitle}
                                        />
                                        <button
                                            onClick={async () => {
                                                if (!editTitleValue.trim() || savingTitle) return;
                                                setSavingTitle(true);
                                                try {
                                                    const res = await apiFetch('/api/estudiante/proyecto', {
                                                        method: 'PUT',
                                                        headers: { 'Content-Type': 'application/json' },
                                                        body: JSON.stringify({ title: editTitleValue.trim() }),
                                                    });
                                                    if (!res.ok) throw new Error('Error al guardar');
                                                    setProyecto((prev: any) => ({ ...prev, title: editTitleValue.trim() }));
                                                    setEditingTitle(false);
                                                } catch {
                                                    setError('Error al actualizar el título');
                                                } finally {
                                                    setSavingTitle(false);
                                                }
                                            }}
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-[#c2410c] text-white hover:bg-[#9a330a]"
                                            title="Guardar"
                                        >
                                            {savingTitle ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
                                        </button>
                                        <button
                                            onClick={() => setEditingTitle(false)}
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#e5e5e5] text-[#57534e] hover:bg-[#f5f5f4]"
                                            title="Cancelar"
                                        >
                                            <X className="h-4 w-4" />
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        <h3 className="text-lg font-bold text-[#1c1917]">{proyecto.title}</h3>
                                        <button
                                            onClick={() => {
                                                setEditTitleValue(proyecto.title);
                                                setEditingTitle(true);
                                            }}
                                            className="inline-flex h-7 w-7 items-center justify-center rounded-lg text-[#78716c] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c]"
                                            title="Editar título del proyecto"
                                        >
                                            <Pencil className="h-3.5 w-3.5" />
                                        </button>
                                    </>
                                )}
                            </div>
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
            <ReadOnlyDeliveryList
                deliveries={visibleDeliveries}
                hasAnyDeliveries={entregas.length > 0}
                openLabel="Ver detalle"
                onOpen={(deliveryId) => navigate(`/estudiante/entregas/${deliveryId}`)}
            />
        </div>
    );
}
