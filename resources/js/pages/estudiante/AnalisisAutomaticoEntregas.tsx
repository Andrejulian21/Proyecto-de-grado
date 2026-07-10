import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ArrowLeft, Eye, Download, FileText, Brain, AlertTriangle, CheckCircle2, XCircle, Loader2 } from 'lucide-react';

interface ChecklistItem {
    id: string;
    label: string;
    passed: boolean | null;
    details: string;
}

export default function AnalisisAutomaticoEntregas() {
    const navigate = useNavigate();
    const [processing, setProcessing] = useState(false);
    const [analyzed, setAnalyzed] = useState(true);

    const [checklist] = useState<ChecklistItem[]>([
        { id: 'c1', label: 'El documento sigue la estructura definida en la plantilla', passed: true, details: 'Secciones: introducción, objetivos, metodología, resultados, conclusiones.' },
        { id: 'c2', label: 'Los objetivos están claramente definidos', passed: true, details: 'Objetivo general y específicos alineados con el título del proyecto.' },
        { id: 'c3', label: 'La metodología es coherente con los objetivos', passed: true, details: 'Metodología ágil SCRUM descrita y justificada.' },
        { id: 'c4', label: 'Las referencias están actualizadas (< 5 años)', passed: false, details: '3 de 12 referencias tienen más de 5 años de antigüedad.' },
        { id: 'c5', label: 'El documento no excede la extensión máxima', passed: true, details: '32 páginas, límite: 40 páginas.' },
        { id: 'c6', label: 'Incluye cronograma actualizado', passed: false, details: 'El cronograma no refleja los cambios del último período.' },
        { id: 'c7', label: 'Lenguaje claro y coherente', passed: true, details: 'Puntuación de legibilidad: 68/100 (aceptable).' },
    ]);

    const passedCount = checklist.filter((c) => c.passed === true).length;
    const totalCount = checklist.length;
    const coherenceScore = 82;

    async function handleAnalyze() {
        setProcessing(true);
        try {
            await new Promise((r) => setTimeout(r, 2000));
            setAnalyzed(true);
        } finally {
            setProcessing(false);
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="IA"
                title="Análisis Automático de Entregas"
                subtitle="Avance 1 — PG-2026-014"
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
                {/* Document */}
                <div className="lg:col-span-3">
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-4 flex items-center justify-between gap-3 flex-wrap">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Documento Analizado</h3>
                            </div>
                            <button className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]">
                                <Download className="h-3.5 w-3.5" />
                                Descargar
                            </button>
                        </div>
                        <div className="flex aspect-[8.5/11] w-full items-center justify-center rounded-lg border border-[#e5e5e5] bg-[#fafaf9]">
                            <div className="flex flex-col items-center gap-3 text-center">
                                <Eye className="h-12 w-12 text-[#78716c]" />
                                <p className="text-sm font-medium text-[#1c1917]">Documento: avance-1-vfinal.pdf</p>
                                <button className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]">
                                    <Eye className="h-4 w-4" />
                                    Ver documento
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Analysis Panel */}
                <div className="lg:col-span-2">
                    <div className="sticky top-20 flex flex-col gap-4">
                        {/* Coherence Score */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="mb-3 flex items-center gap-2">
                                <Brain className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-sm font-bold text-[#1c1917]">Puntaje de Coherencia</h3>
                            </div>
                            <div className="flex items-baseline gap-1">
                                <span className="text-4xl font-bold text-[#c2410c] tabular-nums">{coherenceScore}</span>
                                <span className="text-sm text-[#78716c]">/ 100</span>
                            </div>
                            <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-[#e7e5e4]">
                                <div
                                    className="h-full rounded-full bg-[#c2410c]"
                                    style={{ width: `${coherenceScore}%` }}
                                />
                            </div>
                            <p className="mt-2 text-xs text-[#57534e]">
                                {coherenceScore >= 80
                                    ? 'Buena coherencia general. El documento está bien estructurado.'
                                    : 'Se detectaron oportunidades de mejora en la coherencia.'}
                            </p>
                        </div>

                        {/* Checklist */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="mb-3 flex items-center justify-between">
                                <h3 className="text-sm font-bold text-[#1c1917]">Checklist de Calidad</h3>
                                <span className="text-xs font-semibold text-[#1c1917] tabular-nums">
                                    {passedCount}/{totalCount}
                                </span>
                            </div>
                            <div className="space-y-3">
                                {checklist.map((item) => (
                                    <div key={item.id} className="flex items-start gap-2.5">
                                        {item.passed === true ? (
                                            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-[#16a34a]" />
                                        ) : item.passed === false ? (
                                            <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-[#dc2626]" />
                                        ) : (
                                            <div className="mt-0.5 h-4 w-4 shrink-0 rounded-full border-2 border-[#e5e5e5]" />
                                        )}
                                        <div>
                                            <p className="text-sm text-[#1c1917]">{item.label}</p>
                                            <p className="text-xs text-[#78716c] mt-0.5">{item.details}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Disclaimer */}
                        <div className="rounded-xl border border-[#fef3c7] bg-[#fef3c7] p-4">
                            <div className="flex items-start gap-2.5">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-[#d97706]" />
                                <div>
                                    <p className="text-xs font-semibold text-[#78350f]">
                                        Análisis asistido por inteligencia artificial
                                    </p>
                                    <p className="text-xs text-[#78350f] mt-1">
                                        Este análisis es una herramienta de apoyo. No reemplaza la revisión
                                        del director ni del evaluador. Los resultados son orientativos y pueden
                                        contener errores. Verifique siempre la información antes de tomar decisiones.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {!analyzed && (
                            <button
                                onClick={handleAnalyze}
                                disabled={processing}
                                className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:opacity-60"
                            >
                                {processing ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Brain className="h-4 w-4" />
                                )}
                                {processing ? 'Analizando...' : 'Analizar Documento'}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
