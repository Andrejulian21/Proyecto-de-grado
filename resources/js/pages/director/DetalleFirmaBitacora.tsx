import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { SignatureCodeInput } from '@/components/bitacoras/SignatureCode';
import { apiFetch } from '@/lib/utils';
import { ArrowLeft, ShieldCheck, User, Calendar, Clock, FileText, Loader2, CheckCircle } from 'lucide-react';

interface BitacoraDetalle {
    id: number;
    topic: string;
    notes: string;
    meeting_date: string;
    duration_hours: number;
    signature_status: string;
    director_signed_at: string | null;
    student_name?: string;
    project_code?: string;
    project_title?: string;
}

export default function DetalleFirmaBitacora() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const [bitacora, setBitacora] = useState<BitacoraDetalle | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [signed, setSigned] = useState(false);

    useEffect(() => {
        if (!id) { setError('ID de bitácora no proporcionado.'); setLoading(false); return; }

        let cancel = false;
        (async () => {
            try {
                const res = await apiFetch(`/api/bitacoras/${id}`);
                if (!res.ok) throw new Error('Error al cargar la bitácora.');
                const body = await res.json();
                if (!cancel) setBitacora(body.data ?? body);
            } catch (err) {
                if (!cancel) setError(err instanceof Error ? err.message : 'Error de conexión.');
            } finally {
                if (!cancel) setLoading(false);
            }
        })();
        return () => { cancel = true; };
    }, [id]);

    if (loading) {
        return (
            <div className="flex items-center justify-center py-20">
                <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
            </div>
        );
    }

    if (error || !bitacora) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Bitácora" title="Error" subtitle="No se pudo cargar la bitácora." />
                <p className="text-sm text-[#dc2626]">{error ?? 'Bitácora no encontrada.'}</p>
                <button onClick={() => navigate(-1)} className="text-sm text-[#c2410c] underline">Volver</button>
            </div>
        );
    }

    if (signed) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Bitácora" title={bitacora.topic} subtitle="Firmada correctamente" />
                <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-[#dcfce7] bg-[#dcfce7] py-16 text-center">
                    <CheckCircle className="h-12 w-12 text-[#16a34a]" />
                    <p className="text-lg font-bold text-[#14532d]">Firma exitosa</p>
                    <p className="text-sm text-[#14532d]">La bitácora ha sido firmada y registrada en el sistema.</p>
                    <button onClick={() => navigate('/bitacoras')} className="rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white">Volver a bitácoras</button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Bitácora"
                title={bitacora.topic}
                subtitle="Revise los detalles de la sesión y firme con el código que el estudiante le compartió."
                actions={
                    <button onClick={() => navigate(-1)}
                        className="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold">
                        <ArrowLeft className="h-4 w-4" /> Volver
                    </button>
                }
            />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                <div className="lg:col-span-3">
                    <div className="rounded-xl border bg-white p-6 shadow-sm">
                        <div className="mb-4 flex items-center gap-2">
                            <FileText className="h-5 w-5 text-[#c2410c]" />
                            <h3 className="text-base font-bold">Detalles de la Sesión</h3>
                            <StatusBadge variant={bitacora.signature_status === 'FirmadaDirector' || bitacora.signature_status === 'Completada' ? 'success' : 'warning'}>
                                {bitacora.signature_status === 'FirmadaDirector' || bitacora.signature_status === 'Completada' ? 'Firmada' : 'Pendiente de firma'}
                            </StatusBadge>
                        </div>

                        <div className="space-y-3">
                            {bitacora.student_name && (
                                <SessionRow icon={User} label="Estudiante" value={bitacora.student_name} />
                            )}
                            {bitacora.project_code && (
                                <SessionRow icon={FileText} label="Proyecto" value={`${bitacora.project_code} — ${bitacora.project_title ?? ''}`} />
                            )}
                            <SessionRow icon={Calendar} label="Fecha de la sesión" value={new Date(bitacora.meeting_date).toLocaleDateString('es-CO', { day: '2-digit', month: 'long', year: 'numeric' })} />
                            <SessionRow icon={Clock} label="Duración" value={`${bitacora.duration_hours} hora(s)`} />
                        </div>

                        <div className="mt-5 space-y-3">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-[#78716c]">Contenido</p>
                                <p className="mt-1 text-sm text-[#57534e] leading-relaxed">{bitacora.notes || 'Sin contenido registrado.'}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="lg:col-span-2">
                    <div className="sticky top-20 rounded-xl border bg-white p-6 shadow-sm">
                        <div className="mb-5 flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#e0e7ff]">
                                <ShieldCheck className="h-5 w-5 text-[#4f46e5]" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold">Firma Digital</h3>
                                <p className="text-xs text-[#57534e]">Código de firma</p>
                            </div>
                        </div>

                        <SignatureCodeInput
                            bitacoraId={bitacora.id}
                            onSuccess={() => setSigned(true)}
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}

function SessionRow({ icon: Icon, label, value }: { icon: React.ComponentType<{ className?: string }>; label: string; value: string }) {
    return (
        <div className="flex items-center gap-3 rounded-lg border bg-[#fafaf9] p-3.5">
            <Icon className="h-5 w-5 text-[#c2410c]" />
            <div>
                <p className="text-xs text-[#78716c]">{label}</p>
                <p className="text-sm font-semibold">{value}</p>
            </div>
        </div>
    );
}
