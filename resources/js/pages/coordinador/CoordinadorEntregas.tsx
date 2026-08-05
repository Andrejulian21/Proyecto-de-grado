import { useState, useCallback, useEffect, useRef } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { GroupSelector } from '@/components/forms/GroupSelector';
import { useEntregas, FASE_SEQUENCE, type Fase, type Entrega, type UpdateEntregaPayload } from '@/hooks/useEntregas';
import ArchivosRequeridosBuilder, {
    SLUG_DOCUMENTO_PROYECTO,
    NOMBRE_DOCUMENTO_PROYECTO,
} from '@/components/entregas/ArchivosRequeridosBuilder';
import IndicadorSumaPar from '@/components/entregas/IndicadorSumaPar';
import type { ArchivoRequeridoConfig } from '@/types/entregas';
import {
    Search,
    FileText,
    Plus,
    Loader2,
    Calendar,
    AlertCircle,
    Pencil,
    Trash2,
    X,
    AlertTriangle,
} from 'lucide-react';

const FASE_LABELS: Record<string, string> = {
    anteproyecto: 'Anteproyecto',
    presentacion_anteproyecto: 'Presentación Anteproyecto',
    desarrollo: 'Desarrollo del proyecto',
    presentacion_final: 'Presentación Final',
};

/** Default main file enforced by RF-ENT-01 (slug documento-proyecto). */
function archivosPorDefecto(): ArchivoRequeridoConfig[] {
    return [
        { id: SLUG_DOCUMENTO_PROYECTO, nombre: NOMBRE_DOCUMENTO_PROYECTO, versionamiento: true },
    ];
}

