import { FileText, Download, Calendar } from 'lucide-react';
import type { DeliveryVersionMock } from '@/types/entregas';

interface DeliveryDocumentPreviewProps {
    version: DeliveryVersionMock;
}

function formatDateTime(iso: string): string {
    try {
        return new Date(iso).toLocaleDateString('es-CO', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return iso;
    }
}

export default function DeliveryDocumentPreview({ version }: DeliveryDocumentPreviewProps) {
    return (
        <div className="flex flex-col items-center justify-center gap-4 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] py-16">
            <FileText className="h-16 w-16 text-[#d6d3d1]" />
            <div className="text-center">
                <p className="text-sm font-semibold text-[#1c1917]">{version.fileName}</p>
                <p className="mt-1 flex items-center justify-center gap-1 text-xs text-[#78716c]">
                    <Calendar className="h-3 w-3" />
                    {formatDateTime(version.uploadedAt)}
                </p>
            </div>
            <button
                type="button"
                onClick={() => {
                    /* mock download — integración futura */
                }}
                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a]"
            >
                <Download className="h-4 w-4" />
                Abrir documento
            </button>
        </div>
    );
}
