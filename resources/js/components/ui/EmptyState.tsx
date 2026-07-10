import type { LucideIcon } from 'lucide-react';

export interface EmptyStateProps {
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: { label: string; onClick: () => void };
}

export function EmptyState({ icon: Icon, title, description, action }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#f5f5f4]">
                <Icon className="h-6 w-6 text-[#78716c]" />
            </div>
            <div className="flex flex-col gap-1">
                <h3 className="text-base font-semibold text-[#1c1917]">{title}</h3>
                {description && (
                    <p className="text-sm text-[#57534e] max-w-sm">{description}</p>
                )}
            </div>
            {action && (
                <button
                    onClick={action.onClick}
                    className="mt-1 inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2.5 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                >
                    <Icon className="h-4 w-4" />
                    {action.label}
                </button>
            )}
        </div>
    );
}
