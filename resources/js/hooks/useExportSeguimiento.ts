import { useCallback, useState } from 'react';
import { apiFetch } from '@/lib/utils';
import { parseFilenameFromDisposition } from '@/hooks/useDirectorCartas';

export interface UseExportSeguimientoResult {
    exporting: boolean;
    error: string | null;
    exportar: (semestreId: number) => Promise<void>;
}

/**
 * Descarga el .xlsx de seguimiento de un semestre (RF-EX-01, D5).
 *
 * GET /api/admin/seguimiento/semestre/{id}/export devuelve un
 * StreamedResponse; el nombre real llega en Content-Disposition
 * (filename* RFC 5987) y se conserva en la descarga del navegador.
 */
export function useExportSeguimiento(): UseExportSeguimientoResult {
    const [exporting, setExporting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const exportar = useCallback(async (semestreId: number) => {
        setExporting(true);
        setError(null);

        try {
            const res = await apiFetch(
                `/api/admin/seguimiento/semestre/${semestreId}/export`,
            );

            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(
                    body?.error ??
                        `Error ${res.status}: al exportar seguimiento`,
                );
            }

            const blob = await res.blob();
            const filename =
                parseFilenameFromDisposition(
                    res.headers.get('Content-Disposition'),
                ) ?? `seguimiento-semestre-${semestreId}.xlsx`;

            const objectUrl = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = objectUrl;
            anchor.download = filename;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            URL.revokeObjectURL(objectUrl);
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : 'Error desconocido al exportar',
            );
        } finally {
            setExporting(false);
        }
    }, []);

    return { exporting, error, exportar };
}