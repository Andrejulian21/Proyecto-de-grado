import type { Alerta } from '@/hooks/useAlertas';
import { MOCK_ENTREGAS } from '@/mocks/entregasMock';
import { MOCK_BITACORA_MEETINGS } from '@/mocks/bitacorasMock';

export const MOCK_ALERTAS: Alerta[] = [
    {
        id: 'bitacora-sin-firmar-102',
        tipo: 'bitacora_sin_firmar',
        mensaje: 'Bitácora #102 del proyecto PG-2026-014 sin firmar desde hace 18h',
        proyecto: 'PG-2026-014',
        timestamp: new Date(Date.now() - 18 * 3600000).toISOString(),
        severidad: 'media',
    },
    {
        id: 'entrega-vencida-3',
        tipo: 'entrega_vencida',
        mensaje: 'Entrega "Informe de Avance 1" del proyecto PG-2026-015 venció hace 5 día(s) sin entrega',
        proyecto: 'PG-2026-015',
        timestamp: new Date(Date.now() - 5 * 86400000).toISOString(),
        severidad: 'alta',
    },
    {
        id: 'firmas-sospechosas-gomez',
        tipo: 'firmas_sospechosas',
        mensaje: 'Director "Dr. Carlos Andrés Gómez" registró 3 firmas de bitácora en la última hora (proyecto: PG-2026-014)',
        proyecto: 'PG-2026-014',
        timestamp: new Date().toISOString(),
        severidad: 'media',
    },
];

export function getMockAlertas(): Alerta[] {
    return structuredClone(MOCK_ALERTAS);
}

export interface AuditLogMock {
    id: number;
    user_name: string | null;
    action: string;
    description: string;
    ip_address: string;
    created_at: string;
}

export const MOCK_AUDIT_LOGS: AuditLogMock[] = [
    {
        id: 1,
        user_name: 'Nicolas Moreno',
        action: 'login.success',
        description: 'Google OAuth login',
        ip_address: '192.168.1.10',
        created_at: '2026-07-23T08:15:00',
    },
    {
        id: 2,
        user_name: 'Julian Director',
        action: 'login.success',
        description: 'external evaluator credential login',
        ip_address: '10.0.0.45',
        created_at: '2026-07-23T09:02:00',
    },
    {
        id: 3,
        user_name: 'Nicolas Moreno',
        action: 'proyecto.created',
        description: 'Created project PG-2026-014',
        ip_address: '192.168.1.10',
        created_at: '2026-07-22T14:30:00',
    },
    {
        id: 4,
        user_name: null,
        action: 'login.rejected',
        description: 'invalid_credentials',
        ip_address: '203.0.113.55',
        created_at: '2026-07-22T11:00:00',
    },
    {
        id: 5,
        user_name: 'Dr. Carlos Andrés Gómez',
        action: 'bitacora.signed',
        description: 'Signed bitácora #101',
        ip_address: '172.16.0.8',
        created_at: '2026-07-21T16:45:00',
    },
];

export function getMockAuditLogs(page = 1, perPage = 20) {
    const start = (page - 1) * perPage;
    return {
        data: structuredClone(MOCK_AUDIT_LOGS.slice(start, start + perPage)),
        meta: { current_page: page, last_page: 1, total: MOCK_AUDIT_LOGS.length },
    };
}

/** Admin entregas list shape for useEntregas hook */
export function getMockAdminEntregas() {
    return MOCK_ENTREGAS.map((e) => ({
        id: e.id,
        title: e.title,
        phase: e.phase,
        status: e.status,
        due_date: e.dueDate ?? '',
        start_date: e.startDate ?? null,
        start_time: e.startTime ?? null,
        hora_maxima: e.horaMaxima ?? null,
        acceptance_criteria: e.acceptanceCriteria ?? '',
        description: e.description ?? '',
        proyecto_id: e.project.id,
        grupo_id: 1,
        semester_id: 1,
        semestre_nombre: '2026-1',
        proyecto: { id: e.project.id, code: e.project.code, title: e.project.title, semester_id: 1 },
        project: { id: e.project.id, code: e.project.code, title: e.project.title },
        project_code: e.project.code,
        descripcion: e.description,
        fecha_limite: e.dueDate,
        fase: e.phase,
        submission: e.versiones.length > 0 ? { id: e.versiones[0].id } : null,
    }));
}

export function getDirectorProyectosWithBitacoras(directorId: number) {
    return MOCK_BITACORA_MEETINGS.filter((m) => m.directorName.includes('Gómez')).slice(0, 3);
}
