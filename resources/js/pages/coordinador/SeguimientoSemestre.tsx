import { useState, useCallback, useMemo } from 'react';
import { apiFetch, cn } from '@/lib/utils';
import { PageHeader } from '@/components/ui/PageHeader';
import { EmptyState } from '@/components/ui/EmptyState';
import { SemestreSelector } from '@/components/seguimiento/SemestreSelector';
import {
    useSeguimientoSemestre,
    type ProyectoSeguimiento,
    type FaseEntregas,
    type EntregaItem,
} from '@/hooks/useSeguimientoSemestre';
import {
    Loader2,
    AlertCircle,
    RefreshCw,
    ChevronRight,
    ChevronDown,
    Save,
    CheckCircle2,
    XCircle,
    Clock,
    FileText,
    EyeOff,
} from 'lucide-react';

/* ------------------------------------------------------------------ */
/*  Constants                                                          */
/* ------------------------------------------------------------------ */

const estadoConfig = {
    entregado: {
        label: 'Entregado',
        icon: CheckCircle2,
        cls: 'bg-[#f0fdf4] text-[#15803d] border-[#bbf7d0]',
    },
    pendiente: {
        label: 'Pendiente',
        icon: Clock,
        cls: 'bg-[#fffbeb] text-[#b45309] border-[#fde68a]',
    },
    no_entrego: {
        label: 'No entregó',
        icon: XCircle,
        cls: 'bg-[#fef2f2] text-[#b91c1c] border-[#fecaca]',
    },
} as const;

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

function getObservacion(proy: ProyectoSeguimiento, fase: string): string {
    return (
        proy.observaciones.find((o) => o.fase === fase)?.contenido ?? ''
    );
}

/* ------------------------------------------------------------------ */
/*  Subcomponents                                                      */
/* ------------------------------------------------------------------ */

function EstadoCell({ estado }: { estado: EntregaItem['estado'] }) {
    const cfg = estadoConfig[estado] ?? estadoConfig.pendiente;
    const Icon = cfg.icon;
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide shadow-sm',
                cfg.cls,
            )}
        >
            <Icon className="h-3 w-3 shrink-0" />
            {cfg.label}
        </span>
    );
}

interface PhaseHeaderProps {
    fase: FaseEntregas;
    collapsed: boolean;
    onToggle: () => void;
}

function PhaseHeader({ fase, collapsed, onToggle }: PhaseHeaderProps) {
    return (
        <th
            className={cn(
                'border-l border-[#e5e5e5] p-0 align-middle text-center',
                collapsed ? 'w-8' : 'min-w-[140px]',
            )}
        >
            <button
                type="button"
                onClick={onToggle}
                aria-label={
                    collapsed
                        ? `Expandir ${fase.fase}`
                        : `Contraer ${fase.fase}`
                }
                title={fase.fase}
                className={cn(
                    'flex w-full items-center justify-center gap-1 px-2 py-3 text-[10px] font-bold uppercase tracking-wider text-[#57534e] transition-colors',
                    'hover:bg-[#fafaf9] hover:text-[#1c1917]',
                )}
            >
                {collapsed ? (
                    <ChevronRight className="h-3.5 w-3.5 shrink-0" />
                ) : (
                    <>
                        <ChevronDown className="h-3.5 w-3.5 shrink-0" />
                        <span className="whitespace-nowrap">
                            {fase.fase}
                        </span>
                    </>
                )}
            </button>
        </th>
    );
}

function PhaseCell({ entregas }: { entregas: EntregaItem[] }) {
    if (entregas.length === 0) {
        return (
            <div className="flex min-h-[48px] items-center justify-center text-[11px] text-[#a8a29e]">
                —
            </div>
        );
    }
    return (
        <div className="flex flex-col gap-2">
            {entregas.map((ent) => (
                <div
                    key={ent.id}
                    className="flex flex-col items-center gap-1"
                >
                    <span
                        className="text-[10px] font-semibold text-[#57534e] text-center leading-tight truncate max-w-[120px]"
                        title={ent.title}
                    >
                        {ent.title}
                    </span>
                    <EstadoCell estado={ent.estado} />
                </div>
            ))}
        </div>
    );
}

