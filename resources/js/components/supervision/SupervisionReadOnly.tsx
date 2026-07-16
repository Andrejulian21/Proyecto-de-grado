import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import {
    ArrowLeft, Award, User, FileText, Calendar, Clock,
    ChevronDown, ChevronRight, Loader2, AlertTriangle, RefreshCw,
} from 'lucide-react';
import { useEffect, useState, useCallback } from 'react';
import { apiFetch } from '@/lib/utils';

/* ── Types ── */

interface Delivery {
    id: number;
    name: string;
    date: string;
    status: 'approved' | 'pending' | 'corrections' | 'rejected';
    grade: string;
}

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
    { id: 1, name: 'Avance 1 — Definición', date: '15/03/2026', status: 'approved', grade: '92' },
    { id: 2, name: 'Avance 2 — Diseño', date: '30/04/2026', status: 'corrections', grade: '78' },
    { id: 3, name: 'Avance 3 — Implementación', date: '15/06/2026', status: 'pending', grade: '—' },
    { id: 4, name: 'Entrega Final', date: '30/11/2026', status: 'pending', grade: '—' },
];

const STEP_LABELS = ['Anteproyecto', 'Presentación Anteproyecto', 'Desarrollo', 'Presentación Final'];

const PHASE_STEP_MAP: Record<string, number> = {
    'anteproyecto': 0,
    'presentacion_anteproyecto': 1,
    'desarrollo': 2,
    'presentacion_final': 3,
};

const statusConfig: Record<string, { label: string; variant: 'success' | 'warning' | 'error' | 'info' | 'inactivo' }> = {
    approved: { label: 'Aprobado', variant: 'success' },
    pending: { label: 'Pendiente', variant: 'warning' },
    corrections: { label: 'Correcciones', variant: 'error' },
    rejected: { label: 'Rechazado', variant: 'error' },
};

/* ── Helpers ── */

function mapEntregaStatus(status: string): Delivery['status'] {
    switch (status) {
        case 'aprobada': return 'approved';
        case 'rechazada': return 'rejected';
        case 'revisada': return 'corrections';
        default: return 'pending';
    }
}

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
}

export default function SupervisionReadOnly({ projectCode, projectTitle, projectId, onBack }: SupervisionReadOnlyProps) {
    const navigate = useNavigate();
    const [expandedDelivery, setExpandedDelivery] = useState<number | null>(null);
    const [projectInfo, setProjectInfo] = useState<ProjectInfo | null>(null);
    const [deliveries, setDeliveries] = useState<Delivery[] | null>(null);
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
                status: mapEntregaStatus(e.status),
                grade: e.consolidated_grade != null ? String(e.consolidated_grade) : '—',
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

                    {/* Stepper */}
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <h3 className="mb-5 text-base font-bold text-[#1c1917]">Progreso del Proyecto</h3>
                        <div className="flex flex-col gap-1 sm:flex-row sm:items-center">
                            {STEP_LABELS.map((label, idx) => {
                                const isCompleted = idx < currentStep;
                                const isCurrent = idx === currentStep;
                                return (
                                    <div key={idx} className="flex items-center sm:flex-1">
                                        <div className="flex items-center gap-2 sm:flex-col sm:items-center sm:gap-1">
                                            <div
                                                className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold transition-colors ${
                                                    isCompleted
                                                        ? 'bg-[#c2410c] text-white'
                                                        : isCurrent
                                                            ? 'border-2 border-[#c2410c] bg-white text-[#c2410c]'
                                                            : 'border-2 border-[#e5e5e5] bg-white text-[#78716c]'
                                                }`}
                                            >
                                                {isCompleted ? (
                                                    <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                                                        <polyline points="20 6 9 17 4 12" />
                                                    </svg>
                                                ) : (
                                                    idx + 1
                                                )}
                                            </div>
                                            <span
                                                className={`text-xs font-semibold whitespace-nowrap ${
                                                    isCurrent ? 'text-[#c2410c]' : 'text-[#78716c]'
                                                }`}
                                            >
                                                {label}
                                            </span>
                                        </div>
                                        {idx < STEP_LABELS.length - 1 && (
                                            <div
                                                className={`mx-3 h-px flex-1 sm:mb-6 ${
                                                    idx < currentStep ? 'bg-[#c2410c]' : 'bg-[#e5e5e5]'
                                                }`}
                                            />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Read-only Deliveries */}
                    <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="border-b border-[#e5e5e5] px-6 py-4">
                            <h3 className="text-base font-bold text-[#1c1917]">Entregas</h3>
                        </div>
                        <div className="divide-y divide-[#e5e5e5]">
                            {displayDeliveries.map((d) => {
                                const config = statusConfig[d.status];
                                const isExpanded = expandedDelivery === d.id;
                                return (
                                    <div key={d.id}>
                                        <button
                                            onClick={() => setExpandedDelivery(isExpanded ? null : d.id)}
                                            className="flex w-full items-center justify-between gap-4 px-6 py-4 text-left transition-colors hover:bg-[#fafaf9]"
                                            aria-expanded={isExpanded}
                                            aria-label={`Entrega: ${d.name}`}
                                        >
                                            <div className="flex items-center gap-4 min-w-0">
                                                {isExpanded ? (
                                                    <ChevronDown className="h-4 w-4 shrink-0 text-[#78716c]" />
                                                ) : (
                                                    <ChevronRight className="h-4 w-4 shrink-0 text-[#78716c]" />
                                                )}
                                                <div className="min-w-0">
                                                    <p className="text-sm font-semibold text-[#1c1917] truncate">{d.name}</p>
                                                    <p className="text-xs text-[#78716c]">{d.date}</p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3 shrink-0">
                                                <StatusBadge variant={config.variant}>{config.label}</StatusBadge>
                                                <span className="text-sm font-bold text-[#1c1917] tabular-nums">{d.grade}</span>
                                            </div>
                                        </button>
                                        {isExpanded && (
                                            <div className="border-t border-[#e5e5e5] bg-[#fafaf9] px-6 py-4">
                                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                    <p className="text-sm text-[#57534e]">
                                                        {d.status === 'pending'
                                                            ? 'El estudiante aún no ha realizado esta entrega.'
                                                            : d.status === 'corrections'
                                                                ? 'Se solicitaron correcciones. Pendiente de re-entrega.'
                                                                : d.status === 'approved'
                                                                    ? 'Entrega revisada y aprobada.'
                                                                    : 'Entrega rechazada.'}
                                                    </p>
                                                    {/* Read-only: only "Ver entrega" button, no "Revisar" or signature controls */}
                                                    <div className="flex items-center gap-2">
                                                        <button
                                                            className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                                                            aria-label={`Ver detalle de ${d.name}`}
                                                        >
                                                            Ver entrega
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                            {displayDeliveries.length === 0 && !loading && (
                                <div className="px-6 py-12 text-center text-sm text-[#a8a29e]">
                                    No hay entregas registradas para este proyecto.
                                </div>
                            )}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
