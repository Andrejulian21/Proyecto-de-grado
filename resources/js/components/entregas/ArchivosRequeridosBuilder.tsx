import { useCallback } from 'react';
import { X, Plus, FileText } from 'lucide-react';
import type { ArchivoRequeridoConfig } from '@/types/entregas';

interface Props {
    value: ArchivoRequeridoConfig[];
    onChange: (archivos: ArchivoRequeridoConfig[]) => void;
    error?: string;
    disabled?: boolean;
}

const MAX_ARCHIVOS = 6;

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
            const next = archivos.filter((_, i) => i !== index);
            onChange(next);
        },
        [archivos, onChange],
    );

    const actualizar = useCallback(
        (index: number, campo: 'nombre' | 'versionamiento', valor: string | boolean) => {
            const next = archivos.map((a, i) => {
                if (i !== index) return a;
                if (campo === 'nombre') {
                    const nuevoNombre = valor as string;
                    return {
                        ...a,
                        nombre: nuevoNombre,
                        id: generarSlug(nuevoNombre) || a.id,
                    };
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
                {archivos.map((archivo, index) => (
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
                                    placeholder="Ej: Documento Anteproyecto"
                                    className="w-full min-h-[36px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#a8a29e] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] disabled:opacity-50"
                                    disabled={disabled}
                                />
                                <span className="mt-0.5 block text-[11px] text-[#a8a29e]">
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

                        <button
                            type="button"
                            onClick={() => eliminar(index)}
                            disabled={disabled}
                            className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[#a8a29e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626] disabled:opacity-30"
                            aria-label={`Eliminar ${archivo.nombre || 'archivo'}`}
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                ))}
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
                <p className="text-xs text-[#dc2626]">{error}</p>
            )}
        </div>
    );
}