function formatDate(dateStr: string): string {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleDateString('es-CO', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch {
        return dateStr;
    }
}

export default function CoordinadorEntregas() {
    const [selectedGroup, setSelectedGroup] = useState<number | null>(null);
    const [faseFilter, setFaseFilter] = useState<string>('');
    const [searchQuery, setSearchQuery] = useState('');
    const [showCreateForm, setShowCreateForm] = useState(false);

    const {
        data: entregas, loading, error, refetch,
        crear, actualizar, eliminar,
        mutationLoading, mutationError, getNextFase,
    } = useEntregas(
        selectedGroup != null
            ? { grupo_id: selectedGroup, fase: faseFilter || null }
            : undefined,
    );

    // ── Ref guard: evita doble envío del formulario ──────────────
    const creatingRef = useRef(false);

    // ── Determine next fase when group changes ───────────────────
    const [nextFase, setNextFase] = useState<Fase>('anteproyecto');
    useEffect(() => {
        if (selectedGroup != null) {
            setNextFase(getNextFase(selectedGroup));
        } else {
            setNextFase('anteproyecto');
        }
    }, [selectedGroup, getNextFase]);

    // ── Create form state ────────────────────────────────────────
    const [formFase, setFormFase] = useState<Fase>('anteproyecto');
    const [formTitulo, setFormTitulo] = useState('');
    const [formDesc, setFormDesc] = useState('');
    const [formFecha, setFormFecha] = useState('');
    const [formFechaInicio, setFormFechaInicio] = useState('');
    const [formHoraInicio, setFormHoraInicio] = useState('');
    const [formHora, setFormHora] = useState('');
    const [formCriterios, setFormCriterios] = useState('');
    const [formGradePercentage, setFormGradePercentage] = useState('');
    const [formArchivos, setFormArchivos] = useState<ArchivoRequeridoConfig[]>(archivosPorDefecto);
    const [formArchivosError, setFormArchivosError] = useState<string | null>(null);
    const [createError, setCreateError] = useState<string | null>(null);

    useEffect(() => {
        setFormFase(nextFase);
    }, [nextFase]);

    const handleCreate = useCallback(
        async (e: React.FormEvent) => {
            e.preventDefault();
            if (creatingRef.current) return; // ← guard contra doble click
            if (!selectedGroup || !formTitulo.trim() || !formDesc.trim() || !formFecha) return;

            // Validate archivos_requeridos
            const validArchivos = formArchivos.filter((a) => a.nombre.trim().length > 0);
            if (validArchivos.length === 0) {
                setFormArchivosError('Debe agregar al menos un archivo requerido con nombre.');
                return;
            }
            setFormArchivosError(null);

            creatingRef.current = true;
            setCreateError(null);
            try {
                await crear({
                    grupo_id: selectedGroup,
                    fase: formFase,
                    titulo: formTitulo.trim(),
                    descripcion: formDesc.trim(),
                    fecha_limite: formFecha,
                    fecha_inicio: formFechaInicio || undefined,
                    hora_inicio: formHoraInicio || undefined,
                    criterios: formCriterios.trim() || undefined,
                    hora_maxima: formHora || undefined,
                    grade_percentage: formGradePercentage === '' ? null : Number(formGradePercentage),
                    archivos_requeridos: validArchivos,
                });
                setFormTitulo('');
                setFormDesc('');
                setFormFecha('');
                setFormFechaInicio('');
                setFormHoraInicio('');
                setFormHora('');
                setFormCriterios('');
                setFormGradePercentage('');
                setFormArchivos(archivosPorDefecto());
                setFormArchivosError(null);
                setShowCreateForm(false);
            } catch (err) {
                setCreateError(err instanceof Error ? err.message : 'Error al crear entrega');
            } finally {
                creatingRef.current = false;
            }
        },
        [selectedGroup, formFase, formTitulo, formDesc, formFecha, formGradePercentage, formArchivos, crear],
    );

    // ── Edit modal state ─────────────────────────────────────────
    const [editingEntrega, setEditingEntrega] = useState<Entrega | null>(null);
    const [editFecha, setEditFecha] = useState('');
    const [editFechaInicio, setEditFechaInicio] = useState('');
    const [editHoraInicio, setEditHoraInicio] = useState('');
    const [editTitulo, setEditTitulo] = useState('');
    const [editDesc, setEditDesc] = useState('');
    const [editHora, setEditHora] = useState('');
    const [editCriterios, setEditCriterios] = useState('');
    const [editGradePercentage, setEditGradePercentage] = useState('');
    const [editFase, setEditFase] = useState<string>('');
    const [editGrupoId, setEditGrupoId] = useState<number | null>(null);
    const [editArchivos, setEditArchivos] = useState<ArchivoRequeridoConfig[]>([]);
    const [editArchivosError, setEditArchivosError] = useState<string | null>(null);
    const [editError, setEditError] = useState<string | null>(null);

    const openEditModal = useCallback((entrega: Entrega) => {
        setEditingEntrega(entrega);
        try {
            setEditFecha(new Date(entrega.due_date).toISOString().slice(0, 10));
        } catch {
            setEditFecha('');
        }
        try {
            setEditFechaInicio(entrega.start_date ? new Date(entrega.start_date).toISOString().slice(0, 10) : '');
        } catch {
            setEditFechaInicio('');
        }
        setEditHoraInicio(entrega.start_time ?? '');
        setEditTitulo(entrega.title || '');
        setEditDesc(entrega.description || '');
        setEditHora(entrega.hora_maxima ?? '');
        setEditCriterios(entrega.acceptance_criteria ?? '');
        setEditGradePercentage(entrega.grade_percentage != null ? String(entrega.grade_percentage) : '');
        setEditFase(entrega.phase);
        setEditGrupoId(entrega.grupo_id);
        // Persisted items use `slug`; normalize to the builder shape
        // (`id`) so the main-file detection (RF-ENT-01) and the outgoing
        // update payload both carry the canonical identity.
        const persistidos = (entrega.archivos_requeridos ?? []).map((a) => ({
            id: a.id ?? a.slug ?? '',
            nombre: a.nombre,
            versionamiento: a.versionamiento,
            analizable_ia: a.analizable_ia,
        }));
        setEditArchivos(persistidos.length > 0 ? persistidos : archivosPorDefecto());
        setEditArchivosError(null);
        setEditError(null);
    }, []);

    const closeEditModal = useCallback(() => {
        setEditingEntrega(null);
        setEditError(null);
    }, []);

    const handleUpdate = useCallback(async () => {
        if (!editingEntrega) return;
        if (!editFecha) {
            setEditError('La fecha límite es obligatoria.');
            return;
        }
        // Validate archivos_requeridos
        const validArchivos = editArchivos.filter((a) => a.nombre.trim().length > 0);
        if (validArchivos.length === 0) {
            setEditArchivosError('Debe haber al menos un archivo requerido con nombre.');
            return;
        }
        setEditArchivosError(null);
        setEditError(null);
        try {
            const payload: UpdateEntregaPayload = {
                titulo: editTitulo.trim(),
                descripcion: editDesc.trim(),
                fecha_limite: editFecha,
                criterios: editCriterios.trim() || null,
                hora_maxima: editHora || null,
                fecha_inicio: editFechaInicio || null,
                hora_inicio: editHoraInicio || null,
                fase: editFase,
                grade_percentage: editGradePercentage === '' ? null : Number(editGradePercentage),
                archivos_requeridos: validArchivos,
            };
            await actualizar(editingEntrega.id, payload);
            closeEditModal();
        } catch (err) {
            setEditError(err instanceof Error ? err.message : 'Error al actualizar');
        }
    }, [editingEntrega, editFecha, editTitulo, editDesc, editHora, editCriterios, editGradePercentage, editFase, editArchivos, actualizar, closeEditModal]);

    // ── Delete confirmation state ────────────────────────────────
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [deleteError, setDeleteError] = useState<string | null>(null);

    const confirmDelete = useCallback(async () => {
        if (deletingId == null) return;
        setDeleteError(null);
        try {
            await eliminar(deletingId);
            setDeletingId(null);
        } catch (err) {
            setDeleteError(err instanceof Error ? err.message : 'Error al eliminar');
        }
    }, [deletingId, eliminar]);

    // ── Filtering ────────────────────────────────────────────────
    const filtered = entregas.filter((e) => {
        if (searchQuery) {
            const q = searchQuery.toLowerCase();
            const matchDesc = e.description?.toLowerCase().includes(q) ?? false;
            const matchFase = FASE_LABELS[e.phase]?.toLowerCase().includes(q) ?? false;
            if (!matchDesc && !matchFase) return false;
        }
        return true;
    });

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Coordinación"
                title="Entregas"
                subtitle="Gestión de entregas de proyectos de grado por grupo y fase"
                actions={
                    <button
                        onClick={() => setShowCreateForm(!showCreateForm)}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                        aria-label="Crear nueva entrega"
                    >
                        <Plus className="h-4 w-4" />
                        Nueva Entrega
                    </button>
                }
            />

            {/* ── Filters row ─────────────────────────────────── */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <GroupSelector
                    value={selectedGroup}
                    onChange={setSelectedGroup}
                    error={undefined}
                />

                <div className="flex flex-col gap-1.5">
                    <label className="text-sm font-semibold text-[#1c1917]">Fase</label>
                    <select
                        value={faseFilter}
                        onChange={(e) => setFaseFilter(e.target.value)}
                        className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                        aria-label="Filtrar por fase"
                    >
                        <option value="">Todas las fases</option>
                        {FASE_SEQUENCE.map((f) => (
                            <option key={f} value={f}>
                                {FASE_LABELS[f]}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="flex flex-col gap-1.5">
                    <label className="text-sm font-semibold text-[#1c1917]">Buscar</label>
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Buscar entregas..."
                            className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            aria-label="Buscar entregas"
                        />
                    </div>
                </div>
            </div>

            {/* ── Create form ─────────────────────────────────── */}
            {showCreateForm && (
                <form
                    onSubmit={handleCreate}
                    className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]"
                >
                    <h3 className="mb-4 text-base font-bold text-[#1c1917]">Nueva Entrega</h3>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <GroupSelector
                                value={selectedGroup}
                                onChange={setSelectedGroup}
                                error={undefined}
                            />
                        </div>

                        <div className="flex flex-col gap-1.5 sm:col-span-2">
                            <label className="text-sm font-semibold text-[#1c1917]">
                                Título de la entrega <span className="text-[#dc2626]">*</span>
                            </label>
                            <input
                                type="text"
                                value={formTitulo}
                                onChange={(e) => setFormTitulo(e.target.value)}
                                placeholder="Ej: Entrega parcial de anteproyecto"
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            />
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label className="text-sm font-semibold text-[#1c1917]">
                                Fase <span className="text-[#dc2626]">*</span>
                            </label>
                            <div className="flex items-center gap-2">
                                <select
                                    value={formFase}
                                    onChange={(e) => setFormFase(e.target.value as Fase)}
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    aria-label="Fase de la entrega"
                                >
                                    {FASE_SEQUENCE.map((f) => (
                                        <option key={f} value={f}>
                                            {FASE_LABELS[f]}
                                        </option>
                                    ))}
                                </select>
                                {selectedGroup != null && (
                                    <span className="whitespace-nowrap rounded-full bg-[#fef3c7] px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.03em] text-[#78350f]">
                                        Siguiente: {FASE_LABELS[nextFase]}
                                    </span>
                                )}
                            </div>
                        </div>

                        <div className="flex flex-col gap-1.5 sm:col-span-2">
                            <label className="text-sm font-semibold text-[#1c1917]">
                                Descripción <span className="text-[#dc2626]">*</span>
                            </label>
                            <textarea
                                value={formDesc}
                                onChange={(e) => setFormDesc(e.target.value)}
                                rows={3}
                                placeholder="Descripción de la entrega"
                                className="w-full min-h-[60px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y"
                                required
                            />
                        </div>

                        <div className="flex flex-col gap-1.5 sm:col-span-2">
                            <label className="text-sm font-semibold text-[#1c1917]">
                                Criterios de aceptación
                            </label>
                            <textarea
                                value={formCriterios}
                                onChange={(e) => setFormCriterios(e.target.value)}
                                rows={3}
                                placeholder="Criterios que debe cumplir la entrega para ser aprobada"
                                className="w-full min-h-[60px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y"
                            />
                        </div>

                        <div className="sm:col-span-2">
                            <ArchivosRequeridosBuilder
                                value={formArchivos}
                                onChange={(archivos) => {
                                    setFormArchivos(archivos);
                                    setFormArchivosError(null);
                                }}
                                error={formArchivosError ?? undefined}
                            />
                        </div>

                        {/* Porcentaje de nota del par de fases (RF-ENT-03/05) */}
                        <div className="flex flex-col gap-1.5 sm:col-span-2">
                            <label htmlFor="form-grade-percentage" className="text-sm font-semibold text-[#1c1917]">
                                Porcentaje de nota (%)
                                <span className="ml-1 text-xs font-normal text-[#a8a29e]">
                                    Peso del par de fases (0-100)
                                </span>
                            </label>
                            <input
                                id="form-grade-percentage"
                                type="number"
                                min={0}
                                max={100}
                                step={0.01}
                                value={formGradePercentage}
                                onChange={(e) => setFormGradePercentage(e.target.value)}
                                placeholder="Ej: 60"
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] tabular-nums"
                            />
                            {selectedGroup != null && (
                                <IndicadorSumaPar
                                    entregas={entregas}
                                    fase={formFase}
                                    valorForm={formGradePercentage}
                                />
                            )}
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label className="text-sm font-semibold text-[#1c1917]">
                                Fecha límite <span className="text-[#dc2626]">*</span>
                            </label>
                            <div className="relative">
                                <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                                <input
                                    type="date"
                                    value={formFecha}
                                    onChange={(e) => setFormFecha(e.target.value)}
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    required
                                />
                            </div>
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label className="text-sm font-semibold text-[#1c1917]">
                                Hora máxima
                            </label>
                            <input
                                type="time"
                                value={formHora}
                                onChange={(e) => setFormHora(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            />
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label className="text-sm font-semibold text-[#1c1917]">
                                Fecha de inicio
                            </label>
                            <div className="relative">
                                <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                                <input
                                    type="date"
                                    value={formFechaInicio}
                                    onChange={(e) => setFormFechaInicio(e.target.value)}
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                />
                            </div>
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label className="text-sm font-semibold text-[#1c1917]">
                                Hora de inicio
                            </label>
                            <input
                                type="time"
                                value={formHoraInicio}
                                onChange={(e) => setFormHoraInicio(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            />
                        </div>
                    </div>

                    {createError && (
                        <div className="mt-3 flex items-center gap-2 rounded-lg bg-[#fee2e2] px-4 py-2 text-sm text-[#dc2626]">
                            <AlertCircle className="h-4 w-4 shrink-0" />
                            {createError}
                        </div>
                    )}

                    {mutationError && !createError && (
                        <div className="mt-3 flex items-center gap-2 rounded-lg bg-[#fee2e2] px-4 py-2 text-sm text-[#dc2626]">
                            <AlertCircle className="h-4 w-4 shrink-0" />
                            {mutationError}
                        </div>
                    )}

                    <div className="mt-5 flex items-center gap-3">
                        <button
                            type="submit"
                            disabled={mutationLoading || !selectedGroup || creatingRef.current}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {mutationLoading ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Plus className="h-4 w-4" />
                            )}
                            {mutationLoading ? 'Creando...' : 'Crear Entrega'}
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setShowCreateForm(false);
                                setCreateError(null);
                            }}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                        >
                            Cancelar
                        </button>
                    </div>
                </form>
            )}

            {/* ── Error banner ────────────────────────────────── */}
            {error && (
                <div className="flex items-center gap-2 rounded-lg border border-[#fecaca] bg-[#fee2e2] px-4 py-3 text-sm text-[#dc2626]">
                    <AlertCircle className="h-4 w-4 shrink-0" />
                    {error}
                    <button
                        onClick={refetch}
                        className="ml-auto rounded-lg px-2 py-1 text-xs font-semibold text-[#dc2626] hover:bg-[#fecaca]"
                    >
                        Reintentar
                    </button>
                </div>
            )}

            {/* ── Loading skeleton ────────────────────────────── */}
            {loading && (
                <div className="flex flex-col gap-3">
                    {[1, 2, 3].map((i) => (
                        <div
                            key={i}
                            className="h-20 animate-pulse rounded-xl border border-[#e5e5e5] bg-[#f5f5f4]"
                        />
                    ))}
                </div>
            )}

            {/* ── Entregas cards ───────────────────────────────── */}
            {!loading && !error && (
                <div>
                    {filtered.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 py-16 text-center">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#f5f5f4]">
                                <FileText className="h-6 w-6 text-[#78716c]" />
                            </div>
                            <h3 className="text-base font-semibold text-[#1c1917]">
                                {selectedGroup ? 'Sin entregas' : 'Seleccione un grupo'}
                            </h3>
                            <p className="text-sm text-[#57534e]">
                                {selectedGroup
                                    ? 'No hay entregas para este grupo con los filtros actuales.'
                                    : 'Seleccione un grupo para ver sus entregas.'}
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {filtered.map((entrega) => (
                                <div
                                    key={entrega.id}
                                    className="flex flex-col rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)] transition-all hover:shadow-[0_4px_12px_rgba(28,25,23,0.08)]"
                                >
                                    {/* Card header */}
                                    <div className="border-b border-[#e5e5e5] px-4 py-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0 flex-1">
                                                <h3 className="truncate text-sm font-semibold text-[#1c1917]">
                                                    {entrega.title || '—'}
                                                </h3>
                                                <p className="text-xs text-[#78716c]">
                                                    {entrega.semestre_nombre ?? '—'}
                                                </p>
                                            </div>
                                            <StatusBadge variant="info">
                                                {FASE_LABELS[entrega.phase] ?? entrega.phase}
                                            </StatusBadge>
                                        </div>
                                    </div>

                                    {/* Card body */}
                                    <div className="flex flex-col gap-2 px-4 py-3 text-xs text-[#57534e]">
                                        {/* Fecha límite */}
                                        <div className="flex items-center gap-2">
                                            <Calendar className="h-3.5 w-3.5 shrink-0 text-[#78716c]" />
                                            <span>{formatDate(entrega.due_date)}</span>
                                        </div>

                                        {/* Fecha de inicio */}
                                        {entrega.start_date && (
                                            <div className="flex items-center gap-2">
                                                <Calendar className="h-3.5 w-3.5 shrink-0 text-[#78716c]" />
                                                <span className="text-xs text-[#16a34a]">Inicia: {formatDate(entrega.start_date)}</span>
                                                {entrega.start_time && (
                                                    <span className="text-xs text-[#16a34a]">{entrega.start_time}</span>
                                                )}
                                            </div>
                                        )}

                                        {/* Hora máxima */}
                                        {entrega.hora_maxima && (
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium text-[#78716c]">Hora máx.:</span>
                                                <span>{entrega.hora_maxima}</span>
                                            </div>
                                        )}

                                        {/* Semestre */}
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium text-[#78716c]">Semestre:</span>
                                            <span>{entrega.semestre_nombre || '—'}</span>
                                        </div>

                                        {/* Proyectos vinculados */}
                                        {entrega.proyectos_count != null && (
                                            <div className="group relative flex items-center gap-2">
                                                <span className="font-medium text-[#78716c]">Proyectos:</span>
                                                <span
                                                    className="cursor-help rounded-full bg-[#f5f5f4] px-2 py-0.5 text-xs font-semibold text-[#c2410c]"
                                                    title={
                                                        entrega.proyectos_list?.length
                                                            ? entrega.proyectos_list.join('\n')
                                                            : undefined
                                                    }
                                                >
                                                    {entrega.proyectos_count} vinculado{entrega.proyectos_count !== 1 ? 's' : ''}
                                                </span>
                                                {entrega.proyectos_list && entrega.proyectos_list.length > 0 && (
                                                    <div className="absolute bottom-full left-0 z-10 mb-2 hidden w-64 rounded-lg border border-[#e5e5e5] bg-white p-2 shadow-lg group-hover:block">
                                                        {entrega.proyectos_list.map((p, i) => (
                                                            <p key={i} className="truncate text-xs text-[#57534e]">
                                                                {p}
                                                            </p>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        {/* Descripción */}
                                        {entrega.description && (
                                            <div>
                                                <span className="font-medium text-[#78716c]">Descripción:</span>
                                                <p className="mt-0.5 line-clamp-2 text-[#1c1917]">
                                                    {entrega.description}
                                                </p>
                                            </div>
                                        )}

                                        {/* Criterios de aceptación */}
                                        {entrega.acceptance_criteria && (
                                            <div>
                                                <span className="font-medium text-[#78716c]">Criterios:</span>
                                                <p className="mt-0.5 line-clamp-2 text-[#1c1917]">
                                                    {entrega.acceptance_criteria}
                                                </p>
                                            </div>
                                        )}
                                    </div>

                                    {/* Card footer — acciones */}
                                    <div className="mt-auto flex items-center justify-end gap-1 border-t border-[#e5e5e5] px-4 py-2">
                                        <button
                                            type="button"
                                            onClick={() => openEditModal(entrega)}
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c]"
                                            aria-label="Editar entrega"
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setDeletingId(entrega.id)}
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626]"
                                            aria-label="Eliminar entrega"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {/* ── Edit Modal ──────────────────────────────────── */}
            {editingEntrega && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
                    <div className="w-full max-w-2xl rounded-xl border border-[#e5e5e5] bg-white shadow-xl">
                        <div className="mb-4 flex items-center justify-between px-6 pt-6">
                            <h3 className="text-base font-bold text-[#1c1917]">Editar Entrega</h3>
                            <button
                                type="button"
                                onClick={closeEditModal}
                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#78716c] transition-colors hover:bg-[#f5f5f4]"
                                aria-label="Cerrar"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <div className="flex flex-col gap-4 px-6 pb-6 max-h-[65vh] overflow-y-auto">
                            {/* Grupo (read-only) */}
                            <GroupSelector value={editGrupoId} onChange={() => {}} readonly error={undefined} />

                            {/* Título */}
                            <div className="flex flex-col gap-1.5">
                                <label className="text-sm font-semibold text-[#1c1917]">
                                    Título de la entrega <span className="text-[#dc2626]">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={editTitulo}
                                    onChange={(e) => setEditTitulo(e.target.value)}
                                    placeholder="Ej: Entrega parcial de anteproyecto"
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                />
                            </div>

                            {/* Fase */}
                            <div className="flex flex-col gap-1.5">
                                <label className="text-sm font-semibold text-[#1c1917]">
                                    Fase <span className="text-[#dc2626]">*</span>
                                </label>
                                <select
                                    value={editFase}
                                    onChange={(e) => setEditFase(e.target.value)}
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    aria-label="Fase de la entrega"
                                >
                                    {FASE_SEQUENCE.map((f) => (
                                        <option key={f} value={f}>
                                            {FASE_LABELS[f]}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Descripción */}
                            <div className="flex flex-col gap-1.5">
                                <label className="text-sm font-semibold text-[#1c1917]">
                                    Descripción <span className="text-[#dc2626]">*</span>
                                </label>
                                <textarea
                                    value={editDesc}
                                    onChange={(e) => setEditDesc(e.target.value)}
                                    rows={3}
                                    placeholder="Descripción de la entrega"
                                    className="w-full min-h-[60px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y"
                                />
                            </div>

                            {/* Criterios de aceptación */}
                            <div className="flex flex-col gap-1.5">
                                <label className="text-sm font-semibold text-[#1c1917]">
                                    Criterios de aceptación
                                </label>
                                <textarea
                                    value={editCriterios}
                                    onChange={(e) => setEditCriterios(e.target.value)}
                                    rows={3}
                                    placeholder="Criterios que debe cumplir la entrega para ser aprobada"
                                    className="w-full min-h-[60px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y"
                                />
                            </div>

                            {/* Archivos requeridos */}
                            <ArchivosRequeridosBuilder
                                value={editArchivos}
                                onChange={(archivos) => {
                                    setEditArchivos(archivos);
                                    setEditArchivosError(null);
                                }}
                                error={editArchivosError ?? undefined}
                            />

                            {/* Porcentaje de nota del par de fases (RF-ENT-03/05) */}
                            <div className="flex flex-col gap-1.5">
                                <label htmlFor="edit-grade-percentage" className="text-sm font-semibold text-[#1c1917]">
                                    Porcentaje de nota (%)
                                    <span className="ml-1 text-xs font-normal text-[#a8a29e]">
                                        Peso del par de fases (0-100)
                                    </span>
                                </label>
                                <input
                                    id="edit-grade-percentage"
                                    type="number"
                                    min={0}
                                    max={100}
                                    step={0.01}
                                    value={editGradePercentage}
                                    onChange={(e) => setEditGradePercentage(e.target.value)}
                                    placeholder="Ej: 60"
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] tabular-nums"
                                />
                                {editGrupoId != null && (
                                    <IndicadorSumaPar
                                        entregas={entregas}
                                        fase={editFase as Fase}
                                        valorForm={editGradePercentage}
                                        excluirId={editingEntrega?.id}
                                    />
                                )}
                            </div>

                            {/* Fecha límite */}
                            <div className="flex flex-col gap-1.5">
                                <label className="text-sm font-semibold text-[#1c1917]">
                                    Fecha límite <span className="text-[#dc2626]">*</span>
                                </label>
                                <div className="relative">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                                    <input
                                        type="date"
                                        value={editFecha}
                                        onChange={(e) => setEditFecha(e.target.value)}
                                        className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    />
                                </div>
                            </div>

                            {/* Hora máxima */}
                            <div className="flex flex-col gap-1.5">
                                <label className="text-sm font-semibold text-[#1c1917]">
                                    Hora máxima
                                </label>
                                <input
                                    type="time"
                                    value={editHora}
                                    onChange={(e) => setEditHora(e.target.value)}
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                />
                            </div>

                            {/* Fecha de inicio */}
                            <div className="flex flex-col gap-1.5">
                                <label className="text-sm font-semibold text-[#1c1917]">
                                    Fecha de inicio
                                </label>
                                <div className="relative">
                                    <Calendar className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                                    <input
                                        type="date"
                                        value={editFechaInicio}
                                        onChange={(e) => setEditFechaInicio(e.target.value)}
                                        className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    />
                                </div>
                            </div>

                            {/* Hora de inicio */}
                            <div className="flex flex-col gap-1.5">
                                <label className="text-sm font-semibold text-[#1c1917]">
                                    Hora de inicio
                                </label>
                                <input
                                    type="time"
                                    value={editHoraInicio}
                                    onChange={(e) => setEditHoraInicio(e.target.value)}
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                />
                            </div>
                        </div>

                        <div className="border-t border-[#e5e5e5] px-6 py-4">
                            {editError && (
                                <div className="mb-3 flex items-center gap-2 rounded-lg bg-[#fee2e2] px-4 py-2 text-sm text-[#dc2626]">
                                    <AlertCircle className="h-4 w-4 shrink-0" />
                                    {editError}
                                </div>
                            )}

                            {mutationError && !editError && (
                                <div className="mb-3 flex items-center gap-2 rounded-lg bg-[#fee2e2] px-4 py-2 text-sm text-[#dc2626]">
                                    <AlertCircle className="h-4 w-4 shrink-0" />
                                    {mutationError}
                                </div>
                            )}

                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={handleUpdate}
                                    disabled={mutationLoading}
                                    className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {mutationLoading ? (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <Pencil className="h-4 w-4" />
                                    )}
                                    Guardar Cambios
                                </button>
                                <button
                                    type="button"
                                    onClick={closeEditModal}
                                    className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* ── Delete Confirmation ─────────────────────────── */}
            {deletingId != null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
                    <div className="w-full max-w-sm rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-xl">
                        <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-[#fee2e2]">
                            <AlertTriangle className="h-5 w-5 text-[#dc2626]" />
                        </div>
                        <h3 className="mb-1 text-base font-bold text-[#1c1917]">Eliminar Entrega</h3>
                        <p className="mb-4 text-sm text-[#57534e]">
                            ¿Estás seguro de que deseas eliminar esta entrega? Esta acción no se puede deshacer.
                        </p>

                        {deleteError && (
                            <div className="mb-3 flex items-center gap-2 rounded-lg bg-[#fee2e2] px-4 py-2 text-sm text-[#dc2626]">
                                <AlertCircle className="h-4 w-4 shrink-0" />
                                {deleteError}
                            </div>
                        )}

                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={confirmDelete}
                                disabled={mutationLoading}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#dc2626] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#b91c1c] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {mutationLoading ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Trash2 className="h-4 w-4" />
                                )}
                                Eliminar
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setDeletingId(null);
                                    setDeleteError(null);
                                }}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
