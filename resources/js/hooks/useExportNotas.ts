import { useCallback, useState } from 'react';
import { apiFetch } from '@/lib/utils';
import { parseFilenameFromDisposition } from '@/hooks/useDirectorCartas';

export interface UseExportNotasResult {
    exporting: boolean;
    error: string | null;
    exportar: (semestreId: number, tipo: string) => Promise<void>;
}

/**
 * Descarga el .xlsx de notas de un semestre.
 *
 * GET /api/admin/notas/export devuelve un StreamedResponse; el nombre real
 * llega en Content-Disposition (filename* RFC 5987) y se conserva en la
 * descarga del navegador.
 */
export function useExportNotas(): UseExportNotasResult {
    const [exporting, setExporting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const exportar = useCallback(async (semestreId: number, tipo: string) => {
        setExporting(true);
        setError(null);

        try {
            const res = await apiFetch(
                `/api/admin/notas/export?semestre_id=${semestreId}&tipo=${tipo}`,
            );

            if (!res.ok) {
                const body = await res.json().catch(() => null);
                throw new Error(
                    body?.error ??
                        `Error ${res.status}: al exportar notas`,
                );
            }

            const blob = await res.blob();
            const filename =
                parseFilenameFromDisposition(
                    res.headers.get('Content-Disposition'),
                ) ?? `notas-${tipo}-semestre-${semestreId}.xlsx`;

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
                    : 'Error desconocido al exportar notas',
            );
        } finally {
            setExporting(false);
        }
    }, []);

    return { exporting, error, exportar };
}
