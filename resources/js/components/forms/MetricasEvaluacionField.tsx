import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { CircleHelp, X } from 'lucide-react';

const PRINCIPLES: {
    title: string;
    body: ReactNode;
}[] = [
    {
        title: 'Definir el rol de la IA',
        body: (
            <>
                <p>Indique el papel que debe asumir el modelo al evaluar.</p>
                <blockquote className="mt-2 rounded-lg border border-[#e5e5e5] bg-[#f5f5f4] px-3 py-2 text-sm italic text-[#1c1917]">
                    «Actúa como un evaluador académico de proyectos de grado en Ingeniería de Sistemas.»
                </blockquote>
            </>
        ),
    },
    {
        title: 'Ser claro y específico',
        body: (
            <>
                <p>Indicar exactamente qué partes del documento deben analizarse.</p>
                <ul className="mt-2 list-disc space-y-0.5 pl-5">
                    {['objetivos', 'diagramas', 'arquitectura', 'requisitos', 'pruebas', 'metodología', 'resultados'].map(
                        (item) => (
                            <li key={item}>{item}</li>
                        ),
                    )}
                </ul>
                <p className="mt-2 font-medium text-[#1c1917]">Evitar instrucciones ambiguas.</p>
            </>
        ),
    },
    {
        title: 'Priorizar criterios de resultado sobre la forma',
        body: (
            <>
                <p>Describir la calidad esperada del contenido académico o técnico.</p>
                <p className="mt-2">Evitar centrarse únicamente en la longitud o formato del documento.</p>
            </>
        ),
    },
    {
        title: 'Establecer criterios técnicos observables',
        body: (
            <>
                <p>Especialmente para Proyecto de Grado 2.</p>
                <ul className="mt-2 list-disc space-y-0.5 pl-5">
                    {[
                        'arquitectura',
                        'UML',
                        'casos de uso',
                        'APIs',
                        'pruebas',
                        'calidad del diseño',
                        'normalización',
                        'patrones',
                    ].map((item) => (
                        <li key={item}>{item}</li>
                    ))}
                </ul>
            </>
        ),
    },
    {
        title: 'Señalar errores críticos',
        body: (
            <>
                <p>Especificar qué errores debe detectar la IA.</p>
                <ul className="mt-2 list-disc space-y-0.5 pl-5">
                    {[
                        'objetivos no medibles',
                        'inconsistencias entre requisitos',
                        'referencias obsoletas',
                        'falta de justificación técnica',
                        'diagramas incompletos',
                        'ausencia de pruebas',
                    ].map((item) => (
                        <li key={item}>{item}</li>
                    ))}
                </ul>
            </>
        ),
    },
    {
        title: 'Solicitar evidencia del documento',
        body: (
            <>
                <p>
                    Indicar que la IA fundamente su evaluación utilizando fragmentos o secciones del documento.
                </p>
                <p className="mt-2 font-medium text-[#1c1917]">No generar observaciones sin justificar.</p>
            </>
        ),
    },
    {
        title: 'Mantener un tono constructivo',
        body: (
            <>
                <p>La retroalimentación debe ayudar al estudiante a mejorar.</p>
                <p className="mt-2">Evitar respuestas únicamente sancionatorias.</p>
            </>
        ),
    },
];

const PG1_ASPECTS = [
    'formulación del problema',
    'hipótesis técnica',
    'coherencia entre objetivos',
    'marco teórico',
    'estado del arte',
    'calidad de referencias',
    'metodología',
    'requisitos funcionales',
    'requisitos no funcionales',
    'criterios de aceptación',
    'redacción técnica',
    'formato institucional',
] as const;

const PG2_ASPECTS = [
    'arquitectura de software',
    'componentes',
    'despliegue',
    'modelado de datos',
    'normalización',
    'diagramas UML',
    'APIs',
    'contratos de interfaz',
    'estrategia de pruebas',
    'resultados de pruebas',
    'métricas de desempeño',
    'deuda técnica',
    'limitaciones',
    'trabajo futuro',
] as const;

const PROMPT_TEMPLATE = `[ROL]
Actúa como evaluador académico de proyectos de grado en Ingeniería de Sistemas.

[OBJETIVO DE LA ENTREGA]
Evaluar el documento correspondiente a la entrega: [Nombre de la entrega].

[CRITERIOS Y MÉTRICAS A EVALUAR]
1. Describir qué debe evaluarse.
2. Indicar el estándar esperado.
3. Especificar criterios técnicos y académicos.

[ERRORES QUE DEBES DETECTAR]
• ...
• ...
• ...

[FORMATO DE LA RETROALIMENTACIÓN]
Generar:
- Resumen ejecutivo.
- Fortalezas.
- Aspectos críticos.
- Recomendaciones.
- Sugerencias técnicas.`;

const cardClass =
    'rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]';

export interface MetricasEvaluacionFieldProps {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
}

