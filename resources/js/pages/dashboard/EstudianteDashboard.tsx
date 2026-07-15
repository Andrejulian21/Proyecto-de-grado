import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
    GraduationCap,
    Lock,
    CheckCircle2,
    Clock,
    ChevronDown,
    ChevronUp,
    AlertCircle,
    User,
    Building,
    ArrowRight,
} from 'lucide-react';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { PageHeader } from '@/components/ui/PageHeader';

/* ── Mock data ── */

const MOCK_PROJECT = {
    code: 'PG-2026-014',
    title: 'Sistema predictivo de deserción estudiantil basado en ML',
    director: 'Carlos Andrés Gómez',
    phase: 'presentacion',
};

const MOCK_PHASES = [
    { id: 'anteproyecto', label: 'Anteproyecto', status: 'done' as const },
    { id: 'presentacion', label: 'Presentación', status: 'current' as const },
    { id: 'desarrollo', label: 'Desarrollo', status: 'future' as const },
    { id: 'final', label: 'Final', status: 'future' as const },
];

interface Delivery {
    id: number;
    type: string;
    label: string;
    status: 'approved' | 'pending' | 'locked';
    deadline: string;
    versions: { version: number; date: string; status: 'approved' | 'pending' | 'rejected'; fileName: string }[];
}

const MOCK_DELIVERIES: Delivery[] = [
    {
        id: 1,
        type: 'anteproyecto',
        label: 'Documento de Anteproyecto',
        status: 'approved',
        deadline: '15/03/2026',
        versions: [
            { version: 2, date: '10/03/2026', status: 'approved', fileName: 'anteproyecto_v2.pdf' },
            { version: 1, date: '01/03/2026', status: 'rejected', fileName: 'anteproyecto_v1.pdf' },
        ],
    },
    {
        id: 2,
        type: 'presentacion',
        label: 'Presentación Anteproyecto',
        status: 'pending',
        deadline: '10/04/2026',
        versions: [],
    },
    {
        id: 6,
        type: 'presentacion',
        label: 'Diapositivas de Presentación',
        status: 'pending',
        deadline: '20/04/2026',
        versions: [
            { version: 1, date: '05/04/2026', status: 'pending', fileName: 'slides_v1.pptx' },
        ],
    },
    {
        id: 3,
        type: 'desarrollo',
        label: 'Informe de Avance 1',
        status: 'locked',
        deadline: '15/06/2026',
        versions: [],
    },
    {
        id: 4,
        type: 'desarrollo',
        label: 'Informe de Avance 2',
        status: 'locked',
        deadline: '15/08/2026',
        versions: [],
    },
    {
        id: 5,
        type: 'final',
        label: 'Documento Final de Grado',
        status: 'locked',
        deadline: '15/12/2026',
        versions: [],
    },
];

/* ── DeliveryAccordion subcomponent ── */

