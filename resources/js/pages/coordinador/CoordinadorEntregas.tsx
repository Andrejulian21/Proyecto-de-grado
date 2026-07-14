import { useState, useCallback, useEffect } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { GroupSelector } from '@/components/forms/GroupSelector';
import { useEntregas, FASE_SEQUENCE, type Fase, type Entrega } from '@/hooks/useEntregas';
import {
    Search,
    FileText,
    Plus,
    Loader2,
    ChevronDown,
    ChevronRight,
    Calendar,
    Clock,
    AlertCircle,
    Trash2,
} from 'lucide-react';

const FASE_LABELS: Record<Fase, string> = {
    anteproyecto: 'Anteproyecto',
    presentacion_anteproyecto: 'Presentación Anteproyecto',
    desarrollo: 'Desarrollo',
    presentacion_final: 'Presentación Final',
};

export default function CoordinadorEntregas() {
    const [selectedGroup, setSelectedGroup] = useState<number | null>(null);
    const [faseFilter, setFaseFilter] = useState<string>('');
    const [searchQuery, setSearchQuery] = useState('');
    const [showCreateForm, setShowCreateForm] = useState(false);

    const { data: entregas, loading, error, refetch, crear, mutationLoading, mutationError, getNextFase } =
        useEntregas(
            selectedGroup != null
                ? { grupo_id: selectedGroup, fase: faseFilter || null }
                : undefined,
        );

    // Determine next fase when group changes
    const [nextFase, setNextFase] = useState<Fase>('anteproyecto');
    useEffect(() => {
        if (selectedGroup != null) {
            setNextFase(getNextFase(selectedGroup));
        } else {
            setNextFase('anteproyecto');
        }
    }, [selectedGroup, getNextFase]);

    // Create form state
    const [formFase, setFormFase] = useState<Fase>('anteproyecto');
    const [formDesc, setFormDesc] = useState('');
    const [formFecha, setFormFecha] = useState('');
    const [formHora, setFormHora] = useState('');
    const [formCriterios, setFormCriterios] = useState('');
    const [createError, setCreateError] = useState<string | null>(null);

    // Update form fase when nextFase changes
    useEffect(() => {
        setFormFase(nextFase);
    }, [nextFase]);

    const handleCreate = useCallback(
        async (e: React.FormEvent) => {
            e.preventDefault();
            if (!selectedGroup || !formDesc.trim() || !formFecha) return;
            setCreateError(null);
            try {
                await crear({
                    grupo_id: selectedGroup,
                    fase: formFase,
                    descripcion: formDesc.trim(),
                    fecha_limite: formFecha,
                    hora_maxima: formHora || undefined,
                    criterios_aceptacion: formCriterios.trim() || undefined,
                });
                setFormDesc('');
                setFormFecha('');
                setFormHora('');
                setFormCriterios('');
                setShowCreateForm(false);
            } catch (err) {
                setCreateError(err instanceof Error ? err.message : 'Error al crear entrega');
            }
        },
        [selectedGroup, formFase, formDesc, formFecha, formHora, formCriterios, crear],
    );

    const filtered = entregas.filter((e) => {
        if (searchQuery) {
            const q = searchQuery.toLowerCase();
            const matchDesc = e.descripcion?.toLowerCase().includes(q) ?? false;
            const matchFase = FASE_LABELS[e.fase]?.toLowerCase().includes(q) ?? false;
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

            {/* Filters row */}
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

                {/* Search */}
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

            {/* Create form */}
            {showCreateForm && (
                <form
                    onSubmit={handleCreate}
                    className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]"
                >
                    <h3 className="mb-4 text-base font-bold text-[#1c1917]">Nueva Entrega</h3>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {/* Group (read-only) */}
                        <div className="sm:col-span-2">
                            <GroupSelector
                                value={selectedGroup}
                                onChange={setSelectedGroup}
                                error={undefined}
                            />
                        </div>

                        {/* Phase (auto-computed) */}
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

                        {/* Date */}
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

                        {/* Hora máxima */}
                        <div className="flex flex-col gap-1.5">
                            <label className="text-sm font-semibold text-[#1c1917]">Hora máxima (opcional)</label>
                            <div className="relative">
                                <Clock className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                                <input
                                    type="time"
                                    value={formHora}
                                    onChange={(e) => setFormHora(e.target.value)}
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                />
                            </div>
                        </div>

                        {/* Descripción */}
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

                        {/* Criterios de aceptación */}
                        <div className="flex flex-col gap-1.5 sm:col-span-2">
                            <label className="text-sm font-semibold text-[#1c1917]">
                                Criterios de aceptación (opcional)
                            </label>
                            <textarea
                                value={formCriterios}
                                onChange={(e) => setFormCriterios(e.target.value)}
                                rows={2}
                                placeholder="Criterios que debe cumplir la entrega"
                                className="w-full min-h-[60px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y"
                            />
                        </div>
                    </div>

                    {createError && (
                        <div className="mt-3 flex items-center gap-2 rounded-lg bg-[#fee2e2] px-4 py-2 text-sm text-[#dc2626]">
                            <AlertCircle className="h-4 w-4 shrink-0" />
                            {createError}
                        </div>
                    )}

                    {mutationError && (
                        <div className="mt-3 flex items-center gap-2 rounded-lg bg-[#fee2e2] px-4 py-2 text-sm text-[#dc2626]">
                            <AlertCircle className="h-4 w-4 shrink-0" />
                            {mutationError}
                        </div>
                    )}

                    <div className="mt-5 flex items-center gap-3">
                        <button
                            type="submit"
                            disabled={mutationLoading || !selectedGroup}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {mutationLoading ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Plus className="h-4 w-4" />
                            )}
                            Crear Entrega
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

            {/* Error banner */}
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

            {/* Loading skeleton */}
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

            {/* Entregas table */}
            {!loading && !error && (
                <div className="overflow-hidden rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
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
                        <table className="w-full" aria-describedby="tabla-entregas">
                            <caption id="tabla-entregas" className="sr-only">
                                Lista de entregas por grupo y fase
                            </caption>
                            <thead>
                                <tr className="border-b border-[#e5e5e5] bg-[#fafaf9]">
                                    <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-[0.05em] text-[#78716c]">
                                        Fase
                                    </th>
                                    <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-[0.05em] text-[#78716c]">
                                        Descripción
                                    </th>
                                    <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-[0.05em] text-[#78716c]">
                                        Fecha Límite
                                    </th>
                                    <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-[0.05em] text-[#78716c]">
                                        Grupo ID
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#e5e5e5]">
                                {filtered.map((entrega) => (
                                    <tr
                                        key={entrega.id}
                                        className="transition-colors hover:bg-[#fafaf9]"
                                    >
                                        <td className="px-5 py-4">
                                            <StatusBadge variant="info">
                                                {FASE_LABELS[entrega.fase] ?? entrega.fase}
                                            </StatusBadge>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-col gap-0.5">
                                                <span className="text-sm font-medium text-[#1c1917]">
                                                    {entrega.descripcion || '—'}
                                                </span>
                                                {entrega.criterios_aceptacion && (
                                                    <span className="text-xs text-[#57534e] line-clamp-1">
                                                        {entrega.criterios_aceptacion}
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-2 text-sm text-[#57534e]">
                                                <Calendar className="h-3.5 w-3.5 text-[#78716c]" />
                                                {entrega.fecha_limite}
                                                {entrega.hora_maxima && (
                                                    <>
                                                        <Clock className="ml-1 h-3.5 w-3.5 text-[#78716c]" />
                                                        {entrega.hora_maxima}
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 text-sm text-[#57534e] tabular-nums">
                                            #{entrega.grupo_id}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            )}
        </div>
    );
}
