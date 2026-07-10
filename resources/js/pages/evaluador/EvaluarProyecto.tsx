import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ArrowLeft, Eye, Download, FileText, Send, Loader2 } from 'lucide-react';

interface RubricItem {
    id: string;
    name: string;
    description: string;
    maxScore: number;
    score: number;
}

const RUBRIC: RubricItem[] = [
    { id: 'c1', name: 'Cumplimiento de Objetivos', description: 'Grado en que el proyecto cumple con los objetivos planteados en la propuesta inicial.', maxScore: 30, score: 0 },
    { id: 'c2', name: 'Calidad Técnica y Metodológica', description: 'Aplicación correcta de metodologías, herramientas y estándares de ingeniería.', maxScore: 25, score: 0 },
    { id: 'c3', name: 'Presentación y Sustentación', description: 'Claridad en la exposición, dominio del tema y capacidad de respuesta a preguntas.', maxScore: 25, score: 0 },
    { id: 'c4', name: 'Aportes y Resultados', description: 'Valor de los resultados obtenidos y su contribución al área de conocimiento.', maxScore: 20, score: 0 },
];

export default function EvaluarProyecto() {
    const navigate = useNavigate();
    const [rubric, setRubric] = useState(RUBRIC);
    const [globalComment, setGlobalComment] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);

    const totalScore = rubric.reduce((s, r) => s + r.score, 0);
    const totalMax = rubric.reduce((s, r) => s + r.maxScore, 0);

    function handleScoreChange(id: string, value: number) {
        setRubric((prev) =>
            prev.map((r) => (r.id === id ? { ...r, score: Math.min(Math.max(0, value), r.maxScore) } : r))
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
                    eyebrow="Evaluación"
                    title="Evaluación Enviada"
                    subtitle="La evaluación del proyecto ha sido registrada exitosamente"
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
                        <svg viewBox="0 0 24 24" className="h-8 w-8 text-[#16a34a]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div>
                        <h3 className="text-lg font-bold text-[#14532d]">Evaluación registrada</h3>
                        <p className="text-sm text-[#14532d] mt-1">
                            Calificación: <span className="font-bold tabular-nums">{totalScore}/{totalMax}</span>
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Evaluación"
                title="Evaluar Proyecto"
                subtitle="PG-2026-014 — Sistema Centralizado de Proyectos de Grado — Carlos Méndez"
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
                {/* Document Viewer */}
                <div className="lg:col-span-3">
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-4 flex items-center justify-between gap-3 flex-wrap">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Documento del Proyecto</h3>
                                <StatusBadge variant="warning">Por evaluar</StatusBadge>
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
                                <p className="text-sm font-medium text-[#1c1917]">Documento del proyecto</p>
                                <button className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]">
                                    <Eye className="h-4 w-4" />
                                    Abrir documento
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Rubric Panel */}
                <div className="lg:col-span-2">
                    <div className="sticky top-20 flex flex-col gap-4">
                        {/* Score summary */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-semibold text-[#57534e]">Calificación Total</span>
                                <span className="text-2xl font-bold text-[#1c1917] tabular-nums">
                                    {totalScore} <span className="text-sm font-normal text-[#78716c]">/ {totalMax}</span>
                                </span>
                            </div>
                        </div>

                        {/* Rubric items */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <h3 className="mb-4 text-sm font-bold text-[#1c1917]">Rúbrica de Evaluación</h3>
                            <div className="space-y-5">
                                {rubric.map((item) => (
                                    <div key={item.id}>
                                        <div className="flex items-center justify-between gap-2 mb-1">
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-semibold text-[#1c1917]">{item.name}</p>
                                                <p className="text-xs text-[#57534e]">{item.description}</p>
                                            </div>
                                            <span className="text-xs text-[#78716c] tabular-nums shrink-0">
                                                {item.score}/{item.maxScore}
                                            </span>
                                        </div>
                                        <input
                                            type="range"
                                            min={0}
                                            max={item.maxScore}
                                            value={item.score}
                                            onChange={(e) => handleScoreChange(item.id, Number(e.target.value))}
                                            className="w-full accent-[#c2410c]"
                                            aria-label={`Puntaje para ${item.name}`}
                                        />
                                        <div className="flex items-center gap-2 mt-1">
                                            <input
                                                type="number"
                                                min={0}
                                                max={item.maxScore}
                                                value={item.score}
                                                onChange={(e) => handleScoreChange(item.id, Number(e.target.value))}
                                                className="w-16 min-h-[32px] rounded-lg border border-[#e5e5e5] bg-white px-2 py-1 text-xs font-semibold text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] tabular-nums"
                                            />
                                            <span className="text-xs text-[#78716c]">/ {item.maxScore}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Comments */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <p className="mb-3 text-sm font-semibold text-[#57534e]">Comentarios Generales</p>
                            <textarea
                                rows={4}
                                value={globalComment}
                                onChange={(e) => setGlobalComment(e.target.value)}
                                placeholder="Escriba sus observaciones sobre el proyecto..."
                                className="w-full min-h-[80px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y"
                            />
                        </div>

                        {/* Submit */}
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
                            Enviar Evaluación
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
