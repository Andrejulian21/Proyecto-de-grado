export interface RecursoMock {
    id: number;
    title: string;
    category: 'documento' | 'formato' | 'enlace' | 'guia' | 'plantilla' | 'tutorial' | 'reglamento';
    description: string;
    author: string;
    author_id: number;
    link?: string | null;
    file_path?: string | null;
    file_size?: number | null;
    downloads: number;
    created_at: string;
    is_active: boolean;
}

let recursosStore: RecursoMock[] = [
    {
        id: 1,
        title: 'Guía para elaboración de anteproyecto',
        category: 'guia',
        description:
            'Guía completa con la estructura y requisitos para la elaboración del documento de anteproyecto de grado. Incluye ejemplos de marco teórico y cronograma.',
        author: 'Coordinación PG',
        author_id: 1,
        file_path: 'recursos/guia-anteproyecto.pdf',
        file_size: 1240,
        downloads: 156,
        created_at: '2026-01-15T10:00:00',
        is_active: true,
    },
    {
        id: 2,
        title: 'Formato de bitácora oficial',
        category: 'formato',
        description: 'Formato oficial para el registro de bitácoras de proyecto de grado con campos obligatorios.',
        author: 'Coordinación PG',
        author_id: 1,
        file_path: 'recursos/formato-bitacora.docx',
        file_size: 89,
        downloads: 203,
        created_at: '2026-01-15T10:00:00',
        is_active: true,
    },
    {
        id: 3,
        title: 'Normas APA séptima edición',
        category: 'enlace',
        description: 'Resumen de las normas APA séptima edición para la presentación de trabajos académicos.',
        author: 'Biblioteca UNAB',
        author_id: 2,
        link: 'https://normasapa.com/',
        downloads: 412,
        created_at: '2026-01-20T08:00:00',
        is_active: true,
    },
    {
        id: 4,
        title: 'Reglamento de proyectos de grado',
        category: 'reglamento',
        description: 'Reglamento institucional vigente para la inscripción, desarrollo y sustentación de proyectos de grado.',
        author: 'Facultad de Ingeniería',
        author_id: 1,
        file_path: 'recursos/reglamento-pg.pdf',
        file_size: 890,
        downloads: 98,
        created_at: '2025-12-01T12:00:00',
        is_active: true,
    },
    {
        id: 5,
        title: 'Plantilla informe final',
        category: 'plantilla',
        description: 'Plantilla Word para el informe final del proyecto de grado con secciones predefinidas.',
        author: 'Coordinación PG',
        author_id: 1,
        file_path: 'recursos/plantilla-final.docx',
        file_size: 156,
        downloads: 67,
        created_at: '2026-02-01T09:00:00',
        is_active: true,
    },
];

export function getRecursos(): RecursoMock[] {
    return structuredClone(recursosStore.filter((r) => r.is_active));
}

export function getAllRecursosAdmin(): RecursoMock[] {
    return structuredClone(recursosStore);
}

export function getRecursoById(id: number): RecursoMock | undefined {
    return structuredClone(recursosStore.find((r) => r.id === id));
}

export function createRecurso(payload: Partial<RecursoMock>): RecursoMock {
    const next: RecursoMock = {
        id: Math.max(0, ...recursosStore.map((r) => r.id)) + 1,
        title: payload.title ?? 'Nuevo recurso',
        category: payload.category ?? 'documento',
        description: payload.description ?? '',
        author: payload.author ?? 'Coordinador',
        author_id: payload.author_id ?? 1,
        link: payload.link ?? null,
        file_path: payload.file_path ?? null,
        file_size: payload.file_size ?? null,
        downloads: 0,
        created_at: new Date().toISOString(),
        is_active: payload.is_active ?? true,
    };
    recursosStore = [...recursosStore, next];
    return structuredClone(next);
}

export function updateRecurso(id: number, payload: Partial<RecursoMock>): RecursoMock | undefined {
    const idx = recursosStore.findIndex((r) => r.id === id);
    if (idx === -1) return undefined;
    recursosStore[idx] = { ...recursosStore[idx], ...payload, id };
    return structuredClone(recursosStore[idx]);
}

export function deleteRecurso(id: number): boolean {
    const before = recursosStore.length;
    recursosStore = recursosStore.filter((r) => r.id !== id);
    return recursosStore.length < before;
}
