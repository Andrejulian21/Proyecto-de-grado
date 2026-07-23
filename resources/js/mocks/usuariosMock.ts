export interface UsuarioMock {
    id: number;
    name: string;
    email: string;
    role: string;
    es_externo: boolean;
    created_at: string;
}

export interface WhitelistMock {
    id: number;
    email: string;
    name: string;
    role: string;
    created_at: string;
}

export interface EvaluadorMock {
    id: number;
    name: string;
    email: string;
    institution: string;
    is_active: boolean;
}

let usuariosStore: UsuarioMock[] = [
    { id: 1, name: 'Nicolas Moreno', email: 'nmoreno534@unab.edu.co', role: 'Coordinador', es_externo: false, created_at: '2026-01-01' },
    { id: 2, name: 'Juan Arteaga', email: 'jarteaga145@unab.edu.co', role: 'Coordinador', es_externo: false, created_at: '2026-01-01' },
    { id: 10, name: 'Dr. Carlos Andrés Gómez', email: 'cgomez@unab.edu.co', role: 'Director', es_externo: false, created_at: '2026-01-05' },
    { id: 11, name: 'Dra. Laura Martínez', email: 'lmartinez@unab.edu.co', role: 'Director', es_externo: false, created_at: '2026-01-05' },
    { id: 20, name: 'Ana Martínez', email: 'ana.m@unab.edu.co', role: 'Estudiante', es_externo: true, created_at: '2026-02-01' },
    { id: 30, name: 'Pedro Evaluador', email: 'pedro.eval@externo.com', role: 'EvaluadorExterno', es_externo: true, created_at: '2026-02-10' },
];

let whitelistStore: WhitelistMock[] = [
    { id: 1, email: 'nmoreno534@unab.edu.co', name: 'Nicolas Moreno', role: 'Coordinador', created_at: '2026-01-01' },
    { id: 2, email: 'jarteaga145@unab.edu.co', name: 'Juan Arteaga', role: 'Coordinador', created_at: '2026-01-01' },
    { id: 3, email: 'cgomez@unab.edu.co', name: 'Dr. Carlos Andrés Gómez', role: 'Director', created_at: '2026-01-05' },
];

let evaluadoresStore: EvaluadorMock[] = [
    { id: 30, name: 'Pedro Evaluador', email: 'pedro.eval@externo.com', institution: 'UIS', is_active: true },
    { id: 31, name: 'María Externa', email: 'maria.ext@uis.edu.co', institution: 'UIS', is_active: true },
    { id: 32, name: 'Carlos Revisor', email: 'carlos.rev@eafit.edu.co', institution: 'EAFIT', is_active: false },
];

export function getMockUsuarios() {
    return structuredClone(usuariosStore);
}

export function getMockWhitelist() {
    return structuredClone(whitelistStore);
}

export function getMockEvaluadores() {
    return structuredClone(evaluadoresStore);
}

export function createMockEvaluador(payload: Partial<EvaluadorMock>) {
    const next = {
        id: Math.max(0, ...evaluadoresStore.map((e) => e.id)) + 1,
        name: payload.name ?? '',
        email: payload.email ?? '',
        institution: payload.institution ?? '',
        is_active: payload.is_active ?? true,
    };
    evaluadoresStore = [...evaluadoresStore, next];
    return structuredClone(next);
}

export function deleteMockUsuario(id: number) {
    usuariosStore = usuariosStore.filter((u) => u.id !== id);
}

export function deleteMockWhitelist(id: number) {
    whitelistStore = whitelistStore.filter((w) => w.id !== id);
}

export interface EvaluadorProyectoMock {
    id: number;
    evaluador_id: number;
    evaluador_name: string;
    proyecto_id: number;
    proyecto_code: string;
    proyecto_title: string;
    assigned_at: string;
}

let evaluadorProyectoStore: EvaluadorProyectoMock[] = [
    {
        id: 1,
        evaluador_id: 30,
        evaluador_name: 'Pedro Evaluador',
        proyecto_id: 1,
        proyecto_code: 'PG-2026-014',
        proyecto_title: 'Sistema Centralizado de Proyectos de Grado',
        assigned_at: '2026-03-01T10:00:00',
    },
    {
        id: 2,
        evaluador_id: 31,
        evaluador_name: 'María Externa',
        proyecto_id: 3,
        proyecto_code: 'PG-2026-008',
        proyecto_title: 'Dashboard de Indicadores de Gestión Académica',
        assigned_at: '2026-03-05T14:00:00',
    },
];

export function getMockEvaluadorProyectoAsignaciones() {
    return [
        {
            id: 1,
            proyecto_id: 1,
            proyecto_codigo: 'PG-2026-014',
            proyecto_nombre: 'Sistema Centralizado de Proyectos de Grado',
            proyecto_director_id: 10,
            proyecto_director_nombre: 'Dr. Carlos Andrés Gómez',
            estudiantes: [
                { id: 201, name: 'Ana Martínez' },
                { id: 202, name: 'Luis Felipe Ríos' },
            ],
            fase: 'Anteproyecto' as const,
            fecha: '2026-04-15',
            hora_inicio: '14:00',
            hora_fin: '16:00',
            hora: '14:00',
            evaluadores_list: [
                { id: 30, name: 'Pedro Evaluador', email: 'pedro.eval@externo.com', role: 'EvaluadorExterno', assignment_id: 1 },
            ],
            evaluador_principal_id: 30,
            evaluador_principal_nombre: 'Pedro Evaluador',
            evaluador_secundario_id: null,
            evaluador_secundario_nombre: null,
            evaluador_tercero_id: null,
            evaluador_tercero_nombre: null,
        },
    ];
}


export function assignMockEvaluadorProyecto(payload: Omit<EvaluadorProyectoMock, 'id' | 'assigned_at'>) {
    const next: EvaluadorProyectoMock = {
        ...payload,
        id: Math.max(0, ...evaluadorProyectoStore.map((e) => e.id)) + 1,
        assigned_at: new Date().toISOString(),
    };
    evaluadorProyectoStore = [...evaluadorProyectoStore, next];
    return structuredClone(next);
}

export function removeMockEvaluadorProyecto(id: number) {
    evaluadorProyectoStore = evaluadorProyectoStore.filter((e) => e.id !== id);
}
