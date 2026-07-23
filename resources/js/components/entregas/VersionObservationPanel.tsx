import { Calendar, MessageSquareText } from 'lucide-react';
import { StatusBadge } from '@/components/ui/StatusBadge';
import type { DeliveryVersionMock } from '@/types/entregas';
import { REVIEW_STATUS_LABELS, REVIEW_STATUS_VARIANTS } from '@/types/entregas';

interface VersionObservationPanelProps {
    version: DeliveryVersionMock;
}

function formatDateTime(iso: string | null): string {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString('es-CO', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return iso;
    }
}

export default function VersionObservationPanel({ version }: VersionObservationPanelProps) {
    const { observation } = version;

    return (
        <div className="rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-4">
            <div className="mb-3 flex items-start justify-between gap-2">
                <div className="flex items-center gap-2">
                    <MessageSquareText className="h-4 w-4 text-[#c2410c]" />
                    <span className="text-sm font-bold text-[#1c1917]">
                        Observación — Versión {version.versionNumber}
                    </span>
                </div>
                <StatusBadge variant={REVIEW_STATUS_VARIANTS[observation.reviewStatus]}>
                    {REVIEW_STATUS_LABELS[observation.reviewStatus]}
                </StatusBadge>
            </div>

            <div className="mb-3 space-y-1 text-xs text-[#78716c]">
                <p className="flex items-center gap-1">
                    <Calendar className="h-3 w-3" />
                    Entregada: {formatDateTime(version.uploadedAt)}
                </p>
                {observation.reviewedAt && (
                    <p className="flex items-center gap-1">
                        <Calendar className="h-3 w-3" />
                        Observación registrada: {formatDateTime(observation.reviewedAt)}
                    </p>
                )}
            </div>

            {observation.text?.trim() ? (
                <div className="rounded-md bg-white p-3">
                    <p className="whitespace-pre-wrap text-sm leading-relaxed text-[#1c1917]">
                        {observation.text}
                    </p>
                </div>
            ) : (
                <p className="text-xs italic text-[#a8a29e]">
                    Sin observaciones del director para esta versión.
                </p>
            )}
        </div>
    );
}
