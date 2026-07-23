import { useState } from 'react';
import { CheckCircle2, Clock, Lock, ChevronDown, ChevronUp, MessageSquare, MessageSquareOff } from 'lucide-react';
import { StatusBadge } from '@/components/ui/StatusBadge';
import type { EntregaData, VersionData } from '@/types/estudiante';

const iconMap: Record<string, React.ReactNode> = {
    approved: <CheckCircle2 className="h-5 w-5 text-[#16a34a]" />,
    pending: <Clock className="h-5 w-5 text-[#d97706]" />,
    enviada: <Clock className="h-5 w-5 text-[#0891b2]" />,
    locked: <Lock className="h-5 w-5 text-[#78716c]" />,
};

const labelMap: Record<string, string> = {
    approved: 'Aprobado',
    pending: 'Pendiente',
    enviada: 'Enviada',
    locked: 'Bloqueado',
};

const badgeVar: Record<string, 'success' | 'warning' | 'inactivo' | 'info'> = {
    approved: 'success',
    pending: 'warning',
    enviada: 'info',
    locked: 'inactivo',
};

function observationLabel(v: VersionData): string {
    if (v.reviewStatus === 'aprobada') return 'Revisada';
    if (v.reviewStatus === 'necesita_ajustes') return 'Con observaciones';
    if (v.hasObservation) return 'Revisada';
    return 'Pendiente';
}

function observationVariant(v: VersionData): 'success' | 'warning' | 'info' {
    if (v.reviewStatus === 'aprobada') return 'success';
    if (v.reviewStatus === 'necesita_ajustes') return 'warning';
    if (v.hasObservation) return 'success';
    return 'warning';
}

export default function DeliveryAccordion({ delivery }: { delivery: EntregaData }) {
    const [expanded, setExpanded] = useState(false);

    return (
        <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <button
                type="button"
                onClick={() => setExpanded(!expanded)}
                className="flex w-full items-center gap-3 p-4 text-left transition-colors hover:bg-[#f5f5f4]"
                aria-expanded={expanded}
            >
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#f5f5f4]">
                    {iconMap[delivery.status]}
                </div>
                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span className="text-sm font-semibold text-[#1c1917]">{delivery.label}</span>
                    <span className="text-xs text-[#57534e]">
                        {delivery.status === 'locked'
                            ? `Disponible: ${delivery.deadline}`
                            : `Límite: ${delivery.deadline}`}
                    </span>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <StatusBadge variant={badgeVar[delivery.status]}>
                        {labelMap[delivery.status]}
                    </StatusBadge>
                    {expanded ? (
                        <ChevronUp className="h-4 w-4 text-[#57534e]" />
                    ) : (
                        <ChevronDown className="h-4 w-4 text-[#57534e]" />
                    )}
                </div>
            </button>

            {expanded && delivery.versions.length > 0 && (
                <div className="overflow-x-auto border-t border-[#e5e5e5]">
                    <table className="w-full min-w-[480px] text-left text-sm tabular-nums">
                        <thead className="bg-[#f5f5f4] text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                            <tr>
                                <th className="px-4 py-2.5">Versión</th>
                                <th className="px-4 py-2.5">Fecha</th>
                                <th className="px-4 py-2.5">Archivo</th>
                                <th className="px-4 py-2.5">Estado</th>
                                <th className="px-4 py-2.5">Observación</th>
                            </tr>
                        </thead>
                        <tbody>
                            {delivery.versions.map((v: VersionData) => (
                                <tr key={v.version} className="border-b border-[#e5e5e5] last:border-none">
                                    <td className="px-4 py-2.5 font-medium text-[#1c1917]">v{v.version}</td>
                                    <td className="px-4 py-2.5 text-[#57534e]">{v.date}</td>
                                    <td className="px-4 py-2.5 text-[#57534e]">{v.fileName}</td>
                                    <td className="px-4 py-2.5">
                                        <StatusBadge
                                            variant={
                                                v.status === 'approved'
                                                    ? 'success'
                                                    : v.status === 'rejected'
                                                      ? 'error'
                                                      : 'warning'
                                            }
                                        >
                                            {v.status === 'approved'
                                                ? 'Aprobado'
                                                : v.status === 'rejected'
                                                  ? 'Rechazado'
                                                  : 'Pendiente'}
                                        </StatusBadge>
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <div className="flex items-center gap-1.5">
                                            {v.hasObservation || v.reviewStatus === 'aprobada' || v.reviewStatus === 'necesita_ajustes' ? (
                                                <MessageSquare className="h-3.5 w-3.5 text-[#16a34a]" aria-hidden />
                                            ) : (
                                                <MessageSquareOff className="h-3.5 w-3.5 text-[#d97706]" aria-hidden />
                                            )}
                                            <StatusBadge variant={observationVariant(v)}>
                                                {observationLabel(v)}
                                            </StatusBadge>
                                        </div>
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
                        ? 'Aún no has subido ninguna versión.'
                        : 'Esta entrega no está disponible aún.'}
                </div>
            )}
        </div>
    );
}
