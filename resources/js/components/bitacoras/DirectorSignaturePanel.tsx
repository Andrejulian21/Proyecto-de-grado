import { User, Calendar, Clock } from 'lucide-react';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { type SignatureStatus, signatureStatusConfig } from '@/mocks/bitacorasMock';

interface DirectorSignaturePanelProps {
    directorName: string;
    status: SignatureStatus;
    signedAt?: string;
    signedTime?: string;
}

export function DirectorSignaturePanel({
    directorName,
    status,
    signedAt,
    signedTime,
}: DirectorSignaturePanelProps) {
    const config = signatureStatusConfig[status];

    return (
        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <h3 className="mb-4 text-base font-bold text-[#1c1917]">Firma del director</h3>

            <div className="flex flex-col gap-3">
                <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                    <User className="h-5 w-5 shrink-0 text-[#c2410c]" />
                    <div>
                        <p className="text-xs text-[#78716c]">Director</p>
                        <p className="text-sm font-semibold text-[#1c1917]">{directorName}</p>
                    </div>
                </div>

                <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                    <div className="flex-1">
                        <p className="text-xs text-[#78716c]">Estado</p>
                        <StatusBadge variant={config.variant}>{config.label}</StatusBadge>
                    </div>
                </div>

                {status === 'firmado' && signedAt && (
                    <>
                        <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                            <Calendar className="h-5 w-5 shrink-0 text-[#4f46e5]" />
                            <div>
                                <p className="text-xs text-[#78716c]">Fecha de firma</p>
                                <p className="text-sm font-semibold text-[#1c1917]">{signedAt}</p>
                            </div>
                        </div>
                        {signedTime && (
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <Clock className="h-5 w-5 shrink-0 text-[#4f46e5]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Hora de firma</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">{signedTime}</p>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
