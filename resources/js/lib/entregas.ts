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

/* ── Entrega status → label + badge variant ── */

/**
 * Canonical entrega status mapping shared by every view that renders a
 * delivery state: the read-only delivery list (student dashboard, director
 * supervision, coordinator supervision), the student delivery detail, the
 * director dashboard table and the delivery review screens. No view may keep
 * its own copy — that duplication is what let the same delivery read
 * differently depending on who was looking at it.
 */
export const ENTREGA_STATUS_MAP: Record<
    string,
    { label: string; variant: 'success' | 'warning' | 'error' | 'info' | 'inactivo' }
> = {
    aprobada: { label: 'Aprobada', variant: 'success' },
    aprobado: { label: 'Aprobada', variant: 'success' },
    rechazada: { label: 'Necesita ajustes', variant: 'warning' },
    rechazado: { label: 'Necesita ajustes', variant: 'warning' },
    revisada: { label: 'Necesita ajustes', variant: 'warning' },
    enviada: { label: 'En revisión', variant: 'info' },
    pendiente: { label: 'Sin revisar', variant: 'warning' },
    solicitada: { label: 'Sin entregar', variant: 'inactivo' },
    creacion: { label: 'Sin entregar', variant: 'inactivo' },
};

/**
 * Resolve the config for a status, falling back to the raw value.
 *
 * The lookup is case-insensitive because this map is the only place that
 * decides what a delivery status reads like: a display-cased value that missed
 * the map would leak through as an unstyled badge with an unmapped variant.
 */
export function entregaStatusConfig(status: string | null) {
    if (!status) return { label: 'Sin revisar', variant: 'warning' as const };
    return ENTREGA_STATUS_MAP[status.trim().toLowerCase()] ?? { label: status, variant: 'inactivo' as const };
}

/* ── Entrega status → read-only list layout state ── */

/**
 * Layout state of a row in the read-only delivery list.
 *
 * It is deliberately coarser than the API status: the list only needs it to
 * pick the contextual sentence shown when a row expands, while the badge label
 * comes from ENTREGA_STATUS_MAP. Keeping the two concerns apart is what lets a
 * submitted-but-ungraded delivery share supervision's "pending" row behaviour
 * without inheriting supervision's label for it.
 */
export type ReadOnlyDeliveryStatus = 'approved' | 'pending' | 'corrections' | 'rejected';

/**
 * The single API-status → layout-state mapping behind every read-only delivery
 * list (student dashboard, director supervision, coordinator supervision).
 *
 * 'enviada' — submitted, not yet graded — lands on the default 'pending'
 * branch on purpose: all three views must agree on the row behaviour. Its badge
 * still reads "En revisión", because the label is resolved from the raw value
 * and never from the layout state.
 */
export function entregaLayoutStatus(status: string | null | undefined): ReadOnlyDeliveryStatus {
    switch (status?.trim().toLowerCase()) {
        case 'aprobada':
        case 'aprobado':
            return 'approved';
        case 'rechazada':
        case 'rechazado':
            return 'rejected';
        case 'revisada':
        case 'revisado':
            return 'corrections';
        default:
            return 'pending';
    }
}
