import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ArrowLeft, Eye, Download, FileText, Send, Loader2, Star } from 'lucide-react';

interface GradeCriterion {
    id: string;
    name: string;
    maxScore: number;
    score: number;
}

const CRITERIA: GradeCriterion[] = [
    { id: 'g1', name: 'Contenido y Estructura', maxScore: 40, score: 0 },
    { id: 'g2', name: 'Sustentación y Dominio', maxScore: 35, score: 0 },
    { id: 'g3', name: 'Resultados y Aportes', maxScore: 25, score: 0 },
];

export default function EvaluadorCalificar() {
    const navigate = useNavigate();
    const [criteria, setCriteria] = useState(CRITERIA);
    const [comment, setComment] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);

    const totalScore = criteria.reduce((s, c) => s + c.score, 0);
    const totalMax = criteria.reduce((s, c) => s + c.maxScore, 0);

    function handleScoreChange(id: string, value: number) {
        setCriteria((prev) =>
            prev.map((c) => (c.id === id ? { ...c, score: Math.min(Math.max(0, value), c.maxScore) } : c))
        );
    }

    async function handleSubmit() {
        if (totalScore === 0) return;
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
                    eyebrow="Calificación"
                    title="Calificación Enviada"
                    subtitle="La calificación ha sido registrada exitosamente"
                    actions={
                        <button
                            onClick={() => navigate('/dashboard/evaluador-externo')}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver al inicio
                        </button>
                    }
                />
                <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-[#dcfce7] bg-[#dcfce7] py-16 text-center">
                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white">
                        <Star className="h-8 w-8 text-[#16a34a]" fill="#16a34a" />
                    </div>
                    <div>
                        <h3 className="text-lg font-bold text-[#14532d]">Calificación registrada</h3>
                        <p className="text-sm text-[#14532d] mt-1">
                            Puntaje: <span className="font-bold tabular-nums">{totalScore}/{totalMax}</span>
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Calificación"
                title="Calificar Proyecto"
                subtitle="PG-2026-014 — Sistema Centralizado de Proyectos de Grado"
                actions={
                    <button
                        onClick={() => navigate('/dashboard/evaluador-externo')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                {/* Document */}
                <div className="lg:col-span-3">
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-4 flex items-center justify-between gap-3 flex-wrap">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Documento</h3>
                                <StatusBadge variant="warning">Pendiente</StatusBadge>
                            </div>
                            <button className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]">
                                <Download className="h-3.5 w-3.5" />
                                Descargar
                            </button>
                        </div>
                        <div className="flex aspect-[8.5/11] w-full items-center justify-center rounded-lg border border-[#e5e5e5] bg-[#fafaf9]">
                            <div className="flex flex-col items-center gap-3 text-center">
                                <Eye className="h-12 w-12 text-[#78716c]" />
                                <p className="text-sm font-medium text-[#1c1917]">Documento del proyecto</p>
                                <button className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]">
                                    <Eye className="h-4 w-4" />
                                    Ver completo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Grade Panel */}
                <div className="lg:col-span-2">
                    <div className="sticky top-20 flex flex-col gap-4">
                        {/* Total */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-semibold text-[#57534e]">Puntaje Total</span>
                                <span className="text-2xl font-bold text-[#1c1917] tabular-nums">
                                    {totalScore} <span className="text-sm font-normal text-[#78716c]">/ {totalMax}</span>
                                </span>
                            </div>
                        </div>

                        {/* Criteria */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <h3 className="mb-4 text-sm font-bold text-[#1c1917]">Criterios</h3>
                            <div className="space-y-5">
                                {criteria.map((c) => (
                                    <div key={c.id}>
                                        <div className="flex items-center justify-between gap-2 mb-1">
                                            <p className="text-sm font-semibold text-[#1c1917]">{c.name}</p>
                                            <span className="text-xs text-[#78716c] tabular-nums">{c.score}/{c.maxScore}</span>
                                        </div>
                                        <input
                                            type="range"
                                            min={0}
                                            max={c.maxScore}
                                            value={c.score}
                                            onChange={(e) => handleScoreChange(c.id, Number(e.target.value))}
                                            className="w-full accent-[#c2410c]"
                                            aria-label={`Puntaje para ${c.name}`}
                                        />
                                        <div className="flex items-center gap-2 mt-1">
                                            <input
                                                type="number"
                                                min={0}
                                                max={c.maxScore}
                                                value={c.score}
                                                onChange={(e) => handleScoreChange(c.id, Number(e.target.value))}
                                                className="w-16 min-h-[32px] rounded-lg border border-[#e5e5e5] bg-white px-2 py-1 text-xs font-semibold text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] tabular-nums"
                                            />
                                            <span className="text-xs text-[#78716c]">/ {c.maxScore}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Comments */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <p className="mb-3 text-sm font-semibold text-[#57534e]">Observaciones</p>
                            <textarea
                                rows={4}
                                value={comment}
                                onChange={(e) => setComment(e.target.value)}
                                placeholder="Comentarios sobre la calificación..."
                                className="w-full min-h-[80px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y"
                            />
                        </div>

                        <button
                            onClick={handleSubmit}
                            disabled={totalScore === 0 || submitting}
                            className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {submitting ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Send className="h-4 w-4" />
                            )}
                            Enviar Calificación
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
