import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { FileText, Users, Clock, ScrollText, GraduationCap, ArrowRight } from 'lucide-react';
import {
    MOCK_BITACORA_PROJECTS,
    bitacoraStatusEmoji,
    bitacoraStatusLabel,
    type BitacoraSignatureStatus,
} from '@/lib/mock/project-data';

const statusVariant: Record<BitacoraSignatureStatus, 'success' | 'warning' | 'error'> = {
    signed: 'success',
    pending_student: 'warning',
    pending_director: 'error',
};

export default function BitacorasDirector() {
    const navigate = useNavigate();

    const pendingCount = MOCK_BITACORA_PROJECTS.filter((p) => p.signatureStatus === 'pending_director').length;
    const signedCount = MOCK_BITACORA_PROJECTS.filter((p) => p.signatureStatus === 'signed').length;

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Director"
                title="Bitácoras"
                subtitle="Revise y firme las bitácoras de los proyectos que supervisa"
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard icon={FileText} label="Proyectos con bitácoras" value={MOCK_BITACORA_PROJECTS.length} />
                <StatCard icon={Clock} label="Pendientes de firma" value={pendingCount} variant="warning" />
                <StatCard icon={Users} label="Al día" value={signedCount} variant="success" />
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {MOCK_BITACORA_PROJECTS.map((project) => (
                    <button
                        key={project.id}
                        type="button"
                        onClick={() => navigate(`/bitacoras/${project.id}/firmar`)}
                        className="group flex flex-col gap-4 rounded-xl border border-[#e5e5e5] bg-white p-5 text-left shadow-[0_1px_2px_rgba(28,25,23,0.05)] transition-all hover:border-[#c2410c] hover:shadow-[0_4px_12px_rgba(194,65,12,0.1)] active:scale-[0.98]"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#fed7aa] group-hover:bg-[#c2410c] transition-colors">
                                <ScrollText className="h-5 w-5 text-[#c2410c] group-hover:text-white transition-colors" />
                            </div>
                            <StatusBadge variant={statusVariant[project.signatureStatus]}>
                                {bitacoraStatusEmoji(project.signatureStatus)} {bitacoraStatusLabel(project.signatureStatus)}
                            </StatusBadge>
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
                            <span className="truncate">{project.students.join(', ')}</span>
                        </div>

                        <div className="flex items-center justify-between border-t border-[#e5e5e5] pt-3">
                            <span className="text-xs text-[#78716c]">
                                Última bitácora: {project.lastBitacoraDate}
                            </span>
                            <ArrowRight className="h-4 w-4 text-[#78716c] group-hover:text-[#c2410c] transition-colors" />
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}
