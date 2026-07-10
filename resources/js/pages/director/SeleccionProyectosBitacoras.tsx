import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { Search, SlidersHorizontal, BookOpen, GraduationCap, FileText } from 'lucide-react';

interface Project {
    id: number;
    code: string;
    title: string;
    student: string;
    status: 'active' | 'completed' | 'on-hold';
    progress: number;
    lastActivity: string;
}

const MOCK_PROJECTS: Project[] = [
    { id: 1, code: 'PG-2026-014', title: 'Sistema Centralizado de Proyectos de Grado', student: 'Carlos Andrés Méndez', status: 'active', progress: 65, lastActivity: '15/04/2026' },
    { id: 2, code: 'PG-2026-015', title: 'Plataforma de Análisis de Sentimientos para Redes Sociales', student: 'María Fernanda Rincón', status: 'active', progress: 40, lastActivity: '12/04/2026' },
    { id: 3, code: 'PG-2026-012', title: 'App Móvil para Gestión de Inventarios Hospitalarios', student: 'Juan David Pérez', status: 'completed', progress: 100, lastActivity: '28/03/2026' },
    { id: 4, code: 'PG-2026-010', title: 'Sistema de Recomendación de Rutas de Transporte', student: 'Laura Patricia Gómez', status: 'on-hold', progress: 30, lastActivity: '20/02/2026' },
    { id: 5, code: 'PG-2026-008', title: 'Dashboard de Indicadores de Gestión Académica', student: 'Andrés Felipe Torres', status: 'active', progress: 80, lastActivity: '10/04/2026' },
    { id: 6, code: 'PG-2026-005', title: 'Plataforma E-Learning para Cursos de Programación', student: 'Diana Carolina Rojas', status: 'active', progress: 55, lastActivity: '14/04/2026' },
];

const statusConfig: Record<string, { label: string; variant: 'success' | 'inactivo' | 'warning' }> = {
    active: { label: 'Activo', variant: 'success' },
    completed: { label: 'Completado', variant: 'inactivo' },
    'on-hold': { label: 'En pausa', variant: 'warning' },
};

export default function SeleccionProyectosBitacoras() {
    const navigate = useNavigate();
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState<string>('all');

    const filtered = MOCK_PROJECTS.filter((p) => {
        const matchesSearch =
            p.title.toLowerCase().includes(search.toLowerCase()) ||
            p.student.toLowerCase().includes(search.toLowerCase()) ||
            p.code.toLowerCase().includes(search.toLowerCase());
        const matchesFilter = filter === 'all' || p.status === filter;
        return matchesSearch && matchesFilter;
    });

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Bitácoras"
                title="Bitácoras de Proyectos"
                subtitle="Seleccione un proyecto para ver y firmar sus bitácoras"
            />

            {/* Search + Filter */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por título, estudiante o código..."
                        className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <SlidersHorizontal className="h-4 w-4 text-[#78716c]" />
                    <select
                        value={filter}
                        onChange={(e) => setFilter(e.target.value)}
                        className="min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                        aria-label="Filtrar por estado"
                    >
                        <option value="all">Todos los estados</option>
                        <option value="active">Activos</option>
                        <option value="completed">Completados</option>
                        <option value="on-hold">En pausa</option>
                    </select>
                </div>
            </div>

            {/* Project Cards */}
            {filtered.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#f5f5f4]">
                        <BookOpen className="h-6 w-6 text-[#78716c]" />
                    </div>
                    <h3 className="text-base font-semibold text-[#1c1917]">Sin resultados</h3>
                    <p className="text-sm text-[#57534e] max-w-sm">
                        No se encontraron proyectos con los filtros aplicados.
                    </p>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {filtered.map((project) => {
                        const config = statusConfig[project.status];
                        return (
                            <button
                                key={project.id}
                                onClick={() => navigate('/director/bitacoras-proyecto')}
                                className="group flex flex-col gap-4 rounded-xl border border-[#e5e5e5] bg-white p-5 text-left shadow-[0_1px_2px_rgba(28,25,23,0.05)] transition-all hover:border-[#c2410c] hover:shadow-[0_4px_12px_rgba(194,65,12,0.1)] active:scale-[0.98]"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#fed7aa] group-hover:bg-[#c2410c] transition-colors">
                                        <BookOpen className="h-5 w-5 text-[#c2410c] group-hover:text-white transition-colors" />
                                    </div>
                                    <StatusBadge variant={config.variant}>{config.label}</StatusBadge>
                                </div>

                                <div className="flex flex-col gap-1 min-w-0">
                                    <span className="text-[11px] font-bold uppercase tracking-[0.05em] text-[#78716c]">
                                        {project.code}
                                    </span>
                                    <h3 className="text-sm font-bold text-[#1c1917] leading-snug line-clamp-2">
                                        {project.title}
                                    </h3>
                                </div>

                                <div className="flex items-center gap-2 text-xs text-[#57534e]">
                                    <GraduationCap className="h-3.5 w-3.5 shrink-0" />
                                    <span className="truncate">{project.student}</span>
                                </div>

                                {/* Progress Bar */}
                                <div className="flex flex-col gap-1">
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="text-[#78716c]">Progreso</span>
                                        <span className="font-semibold text-[#1c1917] tabular-nums">{project.progress}%</span>
                                    </div>
                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-[#e7e5e4]">
                                        <div
                                            className="h-full rounded-full bg-[#c2410c] transition-all"
                                            style={{ width: `${project.progress}%` }}
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between border-t border-[#e5e5e5] pt-3">
                                    <span className="text-xs text-[#78716c]">
                                        Última actividad: {project.lastActivity}
                                    </span>
                                    <FileText className="h-4 w-4 text-[#78716c] group-hover:text-[#c2410c] transition-colors" />
                                </div>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
