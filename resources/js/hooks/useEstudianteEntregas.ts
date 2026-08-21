import { useCallback, useEffect, useState } from 'react';
import { apiFetch } from '@/lib/utils';
import type { EntregaData } from '@/types/estudiante';

const LABELS: Record<string, string> = {
    anteproyecto: 'Documento de Anteproyecto',
    presentacion_anteproyecto: 'Presentación Anteproyecto',
    desarrollo: 'Informe de Avance',
    presentacion_final: 'Informe Final',
};

export const ESTUDIANTE_PHASES = [
    { id: 'anteproyecto', label: 'Anteproyecto' },
    { id: 'presentacion_anteproyecto', label: 'Presentación Anteproyecto' },
    { id: 'desarrollo', label: 'Desarrollo del proyecto' },
    { id: 'presentacion_final', label: 'Presentación Final' },
] as const;

function toDate(d: string | undefined) {
    return d
        ? new Date(d).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' })
        : '—';
}

function toDateTime(d: string | undefined) {
    if (!d) return '—';
    return new Date(d).toLocaleString('es-CO', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function previewObservation(text: string | null | undefined): string | null {
    if (!text || !text.trim()) return null;
    const trimmed = text.trim();
    return trimmed.length <= 80 ? trimmed : `${trimmed.slice(0, 80)}…`;
}

function mapStatus(s: string | undefined): EntregaData['status'] {
    if (s === 'aprobada' || s === 'Aprobada') return 'approved';
    if (s === 'enviada' || s === 'Enviada') return 'enviada';
    if (s === 'pendiente' || s === 'Pendiente') return 'pending';
    return 'pending';
}

function mapVersionStatus(s: string | undefined): EntregaData['versions'][number]['status'] {
    if (s === 'aprobado' || s === 'aprobada') return 'approved';
    if (s === 'rechazado' || s === 'rechazada' || s === 'revisada') return 'rejected';
    return 'pending';
}

export interface EntregaAnalisisContext {
    id: number;
    titulo: string;
    fase: string;
    faseLabel: string;
    descripcion: string | null;
    metricas_evaluacion: string | null;
    criterios_aceptacion: string | null;
    estado: string;
}

export function buildPhaseSteps(current: string) {
    const idx = ESTUDIANTE_PHASES.findIndex((p) => p.id === current);
    return ESTUDIANTE_PHASES.map((p, i) => ({
        ...p,
        status: (i < idx ? 'done' : i === idx ? 'current' : 'future') as 'done' | 'current' | 'future',
    }));
}

export function mapEntregaToAnalisisContext(raw: any): EntregaAnalisisContext {
    const fase = raw.fase ?? raw.phase ?? '';
    return {
        id: raw.id,
        titulo: raw.titulo || raw.title || LABELS[fase] || `Entrega #${raw.id}`,
        fase,
        faseLabel: ESTUDIANTE_PHASES.find((p) => p.id === fase)?.label ?? fase,
        descripcion: raw.descripcion ?? raw.description ?? null,
        metricas_evaluacion: raw.metricas_evaluacion ?? raw.evaluation_metrics ?? null,
        criterios_aceptacion: raw.criterios ?? raw.acceptance_criteria ?? null,
        estado: raw.estado || raw.status || '',
    };
}

export function useEstudianteEntregas() {
    const [proyecto, setProyecto] = useState<any>(null);
    const [entregas, setEntregas] = useState<EntregaData[]>([]);
    const [rawEntregas, setRawEntregas] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const reload = useCallback(async () => {
        setLoading(true);
        setError('');
        try {
            const [pr, er] = await Promise.all([
                apiFetch('/api/estudiante/proyecto'),
                apiFetch('/api/estudiante/entregas'),
            ]);
            if (!pr.ok || !er.ok) {
                setError('Error al cargar los datos.');
                return;
            }
            const pd = await pr.json();
            const ed = await er.json();
            const list = ed.data || [];
            setProyecto(pd.data);
            setRawEntregas(list);
            setEntregas(
                list.map((e: any) => ({
                    id: e.id,
                    fase: e.fase,
                    title: e.titulo || e.title || LABELS[e.fase] || `Entrega #${e.id}`,
                    status: mapStatus(e.estado || e.status),
                    deadline: toDate(e.fecha_limite || e.due_date),
                    grade: e.nota ?? e.consolidated_grade ?? null,
                    versions: (e.versiones || []).map((v: any) => ({
                        version: v.numero_version ?? v.version_number ?? 0,
                        date: toDateTime(v.subido_en || v.uploaded_at || v.created_at),
                        status: mapVersionStatus(v.estado || v.status),
                        fileName:
                            v.nombre_archivo ||
                            v.original_name ||
                            (v.ruta_archivo || '').split('/').pop() ||
                            'documento.pdf',
                        observaciones: previewObservation(v.observacion ?? v.director_notes),
                    })),
                })),
            );
        } catch {
            setError('Error de conexión.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void reload();
    }, [reload]);

    return { proyecto, entregas, rawEntregas, loading, error, reload };
}
