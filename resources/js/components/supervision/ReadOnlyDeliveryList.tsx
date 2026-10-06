import { useState, type ReactNode } from 'react';
import { ChevronDown, ChevronRight, Eye } from 'lucide-react';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { entregaStatusConfig, type ReadOnlyDeliveryStatus } from '@/lib/entregas';

export type { ReadOnlyDeliveryStatus };

export interface ReadOnlyDelivery {
    id: number;
    name: string;
    date: string;
    phase: string;
    /**
     * Raw API status. The badge label is resolved from this through
     * ENTREGA_STATUS_MAP — never from `status`, which cannot tell a submitted
     * delivery apart from one that was never submitted.
     */
    apiStatus: string | null;
    /** Layout state; drives the contextual sentence only. */
    status: ReadOnlyDeliveryStatus;
    /** Pre-formatted grade; `'—'` when the delivery is ungraded. */
    grade: string;
}

export interface ReadOnlyDeliveryListProps {
    /** Already filtered by the caller: this component never filters. */
    deliveries: ReadOnlyDelivery[];
    /** Heading prefix; the component appends the visible row count. */
    title?: string;
    /**
     * Navigation is injected because each caller routes to a different surface
     * (student detail vs. director/coordinator supervision), and hardcoding a
     * route here is what let these views drift apart in the first place.
     */
    onOpen: (deliveryId: number) => void;
    openLabel?: string;
    openDisabled?: (deliveryId: number) => boolean;
    emptyMessage?: string;
    emptyAllMessage?: string;
    /**
     * Whether the parent holds deliveries outside the current phase. Used only
     * to pick the empty-state copy, so an empty phase is not reported as a
     * project without deliveries.
     */
    hasAnyDeliveries?: boolean;
    /**
     * Extra actions appended next to the built-in open button, rendered only
     * while the row is expanded.
     *
     * A slot rather than a prop per action: the director's supervision view owns
     * a "Revisar" control that goes to its own review route, and forking the list
     * to host it would recreate the per-view copy this component exists to end.
     * The component stays read-only — it renders whatever the caller returns and
     * never decides what a review affordance looks like.
     */
    renderActions?: (delivery: ReadOnlyDelivery) => ReactNode;
}

const DEFAULT_TITLE = 'Entregas';
const DEFAULT_OPEN_LABEL = 'Ver entrega';
const DEFAULT_EMPTY_MESSAGE = 'No hay entregas para esta fase.';
const DEFAULT_EMPTY_ALL_MESSAGE = 'No hay entregas registradas para este proyecto.';

/**
 * Row-level explanation, keyed by layout state. Read-only supervision is a
 * different audience from the detail view: it says what is missing instead of
 * inviting an action.
 */
function contextualSentence(status: ReadOnlyDeliveryStatus): string {
    switch (status) {
        case 'pending':
            return 'El estudiante aún no ha realizado esta entrega.';
        case 'corrections':
            return 'Se solicitaron correcciones. Pendiente de re-entrega.';
        case 'approved':
            return 'Entrega revisada y aprobada.';
        default:
            return 'Entrega rechazada.';
    }
}

export default function ReadOnlyDeliveryList({
    deliveries,
    title = DEFAULT_TITLE,
    onOpen,
    openLabel = DEFAULT_OPEN_LABEL,
    openDisabled = () => false,
    emptyMessage = DEFAULT_EMPTY_MESSAGE,
    emptyAllMessage = DEFAULT_EMPTY_ALL_MESSAGE,
    hasAnyDeliveries = true,
    renderActions,
}: ReadOnlyDeliveryListProps) {
    const [expandedDelivery, setExpandedDelivery] = useState<number | null>(null);

    return (
        <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <div className="border-b border-[#e5e5e5] px-6 py-4">
                <h3 className="text-base font-bold text-[#1c1917]">{title} ({deliveries.length})</h3>
            </div>
            <div className="divide-y divide-[#e5e5e5]">
                {deliveries.map((d) => {
                    const config = entregaStatusConfig(d.apiStatus);
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
                                        <p className="text-sm text-[#57534e]">{contextualSentence(d.status)}</p>
                                        {/* Read-only: this component ships only the
                                            detail button. Any review control comes
                                            from the caller's renderActions slot. */}
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => onOpen(d.id)}
                                                className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98] disabled:opacity-50"
                                                aria-label={`Ver detalle de ${d.name}`}
                                                disabled={openDisabled(d.id)}
                                            >
                                                <Eye className="h-3.5 w-3.5" />
                                                {openLabel}
                                            </button>
                                            {renderActions?.(d)}
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    );
                })}
                {deliveries.length === 0 && (
                    <div className="px-6 py-12 text-center text-sm text-[#a8a29e]">
                        {hasAnyDeliveries ? emptyMessage : emptyAllMessage}
                    </div>
                )}
            </div>
        </div>
    );
}
