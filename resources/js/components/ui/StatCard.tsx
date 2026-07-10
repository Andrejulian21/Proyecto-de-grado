import { type LucideIcon, TrendingUp, TrendingDown } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface StatCardProps {
    icon: LucideIcon;
    label: string;
    value: string | number;
    trend?: { direction: 'up' | 'down'; value: string };
    variant?: 'default' | 'warning' | 'success';
    className?: string;
}

const variantStyles: Record<string, string> = {
    default: 'text-[#c2410c] bg-[#fed7aa]',
    warning: 'text-[#d97706] bg-[#fef3c7]',
    success: 'text-[#16a34a] bg-[#dcfce7]',
};

export function StatCard({
    icon: Icon,
    label,
    value,
    trend,
    variant = 'default',
    className,
}: StatCardProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]',
                className,
            )}
        >
            <div
                className={cn(
                    'flex h-10 w-10 items-center justify-center rounded-xl',
                    variantStyles[variant],
                )}
            >
                <Icon className="h-5 w-5" />
            </div>

            <div className="flex flex-col gap-0.5">
                <span className="text-sm text-[#57534e]">{label}</span>
                <span className="text-2xl font-bold text-[#1c1917] tabular-nums">{value}</span>
            </div>

            {trend && (
                <div className="flex items-center gap-1 text-sm">
                    {trend.direction === 'up' ? (
                        <TrendingUp className="h-4 w-4 text-[#16a34a]" />
                    ) : (
                        <TrendingDown className="h-4 w-4 text-[#dc2626]" />
                    )}
                    <span
                        className={cn(
                            'font-semibold',
                            trend.direction === 'up' ? 'text-[#16a34a]' : 'text-[#dc2626]',
                        )}
                    >
                        {trend.value}
                    </span>
                </div>
            )}
        </div>
    );
}
