import { MessageSquare, MessageSquareOff } from 'lucide-react';
import type { DeliveryVersionMock } from '@/types/entregas';
import { hasVersionObservation, sortVersionsDesc } from '@/types/entregas';

interface DeliveryVersionSelectorProps {
    versions: DeliveryVersionMock[];
    selectedVersionId: number | null;
    onSelect: (versionId: number) => void;
}

export default function DeliveryVersionSelector({
    versions,
    selectedVersionId,
    onSelect,
}: DeliveryVersionSelectorProps) {
    const sorted = sortVersionsDesc(versions);

    if (sorted.length === 0) {
        return null;
    }

    if (sorted.length > 4) {
        const selectedIdx = sorted.findIndex((v) => v.id === selectedVersionId);
        return (
            <select
                value={selectedIdx >= 0 ? selectedIdx : 0}
                onChange={(e) => {
                    const v = sorted[Number(e.target.value)];
                    if (v) onSelect(v.id);
                }}
                className="rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] focus:border-[#c2410c] focus:outline-none focus:ring-1 focus:ring-[#c2410c]"
                aria-label="Seleccionar versión"
            >
                {sorted.map((v, idx) => (
                    <option key={v.id} value={idx}>
                        Versión {v.versionNumber}
                        {hasVersionObservation(v) ? ' · Revisada' : ' · Pendiente'}
                    </option>
                ))}
            </select>
        );
    }

    return (
        <div
            className="flex items-center gap-1 overflow-x-auto"
            role="tablist"
            aria-label="Versiones de la entrega"
        >
            {sorted.map((v) => {
                const isActive = v.id === selectedVersionId;
                const reviewed = hasVersionObservation(v);
                return (
                    <button
                        key={v.id}
                        type="button"
                        role="tab"
                        aria-selected={isActive}
                        onClick={() => onSelect(v.id)}
                        className={`inline-flex min-h-[32px] shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                            isActive
                                ? 'bg-[#c2410c] text-white shadow-sm'
                                : 'bg-[#f5f5f4] text-[#57534e] hover:bg-[#e7e5e4]'
                        }`}
                    >
                        v{v.versionNumber}
                        <span
                            className={`h-1.5 w-1.5 rounded-full ${
                                isActive
                                    ? reviewed
                                        ? 'bg-[#86efac]'
                                        : 'bg-[#fde68a]'
                                    : reviewed
                                      ? 'bg-[#16a34a]'
                                      : 'bg-[#d97706]'
                            }`}
                            title={reviewed ? 'Con observación' : 'Pendiente de revisión'}
                            aria-hidden
                        />
                    </button>
                );
            })}
        </div>
    );
}

export function VersionReviewIndicator({ version }: { version: DeliveryVersionMock }) {
    const reviewed = hasVersionObservation(version);
    return reviewed ? (
        <MessageSquare className="h-3.5 w-3.5 text-[#16a34a]" aria-label="Con observación" />
    ) : (
        <MessageSquareOff className="h-3.5 w-3.5 text-[#d97706]" aria-label="Sin observación" />
    );
}
