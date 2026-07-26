import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { GraduationCap, User, AlertTriangle, Loader2, FileText, Eye } from 'lucide-react';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { PageHeader } from '@/components/ui/PageHeader';
import DeliveryAccordion from '@/components/DeliveryAccordion';
import { PhaseStepper } from '@/components/project/PhaseStepper';
import { buildPhaseSteps, useEstudianteEntregas } from '@/hooks/useEstudianteEntregas';

export default function EstudianteDashboard() {
    const navigate = useNavigate();
    const { proyecto, entregas, loading, error } = useEstudianteEntregas();
    const [selectedPhaseId, setSelectedPhaseId] = useState<string | null>(null);

    if (loading) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-20">
                <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
                <p className="text-sm text-[#78716c]">Cargando tu proyecto...</p>
            </div>
        );
    }
    if (error) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-20">
                <AlertTriangle className="h-8 w-8 text-[#dc2626]" />
                <p className="text-sm font-semibold text-[#1c1917]">{error}</p>
                <button
                    onClick={() => window.location.reload()}
                    className="rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white hover:bg-[#9a330a]"
                >
                    Reintentar
                </button>
            </div>
        );
    }
    if (!proyecto) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-20">
                <GraduationCap className="h-12 w-12 text-[#d6d3d1]" />
                <p className="text-sm text-[#78716c]">No tienes un proyecto de grado asignado.</p>
            </div>
        );
    }

    const phases = buildPhaseSteps(proyecto.current_phase);
    const activePhaseId = selectedPhaseId ?? proyecto.current_phase;
    const deliveryCountByPhase = (phaseId: string) => entregas.filter((e) => e.fase === phaseId).length;
    const filtered = entregas.filter((e) => e.fase === activePhaseId);

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Proyecto Activo"
                title="Mi Proyecto de Grado"
                subtitle="Gestiona las entregas y el progreso de tu proyecto de grado"
            />
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-4">
                        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#fed7aa]">
                            <GraduationCap className="h-7 w-7 text-[#c2410c]" />
                        </div>
                        <div className="flex flex-col gap-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-xs font-bold uppercase tracking-[0.05em] text-[#c2410c]">
                                    {proyecto.code}
                                </span>
                                <StatusBadge variant="en-curso">En Curso</StatusBadge>
                            </div>
                            <h3 className="text-lg font-bold text-[#1c1917]">{proyecto.title}</h3>
                            <span className="flex items-center gap-1.5 text-sm text-[#57534e]">
                                <User className="h-3.5 w-3.5" /> Director: {proyecto.director?.name}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <PhaseStepper
                phases={phases}
                selectedPhaseId={activePhaseId}
                onSelectPhase={setSelectedPhaseId}
                deliveryCountByPhase={deliveryCountByPhase}
            />
            <div className="flex flex-col gap-3">
                <h3 className="text-sm font-bold uppercase tracking-[0.05em] text-[#57534e]">
                    Entregas ({filtered.length})
                </h3>
                {filtered.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-[#e5e5e5] bg-white py-12 text-sm text-[#78716c]">
                        <FileText className="h-8 w-8 text-[#d6d3d1]" />
                        No hay entregas para esta fase.
                    </div>
                ) : (
                    filtered.map((d) => (
                        <div key={d.id} className="flex flex-col">
                            <DeliveryAccordion delivery={d} />
                            <div className="flex justify-end rounded-b-xl border-x border-b border-[#e5e5e5] bg-white px-4 pb-3 pt-0">
                                <button
                                    onClick={() => navigate(`/estudiante/entregas/${d.id}`)}
                                    className="inline-flex items-center gap-1.5 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                                >
                                    <Eye className="h-3.5 w-3.5" />
                                    Ver detalle
                                </button>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}
