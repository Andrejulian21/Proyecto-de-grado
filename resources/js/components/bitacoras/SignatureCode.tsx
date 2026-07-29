import { useState, useEffect, useRef, useCallback } from 'react';
import { Copy, Check, RefreshCw, Loader2, X, ShieldCheck, Clock } from 'lucide-react';
import { cn, apiFetch } from '@/lib/utils';
import { TOTPInput } from '@/components/ui/TOTPInput';

export interface SignatureCodeDisplayProps {
    bitacoraId: number;
    code: string;
    expiresAt: string; // ISO8601 from the API
    onClose: () => void;
}

/**
 * PR 1 — T-1.6 / T-1.8: modal shown to the student right after creating
 * a bitacora. Displays the plain 6-digit signature code with a 2-minute
 * countdown, a copy-to-clipboard button, and a "Solicitar nuevo codigo"
 * button that becomes active once the code expires (and only while
 * `signature_retries < 1`, enforced by the backend).
 */
export function SignatureCodeDisplay({
    bitacoraId,
    code,
    expiresAt,
    onClose,
}: SignatureCodeDisplayProps) {
    const [copied, setCopied] = useState(false);
    const [resendOpen, setResendOpen] = useState(false);
    const [resendCode, setResendCode] = useState<string | null>(null);
    const [resendExpiresAt, setResendExpiresAt] = useState<string | null>(null);
    const [resending, setResending] = useState(false);
    const [resendError, setResendError] = useState<string | null>(null);
    const [now, setNow] = useState(() => Date.now());
    const tickRef = useRef<number | null>(null);

    const expiry = new Date(expiresAt).getTime();
    const remainingMs = Math.max(0, expiry - now);

    useEffect(() => {
        tickRef.current = window.setInterval(() => setNow(Date.now()), 1000);
        return () => {
            if (tickRef.current !== null) window.clearInterval(tickRef.current);
        };
    }, []);

    useEffect(() => {
        function onKey(e: KeyboardEvent) {
            if (e.key === 'Escape') onClose();
        }
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [onClose]);

    const handleCopy = useCallback(async () => {
        try {
            await navigator.clipboard.writeText(resendCode ?? code);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            // Clipboard API unavailable — silently no-op; user can still
            // read the digits off the screen.
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- `code`
        // is captured here so the user always copies the latest one. The
        // dependency is included via the closure on `resendCode` updates.
    }, [code, resendCode]);

    const handleResend = useCallback(async () => {
        setResending(true);
        setResendError(null);
        try {
            const res = await apiFetch(
                `/api/bitacoras/${bitacoraId}/re-solicitar-codigo`,
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({}),
                },
            );
            if (!res.ok) {
                const body = (await res.json().catch(() => null)) as
                    | { error?: string; message?: string }
                    | null;
                setResendError(body?.error ?? body?.message ?? 'No se pudo re-solicitar el codigo.');
                return;
            }
            const body = (await res.json()) as { data: { signature_code_plain: string; signature_code_expires_at: string } };
            setResendCode(body.data.signature_code_plain);
            setResendExpiresAt(body.data.signature_code_expires_at);
            setNow(Date.now());
        } catch {
            setResendError('Error de conexion. Intente de nuevo.');
        } finally {
            setResending(false);
        }
    }, [bitacoraId]);

    const activeCode = resendCode ?? code;
    const activeExpiresAt = resendExpiresAt ?? expiresAt;
    const activeRemaining = Math.max(0, new Date(activeExpiresAt).getTime() - now);
    const activeExpired = activeRemaining <= 0;
    const mm = Math.floor(activeRemaining / 60_000);
    const ss = Math.floor((activeRemaining % 60_000) / 1000);

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
            role="dialog"
            aria-modal="true"
            aria-label="Codigo de firma"
        >
            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-[0_20px_60px_rgba(28,25,23,0.15)] motion-reduce:shadow-none">
                <div className="mb-5 flex items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#e0e7ff]">
                            <ShieldCheck className="h-5 w-5 text-[#4f46e5]" />
                        </div>
                        <div className="flex flex-col gap-1">
                            <h2 className="text-lg font-bold text-[#1c1917]">Codigo de firma</h2>
                            <p className="text-sm text-[#57534e]">
                                Comparte este codigo de 6 digitos con tu director para que firme la bitacora.
                            </p>
                        </div>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#1c1917]"
                        aria-label="Cerrar"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <div
                    className={cn(
                        'mb-5 flex flex-col items-center gap-3 rounded-xl border bg-[#fafaf9] p-6',
                        activeExpired ? 'border-[#fecaca]' : 'border-[#e5e5e5]',
                    )}
                >
                    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.08em] text-[#78716c]">
                        <Clock className="h-3.5 w-3.5" />
                        {activeExpired ? 'Codigo expirado' : `Expira en ${String(mm).padStart(2, '0')}:${String(ss).padStart(2, '0')}`}
                    </div>
                    <div
                        className={cn(
                            'font-mono text-4xl font-bold tracking-[0.4em] tabular-nums sm:text-5xl',
                            activeExpired ? 'text-[#dc2626] line-through' : 'text-[#1c1917]',
                        )}
                        aria-label="Codigo de firma de seis digitos"
                    >
                        {activeCode}
                    </div>
                    <button
                        type="button"
                        onClick={handleCopy}
                        disabled={activeExpired}
                        className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {copied ? <Check className="h-3.5 w-3.5 text-[#15803d]" /> : <Copy className="h-3.5 w-3.5" />}
                        {copied ? 'Copiado' : 'Copiar codigo'}
                    </button>
                </div>

                {resendError && (
                    <div className="mb-4 rounded-lg border border-[#fee2e2] bg-[#fee2e2] px-4 py-3 text-sm text-[#7f1d1d]">
                        {resendError}
                    </div>
                )}

                {activeExpired && !resendOpen && (
                    <button
                        type="button"
                        onClick={() => { setResendOpen(true); void handleResend(); }}
                        disabled={resending}
                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {resending ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <RefreshCw className="h-4 w-4" />
                        )}
                        Solicitar nuevo codigo
                    </button>
                )}

                <p className="mt-4 text-center text-xs text-[#78716c]">
                    Solo puedes solicitar un nuevo codigo una vez. Pasada esa oportunidad, la bitacora queda sin firmar.
                </p>
            </div>
        </div>
    );
}