function BitacorasCell({ count }: { count: number }) {
    return (
        <span className="inline-flex items-center gap-0.5 rounded-full bg-[#fed7aa] px-2.5 py-0.5 text-[11px] font-bold tabular-nums text-[#c2410c]">
            {count}
            <span className="text-[10px] font-semibold text-[#c2410c]/70">
                /16
            </span>
        </span>
    );
}

interface ObservationsPanelProps {
    proyecto: ProyectoSeguimiento;
    selectedSemestre: number;
    onSaved: () => void;
}

function ObservationsPanel({
    proyecto,
    selectedSemestre,
    onSaved,
}: ObservationsPanelProps) {
    const [drafts, setDrafts] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState<Record<string, boolean>>({});

    const handleSave = useCallback(
        async (fase: string) => {
            const value = drafts[fase];
            if (value === undefined) return;

            setSaving((prev) => ({ ...prev, [fase]: true }));
            try {
                const res = await apiFetch(
                    '/api/admin/seguimiento/observaciones',
                    {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            proyecto_id: proyecto.id,
                            semestre_id: selectedSemestre,
                            fase,
                            observacion: value,
                        }),
                    },
                );
                if (!res.ok) throw new Error('Error al guardar');
                setDrafts((prev) => {
                    const next = { ...prev };
                    delete next[fase];
                    return next;
                });
                onSaved();
            } catch (err) {
                console.error('Error saving observation:', err);
            } finally {
                setSaving((prev) => ({ ...prev, [fase]: false }));
            }
        },
        [drafts, proyecto.id, selectedSemestre, onSaved],
    );

    return (
        <div className="rounded-lg border border-[#e5e5e5] bg-white shadow-sm">
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-[#e5e5e5] bg-[#f5f5f4] px-4 py-2">
                <span className="text-sm font-semibold text-[#1c1917]">
                    Observaciones
                </span>
                <span className="text-xs text-[#a8a29e]">—</span>
                <span className="text-sm font-medium text-[#1c1917]">
                    {proyecto.proyecto_nombre}
                </span>
                <span className="rounded bg-white px-1.5 py-0.5 font-mono text-[10px] uppercase tracking-wide text-[#78716c]">
                    {proyecto.proyecto_codigo}
                </span>
            </div>
            <div className="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2">
                {proyecto.fases.map((fase) => {
                    const draftVal =
                        drafts[fase.key] ??
                        getObservacion(proyecto, fase.key);
                    const savedVal = getObservacion(proyecto, fase.key);
                    const isSaving = saving[fase.key];
                    const dirty = draftVal !== savedVal;
                    const fieldId = `obs-${proyecto.id}-${fase.key}`;
                    return (
                        <div
                            key={fase.key}
                            className="flex flex-col gap-1.5"
                        >
                            <label
                                htmlFor={fieldId}
                                className="text-[10px] font-bold uppercase tracking-wider text-[#57534e]"
                            >
                                {fase.fase}
                            </label>
                            <div className="flex gap-1.5">
                                <textarea
                                    id={fieldId}
                                    value={draftVal}
                                    onChange={(e) =>
                                        setDrafts((prev) => ({
                                            ...prev,
                                            [fase.key]: e.target.value,
                                        }))
                                    }
                                    rows={2}
                                    placeholder="Sin observaciones..."
                                    className="min-h-[36px] flex-1 resize-y rounded-md border border-[#e5e5e5] bg-white px-2.5 py-1.5 text-xs leading-snug text-[#1c1917] placeholder:text-[#a8a29e] transition-colors hover:border-[#c2410c] focus:border-[#c2410c] focus:outline-none focus:ring-2 focus:ring-[#c2410c]/20"
                                />
                                <button
                                    type="button"
                                    onClick={() => handleSave(fase.key)}
                                    disabled={!dirty || isSaving}
                                    aria-label={`Guardar observación de ${fase.fase}`}
                                    className={cn(
                                        'inline-flex h-[36px] w-[36px] shrink-0 items-center justify-center rounded-md text-white shadow-sm transition-all',
                                        dirty && !isSaving
                                            ? 'bg-[#c2410c] hover:bg-[#9a3412] active:scale-[0.96]'
                                            : 'bg-[#e5e5e5] text-[#a8a29e] cursor-not-allowed',
                                    )}
                                >
                                    {isSaving ? (
                                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                    ) : (
                                        <Save className="h-3.5 w-3.5" />
                                    )}
                                </button>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/*  Main component                                                     */
/* ------------------------------------------------------------------ */

export interface SeguimientoSemestreProps {
    showHeader?: boolean;
}

export default function SeguimientoSemestre({
    showHeader = true,
}: SeguimientoSemestreProps) {
    const [selectedSemestre, setSelectedSemestre] = useState<number | null>(
        null,
    );
    const { data, loading, error, refetch } =
        useSeguimientoSemestre(selectedSemestre);
    const [expandedProject, setExpandedProject] = useState<number | null>(
        null,
    );
    const [collapsedPhases, setCollapsedPhases] = useState<Set<string>>(
        new Set(),
    );

    const togglePhase = useCallback((faseKey: string) => {
        setCollapsedPhases((prev) => {
            const next = new Set(prev);
            if (next.has(faseKey)) next.delete(faseKey);
            else next.add(faseKey);
            return next;
        });
    }, []);

    const proyectos = data?.proyectos ?? [];

    const canonicalPhases: FaseEntregas[] = useMemo(
        () => proyectos[0]?.fases ?? [],
        [proyectos],
    );

    const expandedProyecto = useMemo(
        () =>
            expandedProject === null
                ? null
                : proyectos.find((p) => p.id === expandedProject) ?? null,
        [proyectos, expandedProject],
    );

    const hasSemestre = selectedSemestre !== null;
    const hasProyectos = proyectos.length > 0;
    const canRenderTable = hasSemestre && !loading && !error && data;

    return (
        <div className="flex flex-col gap-6">
            {showHeader && (
                <PageHeader
                    eyebrow="Coordinación"
                    title="Seguimiento por Semestre"
                    subtitle="Monitoreo del avance de proyectos por semestre académico"
                    actions={
                        selectedSemestre ? (
                            <button
                                type="button"
                                onClick={refetch}
                                disabled={loading}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#fafaf9] active:scale-[0.98] disabled:opacity-60"
                            >
                                <RefreshCw
                                    className={cn(
                                        'h-4 w-4',
                                        loading && 'animate-spin',
                                    )}
                                />
                                Refrescar
                            </button>
                        ) : null
                    }
                />
            )}

            <div className="flex max-w-md flex-col gap-1.5">
                <label
                    htmlFor="semestre-trigger"
                    className="text-xs font-semibold text-[#57534e]"
                >
                    Semestre académico
                </label>
                <SemestreSelector
                    value={selectedSemestre}
                    onChange={setSelectedSemestre}
                />
            </div>

            {error && (
                <div
                    role="alert"
                    className="flex items-center gap-2 rounded-lg border border-[#fecaca] bg-[#fef2f2] px-4 py-3 text-sm text-[#b91c1c]"
                >
                    <AlertCircle className="h-4 w-4 shrink-0" />
                    <span className="flex-1">{error}</span>
                    <button
                        type="button"
                        onClick={refetch}
                        className="rounded-md px-2 py-1 text-xs font-semibold text-[#b91c1c] hover:bg-[#fecaca]"
                    >
                        Reintentar
                    </button>
                </div>
            )}

            {loading && (
                <div className="flex flex-col gap-3" aria-busy="true">
                    {[1, 2, 3].map((i) => (
                        <div
                            key={i}
                            className="h-36 animate-pulse rounded-xl border border-[#e5e5e5] bg-[#fafaf9]"
                        />
                    ))}
                </div>
            )}

            {!hasSemestre && !loading && (
                <EmptyState
                    icon={EyeOff}
                    title="Selecciona un semestre"
                    description="Elige un semestre académico para ver el seguimiento de proyectos."
                />
            )}

            {canRenderTable && !hasProyectos && (
                <EmptyState
                    icon={FileText}
                    title="Sin proyectos"
                    description="No hay proyectos registrados para este semestre."
                />
            )}

            {canRenderTable && hasProyectos && (
                <>
                    <div className="overflow-x-auto rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_3px_rgba(28,25,23,0.08),0_1px_2px_rgba(28,25,23,0.06)]">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-[#e5e5e5] bg-[#fafaf9] text-[#1c1917]">
                                <tr>
                                    <th
                                        scope="col"
                                        className="sticky left-0 z-10 whitespace-nowrap bg-[#fafaf9] px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider"
                                    >
                                        Estudiantes
                                    </th>
                                    <th
                                        scope="col"
                                        className="whitespace-nowrap px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider"
                                    >
                                        Proyecto
                                    </th>
                                    <th
                                        scope="col"
                                        className="whitespace-nowrap px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider"
                                    >
                                        Director
                                    </th>
                                    {canonicalPhases.map((fase) => (
                                        <PhaseHeader
                                            key={fase.key}
                                            fase={fase}
                                            collapsed={collapsedPhases.has(
                                                fase.key,
                                            )}
                                            onToggle={() =>
                                                togglePhase(fase.key)
                                            }
                                        />
                                    ))}
                                    <th
                                        scope="col"
                                        className="border-l border-[#e5e5e5] whitespace-nowrap px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wider"
                                    >
                                        <div className="flex items-center justify-center gap-1.5">
                                            <FileText className="h-3 w-3" />
                                            Bitácoras PG1
                                        </div>
                                    </th>
                                    <th
                                        scope="col"
                                        className="border-l border-[#e5e5e5] whitespace-nowrap px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wider"
                                    >
                                        <div className="flex items-center justify-center gap-1.5">
                                            <FileText className="h-3 w-3" />
                                            Bitácoras PG2
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {proyectos.map((proy) => {
                                    const isExpanded =
                                        expandedProject === proy.id;
                                    return (
                                        <tr
                                            key={proy.id}
                                            className="group border-b border-[#e5e5e5] transition-colors hover:bg-[#fafaf9] last:border-b-0"
                                        >
                                            <td className="sticky left-0 z-10 border-r border-[#e5e5e5] bg-white px-4 py-3 align-top transition-colors group-hover:bg-[#fafaf9]">
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setExpandedProject(
                                                                isExpanded
                                                                    ? null
                                                                    : proy.id,
                                                            )
                                                        }
                                                        aria-expanded={
                                                            isExpanded
                                                        }
                                                        aria-label={
                                                            isExpanded
                                                                ? 'Contraer observaciones'
                                                                : 'Expandir observaciones'
                                                        }
                                                        className="shrink-0 rounded p-0.5 text-[#78716c] transition-colors hover:bg-[#fed7aa] hover:text-[#c2410c]"
                                                    >
                                                        {isExpanded ? (
                                                            <ChevronDown className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronRight className="h-4 w-4" />
                                                        )}
                                                    </button>
                                                    <span className="text-sm font-medium text-[#1c1917]">
                                                        {proy.estudiantes}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 align-top">
                                                <div className="flex flex-col">
                                                    <span className="text-sm font-medium text-[#1c1917]">
                                                        {
                                                            proy.proyecto_nombre
                                                        }
                                                    </span>
                                                    <span className="text-xs text-[#78716c]">
                                                        {
                                                            proy.proyecto_codigo
                                                        }
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 align-top text-sm text-[#1c1917]">
                                                {proy.director}
                                            </td>
                                            {canonicalPhases.map((fase) => {
                                                if (
                                                    collapsedPhases.has(
                                                        fase.key,
                                                    )
                                                ) {
                                                    return (
                                                        <td
                                                            key={fase.key}
                                                            className="w-0 p-0 overflow-hidden border-l border-[#e5e5e5]"
                                                        />
                                                    );
                                                }
                                                const proyFase =
                                                    proy.fases.find(
                                                        (f) =>
                                                            f.key ===
                                                            fase.key,
                                                    );
                                                return (
                                                    <td
                                                        key={fase.key}
                                                        className="border-l border-[#e5e5e5] px-3 py-3 align-top"
                                                    >
                                                        <PhaseCell
                                                            entregas={
                                                                proyFase?.entregas ??
                                                                []
                                                            }
                                                        />
                                                    </td>
                                                );
                                            })}
                                            <td className="border-l border-[#e5e5e5] px-4 py-3 text-center align-top">
                                                <BitacorasCell
                                                    count={
                                                        proy.bitacoras_grupo_a
                                                    }
                                                />
                                            </td>
                                            <td className="border-l border-[#e5e5e5] px-4 py-3 text-center align-top">
                                                <BitacorasCell
                                                    count={
                                                        proy.bitacoras_grupo_b
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {expandedProyecto && selectedSemestre && (
                        <ObservationsPanel
                            proyecto={expandedProyecto}
                            selectedSemestre={selectedSemestre}
                            onSaved={refetch}
                        />
                    )}
                </>
            )}
        </div>
    );
}
