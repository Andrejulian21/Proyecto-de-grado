import { useState, useMemo, useEffect, useCallback } from 'react';
import { UserPlus, Users, CalendarDays, Search, Loader2, Pencil, Trash2, X } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { CalendarGrid, type CalendarAssignment } from '@/components/calendar/CalendarGrid';
import { ResultsTable } from '@/components/tables/ResultsTable';
import { useEvaluadorProyecto, type EvaluadorProyecto, type CreateEvaluadorPayload, type UpdateEvaluadorPayload } from '@/hooks/useEvaluadorProyecto';
import { useEvaluaciones } from '@/hooks/useEvaluaciones';
import { useProyectos } from '@/hooks/useProyectos';
import { cn } from '@/lib/utils';

/* ── Modal Component ── */

function EditModal({
    open,
    assignment,
    onSave,
    onClose,
    saving,
}: {
    open: boolean;
    assignment: EvaluadorProyecto | null;
    onSave: (id: number, payload: UpdateEvaluadorPayload) => void;
    onClose: () => void;
    saving: boolean;
}) {
    const [fase, setFase] = useState<'Anteproyecto' | 'Final'>('Anteproyecto');
    const [fecha, setFecha] = useState('');
    const [hora, setHora] = useState('');

    useEffect(() => {
        if (assignment) {
            setFase(assignment.fase);
            // Normalize date for input
            if (assignment.fecha) {
                const parts = assignment.fecha.split('/');
                if (parts.length === 3) {
                    setFecha(`${parts[2]}-${parts[1]}-${parts[0]}`);
                } else {
                    setFecha(assignment.fecha);
                }
            }
            setHora(assignment.hora || '');
        }
    }, [assignment]);

    if (!open || !assignment) return null;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSave(assignment.id, { fase, fecha, hora });
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
                    <div className="flex flex-col gap-1.5">
                        <label className="text-sm font-semibold text-[#1c1917]">Proyecto</label>
                        <p className="rounded-lg border border-[#e5e5e5] bg-[#f5f5f4] px-3 py-2 text-sm text-[#57534e]">
                            {assignment.proyecto_codigo} — {assignment.proyecto_nombre}
                        </p>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label htmlFor="edit-fase" className="text-sm font-semibold text-[#1c1917]">Fase</label>
                        <select
                            id="edit-fase"
                            value={fase}
                            onChange={(e) => setFase(e.target.value as 'Anteproyecto' | 'Final')}
                            className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                        >
                            <option value="Anteproyecto">Anteproyecto</option>
                            <option value="Final">Evaluación Final</option>
                        </select>
                    </div>

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

                    <div className="flex flex-col gap-1.5">
                        <label htmlFor="edit-hora" className="text-sm font-semibold text-[#1c1917]">Hora</label>
                        <input
                            id="edit-hora"
                            type="time"
                            value={hora}
                            onChange={(e) => setHora(e.target.value)}
                            className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            required
                        />
                    </div>

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
        refetch,
        crear,
        actualizar,
        eliminar,
    } = useEvaluadorProyecto();

    const {
        data: resultados,
        loading: loadingResultados,
    } = useEvaluaciones();

    const {
        data: proyectos,
        loading: loadingProyectos,
    } = useProyectos();

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
    const [formEvalPrincipal, setFormEvalPrincipal] = useState('');
    const [formEvalSecundario, setFormEvalSecundario] = useState('');
    const [formEvalTercero, setFormEvalTercero] = useState('');
    const [formFecha, setFormFecha] = useState('');
    const [formHora, setFormHora] = useState('');

    /* ── Evaluadores disponibles (mock hasta que haya endpoint) ── */
    // TODO: reemplazar con fetch a /api/admin/evaluadores
    const evaluadoresDisponibles = useMemo(() => [
        { id: 1, name: 'Dr. Pedro Castillo' },
        { id: 2, name: 'Dra. Sofía Vargas' },
        { id: 3, name: 'Dr. Miguel Ángel Ruiz' },
        { id: 4, name: 'Dra. Laura Mendoza' },
        { id: 5, name: 'Dr. Andrés Felipe Ríos' },
    ], []);

    /* ── Filters ── */
    const filtered = useMemo(() => {
        if (!search.trim()) return asignaciones;
        const q = search.toLowerCase();
        return asignaciones.filter((a) =>
            a.proyecto_codigo.toLowerCase().includes(q) ||
            a.proyecto_nombre.toLowerCase().includes(q) ||
            a.estudiantes.some((e) => e.name.toLowerCase().includes(q)) ||
            a.evaluador_principal_nombre.toLowerCase().includes(q),
        );
    }, [asignaciones, search]);

    /* ── Calendar data ── */
    const calendarAssignments: CalendarAssignment[] = useMemo(() =>
        asignaciones.map((a) => ({
            date: a.fecha,
            label: `${a.proyecto_codigo}`,
        })),
    [asignaciones]);

    /* ── Stats ── */
    const scheduledCount = asignaciones.filter((a) => a.fecha && new Date(a.fecha) >= new Date()).length;
    const completedCount = resultados.length;

    /* ── Formatters ── */
    function formatEvaluadores(asig: EvaluadorProyecto): string {
        const list = [asig.evaluador_principal_nombre];
        if (asig.evaluador_secundario_nombre) list.push(asig.evaluador_secundario_nombre);
        if (asig.evaluador_tercero_nombre) list.push(asig.evaluador_tercero_nombre);
        return list.join(', ');
    }

    function formatDate(dateStr: string): string {
        // Convert YYYY-MM-DD to DD/MM/YYYY
        if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
            const [y, m, d] = dateStr.split('-');
            return `${d}/${m}/${y}`;
        }
        return dateStr;
    }

    /* ── Handlers ── */
    const handleRegister = useCallback(async (e: React.FormEvent) => {
        e.preventDefault();
        if (!formProyectoId || !formEvalPrincipal || !formEvalSecundario || !formFecha || !formHora) return;

        const payload: CreateEvaluadorPayload = {
            proyecto_id: Number(formProyectoId),
            fase: formFase,
            evaluador_principal_id: Number(formEvalPrincipal),
            evaluador_secundario_id: Number(formEvalSecundario),
            evaluador_tercero_id: formEvalTercero ? Number(formEvalTercero) : null,
            fecha: formFecha,
            hora: formHora,
        };

        try {
            await crear(payload);
            setFormProyectoId('');
            setFormFase('Anteproyecto');
            setFormEvalPrincipal('');
            setFormEvalSecundario('');
            setFormEvalTercero('');
            setFormFecha('');
            setFormHora('');
            setShowRegisterForm(false);
        } catch {
            // error is handled by mutationError in the hook
        }
    }, [formProyectoId, formFase, formEvalPrincipal, formEvalSecundario, formEvalTercero, formFecha, formHora, crear]);

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
                    {row.fase === 'Final' ? 'Evaluación Final' : 'Anteproyecto'}
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
            key: 'hora',
            label: 'Hora',
            render: (row) => (
                <span className="text-[#78716c]">{row.hora || '—'}</span>
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

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {/* Project */}
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="reg-proyecto" className="text-sm font-semibold text-[#1c1917]">
                                Proyecto <span className="text-[#dc2626]">*</span>
                            </label>
                            <select
                                id="reg-proyecto"
                                value={formProyectoId}
                                onChange={(e) => setFormProyectoId(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            >
                                <option value="">Seleccione un proyecto</option>
                                {loadingProyectos ? (
                                    <option value="" disabled>Cargando proyectos...</option>
                                ) : (
                                    proyectos
                                        .filter((p) => p.status === 'active' || p.status === 'inscribed')
                                        .map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.code} — {p.title}
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
                                    Anteproyecto
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
                                    Evaluación Final
                                </label>
                            </div>
                        </div>

                        {/* Evaluador Principal */}
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="reg-eval1" className="text-sm font-semibold text-[#1c1917]">
                                Evaluador Principal <span className="text-[#dc2626]">*</span>
                            </label>
                            <select
                                id="reg-eval1"
                                value={formEvalPrincipal}
                                onChange={(e) => setFormEvalPrincipal(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            >
                                <option value="">Seleccione evaluador</option>
                                {evaluadoresDisponibles.map((ev) => (
                                    <option key={ev.id} value={ev.id}>{ev.name}</option>
                                ))}
                            </select>
                        </div>

                        {/* Evaluador Secundario */}
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="reg-eval2" className="text-sm font-semibold text-[#1c1917]">
                                Evaluador Secundario <span className="text-[#dc2626]">*</span>
                            </label>
                            <select
                                id="reg-eval2"
                                value={formEvalSecundario}
                                onChange={(e) => setFormEvalSecundario(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            >
                                <option value="">Seleccione evaluador</option>
                                {evaluadoresDisponibles.filter((ev) => ev.id !== Number(formEvalPrincipal)).map((ev) => (
                                    <option key={ev.id} value={ev.id}>{ev.name}</option>
                                ))}
                            </select>
                        </div>

                        {/* Evaluador Tercero (opcional) */}
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="reg-eval3" className="text-sm font-semibold text-[#1c1917]">
                                Evaluador Adicional <span className="text-[#78716c]">(opcional)</span>
                            </label>
                            <select
                                id="reg-eval3"
                                value={formEvalTercero}
                                onChange={(e) => setFormEvalTercero(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            >
                                <option value="">Sin evaluador adicional</option>
                                {evaluadoresDisponibles
                                    .filter((ev) => ev.id !== Number(formEvalPrincipal) && ev.id !== Number(formEvalSecundario))
                                    .map((ev) => (
                                        <option key={ev.id} value={ev.id}>{ev.name}</option>
                                    ))}
                            </select>
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

                        {/* Time */}
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="reg-hora" className="text-sm font-semibold text-[#1c1917]">
                                Hora <span className="text-[#dc2626]">*</span>
                            </label>
                            <input
                                id="reg-hora"
                                type="time"
                                value={formHora}
                                onChange={(e) => setFormHora(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            />
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
