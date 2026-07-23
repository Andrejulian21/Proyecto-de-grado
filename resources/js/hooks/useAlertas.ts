import { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '@/lib/utils';
import { FRONTEND_VALIDATION_MODE, mockDelay } from '@/mocks/validationMode';
import { getMockAlertas } from '@/mocks/coordinadorMock';

export interface Alerta {
    id: string;
    tipo: 'bitacora_sin_firmar' | 'entrega_vencida' | 'firmas_sospechosas';
    mensaje: string;
    proyecto: string;
    timestamp: string;
    severidad: 'alta' | 'media';
}

interface BitacoraEntry {
    id: number;
    proyecto_id?: number;
    proyecto?: { code: string; title: string };
    project_code?: string;
    director_id?: number;
    director_name?: string;
    created_at: string;
    signed_at?: string | null;
    fecha_firma?: string | null;
    status?: string;
}

interface EntregaEntry {
    id: number;
    grupo_id: number;
    fase: string;
    descripcion?: string;
    fecha_limite: string;
    project?: { code: string; title: string };
    project_code?: string;
    submission?: { id: number } | null;
    status?: string;
}

interface UseAlertasResult {
    data: Alerta[];
    loading: boolean;
    error: string | null;
    refetch: () => void;
}

export function useAlertas(): UseAlertasResult {
    const [data, setData] = useState<Alerta[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const deriveAlertas = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            if (FRONTEND_VALIDATION_MODE) {
                await mockDelay();
                setData(getMockAlertas());
                return;
            }
            // Fetch source data from existing endpoints
            const [bitacorasRes, entregasRes] = await Promise.all([
                apiFetch('/api/admin/bitacoras?limit=200'),
                apiFetch('/api/admin/entregas?limit=200'),
            ]);

            const bitacoras: BitacoraEntry[] = bitacorasRes.ok
                ? (await bitacorasRes.json()).data ?? []
                : [];
            const entregas: EntregaEntry[] = entregasRes.ok
                ? (await entregasRes.json()).data ?? []
                : [];

            const alertas: Alerta[] = [];
            const now = new Date();

            // Regla 1: Bitácoras sin firmar > 1h desde creación
            for (const bit of bitacoras) {
                if (!bit.created_at) continue;
                const createdAt = new Date(bit.created_at);
                if (isNaN(createdAt.getTime())) continue;
                const diffMs = now.getTime() - createdAt.getTime();
                const diffHours = diffMs / (1000 * 60 * 60);

                const isUnsigned =
                    bit.signed_at === null ||
                    bit.signed_at === undefined ||
                    bit.fecha_firma === null ||
                    bit.fecha_firma === undefined ||
                    bit.status === 'pendiente';

                if (isUnsigned && diffHours > 1) {
                    const projectRef = bit.project_code ?? bit.proyecto?.code ?? '—';
                    alertas.push({
                        id: `bitacora-sin-firmar-${bit.id}`,
                        tipo: 'bitacora_sin_firmar',
                        mensaje: `Bitácora #${bit.id} del proyecto ${projectRef} sin firmar desde hace ${Math.round(diffHours)}h`,
                        proyecto: projectRef,
                        timestamp: createdAt.toISOString(),
                        severidad: diffHours > 24 ? 'alta' : 'media',
                    });
                }
            }

            // Regla 2: Entregas con deadline pasado y sin submission
            for (const ent of entregas) {
                if (!ent.fecha_limite) continue;
                const deadline = new Date(ent.fecha_limite);
                if (isNaN(deadline.getTime()) || deadline >= now) continue;

                const hasSubmission =
                    ent.submission != null ||
                    ent.status === 'submitted' ||
                    ent.status === 'approved';

                if (!hasSubmission) {
                    const projectRef = ent.project_code ?? '—';
                    const diffDays = Math.round(
                        (now.getTime() - deadline.getTime()) / (1000 * 60 * 60 * 24),
                    );
                    alertas.push({
                        id: `entrega-vencida-${ent.id}`,
                        tipo: 'entrega_vencida',
                        mensaje: `Entrega "${ent.descripcion ?? ent.fase}" del proyecto ${projectRef} venció hace ${diffDays} día(s) sin entrega`,
                        proyecto: projectRef,
                        timestamp: deadline.toISOString(),
                        severidad: diffDays > 7 ? 'alta' : 'media',
                    });
                }
            }

            // Regla 3: Directores con >2 firmas de bitácora en ventana de 1h
            const signWindow: Record<string, { count: number; project: string; director: string }> = {};
            for (const bit of bitacoras) {
                const signTime = bit.fecha_firma ?? bit.signed_at;
                if (!signTime) continue;

                const signDate = new Date(signTime);
                if (isNaN(signDate.getTime())) continue;
                const diffMs = now.getTime() - signDate.getTime();
                if (diffMs > 60 * 60 * 1000) continue; // Only last 1h

                // Group by director
                const dirKey = bit.director_name ?? `dir_${bit.director_id}`;
                if (!signWindow[dirKey]) {
                    signWindow[dirKey] = { count: 0, project: '', director: dirKey };
                }
                signWindow[dirKey].count++;
                signWindow[dirKey].project =
                    bit.project_code ?? bit.proyecto?.code ?? '—';
            }

            for (const [dirKey, info] of Object.entries(signWindow)) {
                if (info.count > 2) {
                    alertas.push({
                        id: `firmas-sospechosas-${dirKey}`,
                        tipo: 'firmas_sospechosas',
                        mensaje: `Director "${info.director}" registró ${info.count} firmas de bitácora en la última hora (proyecto: ${info.project})`,
                        proyecto: info.project,
                        timestamp: now.toISOString(),
                        severidad: 'media',
                    });
                }
            }

            setData(alertas);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            setError(message);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        deriveAlertas();
    }, [deriveAlertas]);

    return { data, loading, error, refetch: deriveAlertas };
}
