import { Eye, PenSquare } from 'lucide-react';
import { StatusBadge } from '@/components/ui/StatusBadge';
import {
    type BitacoraMeeting,
    formatCreatedAt,
    formatMeetingDate,
    signatureStatusConfig,
} from '@/mocks/bitacorasMock';

interface BitacoraMeetingCardProps {
    meeting: BitacoraMeeting;
    role: 'director' | 'coordinador';
    onView: () => void;
    onReview?: () => void;
}

export function BitacoraMeetingCard({ meeting, role, onView, onReview }: BitacoraMeetingCardProps) {
    const status = signatureStatusConfig[meeting.signatureStatus];
    const canReview = role === 'director' && meeting.signatureStatus === 'pendiente';

    return (
        <article className="flex flex-col gap-4 rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge variant={status.variant}>{status.label}</StatusBadge>
                        <span className="text-xs text-[#78716c]">
                            Reunión: {formatMeetingDate(meeting.meetingDate)}
                        </span>
                    </div>
                    <h3 className="mt-2 text-base font-bold text-[#1c1917]">{meeting.topic}</h3>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-2 text-xs text-[#57534e] sm:grid-cols-2">
                <p>
                    <span className="font-semibold text-[#1c1917]">Proyecto:</span>{' '}
                    {meeting.projectCode} — {meeting.projectName}
                </p>
                <p>
                    <span className="font-semibold text-[#1c1917]">Director:</span> {meeting.directorName}
                </p>
                <p className="sm:col-span-2">
                    <span className="font-semibold text-[#1c1917]">Integrantes:</span>{' '}
                    {meeting.members.join(', ')}
                </p>
                <p>
                    <span className="font-semibold text-[#1c1917]">Creada:</span>{' '}
                    {formatCreatedAt(meeting.createdAt)}
                </p>
                {meeting.signatureStatus === 'firmado' && meeting.signedAt && (
                    <p>
                        <span className="font-semibold text-[#1c1917]">Firmada:</span>{' '}
                        {meeting.signedAt} {meeting.signedTime}
                    </p>
                )}
            </div>

            <p className="line-clamp-2 text-sm leading-relaxed text-[#57534e]">{meeting.summary}</p>

            {meeting.signatureStatus === 'rechazado' && meeting.rejectionComment && (
                <div className="rounded-lg border border-[#fee2e2] bg-[#fef2f2] px-3 py-2 text-xs text-[#7f1d1d]">
                    <span className="font-semibold">Motivo del rechazo:</span> {meeting.rejectionComment}
                </div>
            )}

            <div className="flex flex-wrap justify-end gap-2 border-t border-[#e5e5e5] pt-3">
                <button
                    type="button"
                    onClick={onView}
                    className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                >
                    <Eye className="h-3.5 w-3.5" />
                    Ver detalle
                </button>
                {canReview && onReview && (
                    <button
                        type="button"
                        onClick={onReview}
                        className="inline-flex min-h-[36px] items-center gap-2 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#9a330a]"
                    >
                        <PenSquare className="h-3.5 w-3.5" />
                        Revisar y firmar
                    </button>
                )}
            </div>
        </article>
    );
}
