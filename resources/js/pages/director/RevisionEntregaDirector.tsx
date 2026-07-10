import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ArrowLeft, CheckCircle2, XCircle, AlertCircle, Download, Eye, FileText, MessageSquare, Send, Loader2 } from 'lucide-react';

interface RubricCriterion {
    id: string;
    name: string;
    maxScore: number;
    score: number;
    observation: string;
}

export default function RevisionEntregaDirector() {
    const navigate = useNavigate();

    const [decision, setDecision] = useState<'approved' | 'corrections' | 'rejected' | null>(null);
    const [selectedScore, setSelectedScore] = useState<number>(75);
    const [comment, setComment] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);

    const [criteria] = useState<RubricCriterion[]>([
        { id: '1', name: 'Estructura y formato', maxScore: 25, score: 20, observation: 'Bien estructurado, faltan algunas secciones' },
        { id: '2', name: 'Contenido técnico', maxScore: 25, score: 18, observation: 'Buen nivel técnico, profundizar en análisis' },
        { id: '3', name: 'Redacción y ortografía', maxScore: 10, score: 7, observation: 'Errores ortográficos menores' },
        { id: '4', name: 'Cumplimiento de objetivos', maxScore: 25, score: 22, observation: 'Objetivos cumplidos satisfactoriamente' },
        { id: '5', name: 'Referencias y anexos', maxScore: 15, score: 8, observation: 'Faltan algunas referencias actualizadas' },
    ]);

    const totalMax = criteria.reduce((s, c) => s + c.maxScore, 0);
    const totalScore = criteria.reduce((s, c) => s + c.score, 0);

    async function handleSubmit() {
        if (!decision || !comment.trim()) return;
        setSubmitting(true);
        try {
            await new Promise((r) => setTimeout(r, 1000));
            setSubmitted(true);
        } finally {
            setSubmitting(false);
        }
    }

    if (submitted) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Revisión"
                    title="Revisión Enviada"
                    subtitle="La revisión ha sido registrada exitosamente"
                    actions={
                        <button
                            onClick={() => navigate('/dashboard/director')}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver al inicio
                        </button>
                    }
                />
                <div className={`flex flex-col items-center justify-center gap-4 rounded-xl border py-16 text-center ${
                    decision === 'approved'
                        ? 'border-[#dcfce7] bg-[#dcfce7]'
                        : decision === 'corrections'
                        ? 'border-[#fef3c7] bg-[#fef3c7]'
                        : 'border-[#fee2e2] bg-[#fee2e2]'
                }`}>
                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white">
                        {decision === 'approved' ? (
                            <CheckCircle2 className="h-8 w-8 text-[#16a34a]" />
                        ) : decision === 'corrections' ? (
                            <AlertCircle className="h-8 w-8 text-[#d97706]" />
                        ) : (
                            <XCircle className="h-8 w-8 text-[#dc2626]" />
                        )}
                    </div>
                    <div>
                        <h3 className="text-lg font-bold text-[#1c1917]">
                            {decision === 'approved' ? 'Entrega Aprobada'
                                : decision === 'corrections' ? 'Correcciones Solicitadas'
                                : 'Entrega Rechazada'}
                        </h3>
                        <p className="text-sm text-[#57534e] mt-1">
                            Calificación: <span className="font-bold tabular-nums">{selectedScore}/100</span>
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Revisión"
                title="Revisar Entrega"
                subtitle="PG-2026-014 · Avance 1 — Estudiante: Carlos Andrés Méndez"
                actions={
                    <button
                        onClick={() => navigate('/dashboard/director')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                {/* Document Viewer */}
                <div className="lg:col-span-3">
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-4 flex items-center justify-between gap-3 flex-wrap">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Documento</h3>
                                <span className="text-xs text-[#78716c]">avance-1-vfinal.pdf</span>
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
                                        Haga clic para ver el documento completo
                                    </p>
                                </div>
                                <button className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]">
                                    <Eye className="h-4 w-4" />
                                    Abrir documento
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Rubric */}
                    <div className="mt-6 rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <h3 className="mb-4 text-base font-bold text-[#1c1917]">Rúbrica de Evaluación</h3>
                        <div className="space-y-3">
                            {criteria.map((c) => (
                                <div key={c.id} className="rounded-lg border border-[#e5e5e5] p-3.5">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-semibold text-[#1c1917]">{c.name}</p>
                                            {c.observation && (
                                                <p className="text-xs text-[#57534e] mt-0.5">{c.observation}</p>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1.5 shrink-0">
                                            <span className="text-lg font-bold text-[#1c1917] tabular-nums">{c.score}</span>
                                            <span className="text-xs text-[#78716c]">/ {c.maxScore}</span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="mt-4 flex items-center justify-between border-t border-[#e5e5e5] pt-4">
                            <span className="text-sm font-bold text-[#1c1917]">Total</span>
                            <span className="text-xl font-bold text-[#1c1917] tabular-nums">
                                {totalScore} / {totalMax}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Review Panel */}
                <div className="lg:col-span-2">
                    <div className="sticky top-20 flex flex-col gap-4">
                        {/* Grade */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <p className="mb-3 text-sm font-semibold text-[#57534e]">Calificación</p>
                            <div className="flex items-center gap-3">
                                <input
                                    type="number"
                                    min={0}
                                    max={100}
                                    value={selectedScore}
                                    onChange={(e) => setSelectedScore(Number(e.target.value))}
                                    className="w-20 min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-lg font-bold text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] tabular-nums"
                                />
                                <span className="text-sm text-[#78716c]">/ 100</span>
                            </div>
                            <input
                                type="range"
                                min={0}
                                max={100}
                                value={selectedScore}
                                onChange={(e) => setSelectedScore(Number(e.target.value))}
                                className="mt-3 w-full accent-[#c2410c]"
                                aria-label="Ajustar calificación"
                            />
                        </div>

                        {/* Decision buttons */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <p className="mb-3 text-sm font-semibold text-[#57534e]">Decisión</p>
                            <div className="flex flex-col gap-2">
                                <button
                                    onClick={() => setDecision('approved')}
                                    className={`flex items-center gap-3 rounded-lg border p-3 text-left transition-all active:scale-[0.98] ${
                                        decision === 'approved'
                                            ? 'border-[#16a34a] bg-[#dcfce7]'
                                            : 'border-[#e5e5e5] hover:bg-[#fafaf9]'
                                    }`}
                                >
                                    <CheckCircle2 className={`h-5 w-5 ${decision === 'approved' ? 'text-[#16a34a]' : 'text-[#78716c]'}`} />
                                    <div>
                                        <p className="text-sm font-semibold text-[#1c1917]">Aprobar</p>
                                        <p className="text-xs text-[#57534e]">La entrega cumple con todos los criterios</p>
                                    </div>
                                </button>
                                <button
                                    onClick={() => setDecision('corrections')}
                                    className={`flex items-center gap-3 rounded-lg border p-3 text-left transition-all active:scale-[0.98] ${
                                        decision === 'corrections'
                                            ? 'border-[#d97706] bg-[#fef3c7]'
                                            : 'border-[#e5e5e5] hover:bg-[#fafaf9]'
                                    }`}
                                >
                                    <AlertCircle className={`h-5 w-5 ${decision === 'corrections' ? 'text-[#d97706]' : 'text-[#78716c]'}`} />
                                    <div>
                                        <p className="text-sm font-semibold text-[#1c1917]">Solicitar Correcciones</p>
                                        <p className="text-xs text-[#57534e]">Se requieren ajustes antes de aprobar</p>
                                    </div>
                                </button>
                                <button
                                    onClick={() => setDecision('rejected')}
                                    className={`flex items-center gap-3 rounded-lg border p-3 text-left transition-all active:scale-[0.98] ${
                                        decision === 'rejected'
                                            ? 'border-[#dc2626] bg-[#fee2e2]'
                                            : 'border-[#e5e5e5] hover:bg-[#fafaf9]'
                                    }`}
                                >
                                    <XCircle className={`h-5 w-5 ${decision === 'rejected' ? 'text-[#dc2626]' : 'text-[#78716c]'}`} />
                                    <div>
                                        <p className="text-sm font-semibold text-[#1c1917]">Rechazar</p>
                                        <p className="text-xs text-[#57534e]">La entrega no cumple con los requisitos mínimos</p>
                                    </div>
                                </button>
                            </div>
                        </div>

                        {/* Comments */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="mb-3 flex items-center gap-2">
                                <MessageSquare className="h-4 w-4 text-[#4f46e5]" />
                                <p className="text-sm font-semibold text-[#57534e]">Comentarios</p>
                            </div>
                            <textarea
                                rows={4}
                                value={comment}
                                onChange={(e) => setComment(e.target.value)}
                                placeholder="Escriba sus observaciones y recomendaciones para el estudiante..."
                                className="w-full min-h-[80px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y"
                            />
                            <div className="mt-1 flex items-center justify-between gap-3">
                                <span className="text-xs text-[#78716c] tabular-nums">{comment.length} caracteres</span>
                                {!comment.trim() && (
                                    <span className="text-xs text-[#dc2626]">Requerido</span>
                                )}
                            </div>
                        </div>

                        {/* Submit */}
                        <button
                            onClick={handleSubmit}
                            disabled={!decision || !comment.trim() || submitting}
                            className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {submitting ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Send className="h-4 w-4" />
                            )}
                            Enviar Revisión
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