export function MetricasEvaluacionField({
    id = 'metricas-evaluacion',
    value,
    onChange,
    placeholder = 'Describa los aspectos más importantes que deben evaluarse en esta entrega',
}: MetricasEvaluacionFieldProps) {
    const [guideOpen, setGuideOpen] = useState(false);

    const closeGuide = useCallback(() => setGuideOpen(false), []);

    useEffect(() => {
        if (!guideOpen) return;

        const onKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') closeGuide();
        };

        document.addEventListener('keydown', onKeyDown);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = '';
        };
    }, [guideOpen, closeGuide]);

    return (
        <div className="flex flex-col gap-1.5 sm:col-span-2">
            <label htmlFor={id} className="inline-flex items-center gap-1.5 text-sm font-semibold text-[#1c1917]">
                Métricas de evaluación
                <button
                    type="button"
                    onClick={() => setGuideOpen(true)}
                    title="Guía para redactar métricas de evaluación."
                    aria-label="Guía para redactar métricas de evaluación"
                    className="inline-flex h-5 w-5 items-center justify-center rounded-full text-[#78716c] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c]"
                >
                    <CircleHelp className="h-4 w-4" aria-hidden="true" />
                </button>
            </label>
            <textarea
                id={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                rows={3}
                placeholder={placeholder}
                className="w-full min-h-[60px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y"
            />

            {guideOpen && (
                <div
                    className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4"
                    onClick={(e) => {
                        if (e.target === e.currentTarget) closeGuide();
                    }}
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="metricas-guide-title"
                >
                    <div className="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-xl border border-[#e5e5e5] bg-white shadow-[0_20px_60px_rgba(28,25,23,0.15)]">
                        <div className="flex items-start justify-between gap-3 border-b border-[#e5e5e5] px-6 py-4">
                            <h2 id="metricas-guide-title" className="text-base font-bold leading-snug text-[#1c1917]">
                                Guía para la Redacción del Prompt de Métricas de Evaluación (IA)
                            </h2>
                            <button
                                type="button"
                                onClick={closeGuide}
                                className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[#78716c] transition-colors hover:bg-[#f5f5f4]"
                                aria-label="Cerrar"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <div className="flex flex-col gap-6 overflow-y-auto px-6 py-5 text-sm text-[#57534e]">
                            {/* Descripción */}
                            <section className="flex flex-col gap-2 rounded-xl border border-[#fed7aa] bg-[#fff7ed] p-4">
                                <p>
                                    Esta guía orienta al Coordinador en la formulación de instrucciones (prompts) que
                                    utilizará la Inteligencia Artificial para generar una retroalimentación automática
                                    sobre las entregas de Proyecto de Grado.
                                </p>
                                <p>
                                    Las métricas escritas aquí servirán como contexto para que la IA comprenda qué debe
                                    evaluar, qué aspectos son prioritarios y cómo debe estructurar su retroalimentación.
                                </p>
                                <p>
                                    Una buena redacción permitirá obtener evaluaciones más precisas, útiles y alineadas
                                    con los objetivos académicos de la entrega.
                                </p>
                            </section>

                            {/* Principios */}
                            <section className="flex flex-col gap-3">
                                <h3 className="text-sm font-bold text-[#1c1917]">
                                    Principios para redactar métricas efectivas
                                </h3>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    {PRINCIPLES.map((principle) => (
                                        <article key={principle.title} className={cardClass}>
                                            <h4 className="mb-2 text-sm font-semibold text-[#c2410c]">
                                                {principle.title}
                                            </h4>
                                            <div className="text-sm leading-relaxed text-[#57534e]">
                                                {principle.body}
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            </section>

                            {/* Aspectos por proyecto */}
                            <section className="flex flex-col gap-3">
                                <h3 className="text-sm font-bold text-[#1c1917]">
                                    Ejemplos de aspectos que pueden evaluarse
                                </h3>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <article className={cardClass}>
                                        <h4 className="text-sm font-semibold text-[#1c1917]">Proyecto de Grado 1</h4>
                                        <p className="mt-0.5 mb-3 text-xs font-medium uppercase tracking-[0.03em] text-[#c2410c]">
                                            Énfasis en Documentación y Formulación
                                        </p>
                                        <p className="mb-2">Puede incluir aspectos como:</p>
                                        <ul className="list-disc space-y-0.5 pl-5">
                                            {PG1_ASPECTS.map((item) => (
                                                <li key={item}>{item}</li>
                                            ))}
                                        </ul>
                                    </article>
                                    <article className={cardClass}>
                                        <h4 className="text-sm font-semibold text-[#1c1917]">Proyecto de Grado 2</h4>
                                        <p className="mt-0.5 mb-3 text-xs font-medium uppercase tracking-[0.03em] text-[#c2410c]">
                                            Énfasis en Diseño, Desarrollo e Implementación
                                        </p>
                                        <p className="mb-2">Puede incluir aspectos como:</p>
                                        <ul className="list-disc space-y-0.5 pl-5">
                                            {PG2_ASPECTS.map((item) => (
                                                <li key={item}>{item}</li>
                                            ))}
                                        </ul>
                                    </article>
                                </div>
                            </section>

                            {/* Estructura sugerida */}
                            <section className="flex flex-col gap-3">
                                <h3 className="text-sm font-bold text-[#1c1917]">
                                    Estructura sugerida para redactar el prompt
                                </h3>
                                <pre className="overflow-x-auto whitespace-pre-wrap rounded-xl border border-[#e5e5e5] bg-[#f5f5f4] p-4 font-mono text-xs leading-relaxed text-[#1c1917]">
                                    {PROMPT_TEMPLATE}
                                </pre>
                            </section>
                        </div>

                        <div className="flex justify-end border-t border-[#e5e5e5] px-6 py-4">
                            <button
                                type="button"
                                onClick={closeGuide}
                                className="inline-flex min-h-[40px] items-center rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a]"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
