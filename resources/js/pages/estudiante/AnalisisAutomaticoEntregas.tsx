import { useState, type ChangeEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { ArrowLeft, Eye, Download, FileText, Brain, AlertTriangle, CheckCircle2, XCircle, Loader2, Upload, Lightbulb } from 'lucide-react';

/* ── Mock data ── */

const MOCK_DELIVERED_DOCUMENT = {
    fileName: 'avance-1-vfinal.pdf',
    label: 'Avance 1 — versión entregada',
    projectCode: 'PG-2026-014',
};

const MOCK_ANALYSIS_SUMMARY = {
    overview:
        'El borrador analizado presenta una estructura sólida y objetivos claros. Se detectaron oportunidades de mejora en referencias bibliográficas y en la actualización del cronograma antes de la entrega oficial.',
    improvements: [
        'Actualizar al menos 3 referencias con fuentes de los últimos 5 años.',
        'Revisar el cronograma para reflejar los cambios del último período académico.',
        'Ampliar la justificación metodológica en la sección de resultados esperados.',
        'Corregir inconsistencias menores de formato APA en la bibliografía.',
    ],
};

interface ChecklistItem {
    id: string;
    label: string;
    passed: boolean | null;
    details: string;
}

const MOCK_CHECKLIST: ChecklistItem[] = [
    { id: 'c1', label: 'El documento sigue la estructura definida en la plantilla', passed: true, details: 'Secciones: introducción, objetivos, metodología, resultados, conclusiones.' },
    { id: 'c2', label: 'Los objetivos están claramente definidos', passed: true, details: 'Objetivo general y específicos alineados con el título del proyecto.' },
    { id: 'c3', label: 'La metodología es coherente con los objetivos', passed: true, details: 'Metodología ágil SCRUM descrita y justificada.' },
    { id: 'c4', label: 'Las referencias están actualizadas (< 5 años)', passed: false, details: '3 de 12 referencias tienen más de 5 años de antigüedad.' },
    { id: 'c5', label: 'El documento no excede la extensión máxima', passed: true, details: '32 páginas, límite: 40 páginas.' },
    { id: 'c6', label: 'Incluye cronograma actualizado', passed: false, details: 'El cronograma no refleja los cambios del último período.' },
    { id: 'c7', label: 'Lenguaje claro y coherente', passed: true, details: 'Puntuación de legibilidad: 68/100 (aceptable).' },
];

const cardClass = 'rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]';

function ErrorAlert({ message }: { message: string }) {
    return (
        <div
            className="flex items-start gap-3 rounded-lg border border-[#dc2626]/20 bg-[#fee2e2] p-4 text-sm text-[#7f1d1d]"
            role="alert"
        >
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
            <span>{message}</span>
        </div>
    );
}

export default function AnalisisAutomaticoEntregas() {
    const navigate = useNavigate();

    const [tempFile, setTempFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);
    const [analyzed, setAnalyzed] = useState(false);
    const [analysisError, setAnalysisError] = useState<string | null>(null);
    const [analyzedFileName, setAnalyzedFileName] = useState<string | null>(null);

    const checklist = MOCK_CHECKLIST;
    const passedCount = checklist.filter((c) => c.passed === true).length;
    const totalCount = checklist.length;
    const coherenceScore = 82;

    function handleFileChange(e: ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0] ?? null;
        setTempFile(file);
        setAnalysisError(null);
        setAnalyzed(false);
        setAnalyzedFileName(null);
    }

    async function handleAnalyze() {
        setAnalysisError(null);

        if (!tempFile) {
            setAnalysisError('Debes seleccionar un archivo antes de iniciar el análisis.');
            return;
        }

        setProcessing(true);
        try {
            await new Promise((r) => setTimeout(r, 2000));
            setAnalyzedFileName(tempFile.name);
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
                subtitle={`${MOCK_DELIVERED_DOCUMENT.label} — ${MOCK_DELIVERED_DOCUMENT.projectCode}`}
                actions={
                    <button
                        type="button"
                        onClick={() => navigate('/dashboard/estudiante')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            {/* ── Carga temporal para análisis previo ── */}
            <div className={cardClass}>
                <div className="mb-4 flex items-center gap-2">
                    <Upload className="h-5 w-5 text-[#c2410c]" />
                    <h3 className="text-base font-bold text-[#1c1917]">Análisis previo a la entrega</h3>
                </div>
                <p className="mb-4 text-sm text-[#57534e]">
                    Selecciona un borrador para analizarlo antes de la entrega oficial. Este archivo es temporal
                    y no se registrará como una versión entregada.
                </p>
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-2">
                        <label htmlFor="temp-analysis-file" className="text-sm font-semibold text-[#1c1917]">
                            Archivo temporal
                        </label>
                        <input
                            id="temp-analysis-file"
                            type="file"
                            accept=".pdf,.doc,.docx,.ppt,.pptx"
                            onChange={handleFileChange}
                            className="block w-full text-sm text-[#57534e] file:mr-4 file:rounded-lg file:border-0 file:bg-[#fed7aa] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#c2410c] hover:file:bg-[#fdba74]"
                        />
                        {tempFile && (
                            <p className="text-xs text-[#57534e]">
                                Seleccionado: <span className="font-medium text-[#1c1917]">{tempFile.name}</span>
                            </p>
                        )}
                        {analyzedFileName && analyzed && (
                            <p className="text-xs text-[#16a34a]">
                                Último análisis: <span className="font-medium">{analyzedFileName}</span>
                            </p>
                        )}
                    </div>

                    {analysisError && <ErrorAlert message={analysisError} />}

                    <button
                        type="button"
                        onClick={handleAnalyze}
                        disabled={processing}
                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:px-6"
                    >
                        {processing ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <Brain className="h-4 w-4" />
                        )}
                        {processing ? 'Analizando...' : 'Analizar archivo'}
                    </button>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                {/* Documento ya entregado */}
                <div className="lg:col-span-3">
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-4 flex items-center justify-between gap-3 flex-wrap">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Documento entregado</h3>
                            </div>
                            <button
                                type="button"
                                className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                            >
                                <Download className="h-3.5 w-3.5" />
                                Descargar
                            </button>
                        </div>
                        <p className="mb-4 text-xs text-[#78716c]">
                            Visualización de la versión oficial ya registrada en el sistema.
                        </p>
                        <div className="flex aspect-[8.5/11] w-full items-center justify-center rounded-lg border border-[#e5e5e5] bg-[#fafaf9]">
                            <div className="flex flex-col items-center gap-3 text-center">
                                <Eye className="h-12 w-12 text-[#78716c]" />
                                <p className="text-sm font-medium text-[#1c1917]">
                                    Documento: {MOCK_DELIVERED_DOCUMENT.fileName}
                                </p>
                                <button
                                    type="button"
                                    className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                                >
                                    <Eye className="h-4 w-4" />
                                    Ver documento
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Panel de análisis */}
                <div className="lg:col-span-2">
                    <div className="sticky top-20 flex flex-col gap-4">
                        {analyzed && (
                            <div className={cardClass}>
                                <div className="mb-3 flex items-center gap-2">
                                    <Lightbulb className="h-5 w-5 text-[#c2410c]" />
                                    <h3 className="text-sm font-bold text-[#1c1917]">Resumen del borrador analizado</h3>
                                </div>
                                <p className="text-sm leading-relaxed text-[#57534e]">
                                    {MOCK_ANALYSIS_SUMMARY.overview}
                                </p>
                                <p className="mb-2 mt-4 text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                    Principales puntos de mejora
                                </p>
                                <ul className="flex flex-col gap-2">
                                    {MOCK_ANALYSIS_SUMMARY.improvements.map((item) => (
                                        <li key={item} className="flex items-start gap-2 text-sm text-[#1c1917]">
                                            <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#c2410c]" />
                                            {item}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {/* Puntaje de coherencia — documento entregado */}
                        <div className={cardClass}>
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

                        {/* Checklist — documento entregado */}
                        <div className={cardClass}>
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
                                        Este análisis es una herramienta de apoyo previa a la entrega oficial. No
                                        reemplaza la revisión del director ni del evaluador. Los resultados son
                                        orientativos y pueden contener errores.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
