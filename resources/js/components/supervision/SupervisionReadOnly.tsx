import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { PhaseStepper, type PhaseStep } from '@/components/project/PhaseStepper';
import {
    ArrowLeft, Award, User, FileText, Calendar, Clock,
    Loader2, AlertTriangle, RefreshCw,
} from 'lucide-react';
import { useEffect, useState, useCallback } from 'react';
import { apiFetch } from '@/lib/utils';
import { entregaLayoutStatus } from '@/lib/entregas';
import ReadOnlyDeliveryList, { type ReadOnlyDelivery } from './ReadOnlyDeliveryList';

/* ── Types ── */

type Delivery = ReadOnlyDelivery;

interface ProjectInfo {
    code: string;
    title: string;
    students: string;
    type: string;
    period: string;
    startDate: string;
    endDate: string;
    currentPhase: string;
}

/* ── Mock data (fallback when no projectId is given) ── */

const MOCK_PROJECT: ProjectInfo = {
    code: 'PG-2026-014',
    title: 'Sistema Centralizado de Proyectos de Grado',
    students: 'Carlos Andrés Méndez, Ana Martínez',
    type: 'Aplicación Web',
    period: '2026-01',
    startDate: '03/02/2026',
    endDate: '30/11/2026',
    currentPhase: 'desarrollo',
};

const MOCK_DELIVERIES: Delivery[] = [
    { id: 1, name: 'Avance 1 — Definición', date: '15/03/2026', phase: 'anteproyecto', apiStatus: 'aprobada', status: 'approved', grade: '92' },
    { id: 2, name: 'Avance 2 — Diseño', date: '30/04/2026', phase: 'presentacion_anteproyecto', apiStatus: 'revisada', status: 'corrections', grade: '78' },
    { id: 3, name: 'Avance 3 — Implementación', date: '15/06/2026', phase: 'desarrollo', apiStatus: 'pendiente', status: 'pending', grade: '—' },
    { id: 4, name: 'Entrega Final', date: '30/11/2026', phase: 'presentacion_final', apiStatus: 'pendiente', status: 'pending', grade: '—' },
];

const PHASE_STEP_MAP: Record<string, number> = {
    'anteproyecto': 0,
    'presentacion_anteproyecto': 1,
    'desarrollo': 2,
    'presentacion_final': 3,
};

const PHASE_IDS = ['anteproyecto', 'presentacion_anteproyecto', 'desarrollo', 'presentacion_final'];
const PHASE_LABELS: Record<string, string> = {
    anteproyecto: 'Anteproyecto',
    presentacion_anteproyecto: 'Presentación Anteproyecto',
    desarrollo: 'Desarrollo del proyecto',
    presentacion_final: 'Presentación Final',
};

/* ── Helpers ── */

function formatDate(dateStr: string | null | undefined): string {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('es-CO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    } catch {
        return dateStr;
    }
}

/* ── Component ── */

interface SupervisionReadOnlyProps {
    /** Override the project code shown in the header */
    projectCode?: string;
    /** Override the project title shown in the header */
    projectTitle?: string;
    /** When provided, fetches real project data from the API */
    projectId?: number;
    /** Optional custom back handler; defaults to navigate(-1) */
    onBack?: () => void;
    /** Director ID to include in navigation for proper back behavior */
    directorId?: number;
}

