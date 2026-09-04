import { useState, useCallback, useEffect } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { apiFetch } from '@/lib/utils';
import {
    ArrowLeft,
    ClipboardCheck,
    Users,
    Calendar,
    Clock,
    UserCheck,
    FileText,
    Loader2,
    AlertCircle,
    Plus,
    Trash2,
    Send,
    BookOpen,
} from 'lucide-react';

/* ── Types ── */

interface Estudiante {
    id: number;
    name: string;
}

interface CoEvaluador {
    id: number;
    name: string;
    email: string;
}

interface EvaluacionAsignada {
    id: number;
    code: string;
    title: string;
    current_phase: string | null;
    status: string | null;
    fase_asignada: string | null;
    fecha: string | null;
    hora_inicio: string | null;
    hora_fin: string | null;
    estudiantes: Estudiante[];
    co_evaluadores: CoEvaluador[];
    semestre: { id: number; name: string; is_active: boolean } | null;
}

interface EntregaInfo {
    id: number;
    title: string;
    phase: string;
    status: string;
    proyecto: { id: number; code: string; title: string } | null;
    versiones: Array<{
        id: number;
        version_number: number;
        original_name: string;
        file_path: string;
        uploaded_at: string;
    }>;
}

interface CriterioForm {
    id: string;
    criterio: string;
    percentage: number;
    grade: string;
}

/* ── Helpers ── */

function faseLabel(fase: string | null): string {
    const labels: Record<string, string> = {
        anteproyecto: 'Anteproyecto',
        presentacion_anteproyecto: 'Presentación Anteproyecto',
        desarrollo: 'Desarrollo del proyecto',
        presentacion_final: 'Presentación Final',
        // Pivot table stores capitalized values
        Anteproyecto: 'Anteproyecto',
        Final: 'Presentación Final',
    };
    return labels[fase ?? ''] ?? fase ?? '—';
}

/* ── Component ── */

