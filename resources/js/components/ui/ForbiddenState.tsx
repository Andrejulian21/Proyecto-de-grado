import { Lock } from 'lucide-react';

interface ForbiddenStateProps {
    message?: string;
    actionLabel?: string;
    onAction?: () => void;
}

/**
 * Reusable "no permission" state for 403 responses. Pair it with the
 * `api:forbidden` event dispatched by `apiFetch` (see `@/lib/utils`).
 */
export function ForbiddenState({
    message = 'No tienes permisos para realizar esta acción.',
    actionLabel,
    onAction,
}: ForbiddenStateProps) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-xl border border-[#fee2e2] bg-[#fee2e2]/40 px-6 py-10 text-center">
            <Lock className="h-10 w-10 text-[#dc2626]" aria-hidden="true" />
            <p className="text-sm font-medium text-[#7f1d1d]" role="alert">
                {message}
            </p>
            {actionLabel && onAction && (
                <button
                    type="button"
                    onClick={onAction}
                    className="inline-flex min-h-[40px] items-center justify-center rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                >
                    {actionLabel}
                </button>
            )}
        </div>
    );
}

export default ForbiddenState;