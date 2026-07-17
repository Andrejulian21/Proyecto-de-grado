import { CheckCircle2 } from 'lucide-react';

export interface PhaseStep {
    id: string;
    label: string;
    status: 'done' | 'current' | 'future';
}

interface PhaseStepperProps {
    phases: PhaseStep[];
    selectedPhaseId: string;
    onSelectPhase: (phaseId: string) => void;
    deliveryCountByPhase: (phaseId: PhaseId) => number;
    title?: string;
    countLabel?: { singular: string; plural: string };
}

export function PhaseStepper({
    phases,
    selectedPhaseId,
    onSelectPhase,
    deliveryCountByPhase,
    title = 'Fases del Proyecto',
    countLabel = { singular: 'entrega', plural: 'entregas' },
}: PhaseStepperProps) {
    return (
        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <h3 className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-[#57534e]">{title}</h3>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                {phases.map((phase, idx) => {
                    const isSelected = selectedPhaseId === phase.id;
                    const count = deliveryCountByPhase(phase.id);

                    return (
                        <div key={phase.id} className="flex items-center gap-3 sm:flex-1 sm:flex-col sm:items-center sm:text-center">
                            <button
                                type="button"
                                onClick={() => onSelectPhase(phase.id)}
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
                                        {count} {count === 1 ? countLabel.singular : countLabel.plural}
                                    </span>
                                </div>
                            </button>
                            {idx < phases.length - 1 && (
                                <div className="hidden h-px flex-1 bg-[#e5e5e5] sm:block" />
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
