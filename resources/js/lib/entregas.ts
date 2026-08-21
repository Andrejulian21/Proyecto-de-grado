import type { DocumentoSolicitado } from '@/types/entregas';

/** @deprecated Prefer DocumentoSolicitado; kept as a type alias. */
export type ArchivoRequeridoConfig = DocumentoSolicitado;

/**
 * Normalized identity of a requested document. The persisted JSON
 * (`entregas.archivos_requeridos`) stores the item under `slug`; the builder
 * and the runtime API responses expose it as `id`.
 */
export function obtenerIdArchivo(config: DocumentoSolicitado): string {
    return config.id || config.slug || '';
}

export function esDocumentoAnalizableIa(config: DocumentoSolicitado): boolean {
    return Boolean(config.analizable_ia);
}

export function idDocumentoAnalizableIa(documentos: DocumentoSolicitado[]): string | null {
    const doc = documentos.find(esDocumentoAnalizableIa);
    return doc ? obtenerIdArchivo(doc) : null;
}

/** Minimal version shape required by the grouping helper. */
export interface VersionAgrupable {
    archivo_requerido_id: string | null;
    version_number: number;
}

export interface DocumentoConVersiones<T extends VersionAgrupable> {
    config: DocumentoSolicitado;
    versiones: T[];
}

/** @deprecated Use DocumentoConVersiones */
export type ArchivoConVersiones<T extends VersionAgrupable> = DocumentoConVersiones<T>;

/**
 * Group the entrega's versions by requested document, normalizing the slug→id
 * identity and sorting versions newest-first. Legacy data (versions without
 * `archivo_requerido_id`) is attributed to the first configured document.
 */
export function agruparVersionesPorArchivo<T extends VersionAgrupable>(
    archivos: DocumentoSolicitado[],
    versiones: T[],
): DocumentoConVersiones<T>[] {
    const hasArchivoIds = versiones.some((v) => v.archivo_requerido_id);

    return archivos.map((raw, idx) => {
        const config = { ...raw, id: obtenerIdArchivo(raw) };

        return {
            config,
            versiones: versiones
                .filter((v) => {
                    if (hasArchivoIds) return v.archivo_requerido_id === config.id;
                    return idx === 0;
                })
                .sort((a, b) => b.version_number - a.version_number),
        };
    });
}