/**
 * PR 1 — T-1.7: form for the director to enter the 6-digit code. Wraps
 * the existing TOTPInput and adds a 5-attempt cap, attempt counter, and
 * visual feedback. The parent passes `onSuccess` and the actual sign
 * request body — this component just tracks local UI state.
 */
export interface SignatureCodeInputProps {
    bitacoraId: number;
    onSuccess: (data: { signature_status: string }) => void;
    disabled?: boolean;
    maxAttempts?: number;
}

const DEFAULT_MAX_ATTEMPTS = 5;

export function SignatureCodeInput({
    bitacoraId,
    onSuccess,
    disabled = false,
    maxAttempts = DEFAULT_MAX_ATTEMPTS,
}: SignatureCodeInputProps) {
    const [code, setCode] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [attempts, setAttempts] = useState(0);
    const [submitting, setSubmitting] = useState(false);
    const attemptsLeft = Math.max(0, maxAttempts - attempts);
    const locked = attempts >= maxAttempts || disabled;

    async function handleSign(value: string) {
        if (locked) return;
        setSubmitting(true);
        setError(null);
        try {
            const res = await apiFetch(`/api/bitacoras/${bitacoraId}/firmar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code: value }),
            });
            const body = (await res.json().catch(() => null)) as
                | { data?: { signature_status?: string }; error?: string; message?: string }
                | null;

            if (res.ok && body?.data) {
                onSuccess({ signature_status: body.data.signature_status ?? 'FirmadaDirector' });
                setCode('');
                setAttempts(0);
                return;
            }

            setAttempts((n) => n + 1);
            setError(
                body?.error ?? body?.message ?? 'No se pudo firmar la bitacora.',
            );
            setCode('');
        } catch {
            setAttempts((n) => n + 1);
            setError('Error de conexion. Intente de nuevo.');
            setCode('');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm text-[#57534e]">
                Ingrese el codigo de 6 digitos que el estudiante le compartio.
            </p>
            <TOTPInput
                onComplete={handleSign}
                error={error ?? undefined}
                disabled={locked || submitting}
            />
            <div className="flex items-center justify-between text-xs">
                <span
                    className={cn(
                        'font-semibold',
                        attemptsLeft <= 1 ? 'text-[#dc2626]' : 'text-[#57534e]',
                    )}
                >
                    {attemptsLeft} {attemptsLeft === 1 ? 'intento restante' : 'intentos restantes'}
                </span>
                {submitting && (
                    <span className="inline-flex items-center gap-1.5 text-[#78716c]">
                        <Loader2 className="h-3 w-3 animate-spin" />
                        Verificando...
                    </span>
                )}
            </div>
        </div>
    );
}
