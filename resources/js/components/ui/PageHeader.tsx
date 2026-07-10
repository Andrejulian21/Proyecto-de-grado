import type { ReactNode } from 'react';

export interface PageHeaderProps {
    eyebrow: string;
    title: string;
    subtitle?: string;
    actions?: ReactNode;
}

export function PageHeader({ eyebrow, title, subtitle, actions }: PageHeaderProps) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="flex flex-col gap-1">
                <span className="inline-flex w-fit items-center gap-1 rounded-full bg-[#fed7aa] px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.05em] text-[#c2410c]">
                    {eyebrow}
                </span>
                <h2 className="mt-1 text-2xl font-bold text-[#1c1917] text-balance">{title}</h2>
                {subtitle && (
                    <p className="text-sm text-[#57534e]">{subtitle}</p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
