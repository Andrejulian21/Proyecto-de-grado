import { ChevronDown, ChevronRight } from 'lucide-react';
import DeliveryTimelineStatusBadge from '@/components/entregas/DeliveryTimelineStatusBadge';
import type { DeliveryTimelineStatus } from '@/types/entregas';
import { TIMELINE_STATUS_LABELS } from '@/types/entregas';

export interface DeliverySupervisionItem {
    id: number;
    title: string;
    dueDate: string;
    phase: string;
    timelineStatus: DeliveryTimelineStatus;
    grade?: string | number | null;
}

interface DeliverySupervisionRowProps {
    delivery: DeliverySupervisionItem;
    isExpanded: boolean;
    onToggle: () => void;
    onReview: () => void;
}

function formatDueDate(iso: string): string {
    try {
        return new Date(iso).toLocaleDateString('es-CO', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return iso;
    }
}

const EXPANDED_HINT: Record<DeliveryTimelineStatus, string> = {
    not_delivered: 'El estudiante aún está dentro del plazo y no ha registrado entrega.',
    on_time: 'Entrega recibida dentro del plazo. Puede revisar versiones y observaciones.',
    late: 'Entrega recibida después del vencimiento. Revise versiones y retroalimentación.',
    overdue: 'Plazo vencido sin entrega registrada. Requiere seguimiento prioritario.',
};

export default function DeliverySupervisionRow({
    delivery,
    isExpanded,
    onToggle,
    onReview,
}: DeliverySupervisionRowProps) {
    const isCritical = delivery.timelineStatus === 'overdue';

    return (
        <div
            className={
                isCritical
                    ? 'border-l-4 border-l-[#dc2626] bg-[#fef2f2]/30'
                    : undefined
            }
        >
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-center justify-between gap-4 px-6 py-4 text-left transition-colors hover:bg-[#fafaf9]"
                aria-expanded={isExpanded}
            >
                <div className="flex min-w-0 items-center gap-4">
                    {isExpanded ? (
                        <ChevronDown className="h-4 w-4 shrink-0 text-[#78716c]" />
                    ) : (
                        <ChevronRight className="h-4 w-4 shrink-0 text-[#78716c]" />
                    )}
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-[#1c1917]">
                            {delivery.title}
                        </p>
                        <p className="text-xs text-[#78716c]">
                            Límite: {formatDueDate(delivery.dueDate)}
                        </p>
                    </div>
                </div>
                <div className="flex shrink-0 items-center gap-3">
                    <DeliveryTimelineStatusBadge status={delivery.timelineStatus} />
                    {delivery.grade != null && (
                        <span className="text-sm font-bold tabular-nums text-[#1c1917]">
                            {delivery.grade}
                        </span>
                    )}
                </div>
            </button>
            {isExpanded && (
                <div className="border-t border-[#e5e5e5] bg-[#fafaf9] px-6 py-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-[#57534e]">
                            {EXPANDED_HINT[delivery.timelineStatus]}
                        </p>
                        <button
                            type="button"
                            onClick={onReview}
                            className="inline-flex min-h-[36px] shrink-0 items-center gap-2 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#9a330a]"
                        >
                            Revisar
                        </button>
                    </div>
                    <p className="mt-2 text-xs text-[#a8a29e]">
                        Estado de plazo: {TIMELINE_STATUS_LABELS[delivery.timelineStatus]}
                    </p>
                </div>
            )}
        </div>
    );
}
