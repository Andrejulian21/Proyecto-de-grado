import type { ArchivoRequeridoConfig } from '@/types/entregas';

/**
 * Slug of the degree project document. It is the ONLY file whose versions
 * carry director observations (RF-SUP-01) and it is always versioned
 * (RF-SUP-02).
 */
export const SLUG_DOCUMENTO_PROYECTO = 'documento-proyecto';

/**
 * Normalized identity of an archivo requerido. The persisted JSON
 * (`entregas.archivos_requeridos`) stores the item under `slug`; the builder
 * and the runtime API responses expose it as `id`. Prefer `id`, fall back to
 * `slug` so both shapes work.
 */
export function obtenerIdArchivo(config: ArchivoRequeridoConfig): string {
    return config.id || (config as unknown as { slug?: string }).slug || '';
}

/** Whether the archivo is the degree project document (slug `documento-proyecto`). */
export function esDocumentoProyecto(config: ArchivoRequeridoConfig): boolean {
    return obtenerIdArchivo(config) === SLUG_DOCUMENTO_PROYECTO;
}

/**
 * RF-SUP-01/02: an archivo may show/accept director observations only when
 * it is the degree project document AND versioning is enabled. Any other
 * file (auxiliary documents, non-versioned files) never carries observations.
 */
export function archivoAceptaObservaciones(config: ArchivoRequeridoConfig): boolean {
    return esDocumentoProyecto(config) && Boolean(config.versionamiento);
}

/** Minimal version shape required by the grouping helper. */
export interface VersionAgrupable {
    archivo_requerido_id: string | null;
    version_number: number;
}

export interface ArchivoConVersiones<T extends VersionAgrupable> {
    config: ArchivoRequeridoConfig;
    versiones: T[];
}

/**
 * Group the entrega's versions by archivo requerido, normalizing the slug→id
 * identity and sorting versions newest-first. Legacy data (versions without
 * `archivo_requerido_id`) is attributed to the first configured archivo.
 */
export function agruparVersionesPorArchivo<T extends VersionAgrupable>(
    archivos: ArchivoRequeridoConfig[],
    versiones: T[],
): ArchivoConVersiones<T>[] {
    const hasArchivoIds = versiones.some((v) => v.archivo_requerido_id);

    return archivos.map((raw, idx) => {
        const config = { ...raw, id: obtenerIdArchivo(raw) };

        return {
            config,
            versiones: versiones
                .filter((v) => {
                    if (hasArchivoIds) return v.archivo_requerido_id === config.id;
                    // Fallback: first config gets all versions (legacy data)
                    return idx === 0;
                })
                .sort((a, b) => b.version_number - a.version_number),
        };
    });
}
