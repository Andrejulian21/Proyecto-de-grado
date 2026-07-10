import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ArrowLeft, Download, CheckCircle2, XCircle, Clock, FileText, Eye } from 'lucide-react';

interface ReviewCriterion {
    id: string;
    label: string;
    passed: boolean | null;
    comment: string;
}

export default function DetalleEntregaEstudiante() {
    const navigate = useNavigate();

    const [criteria] = useState<ReviewCriterion[]>([
        { id: '1', label: 'Cumple con el formato establecido', passed: true, comment: 'Formato correcto' },
        { id: '2', label: 'Contenido completo según la rúbrica', passed: true, comment: 'Todos los puntos cubiertos' },
        { id: '3', label: 'Redacción y ortografía adecuadas', passed: false, comment: 'Se encontraron errores ortográficos menores' },
        { id: '4', label: 'Referencias bibliográficas completas', passed: true, comment: 'Normas APA correctas' },
        { id: '5', label: 'Anexos y apéndices incluidos', passed: null, comment: '' },
    ]);

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Entrega"
                title="Avance 1 — Revisión"
                subtitle="PG-2026-014 · Entregado el 15/04/2026"
                actions={
                    <button
                        onClick={() => navigate('/dashboard/estudiante')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                {/* Document Preview */}
                <div className="lg:col-span-3">
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-4 flex items-center justify-between gap-3 flex-wrap">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Documento Entregado</h3>
                            </div>
                            <button className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]">
                                <Download className="h-3.5 w-3.5" />
                                Descargar
                            </button>
                        </div>

                        <div className="flex aspect-[8.5/11] w-full items-center justify-center rounded-lg border border-[#e5e5e5] bg-[#fafaf9]">
                            <div className="flex flex-col items-center gap-3 text-center">
                                <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4]">
                                    <Eye className="h-8 w-8 text-[#78716c]" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-[#1c1917]">Vista previa del documento</p>
                                    <p className="text-xs text-[#57534e] mt-1">
                                        avance-1-vfinal.pdf (2.4 MB)
                                    </p>
                                </div>
                                <button className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]">
                                    <Eye className="h-4 w-4" />
                                    Ver documento completo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Review Panel */}
                <div className="lg:col-span-2">
                    <div className="flex flex-col gap-4">
                        {/* Status */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#fef3c7] text-[#d97706]">
                                    <Clock className="h-5 w-5" />
                                </div>
                                <div>
                                    <p className="text-sm text-[#57534e]">Estado de la revisión</p>
                                    <StatusBadge variant="warning">Pendiente</StatusBadge>
                                </div>
                            </div>
                        </div>

                        {/* Overall Grade */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <p className="mb-3 text-sm font-semibold text-[#57534e]">Calificación</p>
                            <div className="flex items-baseline gap-1">
                                <span className="text-3xl font-bold text-[#1c1917] tabular-nums">—</span>
                                <span className="text-sm text-[#78716c]">/ 100</span>
                            </div>
                            <p className="mt-1 text-xs text-[#78716c]">
                                Pendiente de revisión por el director
                            </p>
                        </div>

                        {/* Comment */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <p className="mb-2 text-sm font-semibold text-[#57534e]">Comentario del director</p>
                            {false ? (
                                <p className="text-sm text-[#1c1917]">—</p>
                            ) : (
                                <p className="text-xs text-[#78716c] italic">
                                    Aún no hay comentarios. El director revisará tu entrega próximamente.
                                </p>
                            )}
                        </div>

                        {/* Criteria Checklist */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <p className="mb-3 text-sm font-semibold text-[#57534e]">Criterios de revisión</p>
                            <ul className="flex flex-col gap-3">
                                {criteria.map((c) => (
                                    <li key={c.id} className="flex items-start gap-2.5">
                                        {c.passed === true && (
                                            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-[#16a34a]" />
                                        )}
                                        {c.passed === false && (
                                            <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-[#dc2626]" />
                                        )}
                                        {c.passed === null && (
                                            <div className="mt-0.5 h-4 w-4 shrink-0 rounded-full border-2 border-[#e5e5e5]" />
                                        )}
                                        <div>
                                            <p className="text-sm text-[#1c1917]">{c.label}</p>
                                            {c.comment && (
                                                <p className="text-xs text-[#78716c] mt-0.5">{c.comment}</p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
