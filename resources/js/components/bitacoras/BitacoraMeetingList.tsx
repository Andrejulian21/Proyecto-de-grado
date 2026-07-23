import { BitacoraMeetingCard } from '@/components/bitacoras/BitacoraMeetingCard';
import { EmptyState } from '@/components/ui/EmptyState';
import type { BitacoraMeeting, SignatureStatus } from '@/mocks/bitacorasMock';
import { FileText } from 'lucide-react';

interface BitacoraMeetingListProps {
    meetings: BitacoraMeeting[];
    role: 'director' | 'coordinador';
    filterStatus: SignatureStatus | 'all';
    onFilterChange: (status: SignatureStatus | 'all') => void;
    onView: (meetingId: number) => void;
    onReview?: (meetingId: number) => void;
}

export function BitacoraMeetingList({
    meetings,
    role,
    filterStatus,
    onFilterChange,
    onView,
    onReview,
}: BitacoraMeetingListProps) {
    const filtered =
        filterStatus === 'all'
            ? meetings
            : meetings.filter((m) => m.signatureStatus === filterStatus);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex justify-end">
                <select
                    value={filterStatus}
                    onChange={(e) => onFilterChange(e.target.value as SignatureStatus | 'all')}
                    className="min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                    aria-label="Filtrar por estado de firma"
                >
                    <option value="all">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="firmado">Firmado</option>
                    <option value="rechazado">Rechazado</option>
                </select>
            </div>

            {filtered.length === 0 ? (
                <EmptyState
                    icon={FileText}
                    title="Sin reuniones"
                    description={
                        filterStatus !== 'all'
                            ? 'No hay bitácoras con el estado seleccionado.'
                            : 'Este proyecto aún no tiene bitácoras registradas.'
                    }
                />
            ) : (
                <div className="flex flex-col gap-4">
                    {filtered.map((meeting) => (
                        <BitacoraMeetingCard
                            key={meeting.id}
                            meeting={meeting}
                            role={role}
                            onView={() => onView(meeting.id)}
                            onReview={onReview ? () => onReview(meeting.id) : undefined}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
