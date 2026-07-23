import { useState } from 'react';
import { Search, BookOpen, GraduationCap, FileText } from 'lucide-react';
import { StatusBadge } from '@/components/ui/StatusBadge';
import type { BitacoraProject } from '@/mocks/bitacorasMock';

interface BitacoraProjectGridProps {
    projects: BitacoraProject[];
    onSelect: (projectId: number) => void;
    emptyMessage?: string;
}

export function BitacoraProjectGrid({
    projects,
    onSelect,
    emptyMessage = 'No se encontraron proyectos.',
}: BitacoraProjectGridProps) {
    const [search, setSearch] = useState('');

    const filtered = projects.filter((p) => {
        const q = search.toLowerCase();
        return (
            p.code.toLowerCase().includes(q) ||
            p.title.toLowerCase().includes(q) ||
            p.directorName.toLowerCase().includes(q) ||
            p.members.some((m) => m.toLowerCase().includes(q))
        );
    });

    return (
        <div className="flex flex-col gap-4">
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Buscar por código, título, director o integrante..."
                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                />
            </div>

            {filtered.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#f5f5f4]">
                        <BookOpen className="h-6 w-6 text-[#78716c]" />
                    </div>
                    <p className="text-sm text-[#57534e]">{emptyMessage}</p>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {filtered.map((project) => (
                        <button
                            key={project.id}
                            type="button"
                            onClick={() => onSelect(project.id)}
                            className="group flex flex-col gap-3 rounded-xl border border-[#e5e5e5] bg-white p-5 text-left shadow-[0_1px_2px_rgba(28,25,23,0.05)] transition-all hover:border-[#c2410c] hover:shadow-[0_4px_12px_rgba(194,65,12,0.1)] active:scale-[0.98]"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#fed7aa] transition-colors group-hover:bg-[#c2410c]">
                                    <BookOpen className="h-5 w-5 text-[#c2410c] transition-colors group-hover:text-white" />
                                </div>
                                {project.pendingCount > 0 ? (
                                    <StatusBadge variant="warning">
                                        {project.pendingCount} pendiente{project.pendingCount > 1 ? 's' : ''}
                                    </StatusBadge>
                                ) : (
                                    <StatusBadge variant="success">Al día</StatusBadge>
                                )}
                            </div>

                            <div className="min-w-0">
                                <span className="text-[11px] font-bold uppercase tracking-[0.05em] text-[#78716c]">
                                    {project.code}
                                </span>
                                <h3 className="mt-1 line-clamp-2 text-sm font-bold text-[#1c1917]">{project.title}</h3>
                            </div>

                            <p className="text-xs text-[#57534e]">
                                Director: {project.directorName}
                            </p>

                            <div className="flex items-center gap-2 text-xs text-[#57534e]">
                                <GraduationCap className="h-3.5 w-3.5 shrink-0" />
                                <span className="truncate">{project.members.join(', ')}</span>
                            </div>

                            <div className="flex items-center justify-between border-t border-[#e5e5e5] pt-3 text-xs text-[#78716c]">
                                <span>{project.meetingCount} reuniones</span>
                                <FileText className="h-4 w-4 text-[#78716c] transition-colors group-hover:text-[#c2410c]" />
                            </div>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
