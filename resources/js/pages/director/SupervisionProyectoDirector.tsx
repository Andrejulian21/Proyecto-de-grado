import { useMemo, useState } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import {
    GraduationCap,
    Lock,
    CheckCircle2,
    Clock,
    User,
    Building,
    ArrowLeft,
    AlertCircle,
} from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { PhaseStepper } from '@/components/project/PhaseStepper';
import {
    DIRECTOR_PHASES,
    getProjectById,
    getDeliveriesByProject,
    type PhaseId,
    type ProjectDelivery,
    type DeliveryStatus,
} from '@/lib/mock/project-data';

function DeliveryCard({ delivery, projectId }: { delivery: ProjectDelivery; projectId: number }) {
    const statusIcon: Record<DeliveryStatus, React.ReactNode> = {
        approved: <CheckCircle2 className="h-5 w-5 text-[#16a34a]" />,
        pending: <Clock className="h-5 w-5 text-[#d97706]" />,
        locked: <Lock className="h-5 w-5 text-[#78716c]" />,
        rejected: <Clock className="h-5 w-5 text-[#dc2626]" />,
    };

    const statusLabel: Record<DeliveryStatus, string> = {
        approved: 'Aprobado',
        pending: 'Pendiente',
        locked: 'Bloqueado',
        rejected: 'Rechazado',
    };

    const statusVariant: Record<DeliveryStatus, 'success' | 'warning' | 'inactivo' | 'error'> = {
        approved: 'success',
        pending: 'warning',
        locked: 'inactivo',
        rejected: 'error',
    };

    return (
        <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <div className="flex w-full items-center gap-3 p-4">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#f5f5f4]">
                    {statusIcon[delivery.status]}
                </div>
                <div className="flex flex-1 flex-col gap-0.5 min-w-0">
                    <span className="text-sm font-semibold text-[#1c1917]">{delivery.label}</span>
                    <span className="text-xs text-[#57534e]">
                        {delivery.status === 'locked' ? `Disponible: ${delivery.deadline}` : `Límite: ${delivery.deadline}`}
                        {delivery.grade !== null && ` · Nota: ${delivery.grade}`}
                    </span>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <StatusBadge variant={statusVariant[delivery.status]}>
                        {statusLabel[delivery.status]}
                    </StatusBadge>
                </div>
            </div>
            <div className="border-t border-[#e5e5e5] px-4 py-3">
                <Link
                    to={`/supervision/${projectId}/entregas/${delivery.id}`}
                    className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a]"
                >
                    Revisar entrega
                </Link>
            </div>
        </div>
    );
}

export default function SupervisionProyectoDirector() {
    const navigate = useNavigate();
    const { proyectoId } = useParams<{ proyectoId: string }>();
    const projectId = Number(proyectoId);
    const project = getProjectById(projectId);

    const [selectedPhaseId, setSelectedPhaseId] = useState<PhaseId>(
        project?.currentPhase ?? 'presentacion',
    );

    const deliveries = useMemo(
        () => (project ? getDeliveriesByProject(project.id) : []),
        [project],
    );

    const selectedPhase = DIRECTOR_PHASES.find((p) => p.id === selectedPhaseId) ?? DIRECTOR_PHASES[0];

    const filteredDeliveries = useMemo(
        () => deliveries.filter((d) => d.phaseId === selectedPhaseId),
        [deliveries, selectedPhaseId],
    );

    const pendingInPhase = filteredDeliveries.find((d) => d.status === 'pending');

    if (!project) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Supervisión" title="Proyecto no encontrado" subtitle="El proyecto solicitado no existe." />
                <button
                    type="button"
                    onClick={() => navigate('/dashboard/director')}
                    className="inline-flex min-h-[40px] items-center gap-2 self-start rounded-lg border border-[#e5e5e5] px-4 py-2 text-sm font-semibold text-[#1c1917] hover:bg-[#f5f5f4]"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Volver al panel
                </button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Supervisión"
                title={project.title}
                subtitle={`${project.code} · ${project.students.join(', ')}`}
                actions={
                    <button
                        type="button"
                        onClick={() => navigate('/dashboard/director')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-4">
                        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#fed7aa]">
                            <GraduationCap className="h-7 w-7 text-[#c2410c]" />
                        </div>
                        <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="text-xs font-bold uppercase tracking-[0.05em] text-[#c2410c]">
                                    {project.code}
                                </span>
                                <StatusBadge variant="en-curso">En Curso</StatusBadge>
                            </div>
                            <h3 className="text-lg font-bold text-[#1c1917]">{project.title}</h3>
                            <div className="flex items-center gap-4 text-sm text-[#57534e] flex-wrap">
                                <span className="flex items-center gap-1.5">
                                    <User className="h-3.5 w-3.5" />
                                    Estudiantes: {project.students.join(', ')}
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <Building className="h-3.5 w-3.5" />
                                    {project.faculty}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <PhaseStepper
                phases={DIRECTOR_PHASES}
                selectedPhaseId={selectedPhaseId}
                onSelectPhase={setSelectedPhaseId}
                deliveryCountByPhase={(phaseId) => deliveries.filter((d) => d.phaseId === phaseId).length}
            />

            <div className="flex flex-col gap-3">
                <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <h3 className="text-sm font-bold uppercase tracking-[0.05em] text-[#57534e]">
                        Entregas — {selectedPhase.label} ({filteredDeliveries.length})
                    </h3>
                    <p className="text-xs text-[#78716c]">
                        Selecciona una fase arriba para ver sus entregas
                    </p>
                </div>

                {filteredDeliveries.length > 0 ? (
                    filteredDeliveries.map((del) => (
                        <DeliveryCard key={del.id} delivery={del} projectId={project.id} />
                    ))
                ) : (
                    <div className="rounded-xl border border-dashed border-[#d6d3d1] bg-white px-4 py-10 text-center text-sm text-[#78716c]">
                        No hay entregas configuradas para la fase <strong>{selectedPhase.label}</strong>.
                    </div>
                )}
            </div>

            {pendingInPhase && (
                <div className="flex items-start gap-3 rounded-xl border border-[#dbeafe] bg-[#dbeafe]/40 p-4">
                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-[#2563eb]" />
                    <p className="text-sm text-[#1e3a8a]">
                        Hay una entrega pendiente de revisión en <strong>{selectedPhase.label}</strong>:{' '}
                        <strong>{pendingInPhase.label}</strong>.
                    </p>
                </div>
            )}
        </div>
    );
}
