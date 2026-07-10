import { type VariantProps, cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.03em] motion-reduce:transition-none',
    {
        variants: {
            variant: {
                success: 'bg-[#dcfce7] text-[#14532d]',
                warning: 'bg-[#fef3c7] text-[#78350f]',
                error: 'bg-[#fee2e2] text-[#7f1d1d]',
                info: 'bg-[#dbeafe] text-[#1e3a8a]',
                'en-curso': 'bg-[#e0e7ff] text-[#312e81]',
                inactivo: 'bg-[#e7e5e4] text-[#57534e]',
                riesgo: 'bg-[#fee2e2] text-[#7f1d1d]',
            },
        },
        defaultVariants: {
            variant: 'inactivo',
        },
    },
);

export interface StatusBadgeProps
    extends React.HTMLAttributes<HTMLSpanElement>,
        VariantProps<typeof badgeVariants> {
    variant?: 'success' | 'warning' | 'error' | 'info' | 'en-curso' | 'inactivo' | 'riesgo';
}

export function StatusBadge({ className, variant, children, ...props }: StatusBadgeProps) {
    return (
        <span className={cn(badgeVariants({ variant }), className)} {...props}>
            {children}
        </span>
    );
}
