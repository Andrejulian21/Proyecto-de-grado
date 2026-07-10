import { useEffect, useCallback } from 'react';
import { X, AlertTriangle, Trash2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface ConfirmDialogProps {
    open: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    onConfirm: () => void;
    onCancel: () => void;
    variant?: 'danger' | 'default';
}

export function ConfirmDialog({
    open,
    title,
    message,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    onConfirm,
    onCancel,
    variant = 'default',
}: ConfirmDialogProps) {
    const handleKeyDown = useCallback(
        (e: KeyboardEvent) => {
            if (e.key === 'Escape') onCancel();
        },
        [onCancel],
    );

    useEffect(() => {
        if (open) {
            document.addEventListener('keydown', handleKeyDown);
            document.body.style.overflow = 'hidden';
        }
        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = '';
        };
    }, [open, handleKeyDown]);

    if (!open) return null;

    const isDanger = variant === 'danger';

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            onClick={(e) => { if (e.target === e.currentTarget) onCancel(); }}
            role="dialog"
            aria-modal="true"
            aria-label={title}
        >
            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-[0_20px_60px_rgba(28,25,23,0.15)] motion-reduce:shadow-none">
                <div className="mb-5 flex items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                        <div
                            className={cn(
                                'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                                isDanger ? 'bg-[#fee2e2] text-[#dc2626]' : 'bg-[#f5f5f4] text-[#c2410c]',
                            )}
                        >
                            {isDanger ? <Trash2 className="h-5 w-5" /> : <AlertTriangle className="h-5 w-5" />}
                        </div>
                        <div className="flex flex-col gap-1">
                            <h2 className="text-lg font-bold text-[#1c1917]">{title}</h2>
                            <p className="text-sm text-[#57534e]">{message}</p>
                        </div>
                    </div>
                    <button
                        onClick={onCancel}
                        className="rounded-lg p-1.5 text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#1c1917]"
                        aria-label="Cerrar"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <div className="flex items-center justify-end gap-2">
                    <button
                        onClick={onCancel}
                        className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2.5 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                    >
                        {cancelLabel}
                    </button>
                    <button
                        onClick={onConfirm}
                        className={cn(
                            'inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition-colors',
                            isDanger
                                ? 'bg-[#dc2626] hover:bg-[#b91c1c]'
                                : 'bg-[#c2410c] hover:bg-[#9a330a]',
                        )}
                    >
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
