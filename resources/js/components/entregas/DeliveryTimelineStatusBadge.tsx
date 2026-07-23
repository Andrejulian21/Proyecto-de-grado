import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    Clock,
    type LucideIcon,
} from 'lucide-react';
import { StatusBadge } from '@/components/ui/StatusBadge';
import type { DeliveryTimelineStatus } from '@/types/entregas';
import { TIMELINE_STATUS_LABELS, TIMELINE_STATUS_VARIANTS } from '@/types/entregas';

const TIMELINE_ICONS: Record<DeliveryTimelineStatus, LucideIcon> = {
    not_delivered: Clock,
    on_time: CheckCircle2,
    late: AlertTriangle,
    overdue: AlertCircle,
};

interface DeliveryTimelineStatusBadgeProps {
    status: DeliveryTimelineStatus;
    showIcon?: boolean;
    className?: string;
}

export default function DeliveryTimelineStatusBadge({
    status,
    showIcon = true,
    className,
}: DeliveryTimelineStatusBadgeProps) {
    const Icon = TIMELINE_ICONS[status];
    const variant = TIMELINE_STATUS_VARIANTS[status];
    const label = TIMELINE_STATUS_LABELS[status];

    return (
        <StatusBadge variant={variant} className={className}>
            {showIcon && <Icon className="mr-1 inline h-3 w-3" aria-hidden />}
            {label}
        </StatusBadge>
    );
}
