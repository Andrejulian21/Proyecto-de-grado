import { useCallback, useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { useEvaluadorAsignaciones } from '@/hooks/useEvaluadorAsignaciones';
import {
    ArrowLeft,
    Download,
    FileText,
    Send,
    Loader2,
    Star,
    AlertCircle,
    Users,
    UserCheck,
    Calendar,
    MessageSquareText,
} from 'lucide-react';
import type { DetalleAsignacionEvaluador } from '@/types/entregas';

function faseLabel(fase: string): string {
    const labels: Record<string, string> = {
        anteproyecto: 'Anteproyecto',
        presentacion_anteproyecto: 'Presentación Anteproyecto',
        desarrollo: 'Desarrollo del proyecto',
        presentacion_final: 'Presentación Final',
    };
    return labels[fase] ?? fase;
}

function formatFecha(fecha: string | null): string {
    if (!fecha) return '—';
    try {
        return new Date(fecha).toLocaleDateString('es-CO', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return fecha;
    }
}

function getDownloadUrl(filePath: string): string {
    return `/storage/${filePath}`;
}

function validarNota(nota: string): string | null {
    const valor = Number(nota);
    if (nota === '' || !Number.isFinite(valor)) return 'La nota es obligatoria.';
    if (valor < 0 || valor > 5) return 'La nota debe estar entre 0 y 5.';
    if (Math.round(valor * 100) / 100 !== valor) return 'La nota debe tener máximo 2 decimales.';
    return null;
}

export default function EvaluadorCalificar() {
    const { id } = useParams<{ id: string }>();
    const asignacionId = id ? Number(id) : NaN;
    const navigate = useNavigate();
    const { obtenerDetalle, enviarEvaluacion } = useEvaluadorAsignaciones();

    const [detalle, setDetalle] = useState<DetalleAsignacionEvaluador | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [nota, setNota] = useState('');
    const [observaciones, setObservaciones] = useState('');
    const [formError, setFormError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [enviada, setEnviada] = useState(false);

    const cargarDetalle = useCallback(async () => {
        if (!Number.isFinite(asignacionId)) {
            setError('Asignación no encontrada.');
            setLoading(false);
            return;
        }
        setLoading(true);
        setError(null);
        try {
            const data = await obtenerDetalle(asignacionId);
            setDetalle(data);
            if (data.evaluacion) {
                setNota(String(data.evaluacion.nota));
                setObservaciones(data.evaluacion.observaciones);
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Error al cargar la asignación');
        } finally {
            setLoading(false);
        }
    }, [asignacionId, obtenerDetalle]);

    useEffect(() => {
        cargarDetalle();
    }, [cargarDetalle]);

    const readOnly = detalle?.evaluacion != null;

    async function handleSubmit() {
        if (!Number.isFinite(asignacionId) || readOnly) return;

        const errorNota = validarNota(nota);
        if (errorNota) {
            setFormError(errorNota);
            return;
        }
        if (!observaciones.trim()) {
            setFormError('Las observaciones son obligatorias.');
            return;
        }

        setSubmitting(true);
        setFormError(null);
        try {
            await enviarEvaluacion(asignacionId, {
                nota: Number(nota),
                observaciones: observaciones.trim(),
            });
            setEnviada(true);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Error desconocido';
            // RF-EVA-03: a 409 means the evaluation was already sent.
            if (message.includes('ya fue enviada') || message.includes('no puede modificarse')) {
                setFormError('Ya evaluaste esta asignación.');
                await cargarDetalle();
            } else {
                setFormError(message);
            }
        } finally {
            setSubmitting(false);
        }
    }

    /* ── Loading ── */
    if (loading) {
        return (
            <div className="flex items-center justify-center py-20" role="status" aria-label="Cargando asignación">
                <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
            </div>
        );
    }

    /* ── Error (404 / 403 / red) ── */
    if (error || !detalle) {
        return (
            <div className="flex flex-col items-center gap-4 py-20">
                <AlertCircle className="h-10 w-10 text-[#dc2626]" aria-hidden="true" />
                <p className="text-sm text-[#dc2626]" role="alert">
                    {error ?? 'No se encontró la asignación.'}
                </p>
                <button
                    onClick={() => navigate('/evaluador/mis-asignaciones')}
                    className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                >
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Volver
                </button>
            </div>
        );
    }

    const { proyecto } = detalle;
    const entrega = detalle.entrega;

    /* ── Success screen ── */
    if (enviada) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Evaluación"
                    title="Evaluación enviada"
                    subtitle={`${proyecto?.codigo ?? ''} — ${proyecto?.titulo ?? 'Proyecto'}`}
                    actions={
                        <button
                            onClick={() => navigate('/evaluador/mis-asignaciones')}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                            Volver
                        </button>
                    }
                />
                <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-[#dcfce7] bg-[#dcfce7] py-16 text-center">
                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white">
                        <Star className="h-8 w-8 text-[#16a34a]" fill="#16a34a" aria-hidden="true" />
                    </div>
                    <div>
                        <h3 className="text-lg font-bold text-[#14532d]">Evaluación enviada</h3>
                        <p className="mt-1 text-sm text-[#14532d]">
                            Tu nota y observaciones han sido registradas. La evaluación no puede modificarse.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={() => {
                        setEnviada(false);
                        cargarDetalle();
                    }}
                    className="inline-flex w-full min-h-[44px] items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                >
                    <FileText className="h-4 w-4" aria-hidden="true" />
                    Ver evaluación
                </button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow={proyecto?.codigo ?? 'Asignación'}
                title={proyecto?.titulo ?? 'Proyecto asignado'}
                subtitle={`Fase a calificar: ${faseLabel(detalle.fase)}`}
                actions={
                    <button
                        onClick={() => navigate('/evaluador/mis-asignaciones')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        Volver
                    </button>
                }
            />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                {/* ── Left: project + delivery context ── */}
                <div className="flex flex-col gap-6 lg:col-span-3">
                    {/* Project info */}
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="flex items-center gap-3">
                                <Users className="h-5 w-5 shrink-0 text-[#78716c]" aria-hidden="true" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Estudiantes</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">
                                        {proyecto?.estudiantes.length
                                            ? proyecto.estudiantes.map((e) => e.name).join(', ')
                                            : '—'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <UserCheck className="h-5 w-5 shrink-0 text-[#78716c]" aria-hidden="true" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Director</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">
                                        {proyecto?.director?.name ?? '—'}
                                    </p>
                                </div>
                            </div>
                            {entrega?.due_date && (
                                <div className="flex items-center gap-3">
                                    <Calendar className="h-5 w-5 shrink-0 text-[#78716c]" aria-hidden="true" />
                                    <div>
                                        <p className="text-xs text-[#78716c]">Fecha límite de la entrega</p>
                                        <p className="text-sm font-semibold text-[#1c1917]">
                                            {formatFecha(entrega.due_date)}
                                        </p>
                                    </div>
                                </div>
                            )}
                            <div className="flex items-center gap-3">
                                <Star className="h-5 w-5 shrink-0 text-[#78716c]" aria-hidden="true" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Nota del director</p>
                                    <p className="text-sm font-semibold text-[#1c1917] tabular-nums">
                                        {entrega?.director_grade != null
                                            ? `${Number(entrega.director_grade).toFixed(2)} / 5.00`
                                            : 'Sin asignar'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Delivery files */}
                    {entrega == null ? (
                        <div className="flex flex-col items-center gap-3 rounded-xl border border-[#e5e5e5] bg-white py-12 text-center">
                            <FileText className="h-10 w-10 text-[#d6d3d1]" aria-hidden="true" />
                            <p className="text-sm text-[#57534e]">
                                No hay una entrega activa para esta fase en el semestre.
                            </p>
                        </div>
                    ) : (
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="mb-4 flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" aria-hidden="true" />
                                <h3 className="text-base font-bold text-[#1c1917]">Documentos de la entrega</h3>
                            </div>

                            {entrega.versiones_documento.length === 0 ? (
                                <p className="text-sm text-[#a8a29e] italic">
                                    El estudiante aún no ha subido documentos para esta entrega.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-2">
                                    {entrega.versiones_documento.map((v) => (
                                        <li
                                            key={v.version_number}
                                            className="flex flex-col gap-1 rounded-lg border border-[#e5e5e5] px-3 py-2 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-[#1c1917]">
                                                    v{v.version_number} — {v.original_name}
                                                </p>
                                                {v.director_notes && (
                                                    <p className="mt-0.5 flex items-start gap-1 text-xs text-[#57534e]">
                                                        <MessageSquareText
                                                            className="mt-0.5 h-3 w-3 shrink-0 text-[#78716c]"
                                                            aria-hidden="true"
                                                        />
                                                        {v.director_notes}
                                                    </p>
                                                )}
                                            </div>
                                            <a
                                                href={getDownloadUrl(v.file_path)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[#e5e5e5] px-2.5 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                                                aria-label={`Descargar versión ${v.version_number}`}
                                            >
                                                <Download className="h-3.5 w-3.5" aria-hidden="true" />
                                                Descargar
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                </div>

                {/* ── Right: grade panel ── */}
                <div className="lg:col-span-2">
                    <div className="sticky top-20 flex flex-col gap-4">
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-base font-bold text-[#1c1917]">Calificación</h3>
                                <StatusBadge variant={readOnly ? 'success' : 'warning'}>
                                    {readOnly ? 'Evaluada' : 'Pendiente'}
                                </StatusBadge>
                            </div>

                            {readOnly && detalle.evaluacion && (
                                <div className="mb-4 rounded-lg border border-[#dcfce7] bg-[#dcfce7] px-4 py-3 text-sm text-[#14532d]">
                                    <p className="font-bold tabular-nums">
                                        Tu nota: {Number(detalle.evaluacion.nota).toFixed(2)} / 5.00
                                    </p>
                                    {detalle.evaluacion.evaluated_at && (
                                        <p className="mt-0.5 text-xs">
                                            Enviada el {formatFecha(detalle.evaluacion.evaluated_at)}
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="flex flex-col gap-4">
                                <div className="flex flex-col gap-1.5">
                                    <label htmlFor="evaluador-nota" className="text-sm font-semibold text-[#1c1917]">
                                        Nota (0 – 5) <span className="text-[#dc2626]">*</span>
                                    </label>
                                    <input
                                        id="evaluador-nota"
                                        type="number"
                                        min={0}
                                        max={5}
                                        step={0.1}
                                        value={nota}
                                        onChange={(e) => setNota(e.target.value)}
                                        disabled={readOnly}
                                        placeholder="Ej: 4.5"
                                        className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] disabled:bg-[#f5f5f4] disabled:opacity-70 tabular-nums"
                                    />
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <label htmlFor="evaluador-observaciones" className="text-sm font-semibold text-[#1c1917]">
                                        Observaciones <span className="text-[#dc2626]">*</span>
                                    </label>
                                    <textarea
                                        id="evaluador-observaciones"
                                        rows={5}
                                        value={observaciones}
                                        onChange={(e) => setObservaciones(e.target.value)}
                                        disabled={readOnly}
                                        placeholder="Escribe tus observaciones sobre el documento (máx. 2000 caracteres)..."
                                        maxLength={2000}
                                        className="w-full min-h-[100px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] disabled:bg-[#f5f5f4] disabled:opacity-70 resize-y"
                                    />
                                </div>

                                {formError && (
                                    <div
                                        className="flex items-center gap-2 rounded-lg border border-[#fee2e2] bg-[#fee2e2] px-4 py-2 text-sm text-[#dc2626]"
                                        role="alert"
                                    >
                                        <AlertCircle className="h-4 w-4 shrink-0" aria-hidden="true" />
                                        {formError}
                                    </div>
                                )}

                                {!readOnly && (
                                    <button
                                        type="button"
                                        onClick={handleSubmit}
                                        disabled={submitting}
                                        className="inline-flex w-full min-h-[44px] items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {submitting ? (
                                            <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                                        ) : (
                                            <Send className="h-4 w-4" aria-hidden="true" />
                                        )}
                                        Enviar Evaluación
                                    </button>
                                )}

                                {readOnly && (
                                    <p className="text-xs text-[#57534e]">
                                        Esta evaluación ya fue enviada y no puede modificarse (RF-EVA-03).
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
