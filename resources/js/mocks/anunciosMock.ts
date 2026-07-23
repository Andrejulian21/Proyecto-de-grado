export interface AnuncioMock {
    id: number;
    title: string;
    category: 'importante' | 'recordatorio' | 'informativo';
    content: string;
    excerpt: string;
    author: string;
    author_id: number;
    published_at: string;
    is_active: boolean;
    attachments?: { name: string; size: string }[];
}

let anunciosStore: AnuncioMock[] = [
    {
        id: 1,
        title: 'Inicio de semestre 2026-1',
        category: 'importante',
        excerpt: 'Se informa a todos los estudiantes que el semestre académico 2026-1 ha iniciado.',
        content:
            'Estimados estudiantes y directores:\n\nEl semestre académico 2026-1 ha iniciado oficialmente. Las fechas de entrega de anteproyectos están publicadas en el calendario del sistema.\n\nRecuerden revisar el módulo de entregas para conocer los plazos de cada fase.\n\nCoordinación de Proyectos de Grado — UNAB.',
        author: 'Nicolas Moreno',
        author_id: 1,
        published_at: '2026-02-03T08:00:00',
        is_active: true,
    },
    {
        id: 2,
        title: 'Recordatorio: fechas límite de entrega',
        category: 'recordatorio',
        excerpt: 'La fecha límite para la entrega de anteproyectos es el 15 de marzo.',
        content:
            'Recordamos a todos los estudiantes que la fecha límite para la entrega del documento de anteproyecto es el **15 de marzo de 2026**.\n\nLas entregas tardías serán marcadas como fuera de plazo en el panel del director.\n\nConsulte el calendario académico para más detalles.',
        author: 'Juan Arteaga',
        author_id: 2,
        published_at: '2026-02-28T10:00:00',
        is_active: true,
    },
    {
        id: 3,
        title: 'Nuevos directores disponibles',
        category: 'informativo',
        excerpt: 'Se han incorporado nuevos directores al sistema de asignación.',
        content:
            'Se informa que se han incorporado nuevos directores de proyecto al sistema. Los coordinadores pueden asignarlos desde el módulo de Gestión de Proyectos.\n\nLos cupos disponibles se muestran en la sección de Directores.',
        author: 'Miguel Afanador',
        author_id: 3,
        published_at: '2026-03-05T14:30:00',
        is_active: true,
        attachments: [{ name: 'directores_2026-1.pdf', size: '245 KB' }],
    },
    {
        id: 4,
        title: 'Taller de metodologías ágiles',
        category: 'informativo',
        excerpt: 'Invitación al taller presencial el 20 de abril.',
        content:
            'El programa invita a estudiantes y directores al taller de metodologías ágiles aplicadas a proyectos de grado.\n\nFecha: 20 de abril, 2:00 PM — Aula 301.',
        author: 'Nicolas Moreno',
        author_id: 1,
        published_at: '2026-03-15T09:00:00',
        is_active: false,
    },
];

export function getAnuncios(activeOnly = false): AnuncioMock[] {
    const list = structuredClone(anunciosStore);
    return activeOnly ? list.filter((a) => a.is_active) : list;
}

export function getAnuncioById(id: number): AnuncioMock | undefined {
    return structuredClone(anunciosStore.find((a) => a.id === id));
}

export function createAnuncio(payload: Partial<AnuncioMock>): AnuncioMock {
    const next: AnuncioMock = {
        id: Math.max(0, ...anunciosStore.map((a) => a.id)) + 1,
        title: payload.title ?? 'Sin título',
        category: payload.category ?? 'informativo',
        content: payload.content ?? '',
        excerpt: payload.excerpt ?? payload.content?.slice(0, 120) ?? '',
        author: payload.author ?? 'Coordinador',
        author_id: payload.author_id ?? 1,
        published_at: payload.published_at ?? new Date().toISOString(),
        is_active: payload.is_active ?? true,
        attachments: payload.attachments,
    };
    anunciosStore = [...anunciosStore, next];
    return structuredClone(next);
}

export function updateAnuncio(id: number, payload: Partial<AnuncioMock>): AnuncioMock | undefined {
    const idx = anunciosStore.findIndex((a) => a.id === id);
    if (idx === -1) return undefined;
    anunciosStore[idx] = { ...anunciosStore[idx], ...payload, id };
    return structuredClone(anunciosStore[idx]);
}

export function deleteAnuncio(id: number): boolean {
    const before = anunciosStore.length;
    anunciosStore = anunciosStore.filter((a) => a.id !== id);
    return anunciosStore.length < before;
}
