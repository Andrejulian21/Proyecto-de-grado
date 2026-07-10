import { useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ChevronDown, ChevronRight, Search, FileText, Calendar, GraduationCap } from 'lucide-react';

interface Project {
    id: number;
    code: string;
    title: string;
    student: string;
    director: string;
    period: string;
    deliveries: Delivery[];
}

interface Delivery {
    id: number;
    name: string;
    date: string;
    status: 'approved' | 'pending' | 'corrections' | 'rejected' | 'not-submitted';
    grade?: string;
}

const MOCK_PROJECTS: Project[] = [
    {
        id: 1, code: 'PG-2026-014', title: 'Sistema Centralizado de Proyectos de Grado', student: 'Carlos Méndez', director: 'Dr. Ricardo Gómez', period: '2026-01',
        deliveries: [
            { id: 1, name: 'Avance 1 — Definición', date: '15/03/2026', status: 'approved', grade: '92' },
            { id: 2, name: 'Avance 2 — Diseño', date: '30/04/2026', status: 'corrections', grade: '78' },
            { id: 3, name: 'Avance 3 — Implementación', date: '15/06/2026', status: 'not-submitted' },
            { id: 4, name: 'Entrega Final', date: '30/11/2026', status: 'not-submitted' },
        ],
    },
    {
        id: 2, code: 'PG-2026-015', title: 'Plataforma de Análisis de Sentimientos', student: 'María Rincón', director: 'Dr. Ricardo Gómez', period: '2026-01',
        deliveries: [
            { id: 5, name: 'Avance 1 — Definición', date: '15/03/2026', status: 'approved', grade: '88' },
            { id: 6, name: 'Avance 2 — Diseño', date: '30/04/2026', status: 'pending' },
            { id: 7, name: 'Avance 3 — Implementación', date: '15/06/2026', status: 'not-submitted' },
            { id: 8, name: 'Entrega Final', date: '30/11/2026', status: 'not-submitted' },
        ],
    },
    {
        id: 3, code: 'PG-2026-008', title: 'Dashboard Indicadores Académicos', student: 'Andrés Torres', director: 'Dra. Laura Martínez', period: '2026-01',
        deliveries: [
            { id: 9, name: 'Avance 1 — Definición', date: '15/03/2026', status: 'rejected', grade: '45' },
            { id: 10, name: 'Avance 2 — Diseño', date: '30/04/2026', status: 'not-submitted' },
            { id: 11, name: 'Avance 3 — Implementación', date: '15/06/2026', status: 'not-submitted' },
            { id: 12, name: 'Entrega Final', date: '30/11/2026', status: 'not-submitted' },
        ],
    },
];

const statusConfig: Record<string, { label: string; variant: 'success' | 'warning' | 'error' | 'inactivo' | 'info' }> = {
    approved: { label: 'Aprobado', variant: 'success' },
    pending: { label: 'Pendiente', variant: 'warning' },
    corrections: { label: 'Correcciones', variant: 'error' },
    rejected: { label: 'Rechazado', variant: 'error' },
    'not-submitted': { label: 'No entregado', variant: 'inactivo' },
};

const STEP_LABELS = ['Inscripción', 'Avance 1', 'Avance 2', 'Avance 3', 'Final'];

