import { useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { PhaseStepper } from '@/components/project/PhaseStepper';
import { ArrowLeft, GraduationCap } from 'lucide-react';
import {
    DIRECTOR_PHASES,
    MOCK_BITACORA_PROJECTS,
    getBitacorasByProject,
    bitacoraStatusEmoji,
    bitacoraStatusLabel,
    type PhaseId,
    type BitacoraSignatureStatus,
} from '@/lib/mock/project-data';

const statusVariant: Record<BitacoraSignatureStatus, 'success' | 'warning' | 'error'> = {
    signed: 'success',
    pending_student: 'warning',
    pending_director: 'error',
};

export default function DetalleFirmaBitacora() {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();
    const projectId = Number(id);
    const project = MOCK_BITACORA_PROJECTS.find((p) => p.id === projectId);

    const [selectedPhaseId, setSelectedPhaseId] = useState<PhaseId>('presentacion');

    const bitacoras = useMemo(
        () => getBitacorasByProject(projectId, selectedPhaseId),
        [projectId, selectedPhaseId],
    );

    const selectedPhase = DIRECTOR_PHASES.find((p) => p.id === selectedPhaseId) ?? DIRECTOR_PHASES[0];

    if (!project) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Bitácora" title="Proyecto no encontrado" subtitle="El proyecto solicitado no existe." />
                <button
                    type="button"
                    onClick={() => navigate('/bitacoras')}
                    className="inline-flex min-h-[40px] items-center gap-2 self-start rounded-lg border border-[#e5e5e5] px-4 py-2 text-sm font-semibold"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Volver
                </button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Bitácora"
                title={project.title}
                subtitle={`${project.code} · ${project.students.join(', ')}`}
                actions={
                    <button
                        type="button"
                        onClick={() => navigate('/bitacoras')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="flex items-start gap-4">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#fed7aa]">
                        <GraduationCap className="h-6 w-6 text-[#c2410c]" />
                    </div>
                    <div>
                        <span className="text-xs font-bold uppercase tracking-[0.05em] text-[#c2410c]">{project.code}</span>
                        <h3 className="text-lg font-bold text-[#1c1917]">{project.title}</h3>
                        <p className="text-sm text-[#57534e]">Estudiantes: {project.students.join(', ')}</p>
                    </div>
                </div>
            </div>

            <PhaseStepper
                phases={DIRECTOR_PHASES}
                selectedPhaseId={selectedPhaseId}
                onSelectPhase={setSelectedPhaseId}
                deliveryCountByPhase={(phaseId) => getBitacorasByProject(projectId, phaseId).length}
                title="Fases del Proyecto"
                countLabel={{ singular: 'bitácora', plural: 'bitácoras' }}
            />

            <div className="flex flex-col gap-3">
                <h3 className="text-sm font-bold uppercase tracking-[0.05em] text-[#57534e]">
                    Bitácoras — {selectedPhase.label} ({bitacoras.length})
                </h3>

                {bitacoras.length > 0 ? (
                    bitacoras.map((b) => (
                        <div
                            key={b.id}
                            className="flex flex-col gap-3 rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)] sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="flex flex-col gap-1 min-w-0">
                                <span className="text-sm font-semibold text-[#1c1917]">{b.author}</span>
                                <span className="text-xs text-[#57534e]">{b.date}</span>
                                <StatusBadge variant={statusVariant[b.status]}>
                                    {bitacoraStatusEmoji(b.status)} {bitacoraStatusLabel(b.status)}
                                </StatusBadge>
                            </div>
                            <button
                                type="button"
                                onClick={() => navigate(`/bitacoras/${b.id}/revision`)}
                                className="inline-flex min-h-[40px] shrink-0 items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a]"
                            >
                                Revisar bitácora
                            </button>
                        </div>
                    ))
                ) : (
                    <div className="rounded-xl border border-dashed border-[#d6d3d1] bg-white px-4 py-10 text-center text-sm text-[#78716c]">
                        No hay bitácoras registradas para la fase <strong>{selectedPhase.label}</strong>.
                    </div>
                )}
            </div>
        </div>
    );
}
