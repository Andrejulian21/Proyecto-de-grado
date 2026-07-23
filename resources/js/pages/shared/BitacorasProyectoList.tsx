import { useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { BitacoraMeetingList } from '@/components/bitacoras/BitacoraMeetingList';
import {
    getMeetingsByProject,
    getProjectById,
    type SignatureStatus,
} from '@/mocks/bitacorasMock';
import { ArrowLeft, FileText, Clock, CheckCircle } from 'lucide-react';

interface BitacorasProyectoListProps {
    role: 'director' | 'coordinador';
    backPath: string;
    revisionPath: (meetingId: number) => string;
}

export function BitacorasProyectoList({ role, backPath, revisionPath }: BitacorasProyectoListProps) {
    const navigate = useNavigate();
    const { proyectoId } = useParams<{ proyectoId: string }>();
    const projectId = Number(proyectoId);
    const project = getProjectById(projectId);
    const [filterStatus, setFilterStatus] = useState<SignatureStatus | 'all'>('all');

    const meetings = useMemo(
        () => (project ? getMeetingsByProject(projectId) : []),
        [project, projectId],
    );

    const pendingCount = meetings.filter((m) => m.signatureStatus === 'pendiente').length;
    const signedCount = meetings.filter((m) => m.signatureStatus === 'firmado').length;

    if (!project) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-16">
                <p className="text-sm text-[#57534e]">Proyecto no encontrado.</p>
                <button
                    type="button"
                    onClick={() => navigate(backPath)}
                    className="text-sm font-semibold text-[#c2410c] hover:underline"
                >
                    Volver a proyectos
                </button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Bitácoras"
                title={project.title}
                subtitle={`${project.code} · ${project.directorName}`}
                actions={
                    <button
                        type="button"
                        onClick={() => navigate(backPath)}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver a proyectos
                    </button>
                }
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard icon={FileText} label="Total reuniones" value={meetings.length} />
                <StatCard icon={Clock} label="Pendientes" value={pendingCount} variant="warning" />
                <StatCard icon={CheckCircle} label="Firmadas" value={signedCount} variant="success" />
            </div>

            <BitacoraMeetingList
                meetings={meetings}
                role={role}
                filterStatus={filterStatus}
                onFilterChange={setFilterStatus}
                onView={(id) => navigate(revisionPath(id))}
                onReview={role === 'director' ? (id) => navigate(revisionPath(id)) : undefined}
            />
        </div>
    );
}
