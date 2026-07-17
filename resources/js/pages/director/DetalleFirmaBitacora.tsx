import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { TOTPInput } from '@/components/ui/TOTPInput';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ArrowLeft, ShieldCheck, User, Calendar, Clock, FileText, PenSquare, Loader2 } from 'lucide-react';

interface SessionDetail {
    label: string;
    value: string;
    icon: typeof User;
}

export default function DetalleFirmaBitacora() {
    const navigate = useNavigate();
    const [totpCode, setTotpCode] = useState('');
    const [totpError, setTotpError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [signed, setSigned] = useState(false);

    const topic = 'Revisión de la arquitectura del sistema';

    const sessions: SessionDetail[] = [
        { label: 'Estudiante', value: 'Carlos Andrés Méndez', icon: User },
        { label: 'Proyecto', value: 'PG-2026-014 — Sistema Centralizado de Proyectos de Grado', icon: FileText },
        { label: 'Fecha de la sesión', value: '15/04/2026', icon: Calendar },
        { label: 'Duración', value: '1 hora 30 minutos', icon: Clock },
    ];

    function handleTOTPComplete(code: string) {
        setTotpCode(code);
        setTotpError('');
    }

    async function handleSign() {
        if (totpCode.length !== 6) {
            setTotpError('Debe ingresar el código TOTP de 6 dígitos.');
            return;
        }
        setSubmitting(true);
        try {
            await new Promise((r) => setTimeout(r, 1000));
            setSigned(true);
        } catch {
            setTotpError('Error al firmar. Intente de nuevo.');
        } finally {
            setSubmitting(false);
        }
    }

    if (signed) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Bitácora"
                    title={topic}
                    subtitle="Firmada — La bitácora ha sido firmada digitalmente con éxito"
                    actions={
                        <button
                            onClick={() => navigate('/director/bitacoras')}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver a bitácoras
                        </button>
                    }
                />
                <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-[#dcfce7] bg-[#dcfce7] py-16 text-center">
                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white">
                        <svg viewBox="0 0 24 24" className="h-8 w-8 text-[#16a34a]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div>
                        <h3 className="text-lg font-bold text-[#14532d]">Firma exitosa</h3>
                        <p className="text-sm text-[#14532d] mt-1">
                            La bitácora ha sido firmada y registrada en el sistema.
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Bitácora"
                title={topic}
                subtitle="Revise los detalles de la sesión y firme con su código TOTP"
                actions={
                    <button
                        onClick={() => navigate('/director/bitacoras')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                {/* Session Details */}
                <div className="lg:col-span-3">
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-4 flex items-center gap-2">
                            <FileText className="h-5 w-5 text-[#c2410c]" />
                            <h3 className="text-base font-bold text-[#1c1917]">Detalles de la Sesión</h3>
                            <StatusBadge variant="warning">Pendiente de firma</StatusBadge>
                        </div>

                        <div className="mb-5 space-y-4">
                            {sessions.map((s) => (
                                <div key={s.label} className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                    <s.icon className="h-5 w-5 text-[#c2410c]" />
                                    <div>
                                        <p className="text-xs text-[#78716c]">{s.label}</p>
                                        <p className="text-sm font-semibold text-[#1c1917]">{s.value}</p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Topic and Description */}
                        <div className="mb-5 space-y-4">
                            <div>
                                <p className="text-xs font-semibold text-[#78716c] uppercase tracking-wide mb-1">Tema</p>
                                <p className="text-sm font-semibold text-[#1c1917]">{topic}</p>
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-[#78716c] uppercase tracking-wide mb-1">Descripción</p>
                                <p className="text-sm text-[#57534e] leading-relaxed">
                                    Se realizó una revisión detallada de la arquitectura propuesta para el sistema,
                                    identificando oportunidades de mejora en la capa de persistencia y validando
                                    el uso de los patrones de diseño seleccionados. Se acordó ajustar el esquema
                                    de base de datos para incluir soporte para auditoría.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* TOTP Sticky Panel */}
                <div className="lg:col-span-2">
                    <div className="sticky top-20 rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-5 flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#e0e7ff]">
                                <ShieldCheck className="h-5 w-5 text-[#4f46e5]" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-[#1c1917]">Firma Digital</h3>
                                <p className="text-xs text-[#57534e]">Verificación TOTP</p>
                            </div>
                        </div>

                        <p className="mb-4 text-xs text-[#57534e]">
                            Ingrese el código de 6 dígitos desde su aplicación de autenticación para firmar esta bitácora.
                        </p>

                        <TOTPInput
                            onComplete={handleTOTPComplete}
                            error={totpError}
                            disabled={submitting}
                        />

                        <div className="mt-2 flex items-center gap-1.5 text-xs text-[#78716c]">
                            <svg viewBox="0 0 24 24" className="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="16" x2="12" y2="12" />
                                <line x1="12" y1="8" x2="12.01" y2="8" />
                            </svg>
                            <span>La firma queda registrada con fecha y hora.</span>
                        </div>

                        <button
                            onClick={handleSign}
                            disabled={submitting || totpCode.length !== 6}
                            className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {submitting ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <PenSquare className="h-4 w-4" />
                            )}
                            Firmar Bitácora
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
