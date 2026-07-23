import { useState } from 'react';
import { ShieldCheck, PenSquare, XCircle, Loader2, Mail } from 'lucide-react';
import { TOTPInput } from '@/components/ui/TOTPInput';
import type { SignatureStatus } from '@/mocks/bitacorasMock';

type FlowStep = 'idle' | 'code_sent' | 'enter_code' | 'rejecting';

interface BitacoraSignFlowProps {
    disabled?: boolean;
    currentStatus: SignatureStatus;
    onSign: (code: string) => Promise<void>;
    onReject: (comment: string) => Promise<void>;
}

export function BitacoraSignFlow({
    disabled = false,
    currentStatus,
    onSign,
    onReject,
}: BitacoraSignFlowProps) {
    const [step, setStep] = useState<FlowStep>('idle');
    const [totpCode, setTotpCode] = useState('');
    const [totpError, setTotpError] = useState('');
    const [rejectComment, setRejectComment] = useState('');
    const [rejectError, setRejectError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    if (disabled || currentStatus !== 'pendiente') {
        return null;
    }

    async function handleSign() {
        if (totpCode.length !== 6) {
            setTotpError('Debe ingresar el código de 6 dígitos.');
            return;
        }
        setSubmitting(true);
        setTotpError('');
        try {
            await new Promise((r) => setTimeout(r, 800));
            await onSign(totpCode);
            setStep('idle');
            setTotpCode('');
        } catch {
            setTotpError('Código inválido. Intente de nuevo.');
        } finally {
            setSubmitting(false);
        }
    }

    async function handleReject() {
        if (!rejectComment.trim()) {
            setRejectError('Debe indicar el motivo del rechazo.');
            return;
        }
        setSubmitting(true);
        setRejectError('');
        try {
            await new Promise((r) => setTimeout(r, 500));
            await onReject(rejectComment.trim());
            setStep('idle');
            setRejectComment('');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <div className="mb-5 flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#e0e7ff]">
                    <ShieldCheck className="h-5 w-5 text-[#4f46e5]" />
                </div>
                <div>
                    <h3 className="text-sm font-bold text-[#1c1917]">Autenticación para firma</h3>
                    <p className="text-xs text-[#57534e]">Código dinámico (simulado)</p>
                </div>
            </div>

            {step === 'idle' && (
                <button
                    type="button"
                    onClick={() => setStep('code_sent')}
                    className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a]"
                >
                    <PenSquare className="h-4 w-4" />
                    Solicitar firma
                </button>
            )}

            {step === 'code_sent' && (
                <div className="flex flex-col gap-4">
                    <div className="flex items-start gap-3 rounded-lg border border-[#dbeafe] bg-[#eff6ff] p-3 text-sm text-[#1e3a8a]">
                        <Mail className="h-4 w-4 shrink-0 mt-0.5" />
                        <p>
                            Se ha enviado un código temporal a su correo institucional (simulado).
                            Ingrese el código para firmar o rechace la bitácora si requiere correcciones.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setStep('enter_code')}
                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-3 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa]"
                    >
                        Ingresar código
                    </button>
                </div>
            )}

            {step === 'enter_code' && (
                <div className="flex flex-col gap-4">
                    <p className="text-xs text-[#57534e]">
                        Ingrese el código de 6 dígitos recibido (simulación: cualquier 6 dígitos).
                    </p>
                    <TOTPInput
                        onComplete={(code) => {
                            setTotpCode(code);
                            setTotpError('');
                        }}
                        error={totpError}
                        disabled={submitting}
                    />
                    <button
                        type="button"
                        onClick={handleSign}
                        disabled={submitting || totpCode.length !== 6}
                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <PenSquare className="h-4 w-4" />}
                        Firmar bitácora
                    </button>
                    <button
                        type="button"
                        onClick={() => setStep('rejecting')}
                        disabled={submitting}
                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#dc2626] bg-white px-4 py-3 text-sm font-semibold text-[#dc2626] transition-colors hover:bg-[#fee2e2]"
                    >
                        <XCircle className="h-4 w-4" />
                        Rechazar
                    </button>
                    <button
                        type="button"
                        onClick={() => setStep('code_sent')}
                        className="text-xs font-semibold text-[#57534e] hover:text-[#c2410c]"
                    >
                        Volver
                    </button>
                </div>
            )}

            {step === 'rejecting' && (
                <div className="flex flex-col gap-4">
                    <div>
                        <label htmlFor="reject-comment" className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                            Motivo del rechazo
                        </label>
                        <textarea
                            id="reject-comment"
                            rows={4}
                            value={rejectComment}
                            onChange={(e) => {
                                setRejectComment(e.target.value);
                                setRejectError('');
                            }}
                            placeholder="Explique qué debe corregir el equipo..."
                            className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                        />
                        {rejectError && (
                            <p className="mt-1 text-xs text-[#dc2626]">{rejectError}</p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={handleReject}
                        disabled={submitting}
                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#dc2626] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#b91c1c] disabled:opacity-60"
                    >
                        {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <XCircle className="h-4 w-4" />}
                        Confirmar rechazo
                    </button>
                    <button
                        type="button"
                        onClick={() => setStep('enter_code')}
                        className="text-xs font-semibold text-[#57534e] hover:text-[#c2410c]"
                    >
                        Cancelar
                    </button>
                </div>
            )}
        </div>
    );
}
