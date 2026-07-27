import { useState, useCallback } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { EmptyState } from '@/components/ui/EmptyState';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { GroupSelector } from '@/components/forms/GroupSelector';
import { StudentAutocomplete } from '@/components/forms/StudentAutocomplete';
import { useProyectos, type Proyecto, type CreateProyectoPayload } from '@/hooks/useProyectos';
import { useGrupos } from '@/hooks/useGrupos';
import { useCupos, type DirectorCupo } from '@/hooks/useCupos';
import {
    Plus,
    Trash2,
    Pencil,
    Loader2,
    Users,
    FolderKanban,
    GraduationCap,
    AlertTriangle,
    Save,
    X,
    Search,
} from 'lucide-react';

/* ------------------------------------------------------------------ */
/*  Status helpers                                                     */
/* ------------------------------------------------------------------ */

const statusConfig: Record<string, { label: string; variant: 'success' | 'info' | 'inactivo' | 'warning' | 'en-curso' | 'riesgo' }> = {
    en_curso: { label: 'En curso', variant: 'success' },
    completado: { label: 'Completado', variant: 'inactivo' },
    en_riesgo: { label: 'En riesgo', variant: 'riesgo' },
    incumplimiento: { label: 'Incumplimiento', variant: 'warning' },
    inscribed: { label: 'Inscrito', variant: 'en-curso' },
};

/* ------------------------------------------------------------------ */
/*  Skeleton helper                                                    */
/* ------------------------------------------------------------------ */

function SectionLoading({ rows = 4 }: { rows?: number }) {
    return (
        <div className="flex flex-col gap-2">
            {Array.from({ length: rows }, (_, i) => (
                <div key={i} className="h-12 w-full animate-pulse rounded-lg bg-[#e7e5e4]" />
            ))}
        </div>
    );
}

