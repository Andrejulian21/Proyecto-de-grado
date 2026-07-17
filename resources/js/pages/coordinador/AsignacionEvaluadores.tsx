import { useState, useMemo, useEffect, useCallback } from 'react';
import { UserPlus, Users, CalendarDays, Search, Loader2, Pencil, Trash2, X, AlertTriangle, GraduationCap, UserCheck } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { CalendarGrid, type CalendarAssignment } from '@/components/calendar/CalendarGrid';
import { ResultsTable } from '@/components/tables/ResultsTable';
import { useEvaluadorProyecto, useEvaluadorUsers, type EvaluadorProyecto, type CreateEvaluadorPayload, type UpdateEvaluadorPayload } from '@/hooks/useEvaluadorProyecto';
import { useEvaluaciones } from '@/hooks/useEvaluaciones';
import { useProyectos } from '@/hooks/useProyectos';
import { cn } from '@/lib/utils';

/* ── Edit Modal ── */

function EditModal({
    open,
    assignment,
    onSave,
    onClose,
    saving,
    existingAssignments,
}: {
    open: boolean;
    assignment: EvaluadorProyecto | null;
    onSave: (id: number, payload: UpdateEvaluadorPayload) => void;
    onClose: () => void;
    saving: boolean;
    existingAssignments: EvaluadorProyecto[];
}) {
    const [fase, setFase] = useState<'Anteproyecto' | 'Final'>('Anteproyecto');
    const [fecha, setFecha] = useState('');
    const [horaInicio, setHoraInicio] = useState('');
    const [horaFin, setHoraFin] = useState('');
    const [editError, setEditError] = useState<string | null>(null);

    useEffect(() => {
        if (assignment) {
            setFase(assignment.fase);
            // Normalize date for input (assignment.fecha comes as "YYYY-MM-DD")
            setFecha(assignment.fecha || '');
            setHoraInicio(assignment.hora_inicio || '');
            setHoraFin(assignment.hora_fin || '');
            setEditError(null);
        }
    }, [assignment]);

    if (!open || !assignment) return null;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setEditError(null);

        if (horaFin && horaInicio && horaFin <= horaInicio) {
            setEditError('La hora fin debe ser posterior a la hora inicio.');
            return;
        }

        // Validate no time conflict: overlap — nuevoInicio < existenteFin && nuevoFin > existenteInicio (exclude current assignment)
        const hasOverlap = existingAssignments.some(
            (a) => a.id !== assignment.id
                && a.fecha === fecha
                && horaInicio < a.hora_fin
                && horaFin > a.hora_inicio,
        );
        if (hasOverlap) {
            setEditError('Ya existe una asignación programada que se superpone con este horario. Por favor, seleccione un horario diferente.');
            return;
        }

        onSave(assignment.id, {
            fase,
            fecha,
            hora_inicio: horaInicio,
            hora_fin: horaFin,
        });
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
            role="dialog"
            aria-modal="true"
            aria-label="Editar asignación"
        >
            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-[0_20px_60px_rgba(28,25,23,0.15)]">
                <div className="mb-5 flex items-center justify-between">
                    <h2 className="text-lg font-bold text-[#1c1917]">Editar Asignación</h2>
                    <button
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-[#57534e] transition-colors hover:bg-[#f5f5f4]"
                        aria-label="Cerrar"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    {/* Project (read-only) */}
                    <div className="flex flex-col gap-1.5">
                        <label className="text-sm font-semibold text-[#1c1917]">Proyecto</label>
                        <p className="rounded-lg border border-[#e5e5e5] bg-[#f5f5f4] px-3 py-2 text-sm text-[#57534e]">
                            {assignment.proyecto_codigo} — {assignment.proyecto_nombre}
                        </p>
                    </div>

                    {/* Evaluators (read-only) */}
                    <div className="flex flex-col gap-1.5">
                        <label className="text-sm font-semibold text-[#1c1917]">Evaluadores</label>
                        <p className="rounded-lg border border-[#e5e5e5] bg-[#f5f5f4] px-3 py-2 text-sm text-[#57534e]">
                            {assignment.evaluadores_list.map((e) => e.name).join(', ')}
                        </p>
                    </div>

                    {/* Fase */}
                    <div className="flex flex-col gap-1.5">
                        <label htmlFor="edit-fase" className="text-sm font-semibold text-[#1c1917]">Fase</label>
                        <select
                            id="edit-fase"
                            value={fase}
                            onChange={(e) => setFase(e.target.value as 'Anteproyecto' | 'Final')}
                            className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                        >
                            <option value="Anteproyecto">Presentación Anteproyecto</option>
                            <option value="Final">Presentación Final</option>
                        </select>
                    </div>

                    {/* Fecha */}
                    <div className="flex flex-col gap-1.5">
                        <label htmlFor="edit-fecha" className="text-sm font-semibold text-[#1c1917]">Fecha</label>
                        <input
                            id="edit-fecha"
                            type="date"
                            value={fecha}
                            onChange={(e) => setFecha(e.target.value)}
                            className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            required
                        />
                    </div>

                    {/* Hora inicio / Hora fin */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="edit-hora-inicio" className="text-sm font-semibold text-[#1c1917]">Hora Inicio</label>
                            <input
                                id="edit-hora-inicio"
                                type="time"
                                value={horaInicio}
                                onChange={(e) => { setHoraInicio(e.target.value); setEditError(null); }}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="edit-hora-fin" className="text-sm font-semibold text-[#1c1917]">Hora Fin</label>
                            <input
                                id="edit-hora-fin"
                                type="time"
                                value={horaFin}
                                onChange={(e) => { setHoraFin(e.target.value); setEditError(null); }}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            />
                        </div>
                    </div>

                    {/* Validation error */}
                    {editError && (
                        <div className="flex items-start gap-2 rounded-lg border border-[#fee2e2] bg-[#fef2f2] p-3 text-sm text-[#dc2626]">
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                            {editError}
                        </div>
                    )}

                    <div className="mt-2 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2.5 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={saving}
                            className="inline-flex items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

/* ── Page Component ── */

export default function AsignacionEvaluadores() {
    const {
        data: asignaciones,
        loading,
        error,
        mutationLoading,
        mutationError,
        crear,
        actualizar,
        eliminar,
    } = useEvaluadorProyecto();

    const {
        data: resultados,
        loading: loadingResultados,
    } = useEvaluaciones();

    const {
        data: evaluadores,
        loading: loadingEvalUsers,
    } = useEvaluadorUsers();

    /* ── Proyectos de semestres activos ── */
    const {
        data: proyectos,
        loading: loadingProyectos,
    } = useProyectos(null, {
        semestre_activo: true,
    });

    /* ── Local state ── */
    const [showRegisterForm, setShowRegisterForm] = useState(false);
    const [search, setSearch] = useState('');

    // Edit modal state
    const [editTarget, setEditTarget] = useState<EvaluadorProyecto | null>(null);

    // Delete confirmation state
    const [deleteTarget, setDeleteTarget] = useState<EvaluadorProyecto | null>(null);

    // Registration form
    const [formProyectoId, setFormProyectoId] = useState('');
    const [formFase, setFormFase] = useState<'Anteproyecto' | 'Final'>('Anteproyecto');
    const [formEvalIds, setFormEvalIds] = useState<number[]>([]);
    const [formFecha, setFormFecha] = useState('');
    const [formHoraInicio, setFormHoraInicio] = useState('');
    const [formHoraFin, setFormHoraFin] = useState('');
    const [formError, setFormError] = useState<string | null>(null);

    /* ── Derive selected project director ── */
    const selectedProyecto = useMemo(() => {
        if (!formProyectoId) return null;
        return proyectos.find((p) => p.id === Number(formProyectoId)) ?? null;
    }, [formProyectoId, proyectos]);

    /* ── Filters ── */
    const filtered = useMemo(() => {
        if (!search.trim()) return asignaciones;
        const q = search.toLowerCase();
        return asignaciones.filter((a) =>
            a.proyecto_codigo.toLowerCase().includes(q) ||
            a.proyecto_nombre.toLowerCase().includes(q) ||
            a.estudiantes.some((e) => e.name.toLowerCase().includes(q)) ||
            a.evaluadores_list.some((ev) => ev.name.toLowerCase().includes(q)),
        );
    }, [asignaciones, search]);

    /* ── Calendar data ── */
    const calendarAssignments: CalendarAssignment[] = useMemo(() =>
        asignaciones.map((a) => ({
            date: a.fecha,
            label: `${a.proyecto_codigo} ${a.hora_inicio}${a.hora_fin ? '-' + a.hora_fin : ''}`,
        })),
    [asignaciones]);

    /* ── Stats ── */
    const scheduledCount = asignaciones.filter((a) => a.fecha && new Date(a.fecha + 'T12:00:00') >= new Date()).length;
    const completedCount = resultados.length;

    /* ── Formatters ── */
    function formatEvaluadores(asig: EvaluadorProyecto): string {
        return asig.evaluadores_list.map((e) => e.name).join(', ');
    }

    function formatDate(dateStr: string): string {
        if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
            const [y, m, d] = dateStr.split('-');
            return `${d}/${m}/${y}`;
        }
        return dateStr;
    }

    function formatHoraRange(asig: EvaluadorProyecto): string {
        if (asig.hora_inicio && asig.hora_fin) {
            return `${asig.hora_inicio} - ${asig.hora_fin}`;
        }
        return asig.hora_inicio || asig.hora || '—';
    }

    /* ── Handlers ── */
    const handleEvaluadorToggle = useCallback((evaluadorId: number) => {
        setFormError(null);
        setFormEvalIds((prev) => {
            if (prev.includes(evaluadorId)) {
                return prev.filter((id) => id !== evaluadorId);
            }
            if (prev.length >= 3) return prev;
            return [...prev, evaluadorId];
        });
    }, []);

    const handleRegister = useCallback(async (e: React.FormEvent) => {
        e.preventDefault();
        setFormError(null);

        if (!formProyectoId || formEvalIds.length < 2 || !formFecha || !formHoraInicio || !formHoraFin) {
            setFormError('Complete todos los campos obligatorios (proyecto, mínimo 2 evaluadores, fecha, hora inicio y hora fin).');
            return;
        }

        if (formHoraFin <= formHoraInicio) {
            setFormError('La hora fin debe ser posterior a la hora inicio.');
            return;
        }

        // Validate director no evalúa su propio proyecto
        if (selectedProyecto?.director) {
            const directorId = selectedProyecto.director.id;
            if (formEvalIds.includes(directorId)) {
                setFormError('Un director no puede evaluar su propio proyecto.');
                return;
            }
        }

        // Validate no time conflict: overlap — nuevoInicio < existenteFin && nuevoFin > existenteInicio
        const hasOverlap = asignaciones.some(
            (a) => a.fecha === formFecha
                && formHoraInicio < a.hora_fin
                && formHoraFin > a.hora_inicio,
        );
        if (hasOverlap) {
            setFormError('Ya existe una asignación programada que se superpone con este horario. Por favor, seleccione un horario diferente.');
            return;
        }

        const payload: CreateEvaluadorPayload = {
            proyecto_id: Number(formProyectoId),
            evaluador_ids: formEvalIds,
            fecha: formFecha,
            hora_inicio: formHoraInicio,
            hora_fin: formHoraFin,
            fase: formFase,
        };

        try {
            await crear(payload);
            setFormProyectoId('');
            setFormFase('Anteproyecto');
            setFormEvalIds([]);
            setFormFecha('');
            setFormHoraInicio('');
            setFormHoraFin('');
            setShowRegisterForm(false);
        } catch {
            // error is handled by mutationError in the hook
        }
    }, [formProyectoId, formEvalIds, formFecha, formHoraInicio, formHoraFin, formFase, selectedProyecto, crear]);

    const handleEdit = useCallback(async (id: number, payload: UpdateEvaluadorPayload) => {
        try {
            await actualizar(id, payload);
            setEditTarget(null);
        } catch {
            // handled by hook
        }
    }, [actualizar]);

    const handleDelete = useCallback(async () => {
        if (!deleteTarget) return;
        try {
            await eliminar(deleteTarget.id);
            setDeleteTarget(null);
        } catch {
            // handled by hook
        }
    }, [deleteTarget, eliminar]);

    /* ── Table columns ── */
    const columns: Column<EvaluadorProyecto>[] = useMemo(() => [
        {
            key: 'proyecto_codigo',
            label: 'ID',
            render: (row) => (
                <span className="font-medium text-[#1c1917]">{row.proyecto_codigo}</span>
            ),
        },
        {
            key: 'proyecto_nombre',
            label: 'Proyecto',
            render: (row) => (
                <span className="text-[#57534e]">{row.proyecto_nombre}</span>
            ),
        },
        {
            key: 'estudiantes',
            label: 'Estudiantes',
            render: (row) => (
                <span className="text-[#57534e]">
                    {row.estudiantes.map((e) => e.name).join(', ')}
                </span>
            ),
        },
        {
            key: 'fase',
            label: 'Fase',
            render: (row) => (
                <StatusBadge variant={row.fase === 'Final' ? 'info' : 'en-curso'}>
                    {row.fase === 'Final' ? 'Presentación Final' : 'Presentación Anteproyecto'}
                </StatusBadge>
            ),
        },
        {
            key: 'evaluadores',
            label: 'Evaluadores',
            render: (row) => (
                <span className="text-[#57534e]">{formatEvaluadores(row)}</span>
            ),
        },
        {
            key: 'fecha',
            label: 'Fecha',
            render: (row) => (
                <span className="text-[#78716c] tabular-nums">{formatDate(row.fecha)}</span>
            ),
        },
        {
            key: 'horario',
            label: 'Horario',
            render: (row) => (
                <span className="text-[#78716c]">{formatHoraRange(row)}</span>
            ),
        },
        {
            key: 'acciones',
            label: 'Acciones',
            className: 'text-right',
            render: (row) => (
                <div className="flex items-center justify-end gap-1">
                    <button
                        onClick={() => setEditTarget(row)}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#e0e7ff] hover:text-[#4f46e5]"
                        aria-label={`Editar asignación ${row.proyecto_codigo}`}
                        title="Editar"
                    >
                        <Pencil className="h-4 w-4" />
                    </button>
                    <button
                        onClick={() => setDeleteTarget(row)}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626]"
                        aria-label={`Eliminar asignación ${row.proyecto_codigo}`}
                        title="Eliminar"
                    >
                        <Trash2 className="h-4 w-4" />
                    </button>
                </div>
            ),
        },
    ], []);

    /* ── Render ── */
    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Evaluación"
                title="Asignación de Evaluadores"
                subtitle="Registre y gestione la asignación de evaluadores externos a proyectos"
                actions={
                    <button
                        onClick={() => setShowRegisterForm(!showRegisterForm)}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                    >
                        <UserPlus className="h-4 w-4" />
                        Registrar Asignación
                    </button>
                }
            />

            {/* Error Banner */}
            {(error || mutationError) && (
                <div className="rounded-lg border border-[#fee2e2] bg-[#fef2f2] p-4 text-sm text-[#dc2626]">
                    {error || mutationError}
                </div>
            )}

            {/* Stats */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard icon={Users} label="Total asignaciones" value={asignaciones.length} />
                <StatCard icon={CalendarDays} label="Agendadas" value={scheduledCount} variant="warning" />
                <StatCard icon={Users} label="Evaluaciones completadas" value={completedCount} variant="success" />
            </div>

            {/* Registration Form */}
            {showRegisterForm && (
                <form onSubmit={handleRegister} className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <h3 className="mb-4 text-base font-bold text-[#1c1917]">Registrar Asignación</h3>

                    {formError && (
                        <div className="mb-4 flex items-start gap-2 rounded-lg border border-[#fee2e2] bg-[#fef2f2] p-3 text-sm text-[#dc2626]">
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                            {formError}
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {/* Project selector */}
                        <div className="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-1">
                            <label htmlFor="reg-proyecto" className="text-sm font-semibold text-[#1c1917]">
                                Proyecto <span className="text-[#dc2626]">*</span>
                            </label>
                            <select
                                id="reg-proyecto"
                                value={formProyectoId}
                                onChange={(e) => { setFormProyectoId(e.target.value); setFormError(null); }}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            >
                                <option value="">Seleccione un proyecto</option>
                                {loadingProyectos ? (
                                    <option value="" disabled>Cargando proyectos...</option>
                                ) : (
                                    proyectos.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.code} — {p.title}
                                            {p.director ? ` (Dir: ${p.director.name})` : ''}
                                            {p.estudiantes.length > 0
                                                ? ` | ${p.estudiantes.map((e) => e.name).join(', ')}`
                                                : ''}
                                        </option>
                                    ))
                                )}
                            </select>
                        </div>

                        {/* Phase */}
                        <div className="flex flex-col gap-1.5">
                            <label className="text-sm font-semibold text-[#1c1917]">
                                Fase <span className="text-[#dc2626]">*</span>
                            </label>
                            <div className="flex items-center gap-4 pt-1.5">
                                <label className="flex items-center gap-2 text-sm text-[#1c1917] cursor-pointer">
                                    <input
                                        type="radio"
                                        name="fase"
                                        value="Anteproyecto"
                                        checked={formFase === 'Anteproyecto'}
                                        onChange={() => setFormFase('Anteproyecto')}
                                        className="accent-[#c2410c]"
                                    />
                                    Presentación Anteproyecto
                                </label>
                                <label className="flex items-center gap-2 text-sm text-[#1c1917] cursor-pointer">
                                    <input
                                        type="radio"
                                        name="fase"
                                        value="Final"
                                        checked={formFase === 'Final'}
                                        onChange={() => setFormFase('Final')}
                                        className="accent-[#c2410c]"
                                    />
                                    Presentación Final
                                </label>
                            </div>
                        </div>

                        {/* Date */}
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="reg-fecha" className="text-sm font-semibold text-[#1c1917]">
                                Fecha <span className="text-[#dc2626]">*</span>
                            </label>
                            <input
                                id="reg-fecha"
                                type="date"
                                value={formFecha}
                                onChange={(e) => setFormFecha(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            />
                        </div>

                        {/* Hora Inicio */}
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="reg-hora-inicio" className="text-sm font-semibold text-[#1c1917]">
                                Hora Inicio <span className="text-[#dc2626]">*</span>
                            </label>
                            <input
                                id="reg-hora-inicio"
                                type="time"
                                value={formHoraInicio}
                                onChange={(e) => { setFormHoraInicio(e.target.value); setFormError(null); }}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            />
                        </div>

                        {/* Hora Fin */}
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="reg-hora-fin" className="text-sm font-semibold text-[#1c1917]">
                                Hora Fin <span className="text-[#dc2626]">*</span>
                            </label>
                            <input
                                id="reg-hora-fin"
                                type="time"
                                value={formHoraFin}
                                onChange={(e) => { setFormHoraFin(e.target.value); setFormError(null); }}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            />
                        </div>

                        {/* Evaluadores selector */}
                        <div className="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-1">
                            <label className="text-sm font-semibold text-[#1c1917]">
                                Evaluadores <span className="text-[#dc2626]">*</span>
                                <span className="font-normal text-[#78716c]"> (seleccione 2-3)</span>
                            </label>
                            {loadingEvalUsers ? (
                                <div className="flex items-center gap-2 text-sm text-[#78716c] py-2">
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    Cargando evaluadores...
                                </div>
                            ) : (
                                <div className="max-h-[240px] overflow-y-auto rounded-lg border border-[#e5e5e5] bg-white">
                                    {evaluadores.length === 0 ? (
                                        <p className="p-3 text-sm text-[#78716c]">No hay evaluadores disponibles.</p>
                                    ) : (
                                        <>
                                            {/* Directores */}
                                            {evaluadores.filter((ev) => ev.role === 'Director').length > 0 && (
                                                <div className="border-b border-[#e5e5e5]">
                                                    <div className="flex items-center gap-2 px-3 pt-2.5 pb-1.5 text-xs font-semibold uppercase tracking-wider text-[#78716c]">
                                                        <UserCheck className="h-3.5 w-3.5" />
                                                        Directores
                                                    </div>
                                                    {evaluadores
                                                        .filter((ev) => ev.role === 'Director')
                                                        .map((ev) => {
                                                            const selected = formEvalIds.includes(ev.id);
                                                            const isDirectorPropio = selectedProyecto?.director?.id === ev.id;
                                                            return (
                                                                <label
                                                                    key={ev.id}
                                                                    className={cn(
                                                                        'flex items-center gap-3 px-3 py-2 text-sm cursor-pointer transition-colors',
                                                                        selected
                                                                            ? 'bg-[#fed7aa] text-[#1c1917]'
                                                                            : 'hover:bg-[#f5f5f4] text-[#1c1917]',
                                                                        isDirectorPropio && selected && 'bg-[#fee2e2]',
                                                                    )}
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={selected}
                                                                        onChange={() => handleEvaluadorToggle(ev.id)}
                                                                        className="accent-[#c2410c]"
                                                                    />
                                                                    <div className="flex flex-1 items-center justify-between gap-2">
                                                                        <div className="truncate">
                                                                            <span>{ev.name}</span>
                                                                            <span className="ml-2 text-[#78716c]">({ev.email})</span>
                                                                        </div>
                                                                        <StatusBadge variant="info">Director</StatusBadge>
                                                                    </div>
                                                                </label>
                                                            );
                                                        })}
                                                </div>
                                            )}
                                            {/* Evaluadores Externos */}
                                            {evaluadores.filter((ev) => ev.role === 'EvaluadorExterno').length > 0 && (
                                                <div>
                                                    <div className="flex items-center gap-2 px-3 pt-2.5 pb-1.5 text-xs font-semibold uppercase tracking-wider text-[#78716c]">
                                                        <GraduationCap className="h-3.5 w-3.5" />
                                                        Evaluadores Externos
                                                    </div>
                                                    {evaluadores
                                                        .filter((ev) => ev.role === 'EvaluadorExterno')
                                                        .map((ev) => {
                                                            const selected = formEvalIds.includes(ev.id);
                                                            return (
                                                                <label
                                                                    key={ev.id}
                                                                    className={cn(
                                                                        'flex items-center gap-3 px-3 py-2 text-sm cursor-pointer transition-colors',
                                                                        selected
                                                                            ? 'bg-[#fed7aa] text-[#1c1917]'
                                                                            : 'hover:bg-[#f5f5f4] text-[#1c1917]',
                                                                    )}
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={selected}
                                                                        onChange={() => handleEvaluadorToggle(ev.id)}
                                                                        className="accent-[#c2410c]"
                                                                    />
                                                                    <div className="flex flex-1 items-center justify-between gap-2">
                                                                        <div className="truncate">
                                                                            <span>{ev.name}</span>
                                                                            <span className="ml-2 text-[#78716c]">({ev.email})</span>
                                                                        </div>
                                                                        <StatusBadge variant="inactivo">Evaluador</StatusBadge>
                                                                    </div>
                                                                </label>
                                                            );
                                                        })}
                                                </div>
                                            )}
                                        </>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="mt-4 flex items-center gap-3">
                        <button
                            type="submit"
                            disabled={mutationLoading}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {mutationLoading ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <UserPlus className="h-4 w-4" />
                            )}
                            Guardar Asignación
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowRegisterForm(false)}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                        >
                            Cancelar
                        </button>
                    </div>
                </form>
            )}

            {/* Search */}
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Buscar asignaciones..."
                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                />
            </div>

            {/* Assignments Table */}
            <DataTable<EvaluadorProyecto>
                columns={columns}
                data={filtered}
                loading={loading}
                emptyMessage="No se encontraron asignaciones."
                getRowKey={(row) => row.id}
            />

            {/* Calendar */}
            <CalendarGrid
                assignments={calendarAssignments}
                className="mt-2"
            />

            {/* Results */}
            <div className="flex flex-col gap-3">
                <h3 className="text-base font-bold text-[#1c1917]">Resultados de Evaluaciones</h3>
                <ResultsTable data={resultados} loading={loadingResultados} />
            </div>

            {/* Edit Modal */}
            <EditModal
                open={editTarget !== null}
                assignment={editTarget}
                onSave={handleEdit}
                onClose={() => setEditTarget(null)}
                saving={mutationLoading}
                existingAssignments={asignaciones}
            />

            {/* Delete Confirmation */}
            <ConfirmDialog
                open={deleteTarget !== null}
                title="Eliminar Asignación"
                message={
                    deleteTarget
                        ? `¿Está seguro de eliminar la asignación de evaluadores para ${deleteTarget.proyecto_codigo}?`
                        : ''
                }
                confirmLabel="Eliminar"
                cancelLabel="Cancelar"
                variant="danger"
                onConfirm={handleDelete}
                onCancel={() => setDeleteTarget(null)}
            />
        </div>
    );
}