export default function CoordinadorEntregas() {
    const [search, setSearch] = useState('');
    const [selectedProject, setSelectedProject] = useState<number | null>(null);
    const [expandedDelivery, setExpandedDelivery] = useState<number | null>(null);

    const filtered = MOCK_PROJECTS.filter((p) =>
        p.title.toLowerCase().includes(search.toLowerCase()) ||
        p.code.toLowerCase().includes(search.toLowerCase()) ||
        p.student.toLowerCase().includes(search.toLowerCase())
    );

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Coordinación"
                title="Entregas"
                subtitle="Gestión de entregas de todos los proyectos de grado"
            />

            {/* Search */}
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Buscar por título, código o estudiante..."
                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                />
            </div>

            {/* Project cards */}
            <div className="flex flex-col gap-4">
                {filtered.map((project) => (
                    <div key={project.id} className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <button
                            onClick={() => setSelectedProject(selectedProject === project.id ? null : project.id)}
                            className="flex w-full items-center justify-between gap-4 px-6 py-4 text-left transition-colors hover:bg-[#fafaf9]"
                        >
                            <div className="flex items-center gap-4 min-w-0">
                                {selectedProject === project.id ? (
                                    <ChevronDown className="h-5 w-5 shrink-0 text-[#c2410c]" />
                                ) : (
                                    <ChevronRight className="h-5 w-5 shrink-0 text-[#78716c]" />
                                )}
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#fed7aa]">
                                    <FileText className="h-5 w-5 text-[#c2410c]" />
                                </div>
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="text-[11px] font-bold uppercase tracking-[0.05em] text-[#78716c]">{project.code}</span>
                                        <span className="text-xs text-[#78716c]">{project.period}</span>
                                    </div>
                                    <h3 className="text-sm font-bold text-[#1c1917] mt-0.5">{project.title}</h3>
                                    <div className="flex items-center gap-3 mt-1 text-xs text-[#57534e]">
                                        <span className="flex items-center gap-1">
                                            <GraduationCap className="h-3 w-3" />{project.student}
                                        </span>
                                        <span>{project.director}</span>
                                    </div>
                                </div>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                {project.deliveries.filter((d) => d.status === 'pending' || d.status === 'corrections').length > 0 && (
                                    <span className="inline-flex items-center justify-center h-6 min-w-[24px] rounded-full bg-[#dc2626] px-1.5 text-[10px] font-bold text-white tabular-nums">
                                        {project.deliveries.filter((d) => d.status === 'pending' || d.status === 'corrections').length}
                                    </span>
                                )}
                            </div>
                        </button>

                        {selectedProject === project.id && (
                            <div className="border-t border-[#e5e5e5]">
                                {/* Mini Stepper */}
                                <div className="flex items-center gap-1 px-6 py-4 overflow-x-auto">
                                    {STEP_LABELS.map((label, idx) => {
                                        const delivery = project.deliveries[idx];
                                        const isCompleted = delivery?.status === 'approved';
                                        const isCurrent = delivery?.status === 'pending' || delivery?.status === 'corrections';
                                        return (
                                            <div key={idx} className="flex items-center min-w-0">
                                                <div className={`flex items-center gap-2 rounded-lg px-3 py-1.5 whitespace-nowrap text-xs ${
                                                    isCompleted ? 'bg-[#dcfce7] text-[#14532d]' :
                                                    isCurrent ? 'bg-[#fef3c7] text-[#78350f]' :
                                                    'bg-[#f5f5f4] text-[#78716c]'
                                                }`}>
                                                    <span className="font-semibold">{label}</span>
                                                </div>
                                                {idx < STEP_LABELS.length - 1 && (
                                                    <div className="mx-1 h-px w-4 bg-[#e5e5e5]" />
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>

                                {/* Deliveries */}
                                <div className="divide-y divide-[#e5e5e5] border-t border-[#e5e5e5]">
                                    {project.deliveries.map((d) => {
                                        const config = statusConfig[d.status];
                                        return (
                                            <div key={d.id}>
                                                <button
                                                    onClick={() => setExpandedDelivery(expandedDelivery === d.id ? null : d.id)}
                                                    className="flex w-full items-center justify-between gap-4 px-6 py-3.5 text-left transition-colors hover:bg-[#fafaf9]"
                                                >
                                                    <div className="flex items-center gap-3 min-w-0">
                                                        {expandedDelivery === d.id ? (
                                                            <ChevronDown className="h-3.5 w-3.5 shrink-0 text-[#78716c]" />
                                                        ) : (
                                                            <ChevronRight className="h-3.5 w-3.5 shrink-0 text-[#78716c]" />
                                                        )}
                                                        <div>
                                                            <p className="text-sm font-medium text-[#1c1917]">{d.name}</p>
                                                            <p className="text-xs text-[#78716c]">{d.date}</p>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2 shrink-0">
                                                        <StatusBadge variant={config.variant}>{config.label}</StatusBadge>
                                                        {d.grade && (
                                                            <span className="text-sm font-bold text-[#1c1917] tabular-nums">{d.grade}</span>
                                                        )}
                                                    </div>
                                                </button>
                                                {expandedDelivery === d.id && (
                                                    <div className="border-t border-[#e5e5e5] bg-[#fafaf9] px-6 py-3">
                                                        <div className="flex items-center justify-between gap-3">
                                                            <div className="flex items-center gap-2 text-xs text-[#57534e]">
                                                                <Calendar className="h-3.5 w-3.5" />
                                                                Fecha límite: {d.date}
                                                            </div>
                                                            <div className="flex items-center gap-2">
                                                                <button className="inline-flex min-h-[32px] items-center gap-1.5 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]">
                                                                    Ver documento
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                ))}

                {filtered.length === 0 && (
                    <div className="flex flex-col items-center gap-3 py-16 text-center">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#f5f5f4]">
                            <FileText className="h-6 w-6 text-[#78716c]" />
                        </div>
                        <h3 className="text-base font-semibold text-[#1c1917]">Sin resultados</h3>
                        <p className="text-sm text-[#57534e]">No se encontraron proyectos.</p>
                    </div>
                )}
            </div>
        </div>
    );
}