export default function SupervisionReadOnly({ projectCode, projectTitle, projectId, onBack, directorId }: SupervisionReadOnlyProps) {
    const navigate = useNavigate();
    const [projectInfo, setProjectInfo] = useState<ProjectInfo | null>(null);
    const [deliveries, setDeliveries] = useState<Delivery[] | null>(null);
    const [selectedPhaseId, setSelectedPhaseId] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [fetchError, setFetchError] = useState<string | null>(null);

    const fetchProject = useCallback(async () => {
        if (projectId === undefined) return;
        setLoading(true);
        setFetchError(null);
        try {
            const res = await apiFetch(`/api/admin/proyectos/${projectId}`);
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(body?.message ?? `Error ${res.status}`);
            }
            const json = await res.json();
            const p = json.data ?? json;

            setProjectInfo({
                code: p.code,
                title: p.title,
                students: (p.estudiantes ?? []).map((s: { name: string }) => s.name).join(', '),
                type: '',
                period: p.semestre?.name ?? '',
                startDate: formatDate(p.semestre?.start_date),
                endDate: formatDate(p.semestre?.end_date),
                currentPhase: p.current_phase ?? '',
            });

            setDeliveries((p.entregas ?? []).map((e: any) => ({
                id: e.id,
                name: e.title,
                date: formatDate(e.due_date),
                phase: e.phase ?? '',
                // Both values travel together: the raw status resolves the badge
                // label through the canonical map, the layout state drives the
                // row's contextual sentence.
                apiStatus: e.status ?? null,
                status: entregaLayoutStatus(e.status),
                // `grade` is this project's grade, resolved server-side from its
                // own pivot. The template's `consolidated_grade` must NEVER be
                // read here: the entrega row is shared by every project of the
                // semester, so that value describes another project's submission
                // and the coordinator would see a grade that is not this one.
                grade: e.grade != null ? String(e.grade) : '—',
            })));
        } catch (err) {
            setFetchError(err instanceof Error ? err.message : 'Error al cargar proyecto');
        } finally {
            setLoading(false);
        }
    }, [projectId]);

    useEffect(() => {
        fetchProject();
    }, [fetchProject]);

    const isRealData = projectId !== undefined;
    const displayProject = projectInfo ?? (isRealData ? null : MOCK_PROJECT);
    const displayDeliveries = deliveries ?? (isRealData ? [] : MOCK_DELIVERIES);
    const displayTitle = projectTitle ?? displayProject?.title ?? '';
    const displayCode = projectCode ?? displayProject?.code ?? '';
    const currentStep = displayProject?.currentPhase
        ? (PHASE_STEP_MAP[displayProject.currentPhase] ?? 2)
        : 3;

    const allPhases: PhaseStep[] = PHASE_IDS.map((id, idx) => ({
        id,
        label: PHASE_LABELS[id],
        status: idx < currentStep ? 'done' : idx === currentStep ? 'current' : 'future',
    }));

    const activePhaseId = selectedPhaseId ?? (displayProject?.currentPhase ?? PHASE_IDS[0]);
    const filteredDeliveries = displayDeliveries.filter((d) => !activePhaseId || d.phase === activePhaseId);
    const deliveryCountByPhase = (phaseId: string) => displayDeliveries.filter((d) => d.phase === phaseId).length;

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Supervisión"
                title={displayTitle}
                subtitle={displayProject ? `${displayCode} · ${displayProject.students}` : undefined}
                actions={
                    <button
                        onClick={() => (onBack ? onBack() : navigate('/dashboard/coordinador'))}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            {/* Loading state for real data */}
            {isRealData && loading && (
                <div className="flex items-center justify-center py-16" role="status" aria-label="Cargando proyecto">
                    <Loader2 className="h-6 w-6 animate-spin text-[#c2410c]" />
                </div>
            )}

            {/* Error state for real data fetch */}
            {isRealData && fetchError && !loading && (
                <div className="rounded-xl border border-[#fee2e2] bg-[#fee2e2]/40 p-4">
                    <div className="flex items-center gap-3">
                        <AlertTriangle className="h-5 w-5 shrink-0 text-[#dc2626]" />
                        <p className="text-sm text-[#7f1d1d]">{fetchError}</p>
                        <button
                            onClick={fetchProject}
                            className="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-[#dc2626]/30 bg-white px-3 py-1.5 text-xs font-semibold text-[#7f1d1d] transition-colors hover:bg-[#fee2e2]"
                            aria-label="Reintentar"
                        >
                            <RefreshCw className="h-3.5 w-3.5" />
                            Reintentar
                        </button>
                    </div>
                </div>
            )}

            {/* Content: show only when not loading real data, or when using mock */}
            {(!isRealData || (!loading && displayProject)) && displayProject && (
                <>
                    {/* Bezel Header */}
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="flex items-center gap-4">
                                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#fed7aa]">
                                    <Award className="h-7 w-7 text-[#c2410c]" />
                                </div>
                                <div>
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="inline-flex items-center rounded-full bg-[#e7e5e4] px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.03em] text-[#57534e]">
                                            {displayCode}
                                        </span>
                                        {displayProject.period && (
                                            <StatusBadge variant="info">{displayProject.period}</StatusBadge>
                                        )}
                                    </div>
                                    <h2 className="mt-1 text-xl font-bold text-[#1c1917]">{displayTitle}</h2>
                                </div>
                            </div>
                        </div>

                        <hr className="my-5 border-t border-[#e5e5e5]" />

                        {/* Info Cards */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <User className="h-5 w-5 text-[#c2410c]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Estudiante(s)</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">{displayProject.students}</p>
                                </div>
                            </div>
                            {displayProject.type && (
                                <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                    <FileText className="h-5 w-5 text-[#4f46e5]" />
                                    <div>
                                        <p className="text-xs text-[#78716c]">Tipo</p>
                                        <p className="text-sm font-semibold text-[#1c1917]">{displayProject.type}</p>
                                    </div>
                                </div>
                            )}
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <Calendar className="h-5 w-5 text-[#16a34a]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Inicio</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">{displayProject.startDate}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <Clock className="h-5 w-5 text-[#d97706]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Fin</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">{displayProject.endDate}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <PhaseStepper
                        phases={allPhases}
                        selectedPhaseId={activePhaseId}
                        onSelectPhase={setSelectedPhaseId}
                        deliveryCountByPhase={deliveryCountByPhase}
                        title="Progreso del Proyecto"
                    />

                    {/* Read-only Deliveries */}
                    <ReadOnlyDeliveryList
                        title="Entregas"
                        deliveries={filteredDeliveries}
                        hasAnyDeliveries={displayDeliveries.length > 0}
                        openLabel="Ver entrega"
                        openDisabled={() => !isRealData || !projectId}
                        onOpen={(deliveryId) => {
                            if (isRealData && projectId) {
                                navigate(`/directores/proyectos/${projectId}/entregas/${deliveryId}?directorId=${directorId ?? ''}`);
                            }
                        }}
                    />
                </>
            )}
        </div>
    );
}