export default function EvaluacionesDirector() {
    const [proyectos, setProyectos] = useState<EvaluacionAsignada[]>([]);
    const [loadingLista, setLoadingLista] = useState(true);
    const [errorLista, setErrorLista] = useState<string | null>(null);

    const [proyectoSeleccionado, setProyectoSeleccionado] = useState<EvaluacionAsignada | null>(null);
    const [entrega, setEntrega] = useState<EntregaInfo | null>(null);
    const [loadingEntrega, setLoadingEntrega] = useState(false);
    const [errorEntrega, setErrorEntrega] = useState<string | null>(null);

    const [criterios, setCriterios] = useState<CriterioForm[]>([]);
    const [submitting, setSubmitting] = useState(false);
    const [submitSuccess, setSubmitSuccess] = useState(false);
    const [submitErrors, setSubmitErrors] = useState<Record<string, string>>({});

    // Fetch proyectos
    useEffect(() => {
        let cancelled = false;
        setLoadingLista(true);

        apiFetch('/api/director/evaluaciones')
            .then(async (res) => {
                if (!res.ok) {
                    const body = await res.json().catch(() => null);
                    throw new Error(body?.message ?? `Error ${res.status}`);
                }
                return res.json();
            })
            .then((json) => {
                if (!cancelled) {
                    setProyectos(json.data ?? json);
                    setLoadingLista(false);
                }
            })
            .catch((err) => {
                if (!cancelled) {
                    setErrorLista(err instanceof Error ? err.message : 'Error desconocido');
                    setLoadingLista(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, []);

    // Load entrega for selected project
    const cargarEntrega = useCallback(async (proyecto: EvaluacionAsignada) => {
        const fase = proyecto.fase_asignada || proyecto.current_phase || 'anteproyecto';
        setProyectoSeleccionado(proyecto);
        setEntrega(null);
        setCriterios([]);
        setSubmitSuccess(false);
        setSubmitErrors({});
        setLoadingEntrega(true);
        setErrorEntrega(null);

        try {
            const res = await apiFetch(
                `/api/director/proyectos/${proyecto.id}/entrega-fase?fase=${encodeURIComponent(fase)}`
            );

            if (!res.ok) {
                if (res.status === 404) {
                    setErrorEntrega('No hay una entrega aprobada para esta fase.');
                    setLoadingEntrega(false);
                    return;
                }
                const body = await res.json().catch(() => null);
                throw new Error(body?.error ?? `Error ${res.status}`);
            }

            const json = await res.json();
            setEntrega(json.data ?? json);

            // Initialize with one empty criterion
            setCriterios([
                { id: crypto.randomUUID(), criterio: '', percentage: 100, grade: '' },
            ]);
        } catch (err) {
            setErrorEntrega(err instanceof Error ? err.message : 'Error al cargar la entrega');
        } finally {
            setLoadingEntrega(false);
        }
    }, []);

    const volverALista = useCallback(() => {
        setProyectoSeleccionado(null);
        setEntrega(null);
        setCriterios([]);
        setSubmitSuccess(false);
        setSubmitErrors({});
    }, []);

    // Criterion management
    const agregarCriterio = useCallback(() => {
        setCriterios((prev) => [
            ...prev,
            { id: crypto.randomUUID(), criterio: '', percentage: 0, grade: '' },
        ]);
    }, []);

    const eliminarCriterio = useCallback((id: string) => {
        setCriterios((prev) => prev.filter((c) => c.id !== id));
    }, []);

    const actualizarCriterio = useCallback(
        (id: string, field: keyof CriterioForm, value: string | number) => {
            setCriterios((prev) =>
                prev.map((c) => (c.id === id ? { ...c, [field]: value } : c))
            );
        },
        []
    );

    const sumaPorcentajes = criterios.reduce((sum, c) => sum + (c.percentage || 0), 0);

    function validarCriterios(): string | null {
        for (const c of criterios) {
            if (!c.criterio.trim()) return 'Todos los criterios deben tener un nombre.';
            if (c.percentage <= 0 || c.percentage > 100) return 'El porcentaje debe estar entre 1 y 100.';
            if (c.grade !== '' && (Number(c.grade) < 0 || Number(c.grade) > 5)) {
                return 'La nota debe estar entre 0.0 y 5.0.';
            }
        }
        if (sumaPorcentajes > 100) return 'La suma de porcentajes no puede exceder 100%.';
        return null;
    }

    async function handleSubmit() {
        const error = validarCriterios();
        if (error) {
            setSubmitErrors({ general: error });
            return;
        }

        if (!entrega) return;

        setSubmitting(true);
        setSubmitErrors({});
        const errores: Record<string, string> = {};

        for (const c of criterios) {
            if (!c.criterio.trim() || c.grade === '' || c.grade === undefined) continue;

            try {
                const res = await apiFetch('/api/evaluaciones', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        entrega_id: entrega.id,
                        criterio: c.criterio.trim(),
                        percentage: c.percentage,
                        grade: c.grade !== '' ? Number(c.grade) : null,
                    }),
                });

                const json = await res.json().catch(() => null);

                if (!res.ok) {
                    const msg =
                        json?.errors?.percentage?.[0] ??
                        json?.errors?.grade?.[0] ??
                        json?.errors?.criterio?.[0] ??
                        json?.error ??
                        `Error ${res.status}`;
                    errores[c.id] = msg;
                }
            } catch (err) {
                errores[c.id] = err instanceof Error ? err.message : 'Error de red';
            }
        }

        setSubmitting(false);

        if (Object.keys(errores).length > 0) {
            setSubmitErrors(errores);
        } else {
            setSubmitSuccess(true);
        }
    }

    // ── Loading state ──
    if (loadingLista) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Evaluaciones" title="Evaluaciones" subtitle="Proyectos donde eres evaluador" />
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {[1, 2, 3].map((i) => (
                        <div key={i} className="animate-pulse rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="mb-3 h-5 w-3/4 rounded bg-[#e5e5e5]" />
                            <div className="mb-2 h-4 w-1/2 rounded bg-[#e5e5e5]" />
                            <div className="h-4 w-2/3 rounded bg-[#e5e5e5]" />
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    // ── Error state ──
    if (errorLista) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Evaluaciones" title="Evaluaciones" subtitle="Proyectos donde eres evaluador" />
                <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-[#fee2e2] bg-[#fee2e2] py-16 text-center">
                    <AlertCircle className="h-12 w-12 text-[#dc2626]" />
                    <div>
                        <h3 className="text-lg font-bold text-[#7f1d1d]">Error al cargar</h3>
                        <p className="mt-1 text-sm text-[#7f1d1d]">{errorLista}</p>
                    </div>
                    <button
                        onClick={() => window.location.reload()}
                        className="inline-flex items-center gap-2 rounded-lg bg-[#dc2626] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#b91c1c] active:scale-[0.98]"
                    >
                        Reintentar
                    </button>
                </div>
            </div>
        );
    }

    // ── Empty state ──
    if (proyectos.length === 0) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Evaluaciones" title="Evaluaciones" subtitle="Proyectos donde eres evaluador" />
                <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-[#e5e5e5] bg-white py-16 text-center">
                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4]">
                        <ClipboardCheck className="h-8 w-8 text-[#78716c]" />
                    </div>
                    <div>
                        <h3 className="text-lg font-bold text-[#1c1917]">Sin evaluaciones asignadas</h3>
                        <p className="mt-1 text-sm text-[#57534e]">
                            No tienes proyectos asignados como evaluador en este momento.
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    // ── Success state (after submitting) ──
    if (submitSuccess) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow="Evaluaciones"
                    title="Evaluación Enviada"
                    subtitle="Tus calificaciones han sido registradas exitosamente"
                    actions={
                        <button
                            onClick={volverALista}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver a evaluaciones
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
                        <p className="mt-1 text-sm text-[#14532d]">
                            Las calificaciones para <strong>{proyectoSeleccionado?.title}</strong> han sido enviadas.
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    // ── Detail view (project selected) ──
    if (proyectoSeleccionado) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader
                    eyebrow={proyectoSeleccionado.code}
                    title={proyectoSeleccionado.title}
                    subtitle={
                        proyectoSeleccionado.fase_asignada
                            ? `Fase: ${faseLabel(proyectoSeleccionado.fase_asignada)}`
                            : undefined
                    }
                    actions={
                        <button
                            onClick={volverALista}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Volver
                        </button>
                    }
                />

                {/* Project info */}
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-[#f5f5f4]">
                                <Users className="h-5 w-5 text-[#78716c]" />
                            </div>
                            <div>
                                <p className="text-xs text-[#78716c]">Estudiantes</p>
                                <p className="text-sm font-semibold text-[#1c1917]">
                                    {proyectoSeleccionado.estudiantes.map((e) => e.name).join(', ') || '—'}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-[#f5f5f4]">
                                <UserCheck className="h-5 w-5 text-[#78716c]" />
                            </div>
                            <div>
                                <p className="text-xs text-[#78716c]">Co-evaluadores</p>
                                <p className="text-sm font-semibold text-[#1c1917]">
                                    {proyectoSeleccionado.co_evaluadores.map((e) => e.name).join(', ') || '—'}
                                </p>
                            </div>
                        </div>
                        {proyectoSeleccionado.fecha && (
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-[#f5f5f4]">
                                    <Calendar className="h-5 w-5 text-[#78716c]" />
                                </div>
                                <div>
                                    <p className="text-xs text-[#78716c]">Fecha asignada</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">
                                        {proyectoSeleccionado.fecha}
                                    </p>
                                </div>
                            </div>
                        )}
                        {proyectoSeleccionado.hora_inicio && (
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-[#f5f5f4]">
                                    <Clock className="h-5 w-5 text-[#78716c]" />
                                </div>
                                <div>
                                    <p className="text-xs text-[#78716c]">Horario</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">
                                        {proyectoSeleccionado.hora_inicio}
                                        {proyectoSeleccionado.hora_fin ? ` - ${proyectoSeleccionado.hora_fin}` : ''}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Approved delivery */}
                {loadingEntrega && (
                    <div className="flex items-center justify-center rounded-xl border border-[#e5e5e5] bg-white py-12">
                        <div className="flex flex-col items-center gap-3">
                            <Loader2 className="h-6 w-6 animate-spin text-[#c2410c]" />
                            <p className="text-sm text-[#57534e]">Cargando entrega aprobada...</p>
                        </div>
                    </div>
                )}

                {errorEntrega && !loadingEntrega && (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-[#fee2e2] bg-[#fee2e2] py-8 text-center">
                        <AlertCircle className="h-8 w-8 text-[#dc2626]" />
                        <p className="text-sm font-medium text-[#7f1d1d]">{errorEntrega}</p>
                    </div>
                )}

                {entrega && !loadingEntrega && (
                    <>
                        {/* Delivery info */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="mb-4 flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">{entrega.title}</h3>
                                <StatusBadge variant="success">Aprobada</StatusBadge>
                            </div>

                            {entrega.versiones && entrega.versiones.length > 0 && (
                                <div className="space-y-2">
                                    <p className="text-xs font-semibold text-[#78716c] uppercase tracking-wider">
                                        Versiones del documento
                                    </p>
                                    {entrega.versiones.map((v) => (
                                        <div
                                            key={v.id}
                                            className="flex items-center justify-between rounded-lg border border-[#e5e5e5] px-3 py-2"
                                        >
                                            <div className="flex items-center gap-2">
                                                <BookOpen className="h-4 w-4 text-[#78716c]" />
                                                <span className="text-sm font-medium text-[#1c1917]">
                                                    v{v.version_number}
                                                </span>
                                                <span className="text-xs text-[#78716c]">{v.original_name}</span>
                                            </div>
                                            {v.file_path && (
                                                <a
                                                    href={`/storage/${v.file_path}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1 rounded-lg border border-[#e5e5e5] px-2.5 py-1 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                                                    aria-label={`Descargar versión ${v.version_number}`}
                                                >
                                                    Descargar
                                                </a>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Grading form */}
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <div className="mb-5 flex items-center justify-between">
                                <h3 className="text-base font-bold text-[#1c1917]">Calificación</h3>
                                <button
                                    onClick={agregarCriterio}
                                    className="inline-flex items-center gap-1.5 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                                >
                                    <Plus className="h-3.5 w-3.5" />
                                    Agregar criterio
                                </button>
                            </div>

                            {submitErrors.general && (
                                <div className="mb-4 flex items-center gap-2 rounded-lg border border-[#fee2e2] bg-[#fee2e2] px-4 py-2 text-sm text-[#7f1d1d]">
                                    <AlertCircle className="h-4 w-4 shrink-0" />
                                    {submitErrors.general}
                                </div>
                            )}

                            <div className="space-y-4">
                                {criterios.map((c, index) => (
                                    <div
                                        key={c.id}
                                        className="rounded-lg border border-[#e5e5e5] p-4 transition-colors hover:border-[#d6d3d1]"
                                    >
                                        <div className="mb-3 flex items-center justify-between">
                                            <span className="text-xs font-semibold text-[#78716c] uppercase tracking-wider">
                                                Criterio {index + 1}
                                            </span>
                                            {criterios.length > 1 && (
                                                <button
                                                    onClick={() => eliminarCriterio(c.id)}
                                                    className="text-[#78716c] transition-colors hover:text-[#dc2626]"
                                                    aria-label={`Eliminar criterio ${index + 1}`}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>

                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-[#57534e]">
                                                    Nombre del criterio
                                                </label>
                                                <input
                                                    type="text"
                                                    value={c.criterio}
                                                    onChange={(e) =>
                                                        actualizarCriterio(c.id, 'criterio', e.target.value)
                                                    }
                                                    placeholder="Ej: Estructura y formato"
                                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-[#57534e]">
                                                    Porcentaje %
                                                </label>
                                                <input
                                                    type="number"
                                                    min={0}
                                                    max={100}
                                                    step={1}
                                                    value={c.percentage}
                                                    onChange={(e) =>
                                                        actualizarCriterio(c.id, 'percentage', Number(e.target.value))
                                                    }
                                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] tabular-nums"
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-[#57534e]">
                                                    Nota (0.0 – 5.0)
                                                </label>
                                                <input
                                                    type="number"
                                                    min={0}
                                                    max={5}
                                                    step={0.1}
                                                    value={c.grade}
                                                    onChange={(e) =>
                                                        actualizarCriterio(c.id, 'grade', e.target.value)
                                                    }
                                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] tabular-nums"
                                                />
                                            </div>
                                        </div>

                                        {submitErrors[c.id] && (
                                            <p className="mt-2 text-xs text-[#dc2626]">{submitErrors[c.id]}</p>
                                        )}
                                    </div>
                                ))}
                            </div>

                            {/* Summary */}
                            <div className="mt-4 flex items-center justify-between border-t border-[#e5e5e5] pt-4">
                                <div className="flex items-center gap-4">
                                    <span className="text-sm font-semibold text-[#57534e]">
                                        Suma %:{' '}
                                        <span
                                            className={`tabular-nums ${
                                                sumaPorcentajes > 100 ? 'text-[#dc2626] font-bold' : 'text-[#1c1917]'
                                            }`}
                                        >
                                            {sumaPorcentajes}%
                                        </span>
                                    </span>
                                    {sumaPorcentajes > 100 && (
                                        <span className="flex items-center gap-1 text-xs text-[#dc2626]">
                                            <AlertCircle className="h-3 w-3" />
                                            Excede el 100%
                                        </span>
                                    )}
                                </div>
                                <button
                                    onClick={handleSubmit}
                                    disabled={submitting || criterios.length === 0}
                                    className="inline-flex items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
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
                    </>
                )}
            </div>
        );
    }

    // ── List view (default) ──
    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Evaluaciones"
                title="Evaluaciones"
                subtitle={`${proyectos.length} proyecto${proyectos.length !== 1 ? 's' : ''} asignado${proyectos.length !== 1 ? 's' : ''}`}
            />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                {proyectos.map((p) => (
                    <button
                        key={p.id}
                        onClick={() => cargarEntrega(p)}
                        className="group rounded-xl border border-[#e5e5e5] bg-white p-5 text-left shadow-[0_1px_2px_rgba(28,25,23,0.05)] transition-all hover:border-[#c2410c] hover:shadow-[0_4px_12px_rgba(194,65,12,0.1)] active:scale-[0.98]"
                    >
                        <div className="mb-3 flex items-start justify-between gap-3">
                            <div className="min-w-0 flex-1">
                                <p className="text-xs font-semibold text-[#c2410c]">{p.code}</p>
                                <h3 className="mt-0.5 text-base font-bold text-[#1c1917] line-clamp-2">
                                    {p.title}
                                </h3>
                            </div>
                            {p.fase_asignada && (
                                <StatusBadge variant="info">{faseLabel(p.fase_asignada)}</StatusBadge>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <div className="flex items-center gap-2 text-xs text-[#78716c]">
                                <Users className="h-3.5 w-3.5 shrink-0" />
                                <span className="truncate">
                                    {p.estudiantes.map((e) => e.name).join(', ') || 'Sin estudiantes'}
                                </span>
                            </div>
                            {p.co_evaluadores.length > 0 && (
                                <div className="flex items-center gap-2 text-xs text-[#78716c]">
                                    <UserCheck className="h-3.5 w-3.5 shrink-0" />
                                    <span className="truncate">
                                        {p.co_evaluadores.map((e) => e.name).join(', ')}
                                    </span>
                                </div>
                            )}
                            {p.fecha && (
                                <div className="flex items-center gap-2 text-xs text-[#78716c]">
                                    <Calendar className="h-3.5 w-3.5 shrink-0" />
                                    <span>{p.fecha}</span>
                                    {p.hora_inicio && (
                                        <>
                                            <Clock className="h-3.5 w-3.5 shrink-0 ml-1" />
                                            <span>
                                                {p.hora_inicio}
                                                {p.hora_fin ? ` - ${p.hora_fin}` : ''}
                                            </span>
                                        </>
                                    )}
                                </div>
                            )}
                        </div>

                        <div className="mt-3 flex items-center gap-1.5 text-xs font-semibold text-[#c2410c] opacity-0 transition-opacity group-hover:opacity-100">
                            <ClipboardCheck className="h-3.5 w-3.5" />
                            Calificar proyecto
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}
