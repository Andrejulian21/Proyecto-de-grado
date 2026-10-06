/**
 * Response shapes of GET /api/director/proyectos/{id} as the director's
 * supervision view consumes them.
 *
 * They live apart from the page because the project contract is the thing that
 * has to stay in sync with the endpoint: `ProjectDelivery.status` and `.grade`
 * are resolved PER PROJECT by the backend (entrega_proyecto pivot), not read off
 * the shared semester-wide entrega template.
 */

export interface ProjectDelivery {
    id: number;
    title: string;
    description?: string;
    due_date: string;
    phase: string;
    /** Verdict for THIS project; never the template's. */
    status: string;
    /** Grade for THIS project, or null when ungraded. */
    grade?: string | number | null;
}

export interface ProjectDetail {
    id: number;
    code: string;
    title: string;
    description?: string;
    status: string;
    current_phase: string | null;
    estudiantes: { id: number; name: string }[];
    tipo?: string;
    period?: string;
    start_date?: string;
    end_date?: string;
    entregas?: ProjectDelivery[];
}