function ErrorBanner({ message, onRetry }: { message: string; onRetry?: () => void }) {
    return (
        <div className="flex items-center gap-3 rounded-lg border border-[#fee2e2] bg-[#fef2f2] px-4 py-3 text-sm text-[#dc2626]">
            <AlertTriangle className="h-5 w-5 shrink-0" />
            <span className="flex-1">{message}</span>
            {onRetry && (
                <button
                    onClick={onRetry}
                    className="shrink-0 rounded-lg border border-[#fecaca] px-3 py-1 text-xs font-semibold transition-colors hover:bg-[#fee2e2]"
                >
                    Reintentar
                </button>
            )}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/*  Project table columns                                              */
/* ------------------------------------------------------------------ */

interface EditTarget {
    proyecto: Proyecto;
    title: string;
    directorId: number;
}

export default function GestionProyectos() {
    /* ── Group filter ──────────────────────────────────────────────── */
    const [selectedGroupId, setSelectedGroupId] = useState<number | null>(null);

    /* ── Hooks ─────────────────────────────────────────────────────── */
    const {
        data: proyectos,
        loading: proyLoading,
        error: proyError,
        refetch: refetchProyectos,
        crear: crearProyecto,
        actualizar: actualizarProyecto,
        eliminar: eliminarProyecto,
        mutationLoading,
    } = useProyectos(selectedGroupId);

    // We need grupos for create-form group display & GroupSelector
    const { data: grupos, refetch: refetchGrupos, actualizar } = useGrupos();

    const {
        data: cupos,
        loading: cuposLoading,
        error: cuposError,
        refetch: refetchCupos,
        updateCupo,
    } = useCupos();

    /* ── Create form state ─────────────────────────────────────────── */
    const [formTitle, setFormTitle] = useState('');
    const [formDirectorId, setFormDirectorId] = useState<number | null>(null);
    const [formStudents, setFormStudents] = useState<{ id: number; name: string; email: string }[]>([]);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [formSubmitting, setFormSubmitting] = useState(false);

    /* ── Edit modal state ──────────────────────────────────────────── */
    const [editTarget, setEditTarget] = useState<EditTarget | null>(null);
    const [editStudents, setEditStudents] = useState<{ id: number; name: string; email: string }[]>([]);

    /* ── Delete confirm state ──────────────────────────────────────── */
    const [deleteTarget, setDeleteTarget] = useState<Proyecto | null>(null);
    const [deleteError, setDeleteError] = useState<string | null>(null);

    /* ── Cupo editing state ────────────────────────────────────────── */
    const [editingCupoId, setEditingCupoId] = useState<number | null>(null);
    const [editingCupoValue, setEditingCupoValue] = useState<number>(0);
    const [editingCupoAreas, setEditingCupoAreas] = useState<string>('');
    const [cupoSaving, setCupoSaving] = useState<number | null>(null);
    const [cupoError, setCupoError] = useState<string | null>(null);

    /* ── Semester toggle state ─────────────────────────────────────── */
    const [toggleLoading, setToggleLoading] = useState(false);
    const [toggleError, setToggleError] = useState<string | null>(null);

    /* ── Selected semester ─────────────────────────────────────────── */
    const selectedGroup = selectedGroupId ? grupos.find((g) => g.id === selectedGroupId) ?? null : null;

    /* ── Search (local filter) ─────────────────────────────────────── */
    const [search, setSearch] = useState('');

    /* ── Create project ────────────────────────────────────────────── */
    const handleCreate = useCallback(async () => {
        const errs: Record<string, string> = {};
        if (!formTitle.trim()) errs.title = 'El título es obligatorio';
        if (selectedGroupId == null) errs.group = 'Debe seleccionar un grupo';
        if (formDirectorId == null) errs.director = 'Debe seleccionar un director';
        if (formStudents.length === 0) errs.students = 'Debe seleccionar al menos 1 estudiante';

        if (Object.keys(errs).length > 0) {
            setFormErrors(errs);
            return;
        }

        setFormSubmitting(true);
        try {
            await crearProyecto({
                title: formTitle.trim(),
                semester_id: selectedGroupId!,
                director_id: formDirectorId!,
                student_ids: formStudents.map((s) => s.id),
            });
            setFormTitle('');
            setFormDirectorId(null);
            setFormStudents([]);
            setFormErrors({});
        } catch {
            setFormErrors({ submit: 'Error al crear el proyecto. Intente nuevamente.' });
        } finally {
            setFormSubmitting(false);
        }
    }, [formTitle, selectedGroupId, formDirectorId, formStudents, crearProyecto]);

    /* ── Edit project ──────────────────────────────────────────────── */
    const handleEditOpen = useCallback((proyecto: Proyecto) => {
        setEditTarget({
            proyecto,
            title: proyecto.title,
            directorId: proyecto.director?.id ?? 0,
        });
        setEditStudents(
            (proyecto.estudiantes ?? []).map((s) => ({ id: s.id, name: s.name, email: s.email ?? '' })),
        );
    }, []);

    const handleEditSave = useCallback(async () => {
        if (!editTarget) return;
        try {
            await actualizarProyecto(editTarget.proyecto.id, {
                title: editTarget.title,
                director_id: editTarget.directorId,
                student_ids: editStudents.map((s) => s.id),
            });
            setEditTarget(null);
        } catch {
            // error handled by hook
        }
    }, [editTarget, editStudents, actualizarProyecto]);

    /* ── Delete project ────────────────────────────────────────────── */
    const handleDelete = useCallback(async () => {
        if (!deleteTarget) return;
        setDeleteError(null);
        try {
            await eliminarProyecto(deleteTarget.id);
            setDeleteTarget(null);
        } catch (err) {
            const msg = err instanceof Error ? err.message : 'Error al eliminar el proyecto';
            setDeleteError(msg);
        }
    }, [deleteTarget, eliminarProyecto]);

    /* ── Cupo save ─────────────────────────────────────────────────── */
    const handleCupoSave = useCallback(
        async (directorId: number) => {
            setCupoSaving(directorId);
            setCupoError(null);
            const result = await updateCupo(directorId, editingCupoValue, editingCupoAreas);
            if (!result.ok) {
                setCupoError(result.error ?? 'Error al guardar cupo');
            } else {
                setEditingCupoId(null);
            }
            setCupoSaving(null);
        },
        [editingCupoValue, editingCupoAreas, updateCupo],
    );

    /* ── Table columns ─────────────────────────────────────────────── */
    const columns: Column<Proyecto>[] = [
        {
            key: 'code',
            label: 'Código',
            className: 'whitespace-nowrap font-mono text-xs',
        },
        {
            key: 'title',
            label: 'Título',
            className: 'max-w-xs',
            render: (row) => (
                <span className="line-clamp-2" title={row.title}>
                    {row.title}
                </span>
            ),
        },
        {
            key: 'students',
            label: 'Estudiantes',
            render: (row) => (
                <span className="text-xs text-[#57534e]">
                    {row.estudiantes?.map((s) => s.name).join(', ') ?? '—'}
                </span>
            ),
        },
        {
            key: 'director',
            label: 'Director',
            render: (row) => (
                <span className="text-sm">{row.director?.name ?? '—'}</span>
            ),
        },
        {
            key: 'phase',
            label: 'Fase',
            render: (row) => (
                <span className="text-xs text-[#57534e]">{row.current_phase ?? '—'}</span>
            ),
        },
        {
            key: 'status',
            label: 'Estado',
            render: (row) => {
                const cfg = statusConfig[row.status] ?? { label: row.status, variant: 'info' as const };
                return <StatusBadge variant={cfg.variant}>{cfg.label}</StatusBadge>;
            },
        },
        {
            key: 'actions',
            label: '',
            className: 'text-right',
            render: (row) => (
                <div className="flex items-center justify-end gap-1">
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            handleEditOpen(row);
                        }}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c] active:scale-[0.98]"
                        aria-label={`Editar proyecto ${row.code}`}
                        title="Editar"
                    >
                        <Pencil className="h-4 w-4" />
                    </button>
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            setDeleteTarget(row);
                        }}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626] active:scale-[0.98]"
                        aria-label={`Eliminar proyecto ${row.code}`}
                        title="Eliminar"
                    >
                        <Trash2 className="h-4 w-4" />
                    </button>
                </div>
            ),
        },
    ];

    /* ── Local filter ──────────────────────────────────────────────── */
    const filtered = proyectos.filter((p) => {
        if (!search.trim()) return true;
        const q = search.toLowerCase();
        return (
            p.code.toLowerCase().includes(q) ||
            p.title.toLowerCase().includes(q) ||
            (p.estudiantes?.some((s) => s.name.toLowerCase().includes(q)) ?? false)
        );
    });

    /* ── Render ────────────────────────────────────────────────────── */
    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Coordinación"
                title="Gestión de Proyectos"
                subtitle="Administre los proyectos de grado, grupos y cupos de dirección"
            />

            {/* ════ Group Selector Section ════ */}
            <section className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="flex items-center gap-2 mb-4">
                    <FolderKanban className="h-5 w-5 text-[#c2410c]" />
                    <h3 className="text-base font-bold text-[#1c1917]">Grupo de proyectos</h3>
                </div>
                <GroupSelector value={selectedGroupId} onChange={setSelectedGroupId} />
                {!selectedGroupId && (
                    <p className="mt-2 text-xs text-[#78716c]">
                        Seleccione un grupo para ver sus proyectos.
                    </p>
                )}

                {selectedGroup && (
                    <div className="mt-4 flex items-center justify-between rounded-lg border border-[#e5e5e5] bg-[#fafaf9] px-4 py-3">
                        <div className="flex items-center gap-3">
                            <span className="text-sm font-semibold text-[#1c1917]">
                                {selectedGroup.name}
                            </span>
                            <span
                                className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold ${
                                    selectedGroup.is_active
                                        ? 'bg-[#dcfce7] text-[#166534]'
                                        : 'bg-[#f5f5f4] text-[#78716c]'
                                }`}
                            >
                                <span
                                    className={`h-1.5 w-1.5 rounded-full ${
                                        selectedGroup.is_active ? 'bg-[#16a34a]' : 'bg-[#a8a29e]'
                                    }`}
                                />
                                {selectedGroup.is_active ? 'Activo' : 'Inactivo'}
                            </span>
                        </div>
                        <button
                            onClick={async () => {
                                if (toggleLoading) return;
                                setToggleLoading(true);
                                setToggleError(null);
                                try {
                                    await actualizar(selectedGroup.id, {
                                        is_active: !selectedGroup.is_active,
                                    });
                                    await refetchGrupos();
                                } catch (err) {
                                    setToggleError(
                                        err instanceof Error ? err.message : 'Error al cambiar estado',
                                    );
                                } finally {
                                    setToggleLoading(false);
                                }
                            }}
                            disabled={toggleLoading}
                            className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors active:scale-[0.98] disabled:opacity-60 ${
                                selectedGroup.is_active
                                    ? 'border border-[#e5e5e5] text-[#dc2626] hover:bg-[#fef2f2]'
                                    : 'bg-[#c2410c] text-white hover:bg-[#9a330a]'
                            }`}
                        >
                            {toggleLoading && <Loader2 className="h-3 w-3 animate-spin" />}
                            {selectedGroup.is_active ? 'Desactivar' : 'Activar'}
                        </button>
                    </div>
                )}

                {toggleError && (
                    <p className="mt-2 text-xs font-medium text-[#dc2626]">{toggleError}</p>
                )}
            </section>

            {/* ════ Project Table Section ════ */}
            <section className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="mb-4 flex items-center justify-between flex-wrap gap-3">
                    <div className="flex items-center gap-2">
                        <GraduationCap className="h-5 w-5 text-[#c2410c]" />
                        <h3 className="text-base font-bold text-[#1c1917]">Proyectos</h3>
                        {selectedGroupId && (
                            <span className="rounded-full bg-[#fed7aa] px-2 py-0.5 text-[11px] font-bold text-[#9a330a]">
                                {filtered.length}
                            </span>
                        )}
                    </div>

                    {/* Search */}
                    <div className="relative max-w-xs w-full">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar proyectos..."
                            className="w-full min-h-[36px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-1.5 text-sm outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            aria-label="Buscar proyectos"
                        />
                    </div>
                </div>

                {proyError && (
                    <ErrorBanner message={proyError} onRetry={refetchProyectos} />
                )}

                {proyLoading ? (
                    <SectionLoading rows={5} />
                ) : !selectedGroupId ? (
                    <EmptyState
                        icon={FolderKanban}
                        title="Seleccione un grupo"
                        description="Use el selector de grupo arriba para ver los proyectos."
                    />
                ) : filtered.length === 0 ? (
                    <EmptyState
                        icon={GraduationCap}
                        title="No hay proyectos en este grupo"
                        description={search ? 'Intente con otros términos de búsqueda.' : 'Cree un nuevo proyecto usando el formulario de abajo.'}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={filtered}
                        getRowKey={(row) => row.id}
                        emptyMessage="No se encontraron proyectos."
                    />
                )}
            </section>

            {/* ════ Create Project Section ════ */}
            <section className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="flex items-center gap-2 mb-4">
                    <Plus className="h-5 w-5 text-[#c2410c]" />
                    <h3 className="text-base font-bold text-[#1c1917]">Crear Proyecto</h3>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {/* Group (readonly) */}
                    <div className="flex flex-col gap-1.5">
                        <label className="text-sm font-semibold text-[#1c1917]">
                            Grupo <span className="text-[#dc2626]">*</span>
                        </label>
                        <div className="min-h-[40px] rounded-lg border border-[#e5e5e5] bg-[#f5f5f4] px-3 py-2 text-sm text-[#57534e]">
                            {selectedGroupId
                                ? grupos.find((g) => g.id === selectedGroupId)?.name ?? '—'
                                : 'Seleccione un grupo primero'}
                        </div>
                        {formErrors.group && (
                            <span className="text-xs font-medium text-[#dc2626]">{formErrors.group}</span>
                        )}
                    </div>

                    {/* Title */}
                    <div className="flex flex-col gap-1.5 md:col-span-2">
                        <label className="text-sm font-semibold text-[#1c1917]">
                            Título del proyecto <span className="text-[#dc2626]">*</span>
                        </label>
                        <input
                            type="text"
                            value={formTitle}
                            onChange={(e) => setFormTitle(e.target.value)}
                            placeholder="Ej: Sistema de Gestión de Inventarios"
                            disabled={selectedGroupId == null}
                            className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] disabled:cursor-not-allowed disabled:opacity-60"
                        />
                        {formErrors.title && (
                            <span className="text-xs font-medium text-[#dc2626]">{formErrors.title}</span>
                        )}
                    </div>

                    {/* Director */}
                    <div className="flex flex-col gap-1.5">
                        <label className="text-sm font-semibold text-[#1c1917]">
                            Director <span className="text-[#dc2626]">*</span>
                        </label>
                        <select
                            value={formDirectorId ?? ''}
                            onChange={(e) => setFormDirectorId(e.target.value ? Number(e.target.value) : null)}
                            disabled={selectedGroupId == null}
                            className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] disabled:cursor-not-allowed disabled:opacity-60"
                            aria-label="Seleccionar director"
                        >
                            <option value="">Seleccionar director...</option>
                            {cupos.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name} ({d.active_projects}/{d.max_capacity})
                                </option>
                            ))}
                        </select>
                        {formErrors.director && (
                            <span className="text-xs font-medium text-[#dc2626]">{formErrors.director}</span>
                        )}
                    </div>

                    {/* Students */}
                    <div className="md:col-span-2">
                        <StudentAutocomplete
                            value={formStudents}
                            onChange={setFormStudents}
                            error={formErrors.students}
                            sinProyecto={true}
                        />
                    </div>
                </div>

                {formErrors.submit && (
                    <div className="mt-3">
                        <span className="text-xs font-medium text-[#dc2626]">{formErrors.submit}</span>
                    </div>
                )}

                <div className="mt-4 flex justify-end">
                    <button
                        onClick={handleCreate}
                        disabled={selectedGroupId == null || formSubmitting || mutationLoading}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {formSubmitting ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <Plus className="h-4 w-4" />
                        )}
                        Crear Proyecto
                    </button>
                </div>
            </section>

            {/* ════ Cupo Management Section ════ */}
            <section className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="mb-4 flex items-center gap-2">
                    <Users className="h-5 w-5 text-[#c2410c]" />
                    <h3 className="text-base font-bold text-[#1c1917]">Cupos de Dirección</h3>
                </div>
                <p className="mb-4 text-sm text-[#57534e]">
                    Gestión de la capacidad máxima de proyectos por director.
                </p>

                {cupoError && <ErrorBanner message={cupoError} />}
                {cuposError && <ErrorBanner message={cuposError} onRetry={refetchCupos} />}

                {cuposLoading ? (
                    <SectionLoading rows={4} />
                ) : cupos.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="Sin directores"
                        description="No hay directores registrados en el sistema."
                    />
                ) : (
                    <div className="w-full overflow-x-auto rounded-lg border border-[#e5e5e5]">
                        <table className="w-full text-left text-sm tabular-nums">
                            <thead className="bg-[#f5f5f4] text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                <tr>
                                    <th className="px-4 py-3">Director</th>
                                    <th className="px-4 py-3">Áreas</th>
                                    <th className="px-4 py-3 text-center">Activos</th>
                                    <th className="px-4 py-3 text-center">Cupo máximo</th>
                                    <th className="px-4 py-3">Ocupación</th>
                                    <th className="px-4 py-3 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                {cupos.map((c) => {
                                    const pct = Math.round(
                                        (c.active_projects / Math.max(c.max_capacity, 1)) * 100,
                                    );
                                    const isEditing = editingCupoId === c.id;
                                    return (
                                        <tr
                                            key={c.id}
                                            className="border-b border-[#e5e5e5] last:border-none"
                                        >
                                            <td className="px-4 py-3 font-medium text-[#1c1917]">
                                                {c.name}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-[#57534e]">
                                                {isEditing ? (
                                                    <textarea
                                                        value={editingCupoAreas}
                                                        onChange={(e) => setEditingCupoAreas(e.target.value)}
                                                        rows={3}
                                                        className="w-full min-w-[200px] rounded-lg border border-[#e5e5e5] bg-white px-2 py-1 text-xs outline-none focus:border-[#c2410c] resize-y"
                                                        aria-label={`Áreas de especialización para ${c.name}`}
                                                        placeholder="Una línea por área"
                                                    />
                                                ) : (
                                                    <span className="whitespace-pre-wrap">{c.areas?.join(', ') || '—'}</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center font-semibold text-[#1c1917]">
                                                {c.active_projects}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {isEditing ? (
                                                    <input
                                                        type="number"
                                                        min={c.active_projects}
                                                        value={editingCupoValue}
                                                        onChange={(e) =>
                                                            setEditingCupoValue(Number(e.target.value))
                                                        }
                                                        className="w-20 rounded-lg border border-[#e5e5e5] px-2 py-1 text-center text-sm outline-none focus:border-[#c2410c]"
                                                        aria-label={`Nuevo cupo máximo para ${c.name}`}
                                                    />
                                                ) : (
                                                    <span className="font-semibold text-[#1c1917]">
                                                        {c.max_capacity}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <div className="h-2 w-full max-w-[100px] overflow-hidden rounded-full bg-[#e7e5e4]">
                                                        <div
                                                            className={`h-full rounded-full ${
                                                                pct >= 100
                                                                    ? 'bg-[#dc2626]'
                                                                    : pct >= 75
                                                                      ? 'bg-[#d97706]'
                                                                      : 'bg-[#16a34a]'
                                                            }`}
                                                            style={{ width: `${Math.min(pct, 100)}%` }}
                                                        />
                                                    </div>
                                                    <span className="text-xs text-[#78716c] tabular-nums">
                                                        {pct}%
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {isEditing ? (
                                                    <div className="flex items-center justify-end gap-1">
                                                        <button
                                                            onClick={() => handleCupoSave(c.id)}
                                                            disabled={cupoSaving === c.id}
                                                            className="inline-flex items-center gap-1 rounded-lg bg-[#16a34a] px-2.5 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-[#15803d] disabled:opacity-60"
                                                        >
                                                            {cupoSaving === c.id ? (
                                                                <Loader2 className="h-3 w-3 animate-spin" />
                                                            ) : (
                                                                <Save className="h-3 w-3" />
                                                            )}
                                                            Guardar
                                                        </button>
                                                        <button
                                                            onClick={() => setEditingCupoId(null)}
                                                            className="inline-flex items-center gap-1 rounded-lg border border-[#e5e5e5] px-2.5 py-1.5 text-xs font-semibold text-[#57534e] transition-colors hover:bg-[#f5f5f4]"
                                                        >
                                                            <X className="h-3 w-3" />
                                                            Cancelar
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <button
                                                        onClick={() => {
                                                            setEditingCupoId(c.id);
                                                            setEditingCupoValue(c.max_capacity);
                                                            setEditingCupoAreas((c.areas ?? []).join('\n'));
                                                            setCupoError(null);
                                                        }}
                                                        className="inline-flex items-center gap-1 rounded-lg border border-[#e5e5e5] px-2.5 py-1.5 text-xs font-semibold text-[#c2410c] transition-colors hover:bg-[#fed7aa]"
                                                        aria-label={`Editar cupo de ${c.name}`}
                                                    >
                                                        <Pencil className="h-3 w-3" />
                                                        Editar
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            {/* ════ Edit Modal ════ */}
            {editTarget && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                    onClick={(e) => {
                        if (e.target === e.currentTarget) setEditTarget(null);
                    }}
                    role="dialog"
                    aria-modal="true"
                    aria-label="Editar proyecto"
                >
                    <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-[0_20px_60px_rgba(28,25,23,0.15)]">
                        <div className="mb-5 flex items-center justify-between">
                            <h2 className="text-lg font-bold text-[#1c1917]">
                                Editar: {editTarget.proyecto.code}
                            </h2>
                            <button
                                onClick={() => setEditTarget(null)}
                                className="rounded-lg p-1.5 text-[#57534e] transition-colors hover:bg-[#f5f5f4]"
                                aria-label="Cerrar"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="flex flex-col gap-4">
                            {/* Title */}
                            <div className="flex flex-col gap-1.5">
                                <label className="text-sm font-semibold text-[#1c1917]">Título</label>
                                <input
                                    type="text"
                                    value={editTarget.title}
                                    onChange={(e) =>
                                        setEditTarget((prev) =>
                                            prev ? { ...prev, title: e.target.value } : null,
                                        )
                                    }
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm outline-none focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                />
                            </div>

                            {/* Director */}
                            <div className="flex flex-col gap-1.5">
                                <label className="text-sm font-semibold text-[#1c1917]">Director</label>
                                <select
                                    value={editTarget.directorId}
                                    onChange={(e) =>
                                        setEditTarget((prev) =>
                                            prev ? { ...prev, directorId: Number(e.target.value) } : null,
                                        )
                                    }
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm outline-none focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    aria-label="Seleccionar director"
                                >
                                    {cupos.map((d) => (
                                        <option key={d.id} value={d.id}>
                                            {d.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Students */}
                            <StudentAutocomplete
                                value={editStudents}
                                onChange={setEditStudents}
                                sinProyecto={true}
                            />
                        </div>

                        <div className="mt-6 flex items-center justify-end gap-2">
                            <button
                                onClick={() => setEditTarget(null)}
                                className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2.5 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={handleEditSave}
                                disabled={mutationLoading}
                                className="inline-flex items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:opacity-60"
                            >
                                {mutationLoading ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Save className="h-4 w-4" />
                                )}
                                Guardar cambios
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ════ Delete Confirmation ════ */}
            <ConfirmDialog
                open={deleteTarget !== null}
                title="Eliminar proyecto"
                message={
                    deleteError
                        ? `No se pudo eliminar: ${deleteError}`
                        : `¿Está seguro de eliminar el proyecto ${deleteTarget?.code}? Esta acción no se puede deshacer.`
                }
                confirmLabel={deleteError ? 'Cerrar' : 'Eliminar'}
                variant={deleteError ? 'warning' : 'danger'}
                onConfirm={() => {
                    if (deleteError) {
                        setDeleteError(null);
                        setDeleteTarget(null);
                    } else {
                        handleDelete();
                    }
                }}
                onCancel={() => { setDeleteError(null); setDeleteTarget(null); }}
            />
        </div>
    );
}
