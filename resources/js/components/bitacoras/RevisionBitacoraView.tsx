import { useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { DirectorSignaturePanel } from '@/components/bitacoras/DirectorSignaturePanel';
import { BitacoraSignFlow } from '@/components/bitacoras/BitacoraSignFlow';
import {
    type BitacoraMeeting,
    formatCreatedAt,
    formatMeetingDate,
    signatureStatusConfig,
} from '@/mocks/bitacorasMock';
import {
    ArrowLeft,
    Calendar,
    User,
    Users,
    FileText,
    AlertCircle,
} from 'lucide-react';

const cardClass = 'rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]';

export interface RevisionBitacoraViewProps {
    mode: 'director' | 'coordinador' | 'student';
    meeting: BitacoraMeeting;
    onBack: () => void;
    onMeetingUpdate?: (updated: BitacoraMeeting) => void;
}

export function RevisionBitacoraView({
    mode,
    meeting: initialMeeting,
    onBack,
    onMeetingUpdate,
}: RevisionBitacoraViewProps) {
    const [meeting, setMeeting] = useState(initialMeeting);
    const statusConfig = signatureStatusConfig[meeting.signatureStatus];
    const canSign = mode === 'director' && meeting.signatureStatus === 'pendiente';

    function updateMeeting(patch: Partial<BitacoraMeeting>) {
        setMeeting((prev) => {
            const next = { ...prev, ...patch };
            onMeetingUpdate?.(next);
            return next;
        });
    }

    async function handleSign(_code: string) {
        const now = new Date();
        updateMeeting({
            signatureStatus: 'firmado',
            signedAt: now.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' }),
            signedTime: now.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' }),
            rejectionComment: undefined,
        });
    }

    async function handleReject(comment: string) {
        updateMeeting({
            signatureStatus: 'rechazado',
            rejectionComment: comment,
            signedAt: undefined,
            signedTime: undefined,
        });
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Bitácora"
                title={meeting.topic}
                subtitle={`${meeting.projectCode} · Reunión ${formatMeetingDate(meeting.meetingDate)}`}
                actions={
                    <button
                        type="button"
                        onClick={onBack}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            {meeting.signatureStatus === 'rechazado' && meeting.rejectionComment && (
                <div className="flex items-start gap-3 rounded-lg border border-[#fee2e2] bg-[#fef2f2] p-4">
                    <AlertCircle className="h-5 w-5 shrink-0 text-[#dc2626]" />
                    <div>
                        <p className="text-sm font-semibold text-[#7f1d1d]">Bitácora rechazada</p>
                        <p className="mt-1 text-sm text-[#7f1d1d]">{meeting.rejectionComment}</p>
                    </div>
                </div>
            )}

            {meeting.signatureStatus === 'firmado' && (
                <div className="rounded-lg border border-[#dcfce7] bg-[#dcfce7]/40 p-4 text-sm text-[#14532d]">
                    Bitácora firmada correctamente el {meeting.signedAt} a las {meeting.signedTime}.
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                <div className="flex flex-col gap-6 lg:col-span-3">
                    <div className={cardClass}>
                        <div className="mb-4 flex flex-wrap items-center gap-3">
                            <FileText className="h-5 w-5 text-[#c2410c]" />
                            <h3 className="text-base font-bold text-[#1c1917]">Información de la reunión</h3>
                            <StatusBadge variant={statusConfig.variant}>{statusConfig.label}</StatusBadge>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <Calendar className="h-5 w-5 text-[#c2410c]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Fecha de reunión</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">
                                        {formatMeetingDate(meeting.meetingDate)}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <Calendar className="h-5 w-5 text-[#4f46e5]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Fecha de creación</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">
                                        {formatCreatedAt(meeting.createdAt)}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <User className="h-5 w-5 text-[#4f46e5]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Director</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">{meeting.directorName}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <Users className="h-5 w-5 text-[#4f46e5]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Integrantes</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">{meeting.members.join(', ')}</p>
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                            <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">Proyecto</p>
                            <p className="mt-1 text-sm font-semibold text-[#1c1917]">
                                {meeting.projectCode} — {meeting.projectName}
                            </p>
                        </div>
                    </div>

                    <div className={cardClass}>
                        <h3 className="mb-3 text-base font-bold text-[#1c1917]">Resumen</h3>
                        <p className="text-sm leading-relaxed text-[#57534e]">{meeting.summary}</p>
                    </div>

                    <div className={cardClass}>
                        <h3 className="mb-3 text-base font-bold text-[#1c1917]">Contenido completo</h3>
                        <p className="text-sm leading-relaxed text-[#57534e]">{meeting.content}</p>
                    </div>
                </div>

                <div className="flex flex-col gap-6 lg:col-span-2">
                    <DirectorSignaturePanel
                        directorName={meeting.directorName}
                        status={meeting.signatureStatus}
                        signedAt={meeting.signedAt}
                        signedTime={meeting.signedTime}
                    />

                    {canSign && (
                        <BitacoraSignFlow
                            currentStatus={meeting.signatureStatus}
                            onSign={handleSign}
                            onReject={handleReject}
                        />
                    )}

                    {mode === 'coordinador' && (
                        <p className="text-xs text-[#78716c]">
                            Vista de solo lectura para coordinación académica.
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