function DeliveryAccordion({ delivery }: { delivery: Delivery }) {
    const [expanded, setExpanded] = useState(false);

    const statusIcon = {
        approved: <CheckCircle2 className="h-5 w-5 text-[#16a34a]" />,
        pending: <Clock className="h-5 w-5 text-[#d97706]" />,
        locked: <Lock className="h-5 w-5 text-[#78716c]" />,
    };

    const statusLabel: Record<string, string> = {
        approved: 'Aprobado',
        pending: 'Pendiente',
        locked: 'Bloqueado',
    };

    return (
        <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <button
                type="button"
                onClick={() => setExpanded(!expanded)}
                className="flex w-full items-center gap-3 p-4 text-left transition-colors hover:bg-[#f5f5f4]"
                aria-expanded={expanded}
            >
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#f5f5f4]">
                    {statusIcon[delivery.status]}
                </div>
                <div className="flex flex-1 flex-col gap-0.5 min-w-0">
                    <span className="text-sm font-semibold text-[#1c1917]">{delivery.label}</span>
                    <span className="text-xs text-[#57534e]">
                        {delivery.status === 'locked' ? `Disponible: ${delivery.deadline}` : `Límite: ${delivery.deadline}`}
                    </span>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <StatusBadge variant={delivery.status === 'approved' ? 'success' : delivery.status === 'pending' ? 'warning' : 'inactivo'}>
                        {statusLabel[delivery.status]}
                    </StatusBadge>
                    {expanded ? (
                        <ChevronUp className="h-4 w-4 text-[#57534e]" />
                    ) : (
                        <ChevronDown className="h-4 w-4 text-[#57534e]" />
                    )}
                </div>
            </button>

            {expanded && delivery.versions.length > 0 && (
                <div className="border-t border-[#e5e5e5]">
                    <table className="w-full text-left text-sm tabular-nums">
                        <thead className="bg-[#f5f5f4] text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                            <tr>
                                <th className="px-4 py-2.5">Versión</th>
                                <th className="px-4 py-2.5">Fecha</th>
                                <th className="px-4 py-2.5">Archivo</th>
                                <th className="px-4 py-2.5">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {delivery.versions.map((v) => (
                                <tr key={v.version} className="border-b border-[#e5e5e5] last:border-none">
                                    <td className="px-4 py-2.5 font-medium text-[#1c1917]">v{v.version}</td>
                                    <td className="px-4 py-2.5 text-[#57534e]">{v.date}</td>
                                    <td className="px-4 py-2.5 text-[#57534e]">{v.fileName}</td>
                                    <td className="px-4 py-2.5">
                                        <StatusBadge variant={v.status === 'approved' ? 'success' : v.status === 'rejected' ? 'error' : 'warning'}>
                                            {v.status === 'approved' ? 'Aprobado' : v.status === 'rejected' ? 'Rechazado' : 'Pendiente'}
                                        </StatusBadge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {expanded && delivery.versions.length === 0 && (
                <div className="border-t border-[#e5e5e5] px-4 py-6 text-center text-sm text-[#78716c]">
                    {delivery.status === 'pending'
                        ? 'Aún no hay versiones registradas para esta entrega.'
                        : 'Esta entrega no está disponible aún.'}
                </div>
            )}

            {expanded && (
                <div className="border-t border-[#e5e5e5] px-4 py-3">
                    <Link
                        to={`/mi-proyecto/entregas/${delivery.id}`}
                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] px-4 py-2.5 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                    >
                        Ver detalle de entrega
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            )}
        </div>
    );
}

/* ── Main component ── */

export default function EstudianteDashboard() {
    const [selectedPhaseId, setSelectedPhaseId] = useState(MOCK_PROJECT.phase);

    const selectedPhase = MOCK_PHASES.find((p) => p.id === selectedPhaseId) ?? MOCK_PHASES[0];

    const filteredDeliveries = useMemo(
        () => MOCK_DELIVERIES.filter((d) => d.type === selectedPhaseId),
        [selectedPhaseId],
    );

    const pendingInPhase = filteredDeliveries.find((d) => d.status === 'pending');

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Proyecto Activo"
                title="Mi Proyecto de Grado"
                subtitle="Gestiona las entregas y el progreso de tu proyecto de grado"
            />

            {/* ── Hero card ── */}
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-4">
                        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#fed7aa]">
                            <GraduationCap className="h-7 w-7 text-[#c2410c]" />
                        </div>
                        <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="text-xs font-bold uppercase tracking-[0.05em] text-[#c2410c]">
                                    {MOCK_PROJECT.code}
                                </span>
                                <StatusBadge variant="en-curso">En Curso</StatusBadge>
                            </div>
                            <h3 className="text-lg font-bold text-[#1c1917]">{MOCK_PROJECT.title}</h3>
                            <div className="flex items-center gap-4 text-sm text-[#57534e] flex-wrap">
                                <span className="flex items-center gap-1.5">
                                    <User className="h-3.5 w-3.5" />
                                    Director: {MOCK_PROJECT.director}
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <Building className="h-3.5 w-3.5" />
                                    Ingeniería de Sistemas
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* ── Phase stepper (clickable) ── */}
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <h3 className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-[#57534e]">
                    Fases del Proyecto
                </h3>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    {MOCK_PHASES.map((phase, idx) => {
                        const isSelected = selectedPhaseId === phase.id;
                        const count = MOCK_DELIVERIES.filter((d) => d.type === phase.id).length;

                        return (
                            <div key={phase.id} className="flex items-center gap-3 sm:flex-1 sm:flex-col sm:items-center sm:text-center">
                                <button
                                    type="button"
                                    onClick={() => setSelectedPhaseId(phase.id)}
                                    className={`flex items-center gap-3 rounded-xl p-2 transition-colors sm:flex-col sm:items-center sm:gap-1 ${
                                        isSelected
                                            ? 'bg-[#fed7aa]/60 ring-2 ring-[#c2410c]/30'
                                            : 'hover:bg-[#f5f5f4]'
                                    }`}
                                    aria-pressed={isSelected}
                                >
                                    <div
                                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold transition-colors ${
                                            phase.status === 'done'
                                                ? 'bg-[#dcfce7] text-[#16a34a]'
                                                : phase.status === 'current'
                                                  ? 'bg-[#fed7aa] text-[#c2410c]'
                                                  : 'bg-[#e7e5e4] text-[#78716c]'
                                        }`}
                                    >
                                        {phase.status === 'done' ? (
                                            <CheckCircle2 className="h-5 w-5" />
                                        ) : (
                                            <span>{idx + 1}</span>
                                        )}
                                    </div>
                                    <div className="flex flex-col items-start sm:items-center">
                                        <span
                                            className={`text-sm font-semibold ${
                                                isSelected || phase.status === 'current'
                                                    ? 'text-[#c2410c]'
                                                    : 'text-[#57534e]'
                                            }`}
                                        >
                                            {phase.label}
                                        </span>
                                        <span className="text-[11px] text-[#78716c]">
                                            {count} {count === 1 ? 'entrega' : 'entregas'}
                                        </span>
                                    </div>
                                </button>
                                {idx < MOCK_PHASES.length - 1 && (
                                    <div className="hidden h-px flex-1 bg-[#e5e5e5] sm:block" />
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* ── Delivery list for selected phase ── */}
            <div className="flex flex-col gap-3">
                <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <h3 className="text-sm font-bold uppercase tracking-[0.05em] text-[#57534e]">
                        Entregas — {selectedPhase.label} ({filteredDeliveries.length})
                    </h3>
                    <p className="text-xs text-[#78716c]">
                        Selecciona una fase arriba para ver sus entregas
                    </p>
                </div>

                {filteredDeliveries.length > 0 ? (
                    filteredDeliveries.map((del) => (
                        <DeliveryAccordion key={del.id} delivery={del} />
                    ))
                ) : (
                    <div className="rounded-xl border border-dashed border-[#d6d3d1] bg-white px-4 py-10 text-center text-sm text-[#78716c]">
                        No hay entregas configuradas para la fase <strong>{selectedPhase.label}</strong>.
                    </div>
                )}
            </div>

            {/* ── Activity hint ── */}
            {pendingInPhase && (
                <div className="flex items-start gap-3 rounded-xl border border-[#dbeafe] bg-[#dbeafe]/40 p-4">
                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-[#2563eb]" />
                    <p className="text-sm text-[#1e3a8a]">
                        Tienes una entrega pendiente en <strong>{selectedPhase.label}</strong>:{' '}
                        <strong>{pendingInPhase.label}</strong>. La fecha límite es el{' '}
                        <strong>{pendingInPhase.deadline}</strong>. Usa la pantalla de detalle para gestionar versiones y revisión.
                    </p>
                </div>
            )}
        </div>
    );
}
