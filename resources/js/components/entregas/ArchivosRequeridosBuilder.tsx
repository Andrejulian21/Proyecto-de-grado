import { useCallback } from 'react';
import { X, Plus, FileText, Cpu } from 'lucide-react';
import type { ArchivoRequeridoConfig } from '@/types/entregas';

interface Props {
    value: ArchivoRequeridoConfig[];
    onChange: (archivos: ArchivoRequeridoConfig[]) => void;
    error?: string;
    disabled?: boolean;
}

const MAX_ARCHIVOS = 6;

/** Slug of the main project file enforced by the backend (RF-ENT-01). */
export const SLUG_DOCUMENTO_PROYECTO = 'documento-proyecto';

/** Label shown for the main project file. */
export const NOMBRE_DOCUMENTO_PROYECTO = 'Documento del proyecto';

function generarSlug(nombre: string): string {
    return nombre
        .toLowerCase()
        .replace(/\s+/g, '_')
        .replace(/[^a-z0-9_]/g, '')
        .replace(/_+/g, '_')
        .replace(/^_|_$/g, '');
}

export default function ArchivosRequeridosBuilder({ value, onChange, error, disabled }: Props) {
    const archivos = value ?? [];

    const agregar = useCallback(() => {
        if (archivos.length >= MAX_ARCHIVOS) return;
        const base = 'nuevo_archivo';
        let idx = 1;
        let slug = base;
        while (archivos.some((a) => a.id === slug)) {
            idx++;
            slug = `${base}_${idx}`;
        }
        onChange([
            ...archivos,
            { id: slug, nombre: '', versionamiento: true },
        ]);
    }, [archivos, onChange]);

    const eliminar = useCallback(
        (index: number) => {
            const archivo = archivos[index];
            // The main project file must always exist (RF-ENT-01).
            if (!archivo || archivo.id === SLUG_DOCUMENTO_PROYECTO) return;
            onChange(archivos.filter((_, i) => i !== index));
        },
        [archivos, onChange],
    );

    const actualizar = useCallback(
        (index: number, campo: 'nombre' | 'versionamiento' | 'analizable_ia', valor: string | boolean) => {
            const next = archivos.map((a, i) => {
                if (i !== index) return a;
                if (campo === 'nombre') {
                    const nuevoNombre = valor as string;
                    const esPrincipal = a.id === SLUG_DOCUMENTO_PROYECTO;
                    return {
                        ...a,
                        nombre: nuevoNombre,
                        // The main file keeps its canonical slug even if renamed.
                        id: esPrincipal ? a.id : generarSlug(nuevoNombre) || a.id,
                    };
                }
                if (campo === 'analizable_ia') {
                    const esPrincipal = a.id === SLUG_DOCUMENTO_PROYECTO;
                    // Backend rejects analizable_ia=true on secondary files (RF-ENT-02).
                    return { ...a, analizable_ia: esPrincipal ? (valor as boolean) : false };
                }
                return { ...a, versionamiento: valor as boolean };
            });
            onChange(next);
        },
        [archivos, onChange],
    );

    return (
        <div className="flex flex-col gap-3">
            <label className="text-sm font-semibold text-[#1c1917]">
                Archivos requeridos <span className="text-[#dc2626]">*</span>
            </label>

            {archivos.length === 0 && (
                <p className="text-xs text-[#a8a29e] italic">
                    No hay archivos requeridos. Agrega al menos uno.
                </p>
            )}

            <div className="flex flex-col gap-2">
                {archivos.map((archivo, index) => {
                    const esPrincipal = archivo.id === SLUG_DOCUMENTO_PROYECTO;
                    return (
                        <div
                            key={index}
                            className="flex items-start gap-3 rounded-lg border border-[#e5e5e5] bg-white p-3 shadow-[0_1px_2px_rgba(28,25,23,0.04)]"
                        >
                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#f5f5f4]">
                                <FileText className="h-4 w-4 text-[#c2410c]" />
                            </div>

                            <div className="flex flex-1 flex-row items-center gap-3">
                                <div className="flex-1">
                                    <input
                                        type="text"
                                        value={archivo.nombre}
                                        onChange={(e) => actualizar(index, 'nombre', e.target.value)}
                                        placeholder={esPrincipal ? NOMBRE_DOCUMENTO_PROYECTO : 'Ej: Anexo'}
                                        aria-label={`Nombre del archivo ${index + 1}`}
                                        className="w-full min-h-[36px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#a8a29e] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] disabled:opacity-50"
                                        disabled={disabled}
                                    />
                                    <span className="mt-0.5 flex items-center gap-1.5 text-[11px] text-[#a8a29e]">
                                        {esPrincipal && (
                                            <span className="rounded-full bg-[#fef3c7] px-1.5 py-px text-[10px] font-bold uppercase tracking-[0.03em] text-[#78350f]">
                                                Principal
                                            </span>
                                        )}
                                        ID: {archivo.id}
                                    </span>
                                </div>

                                <label className="flex shrink-0 items-center gap-2 text-sm text-[#57534e] whitespace-nowrap">
                                    <input
                                        type="checkbox"
                                        checked={archivo.versionamiento}
                                        onChange={(e) => actualizar(index, 'versionamiento', e.target.checked)}
                                        className="h-4 w-4 rounded border-[#d6d3d1] text-[#c2410c] focus:ring-[#c2410c] focus:ring-offset-0 disabled:opacity-50"
                                        disabled={disabled}
                                    />
                                    Versiones
                                </label>
                            </div>

                            {/* Analizable con IA — only the main file (RF-ENT-02). */}
                            <label className="flex shrink-0 items-center gap-1.5 text-sm text-[#57534e] whitespace-nowrap">
                                <Cpu className="h-4 w-4 text-[#78716c]" aria-hidden="true" />
                                <input
                                    type="checkbox"
                                    checked={esPrincipal ? Boolean(archivo.analizable_ia) : false}
                                    onChange={(e) => actualizar(index, 'analizable_ia', e.target.checked)}
                                    aria-label={`Analizable con IA para ${archivo.nombre || 'el archivo'}`}
                                    title={
                                        esPrincipal
                                            ? 'Permite el análisis automático con IA del documento del proyecto'
                                            : 'Solo el documento del proyecto puede ser analizable con IA'
                                    }
                                    className="h-4 w-4 rounded border-[#d6d3d1] text-[#c2410c] focus:ring-[#c2410c] focus:ring-offset-0 disabled:opacity-40"
                                    disabled={disabled || !esPrincipal}
                                />
                                Analizable con IA
                            </label>

                            <button
                                type="button"
                                onClick={() => eliminar(index)}
                                disabled={disabled || esPrincipal}
                                title={
                                    esPrincipal
                                        ? 'El archivo principal no puede eliminarse'
                                        : 'Eliminar archivo'
                                }
                                className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[#a8a29e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626] disabled:opacity-30"
                                aria-label={`Eliminar ${archivo.nombre || 'archivo'}`}
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    );
                })}
            </div>

            {archivos.length < MAX_ARCHIVOS && (
                <button
                    type="button"
                    onClick={agregar}
                    disabled={disabled}
                    className="inline-flex min-h-[36px] items-center justify-center gap-1.5 rounded-lg border-2 border-dashed border-[#d6d3d1] bg-transparent px-4 py-2 text-sm font-semibold text-[#57534e] transition-colors hover:border-[#c2410c] hover:text-[#c2410c] active:scale-[0.98] disabled:opacity-50"
                >
                    <Plus className="h-4 w-4" />
                    Agregar archivo
                </button>
            )}

            {archivos.length >= MAX_ARCHIVOS && (
                <p className="text-xs text-[#a8a29e]">
                    Máximo {MAX_ARCHIVOS} archivos alcanzado.
                </p>
            )}

            {error && (
                <p className="text-xs text-[#dc2626]" role="alert">{error}</p>
            )}
        </div>
    );
}
