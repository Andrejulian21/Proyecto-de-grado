import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Plus, FileText, Loader2, X, Save } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { EmptyState } from '@/components/ui/EmptyState';
import { PhaseStepper } from '@/components/project/PhaseStepper';
import {
    STUDENT_PHASES,
    MOCK_BITACORAS,
    bitacoraStatusEmoji,
    bitacoraStatusLabel,
    type PhaseId,
    type BitacoraEntry,
    type BitacoraSignatureStatus,
} from '@/lib/mock/project-data';

const STUDENT_PROJECT_ID = 1;

const statusVariant: Record<BitacoraSignatureStatus, 'success' | 'warning' | 'error'> = {
    signed: 'success',
    pending_student: 'warning',
    pending_director: 'error',
};

type PageState = 'loading' | 'empty' | 'data';

function formatDateNow(): string {
    return new Date().toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

export default function BitacorasEstudiante() {
    const navigate = useNavigate();
    const [pageState] = useState<PageState>('data');
    const [selectedPhaseId, setSelectedPhaseId] = useState<PhaseId>('presentacion');
    const [bitacoras, setBitacoras] = useState<BitacoraEntry[]>(
        () => MOCK_BITACORAS.filter((b) => b.projectId === STUDENT_PROJECT_ID),
    );
    const [dialogOpen, setDialogOpen] = useState(false);
    const [content, setContent] = useState('');
    const [weeklySummary, setWeeklySummary] = useState('');
    const [formError, setFormError] = useState('');

    const selectedPhase = STUDENT_PHASES.find((p) => p.id === selectedPhaseId) ?? STUDENT_PHASES[0];

    const filteredBitacoras = useMemo(
        () => bitacoras.filter((b) => b.phaseId === selectedPhaseId),
        [bitacoras, selectedPhaseId],
    );

    function handleOpenDialog() {
        setContent('');
        setWeeklySummary('');
        setFormError('');
        setDialogOpen(true);
    }

    function handleCreateBitacora() {
        if (!content.trim() || !weeklySummary.trim()) {
            setFormError('Debes completar el contenido y el resumen semanal.');
            return;
        }

        const newEntry: BitacoraEntry = {
            id: Date.now(),
            projectId: STUDENT_PROJECT_ID,
            phaseId: selectedPhaseId,
            author: 'Ana Martínez',
            date: formatDateNow(),
            content: content.trim(),
            weeklySummary: weeklySummary.trim(),
            status: 'pending_director',
        };

        setBitacoras((prev) => [newEntry, ...prev]);
        setDialogOpen(false);
    }

    function renderBitacoraList() {
        if (filteredBitacoras.length === 0) {
            return (
                <div className="rounded-xl border border-dashed border-[#d6d3d1] bg-white px-4 py-10 text-center text-sm text-[#78716c]">
                    No hay bitácoras registradas para la fase <strong>{selectedPhase.label}</strong>.
                </div>
            );
        }

        return filteredBitacoras.map((b) => (
            <div
                key={b.id}
                className="flex flex-col gap-3 rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)] sm:flex-row sm:items-center sm:justify-between"
            >
                <div className="flex flex-col gap-1 min-w-0">
                    <span className="text-sm font-semibold text-[#1c1917]">{b.author}</span>
                    <span className="text-xs text-[#57534e]">{b.date}</span>
                    <p className="text-xs text-[#78716c] line-clamp-2">{b.content}</p>
                    <StatusBadge variant={statusVariant[b.status]}>
                        {bitacoraStatusEmoji(b.status)} {bitacoraStatusLabel(b.status)}
                    </StatusBadge>
                </div>
                <button
                    type="button"
                    onClick={() => navigate(`/bitacora/${b.id}/revision`)}
                    className="inline-flex min-h-[40px] shrink-0 items-center justify-center gap-2 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] px-4 py-2.5 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                >
                    Revisar bitácora
                </button>
            </div>
        ));
    }

    function renderContent() {
        switch (pageState) {
            case 'loading':
                return (
                    <div className="flex items-center justify-center py-16">
                        <Loader2 className="h-6 w-6 animate-spin text-[#c2410c]" />
                    </div>
                );
            case 'empty':
                return (
                    <EmptyState
                        icon={FileText}
                        title="No has registrado bitácoras"
                        description="Crea una nueva bitácora para comenzar a registrar tus avances."
                        action={{ label: 'Nueva Bitácora', onClick: handleOpenDialog }}
                    />
                );
            case 'data':
                return (
                    <div className="flex flex-col gap-6">
                        <PhaseStepper
                            phases={STUDENT_PHASES}
                            selectedPhaseId={selectedPhaseId}
                            onSelectPhase={setSelectedPhaseId}
                            deliveryCountByPhase={(phaseId) =>
                                bitacoras.filter((b) => b.phaseId === phaseId).length
                            }
                            countLabel={{ singular: 'bitácora', plural: 'bitácoras' }}
                        />
                        <div className="flex flex-col gap-3">
                            <h3 className="text-sm font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                Bitácoras — {selectedPhase.label} ({filteredBitacoras.length})
                            </h3>
                            {renderBitacoraList()}
                        </div>
                    </div>
                );
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Bitácora"
                title="Bitácoras"
                subtitle="Registro de sesiones y avances de tu proyecto de grado"
                actions={
                    <button
                        type="button"
                        onClick={handleOpenDialog}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                        aria-label="Crear nueva bitácora"
                    >
                        <Plus className="h-4 w-4" />
                        Nueva Bitácora
                    </button>
                }
            />

            {renderContent()}

            {dialogOpen && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                    onClick={(e) => { if (e.target === e.currentTarget) setDialogOpen(false); }}
                    role="dialog"
                    aria-modal="true"
                    aria-label="Nueva bitácora"
                >
                    <div className="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-[0_20px_60px_rgba(28,25,23,0.15)]">
                        <div className="flex items-start justify-between gap-3 border-b border-[#e5e5e5] p-6">
                            <div>
                                <h2 className="text-lg font-bold text-[#1c1917]">Nueva Bitácora</h2>
                                <p className="mt-1 text-sm text-[#57534e]">
                                    Fase: {selectedPhase.label} · Fecha: {formatDateNow()} (automática)
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setDialogOpen(false)}
                                className="rounded-lg p-1.5 text-[#57534e] hover:bg-[#f5f5f4]"
                                aria-label="Cerrar"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="flex-1 overflow-y-auto p-6">
                            <div className="flex flex-col gap-5">
                                <div>
                                    <label htmlFor="new-content" className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                        Contenido
                                    </label>
                                    <textarea
                                        id="new-content"
                                        rows={6}
                                        value={content}
                                        onChange={(e) => setContent(e.target.value)}
                                        placeholder="Describe lo realizado en la sesión de trabajo..."
                                        className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    />
                                </div>
                                <div>
                                    <label htmlFor="new-summary" className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                        Resumen semanal
                                    </label>
                                    <textarea
                                        id="new-summary"
                                        rows={3}
                                        value={weeklySummary}
                                        onChange={(e) => setWeeklySummary(e.target.value)}
                                        placeholder="Resumen breve del avance de la semana..."
                                        className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    />
                                </div>
                                {formError && (
                                    <p className="text-sm text-[#dc2626]" role="alert">{formError}</p>
                                )}
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-2 border-t border-[#e5e5e5] p-6">
                            <button
                                type="button"
                                onClick={() => setDialogOpen(false)}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] px-4 py-2 text-sm font-semibold text-[#57534e] hover:bg-[#f5f5f4]"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={handleCreateBitacora}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white hover:bg-[#9a330a]"
                            >
                                <Save className="h-4 w-4" />
                                Crear bitácora
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
