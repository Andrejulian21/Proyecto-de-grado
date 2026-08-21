import { Loader2, Shuffle, X, AlertTriangle } from 'lucide-react';

export interface DirectorReassignDeleteDialogProps {
    open: boolean;
    email: string;
    projectCount: number;
    message: string;
    loading: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

export function DirectorReassignDeleteDialog({
    open,
    email,
    projectCount,
    message,
    loading,
    onConfirm,
    onCancel,
}: DirectorReassignDeleteDialogProps) {
    if (!open) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            onClick={(e) => {
                if (e.target === e.currentTarget && !loading) onCancel();
            }}
            role="dialog"
            aria-modal="true"
            aria-labelledby="reassign-delete-title"
        >
            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-[0_20px_60px_rgba(28,25,23,0.15)]">
                <div className="mb-4 flex items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#fed7aa] text-[#c2410c]">
                            <AlertTriangle className="h-5 w-5" />
                        </div>
                        <div className="flex flex-col gap-1">
                            <h2 id="reassign-delete-title" className="text-lg font-bold text-[#1c1917]">
                                Director con proyectos asignados
                            </h2>
                            <p className="text-sm text-[#57534e]">{message}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={loading}
                        className="rounded-lg p-1.5 text-[#57534e] transition-colors hover:bg-[#f5f5f4] disabled:opacity-50"
                        aria-label="Cerrar"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <p className="mb-5 text-sm text-[#57534e]">
                    <strong>{email}</strong> dirige {projectCount} proyecto{projectCount === 1 ? '' : 's'}.
                    Puede eliminar al director y distribuir aleatoriamente esos proyectos entre los
                    demás directores existentes. Esta acción no se puede deshacer.
                </p>

                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={loading}
                        className="rounded-lg border border-[#e5e5e5] px-4 py-2.5 text-sm font-medium text-[#1c1917] transition hover:bg-[#f5f5f4] disabled:opacity-50"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={loading}
                        className="inline-flex items-center justify-center gap-2 rounded-lg bg-[#dc2626] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#b91c1c] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {loading ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <Shuffle className="h-4 w-4" />
                        )}
                        Eliminar y redistribuir proyectos
                    </button>
                </div>
            </div>
        </div>
    );
}
