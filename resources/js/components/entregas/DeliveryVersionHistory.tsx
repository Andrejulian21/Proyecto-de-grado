import { Calendar, ChevronRight } from 'lucide-react';
import { StatusBadge } from '@/components/ui/StatusBadge';
import type { DeliveryVersionMock } from '@/types/entregas';
import {
    REVIEW_STATUS_LABELS,
    REVIEW_STATUS_VARIANTS,
    hasVersionObservation,
    sortVersionsDesc,
} from '@/types/entregas';
import { VersionReviewIndicator } from '@/components/entregas/DeliveryVersionSelector';

interface DeliveryVersionHistoryProps {
    versions: DeliveryVersionMock[];
    selectedVersionId: number | null;
    onSelect: (versionId: number) => void;
}

function formatDateTime(iso: string): string {
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

export default function DeliveryVersionHistory({
    versions,
    selectedVersionId,
    onSelect,
}: DeliveryVersionHistoryProps) {
    const sorted = sortVersionsDesc(versions);

    if (sorted.length === 0) {
        return (
            <p className="text-sm text-[#78716c] italic">No hay versiones registradas.</p>
        );
    }

    return (
        <div className="flex flex-col gap-2" role="list" aria-label="Historial de versiones">
            <h4 className="text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                Historial de versiones
            </h4>
            <ul className="flex flex-col gap-2">
                {sorted.map((v) => {
                    const isActive = v.id === selectedVersionId;
                    const excerpt = v.observation.text?.trim()
                        ? v.observation.text.slice(0, 80) + (v.observation.text.length > 80 ? '…' : '')
                        : 'Sin observaciones';

                    return (
                        <li key={v.id}>
                            <button
                                type="button"
                                onClick={() => onSelect(v.id)}
                                className={`flex w-full items-start gap-3 rounded-lg border p-3 text-left transition-colors ${
                                    isActive
                                        ? 'border-[#c2410c] bg-[#fff7ed]'
                                        : 'border-[#e5e5e5] bg-white hover:bg-[#fafaf9]'
                                }`}
                                aria-current={isActive ? 'true' : undefined}
                            >
                                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-semibold text-[#1c1917]">
                                            Versión {v.versionNumber}
                                        </span>
                                        <StatusBadge variant={REVIEW_STATUS_VARIANTS[v.observation.reviewStatus]}>
                                            {REVIEW_STATUS_LABELS[v.observation.reviewStatus]}
                                        </StatusBadge>
                                        <VersionReviewIndicator version={v} />
                                    </div>
                                    <p className="flex items-center gap-1 text-xs text-[#78716c]">
                                        <Calendar className="h-3 w-3 shrink-0" />
                                        {formatDateTime(v.uploadedAt)}
                                    </p>
                                    <p
                                        className={`text-xs leading-relaxed ${
                                            hasVersionObservation(v)
                                                ? 'text-[#57534e]'
                                                : 'italic text-[#a8a29e]'
                                        }`}
                                    >
                                        {excerpt}
                                    </p>
                                </div>
                                <ChevronRight
                                    className={`mt-1 h-4 w-4 shrink-0 ${
                                        isActive ? 'text-[#c2410c]' : 'text-[#d6d3d1]'
                                    }`}
                                    aria-hidden
                                />
                            </button>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
