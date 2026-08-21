export interface DirectorAcademicFormValues {
    areas: string;
    researchLines: string;
    technologies: string;
    methodologies: string;
    academicExperience: string;
    yearsOfExperience: string;
}

interface Props {
    idPrefix: string;
    values: DirectorAcademicFormValues;
    onChange: (patch: Partial<DirectorAcademicFormValues>) => void;
    compact?: boolean;
}

const inputClass =
    'w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]';

export function emptyDirectorAcademicForm(): DirectorAcademicFormValues {
    return {
        areas: '',
        researchLines: '',
        technologies: '',
        methodologies: '',
        academicExperience: '',
        yearsOfExperience: '',
    };
}

export function listToTextarea(items: string[] | null | undefined): string {
    return (items ?? []).join('\n');
}

export function DirectorAcademicFields({ idPrefix, values, onChange, compact = false }: Props) {
    const gap = compact ? 'mb-3' : 'mb-4';

    return (
        <div className="flex flex-col">
            <div className={`flex flex-col gap-1.5 ${gap}`}>
                <label htmlFor={`${idPrefix}-areas`} className="text-sm font-semibold text-[#1c1917]">
                    Áreas de especialización
                </label>
                <textarea
                    id={`${idPrefix}-areas`}
                    rows={compact ? 2 : 3}
                    value={values.areas}
                    onChange={(e) => onChange({ areas: e.target.value })}
                    className={`${inputClass} resize-y`}
                    placeholder={'Una por línea\nEj: Inteligencia Artificial\nDesarrollo Web'}
                />
                <span className="text-xs text-[#57534e]">
                    Se usa también en cupos y listados de Directores.
                </span>
            </div>

            <div className={`flex flex-col gap-1.5 ${gap}`}>
                <label htmlFor={`${idPrefix}-lines`} className="text-sm font-semibold text-[#1c1917]">
                    Líneas de investigación
                </label>
                <textarea
                    id={`${idPrefix}-lines`}
                    rows={compact ? 2 : 3}
                    value={values.researchLines}
                    onChange={(e) => onChange({ researchLines: e.target.value })}
                    className={`${inputClass} resize-y`}
                    placeholder={'Una por línea\nEj: IA aplicada a educación'}
                />
            </div>

            <div className={`grid grid-cols-1 gap-3 sm:grid-cols-2 ${gap}`}>
                <div className="flex flex-col gap-1.5">
                    <label htmlFor={`${idPrefix}-tech`} className="text-sm font-semibold text-[#1c1917]">
                        Tecnologías dominadas
                    </label>
                    <textarea
                        id={`${idPrefix}-tech`}
                        rows={compact ? 2 : 3}
                        value={values.technologies}
                        onChange={(e) => onChange({ technologies: e.target.value })}
                        className={`${inputClass} resize-y`}
                        placeholder={'Una por línea\nEj: Laravel\nReact'}
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <label htmlFor={`${idPrefix}-method`} className="text-sm font-semibold text-[#1c1917]">
                        Metodologías
                    </label>
                    <textarea
                        id={`${idPrefix}-method`}
                        rows={compact ? 2 : 3}
                        value={values.methodologies}
                        onChange={(e) => onChange({ methodologies: e.target.value })}
                        className={`${inputClass} resize-y`}
                        placeholder={'Una por línea\nEj: SCRUM\nDesign Science'}
                    />
                </div>
            </div>

            <div className={`flex flex-col gap-1.5 ${gap}`}>
                <label htmlFor={`${idPrefix}-years`} className="text-sm font-semibold text-[#1c1917]">
                    Años de experiencia
                </label>
                <input
                    id={`${idPrefix}-years`}
                    type="number"
                    min={0}
                    max={80}
                    value={values.yearsOfExperience}
                    onChange={(e) => onChange({ yearsOfExperience: e.target.value })}
                    className={inputClass}
                    placeholder="Ej: 8"
                />
            </div>

            <div className="flex flex-col gap-1.5">
                <label htmlFor={`${idPrefix}-bio`} className="text-sm font-semibold text-[#1c1917]">
                    Descripción profesional
                </label>
                <textarea
                    id={`${idPrefix}-bio`}
                    rows={compact ? 3 : 4}
                    value={values.academicExperience}
                    onChange={(e) => onChange({ academicExperience: e.target.value })}
                    className={`${inputClass} resize-y`}
                    placeholder="Resumen de trayectoria académica y/o profesional relevante para dirigir proyectos de grado."
                />
            </div>
        </div>
    );
}
