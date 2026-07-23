import DeliveryTimelineStatusBadge from '@/components/entregas/DeliveryTimelineStatusBadge';
import type { DeliveryTimelineStatus } from '@/types/entregas';

const LEGEND_STATUSES: DeliveryTimelineStatus[] = [
    'overdue',
    'late',
    'not_delivered',
    'on_time',
];

export default function DeliveryTimelineStatusLegend() {
    return (
        <div
            className="flex flex-wrap items-center gap-2"
            aria-label="Leyenda de estados de entrega"
        >
            <span className="text-[11px] font-bold uppercase tracking-[0.05em] text-[#78716c]">
                Plazo:
            </span>
            {LEGEND_STATUSES.map((status) => (
                <DeliveryTimelineStatusBadge key={status} status={status} />
            ))}
        </div>
    );
}
