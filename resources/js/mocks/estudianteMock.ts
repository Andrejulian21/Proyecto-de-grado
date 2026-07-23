import { getMeetingsByProject } from '@/mocks/bitacorasMock';
import { getEntregasSummaryForDashboard } from '@/mocks/entregasMock';

/** Primary student project — IDs aligned with entregasMock + bitacorasMock (project id 1). */
export const MOCK_ESTUDIANTE_PROYECTO = {
    id: 1,
    code: 'PG-2026-014',
    title: 'Sistema Centralizado de Proyectos de Grado',
    current_phase: 'desarrollo',
    status: 'EnCurso',
    director: { id: 10, name: 'Dr. Carlos Andrés Gómez', email: 'cgomez@unab.edu.co' },
    estudiantes: [
        { id: 201, name: 'Ana Martínez' },
        { id: 202, name: 'Luis Felipe Ríos' },
    ],
    semester: { id: 1, name: '2026-1', is_active: true },
    description:
        'Plataforma web para gestionar el ciclo de vida completo de proyectos de grado en Ingeniería de Sistemas UNAB.',
    start_date: '2026-02-01',
    end_date: '2026-06-30',
};

export function getEstudianteProyecto() {
    return structuredClone(MOCK_ESTUDIANTE_PROYECTO);
}

export function getEstudianteEntregas() {
    return getEntregasSummaryForDashboard();
}

export function getEstudianteBitacoras() {
    return getMeetingsByProject(MOCK_ESTUDIANTE_PROYECTO.id).map((m) => ({
        id: m.id,
        tema: m.topic,
        topic: m.topic,
        notes: m.summary,
        observaciones: m.content,
        meeting_date: m.meetingDate,
        fecha_reunion: m.meetingDate,
        duration_hours: 1.5,
        duracion_horas: 1.5,
        signature_status:
            m.signatureStatus === 'firmado'
                ? 'Completada'
                : m.signatureStatus === 'rechazado'
                  ? 'Rechazada'
                  : 'Pendiente',
        estado_firma:
            m.signatureStatus === 'firmado'
                ? 'Completada'
                : m.signatureStatus === 'rechazado'
                  ? 'Rechazada'
                  : 'Pendiente',
        proyecto_id: m.projectId,
    }));
}
