import { useCallback, useState } from 'react';
import { X, Plus, FileText, Cpu } from 'lucide-react';
import type { DocumentoSolicitado } from '@/types/entregas';
import { obtenerIdArchivo } from '@/lib/entregas';

interface Props {
    value: DocumentoSolicitado[];
    onChange: (documentos: DocumentoSolicitado[]) => void;
    error?: string;
    disabled?: boolean;
}

const MAX_DOCUMENTOS = 6;
export const MENSAJE_IA_UNICO = 'Solo un documento de la entrega puede analizarse con IA.';

function generarSlug(nombre: string): string {
    return nombre
        .toLowerCase()
        .replace(/\s+/g, '_')
        .replace(/[^a-z0-9_]/g, '')
        .replace(/_+/g, '_')
        .replace(/^_|_$/g, '');
}

export default function ArchivosRequeridosBuilder({ value, onChange, error, disabled }: Props) {
    const documentos = value ?? [];
    const [iaError, setIaError] = useState<string | null>(null);

    const agregar = useCallback(() => {
        if (documentos.length >= MAX_DOCUMENTOS) return;
        const base = 'documento';
        let idx = documentos.length + 1;
        let slug = `${base}_${idx}`;
        while (documentos.some((a) => obtenerIdArchivo(a) === slug)) {
            idx++;
            slug = `${base}_${idx}`;
        }
        setIaError(null);
        onChange([
            ...documentos,
            { id: slug, nombre: '', versionamiento: true, analizable_ia: false },
        ]);
    }, [documentos, onChange]);

    const eliminar = useCallback(
        (index: number) => {
            if (documentos.length <= 1) return;
            setIaError(null);
            onChange(documentos.filter((_, i) => i !== index));
        },
        [documentos, onChange],
    );

    const actualizar = useCallback(
        (index: number, campo: 'nombre' | 'versionamiento' | 'analizable_ia', valor: string | boolean) => {
            if (campo === 'analizable_ia' && valor === true) {
                const otroIa = documentos.some((a, i) => i !== index && Boolean(a.analizable_ia));
                if (otroIa) {
                    setIaError(MENSAJE_IA_UNICO);
                    return;
                }
            }
            if (campo === 'analizable_ia') {
                setIaError(null);
            }

            const next = documentos.map((a, i) => {
                if (i !== index) return a;
                if (campo === 'nombre') {
                    const nuevoNombre = valor as string;
                    const slug = generarSlug(nuevoNombre) || obtenerIdArchivo(a);
                    const taken = documentos.some((other, oi) => oi !== index && obtenerIdArchivo(other) === slug);
                    return {
                        ...a,
                        nombre: nuevoNombre,
                        id: taken ? obtenerIdArchivo(a) : slug,
                    };
                }
                if (campo === 'analizable_ia') {
                    return { ...a, analizable_ia: valor as boolean };
                }
                return { ...a, versionamiento: valor as boolean };
            });
            onChange(next);
        },
        [documentos, onChange],
    );

    return (
        <div className="flex flex-col gap-3">
            <label className="text-sm font-semibold text-[#1c1917]">
                Documentos solicitados <span className="text-[#dc2626]">*</span>
            </label>
            <p className="text-xs text-[#78716c]">
                Cada título identifica el documento que el estudiante debe entregar. Solo uno puede analizarse con IA.
            </p>

            {documentos.length === 0 && (
                <p className="text-xs text-[#a8a29e] italic">
                    No hay documentos solicitados. Agrega al menos uno.
                </p>
            )}

            <div className="flex flex-col gap-2">
                {documentos.map((documento, index) => (
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
                                    value={documento.nombre}
                                    onChange={(e) => actualizar(index, 'nombre', e.target.value)}
                                    placeholder="Ej: Planteamiento del problema"
                                    aria-label={`Título del documento ${index + 1}`}
                                    className="w-full min-h-[36px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#a8a29e] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] disabled:opacity-50"
                                    disabled={disabled}
                                />
                                <span className="mt-0.5 flex items-center gap-1.5 text-[11px] text-[#a8a29e]">
                                    ID: {obtenerIdArchivo(documento) || '—'}
                                </span>
                            </div>

                            <label className="flex shrink-0 items-center gap-2 text-sm text-[#57534e] whitespace-nowrap">
                                <input
                                    type="checkbox"
                                    checked={documento.versionamiento}
                                    onChange={(e) => actualizar(index, 'versionamiento', e.target.checked)}
                                    className="h-4 w-4 rounded border-[#d6d3d1] text-[#c2410c] focus:ring-[#c2410c] focus:ring-offset-0 disabled:opacity-50"
                                    disabled={disabled}
                                />
                                Versiones
                            </label>

                            <label className="flex shrink-0 items-center gap-1.5 text-sm text-[#57534e] whitespace-nowrap">
                                <Cpu className="h-4 w-4 text-[#78716c]" aria-hidden="true" />
                                <input
                                    type="checkbox"
                                    checked={Boolean(documento.analizable_ia)}
                                    onChange={(e) => actualizar(index, 'analizable_ia', e.target.checked)}
                                    aria-label={`Analizable con IA para ${documento.nombre || 'el documento'}`}
                                    title="Solo un documento de la entrega puede analizarse con IA"
                                    className="h-4 w-4 rounded border-[#d6d3d1] text-[#c2410c] focus:ring-[#c2410c] focus:ring-offset-0 disabled:opacity-40"
                                    disabled={disabled}
                                />
                                Analizable con IA
                            </label>
                        </div>

                        <button
                            type="button"
                            onClick={() => eliminar(index)}
                            disabled={disabled || documentos.length <= 1}
                            title={
                                documentos.length <= 1
                                    ? 'Debe quedar al menos un documento solicitado'
                                    : 'Eliminar documento'
                            }
                            className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[#a8a29e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626] disabled:opacity-30"
                            aria-label={`Eliminar ${documento.nombre || 'documento'}`}
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                ))}
            </div>

            {documentos.length < MAX_DOCUMENTOS && (
                <button
                    type="button"
                    onClick={agregar}
                    disabled={disabled}
                    className="inline-flex min-h-[36px] items-center justify-center gap-1.5 rounded-lg border-2 border-dashed border-[#d6d3d1] bg-transparent px-4 py-2 text-sm font-semibold text-[#57534e] transition-colors hover:border-[#c2410c] hover:text-[#c2410c] active:scale-[0.98] disabled:opacity-50"
                >
                    <Plus className="h-4 w-4" />
                    Agregar documento
                </button>
            )}

            {documentos.length >= MAX_DOCUMENTOS && (
                <p className="text-xs text-[#a8a29e]">
                    Máximo {MAX_DOCUMENTOS} documentos alcanzado.
                </p>
            )}

            {(iaError || error) && (
                <p className="text-xs text-[#dc2626]" role="alert">{iaError || error}</p>
            )}
        </div>
    );
}
