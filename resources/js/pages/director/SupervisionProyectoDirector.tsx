import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ArrowLeft, ChevronDown, ChevronRight, Calendar, User, FileText, Clock, Award } from 'lucide-react';

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
    student: string;
    type: string;
    period: string;
    startDate: string;
    endDate: string;
}

const MOCK_PROJECT: ProjectInfo = {
    code: 'PG-2026-014',
    title: 'Sistema Centralizado de Proyectos de Grado',
    student: 'Carlos Andrés Méndez',
    type: 'Aplicación Web',
    period: '2026-01',
    startDate: '03/02/2026',
    endDate: '30/11/2026',
};

const MOCK_DELIVERIES: Delivery[] = [
    { id: 1, name: 'Avance 1 — Definición', date: '15/03/2026', status: 'approved', grade: '92' },
    { id: 2, name: 'Avance 2 — Diseño', date: '30/04/2026', status: 'corrections', grade: '78' },
    { id: 3, name: 'Avance 3 — Implementación', date: '15/06/2026', status: 'pending', grade: '—' },
    { id: 4, name: 'Entrega Final', date: '30/11/2026', status: 'pending', grade: '—' },
];

const STEP_LABELS = [
    'Inscripción',
    'Avance 1',
    'Avance 2',
    'Avance 3',
    'Final',
];

const statusConfig: Record<string, { label: string; variant: 'success' | 'warning' | 'error' | 'info' | 'inactivo' }> = {
    approved: { label: 'Aprobado', variant: 'success' },
    pending: { label: 'Pendiente', variant: 'warning' },
    corrections: { label: 'Correcciones', variant: 'error' },
    rejected: { label: 'Rechazado', variant: 'error' },
};

export default function SupervisionProyectoDirector() {
    const navigate = useNavigate();
    const [expandedDelivery, setExpandedDelivery] = useState<number | null>(null);
    const currentStep = 3;

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Supervisión"
                title={MOCK_PROJECT.title}
                subtitle={`${MOCK_PROJECT.code} · ${MOCK_PROJECT.student}`}
                actions={
                    <button
                        onClick={() => navigate('/dashboard/director')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

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
                                    {MOCK_PROJECT.code}
                                </span>
                                <StatusBadge variant="info">{MOCK_PROJECT.period}</StatusBadge>
                            </div>
                            <h2 className="mt-1 text-xl font-bold text-[#1c1917]">{MOCK_PROJECT.title}</h2>
                        </div>
                    </div>
                </div>

                <hr className="my-5 border-t border-[#e5e5e5]" />

                {/* Info Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                        <User className="h-5 w-5 text-[#c2410c]" />
                        <div>
                            <p className="text-xs text-[#78716c]">Estudiante</p>
                            <p className="text-sm font-semibold text-[#1c1917]">{MOCK_PROJECT.student}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                        <FileText className="h-5 w-5 text-[#4f46e5]" />
                        <div>
                            <p className="text-xs text-[#78716c]">Tipo</p>
                            <p className="text-sm font-semibold text-[#1c1917]">{MOCK_PROJECT.type}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                        <Calendar className="h-5 w-5 text-[#16a34a]" />
                        <div>
                            <p className="text-xs text-[#78716c]">Inicio</p>
                            <p className="text-sm font-semibold text-[#1c1917]">{MOCK_PROJECT.startDate}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                        <Clock className="h-5 w-5 text-[#d97706]" />
                        <div>
                            <p className="text-xs text-[#78716c]">Fin</p>
                            <p className="text-sm font-semibold text-[#1c1917]">{MOCK_PROJECT.endDate}</p>
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

            {/* Deliveries */}
            <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="border-b border-[#e5e5e5] px-6 py-4">
                    <h3 className="text-base font-bold text-[#1c1917]">Entregas</h3>
                </div>
                <div className="divide-y divide-[#e5e5e5]">
                    {MOCK_DELIVERIES.map((d) => {
                        const config = statusConfig[d.status];
                        const isExpanded = expandedDelivery === d.id;
                        return (
                            <div key={d.id}>
                                <button
                                    onClick={() => setExpandedDelivery(isExpanded ? null : d.id)}
                                    className="flex w-full items-center justify-between gap-4 px-6 py-4 text-left transition-colors hover:bg-[#fafaf9]"
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
                                                    : 'Entrega revisada y aprobada.'}
                                            </p>
                                            <div className="flex items-center gap-2">
                                                <button className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]">
                                                    Ver entrega
                                                </button>
                                                <button className="inline-flex min-h-[36px] items-center gap-2 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]">
                                                    Revisar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
