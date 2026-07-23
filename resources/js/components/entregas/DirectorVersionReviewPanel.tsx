import { useEffect, useState } from 'react';
import {
    AlertTriangle,
    CheckCircle2,
    Loader2,
    MessageSquareText,
    Send,
} from 'lucide-react';
import type { DeliveryVersionMock, VersionReviewStatus } from '@/types/entregas';

export interface VersionReviewSavePayload {
    text: string;
    reviewStatus: Extract<VersionReviewStatus, 'aprobada' | 'necesita_ajustes'>;
}

interface DirectorVersionReviewPanelProps {
    version: DeliveryVersionMock;
    onSave: (payload: VersionReviewSavePayload) => void;
    submitting?: boolean;
}

export default function DirectorVersionReviewPanel({
    version,
    onSave,
    submitting = false,
}: DirectorVersionReviewPanelProps) {
    const [notes, setNotes] = useState(version.observation.text ?? '');
    const [decision, setDecision] = useState<'aprobada' | 'necesita_ajustes' | null>(
        version.observation.reviewStatus === 'aprobada'
            ? 'aprobada'
            : version.observation.reviewStatus === 'necesita_ajustes'
              ? 'necesita_ajustes'
              : null,
    );

    useEffect(() => {
        setNotes(version.observation.text ?? '');
        setDecision(
            version.observation.reviewStatus === 'aprobada'
                ? 'aprobada'
                : version.observation.reviewStatus === 'necesita_ajustes'
                  ? 'necesita_ajustes'
                  : null,
        );
    }, [version.id, version.observation.text, version.observation.reviewStatus]);

    return (
        <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <div className="mb-2 flex items-center gap-2">
                <MessageSquareText className="h-5 w-5 text-[#c2410c]" />
                <h3 className="text-base font-bold text-[#1c1917]">
                    Revisar versión {version.versionNumber}
                </h3>
            </div>
            <p className="mb-6 text-xs text-[#78716c]">
                Las observaciones se guardan únicamente para esta versión. Cambie de versión en el
                selector para revisar otra entrega.
            </p>

            <div className="flex flex-col gap-6">
                <div>
                    <label
                        htmlFor={`director-notes-v${version.versionNumber}`}
                        className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]"
                    >
                        Observaciones (versión {version.versionNumber})
                    </label>
                    <textarea
                        id={`director-notes-v${version.versionNumber}`}
                        rows={5}
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        placeholder="Escriba sus observaciones sobre esta versión..."
                        className="w-full min-h-[100px] resize-y rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                    />
                </div>

                <div>
                    <p className="mb-3 text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                        Decisión para versión {version.versionNumber}
                    </p>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            onClick={() => setDecision('aprobada')}
                            className={`flex flex-1 items-center gap-3 rounded-lg border p-4 text-left transition-all active:scale-[0.98] ${
                                decision === 'aprobada'
                                    ? 'border-[#16a34a] bg-[#dcfce7]'
                                    : 'border-[#e5e5e5] hover:bg-[#fafaf9]'
                            }`}
                        >
                            <CheckCircle2
                                className={`h-5 w-5 ${
                                    decision === 'aprobada' ? 'text-[#16a34a]' : 'text-[#78716c]'
                                }`}
                            />
                            <div>
                                <p className="text-sm font-semibold text-[#1c1917]">Aprobada</p>
                                <p className="text-xs text-[#57534e]">
                                    Esta versión cumple con los criterios
                                </p>
                            </div>
                        </button>
                        <button
                            type="button"
                            onClick={() => setDecision('necesita_ajustes')}
                            className={`flex flex-1 items-center gap-3 rounded-lg border p-4 text-left transition-all active:scale-[0.98] ${
                                decision === 'necesita_ajustes'
                                    ? 'border-[#d97706] bg-[#fef3c7]'
                                    : 'border-[#e5e5e5] hover:bg-[#fafaf9]'
                            }`}
                        >
                            <AlertTriangle
                                className={`h-5 w-5 ${
                                    decision === 'necesita_ajustes'
                                        ? 'text-[#d97706]'
                                        : 'text-[#78716c]'
                                }`}
                            />
                            <div>
                                <p className="text-sm font-semibold text-[#1c1917]">
                                    Necesita ajustes
                                </p>
                                <p className="text-xs text-[#57534e]">
                                    Se requieren correcciones en esta versión
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    onClick={() => {
                        if (decision) {
                            onSave({ text: notes.trim(), reviewStatus: decision });
                        }
                    }}
                    disabled={!decision || submitting}
                    className="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {submitting ? (
                        <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                        <Send className="h-4 w-4" />
                    )}
                    {submitting ? 'Guardando...' : `Guardar revisión — v${version.versionNumber}`}
                </button>
            </div>
        </div>
    );
}
