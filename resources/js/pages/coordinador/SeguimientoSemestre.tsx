import { useState, useCallback } from 'react';
import { apiFetch, cn } from '@/lib/utils';
import { PageHeader } from '@/components/ui/PageHeader';
import { EmptyState } from '@/components/ui/EmptyState';
import { SemestreSelector } from '@/components/seguimiento/SemestreSelector';

export interface SeguimientoSemestreProps {
    showHeader?: boolean;
}
import {
    useSeguimientoSemestre,
    type ProyectoSeguimiento,
    type FaseEntregas,
} from '@/hooks/useSeguimientoSemestre';
import {
    Loader2,
    AlertCircle,
    RefreshCw,
    ChevronDown,
    ChevronRight,
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
        color: 'text-[#16a34a]',
        bg: 'bg-[#f0fdf4]',
        border: 'border-[#bbf7d0]',
    } as const,
    pendiente: {
        label: 'Pendiente',
        icon: Clock,
        color: 'text-[#d97706]',
        bg: 'bg-[#fffbeb]',
        border: 'border-[#fde68a]',
    } as const,
    no_entrego: {
        label: 'No entregó',
        icon: XCircle,
        color: 'text-[#dc2626]',
        bg: 'bg-[#fef2f2]',
        border: 'border-[#fecaca]',
    } as const,
} as const;

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

function getObservacion(
    proy: ProyectoSeguimiento,
    faseKey: string,
): string {
    const obs = proy.observaciones.find((o) => o.fase === faseKey);
    return obs?.contenido ?? '';
}

/* ------------------------------------------------------------------ */
/*  Status cell                                                        */
/* ------------------------------------------------------------------ */

function EstadoCell({ estado }: { estado: string }) {
    const cfg =
        estadoConfig[estado as keyof typeof estadoConfig] ??
        estadoConfig.pendiente;
    const Icon = cfg.icon;
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.03em] shadow-sm',
                cfg.bg,
                cfg.color,
                cfg.border,
            )}
        >
            <Icon className="h-3.5 w-3.5 shrink-0" />
            {cfg.label}
        </span>
    );
}

/* ------------------------------------------------------------------ */
/*  Component                                                          */
/* ------------------------------------------------------------------ */

export default function SeguimientoSemestre({ showHeader = true }: SeguimientoSemestreProps) {
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

    // Observations editing state: key = `${proyectoId}-${faseKey}`
    const [obsDraft, setObsDraft] = useState<Record<string, string>>({});
    const [savingObs, setSavingObs] = useState<Record<string, boolean>>({});

    const togglePhase = (faseKey: string) => {
        setCollapsedPhases((prev) => {
            const next = new Set(prev);
            if (next.has(faseKey)) next.delete(faseKey);
            else next.add(faseKey);
            return next;
        });
    };

    const handleObsChange = useCallback(
        (proyId: number, faseKey: string, value: string) => {
            const editKey = proyId + '-' + faseKey;
            setObsDraft((prev) => ({
                ...prev,
                [editKey]: value,
            }));
        },
        [],
    );

    const saveObservation = useCallback(
        async (proyId: number, faseKey: string) => {
            const key = `${proyId}-${faseKey}`;
            const contenido = obsDraft[key];
            if (contenido === undefined) return;

            setSavingObs((prev) => ({ ...prev, [key]: true }));

            try {
                const res = await apiFetch(
                    '/api/admin/seguimiento/observaciones',
                    {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            proyecto_id: proyId,
                            semestre_id: selectedSemestre,
                            fase: faseKey,
                            observacion: contenido,
                        }),
                    },
                );
                if (!res.ok) throw new Error('Error al guardar observación');

                // Clear draft and refetch to get fresh data with saved observation
                setObsDraft((prev) => {
                    const next = { ...prev };
                    delete next[key];
                    return next;
                });
                refetch();
            } catch (err) {
                console.error(err);
            } finally {
                setSavingObs((prev) => ({ ...prev, [key]: false }));
            }
        },
        [obsDraft, selectedSemestre, refetch],
    );

    // Derive canonical column layout from the first project (safe assumption)
    const canonicalPhases: FaseEntregas[] =
        data?.proyectos?.[0]?.fases ?? [];

    return (
        <div className="flex flex-col gap-6">
            {showHeader && (
                <PageHeader
                    eyebrow="Coordinación"
                    title="Seguimiento por Semestre"
                    subtitle="Monitoreo del avance de proyectos por semestre académico"
                    actions={
                        selectedSemestre && (
                            <button
                                onClick={refetch}
                                disabled={loading}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98] disabled:opacity-60"
                                aria-label="Refrescar datos"
                            >
                                <RefreshCw
                                    className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`}
                                />
                                Refrescar
                            </button>
                        )
                    }
                />
            )}

            {/* Semester selector */}
            <div className="w-full max-w-xs">
                <label className="mb-1.5 block text-xs font-semibold text-[#57534e]">
                    Semestre académico
                </label>
                <SemestreSelector
                    value={selectedSemestre}
                    onChange={setSelectedSemestre}
                />
            </div>

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
                            className="h-36 animate-pulse rounded-xl border border-[#e5e5e5] bg-[#f5f5f4]"
                        />
                    ))}
                </div>
            )}

            {/* No semester selected */}
            {!selectedSemestre && !loading && (
                <EmptyState
                    icon={EyeOff}
                    title="Selecciona un semestre"
                    description="Elige un semestre académico para ver el seguimiento de proyectos."
                />
            )}

            {/* Data table */}
            {selectedSemestre &&
                !loading &&
                !error &&
                data &&
                (data.proyectos?.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title="Sin proyectos"
                        description="No hay proyectos registrados para este semestre."
                    />
                ) : (
                    <div className="w-full overflow-x-auto rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_3px_rgba(28,25,23,0.08),0_1px_2px_rgba(28,25,23,0.06)]">
                        <table className="w-full text-left text-sm tabular-nums">
                            {/* ============= HEAD ============= */}
                            <thead className="bg-[#f5f5f4] text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                {/* Row 1: group headers */}
                                <tr>
                                    <th className="sticky left-0 z-10 whitespace-nowrap bg-[#f5f5f4] px-4 py-3 text-left">
                                        Estudiantes
                                    </th>
                                    <th className="whitespace-nowrap px-4 py-3 text-left">
                                        Proyecto
                                    </th>
                                    <th className="whitespace-nowrap px-4 py-3 text-left">
                                        Director
                                    </th>

                                    {canonicalPhases.map((fase) => {
                                        const collapsed =
                                            collapsedPhases.has(fase.key);
                                        return (
                                            <th
                                                key={fase.key}
                                                className={cn(
                                                    'border-l border-[#e5e5e5] p-0 align-top',
                                                    collapsed && 'w-0 overflow-hidden',
                                                )}
                                            >
                                                <button
                                                    onClick={() =>
                                                        togglePhase(fase.key)
                                                    }
                                                    className="flex w-full items-center gap-1 px-2 py-3 text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e] whitespace-nowrap transition-colors hover:bg-[#e7e5e4]"
                                                >
                                                    {collapsed ? (
                                                        <ChevronRight className="h-3 w-3 shrink-0" />
                                                    ) : (
                                                        <ChevronDown className="h-3 w-3 shrink-0" />
                                                    )}
                                                    {collapsed ? '' : fase.fase}
                                                </button>
                                            </th>
                                        );
                                    })}

                                    <th className="border-l border-[#e5e5e5] px-4 py-3 text-center">
                                        <div className="flex items-center justify-center gap-1">
                                            <FileText className="h-3 w-3" />
                                            Bitacoras Proyecto de Grado 1
                                        </div>
                                    </th>
                                    <th className="border-l border-[#e5e5e5] px-4 py-3 text-center">
                                        <div className="flex items-center justify-center gap-1">
                                            <FileText className="h-3 w-3" />
                                            Bitacoras Proyecto de Grado 2
                                        </div>
                                    </th>
                                </tr>
                            </thead>

                            {/* ============= BODY ============= */}
                            <tbody>
                                {data.proyectos?.map((proy) => {
                                    const isExpanded =
                                        expandedProject === proy.id;

                                    return (
                                        <tr
                                            key={proy.id}
                                            className="group border-b border-[#e5e5e5] transition-colors hover:bg-[#fafaf9] last:border-b-0"
                                        >
                                            {/* Estudiantes */}
                                            <td className="sticky left-0 z-10 border-r border-[#e5e5e5] bg-white px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        onClick={() =>
                                                            setExpandedProject(
                                                                isExpanded
                                                                    ? null
                                                                    : proy.id,
                                                            )
                                                        }
                                                        className="shrink-0 text-[#78716c] transition-colors hover:text-[#1c1917]"
                                                        aria-label={
                                                            isExpanded
                                                                ? 'Contraer'
                                                                : 'Expandir'
                                                        }
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

                                            {/* Proyecto */}
                                            <td className="px-4 py-3">
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

                                            {/* Director */}
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-[#1c1917]">
                                                {proy.director}
                                            </td>

                                            {/* Phase columns: entregas stacked per phase */}
                                            {canonicalPhases.map((fase) => {
                                                if (collapsedPhases.has(fase.key)) return null;
                                                const proyFase =
                                                    proy.fases.find(
                                                        (f) =>
                                                            f.key ===
                                                            fase.key,
                                                    );
                                                const entregas =
                                                    proyFase?.entregas ?? [];
                                                return (
                                                    <td
                                                        key={fase.key}
                                                        className="border-l border-[#e5e5e5] px-3 py-3 align-top"
                                                    >
                                                        <div className="flex flex-col gap-2">
                                                            {entregas.map((ent) => (
                                                                <div key={ent.id} className="flex flex-col items-center gap-0.5">
                                                                    <span className="text-[9px] font-semibold uppercase tracking-[0.05em] text-[#78716c] text-center leading-tight max-w-[100px] truncate" title={ent.title}>
                                                                        {ent.title}
                                                                    </span>
                                                                    <EstadoCell estado={ent.estado} />
                                                                </div>
                                                            ))}
                                                            {entregas.length === 0 && (
                                                                <span className="text-[10px] text-[#a8a29e] text-center">—</span>
                                                            )}
                                                        </div>
                                                    </td>
                                                );
                                            })}

                                            {/* Proyecto de Grado 1 (semana 1-16) */}
                                            <td className="border-l border-[#e5e5e5] px-4 py-3 text-center">
                                                <span className="inline-flex items-center gap-1 rounded-full bg-[#e0e7ff] px-2.5 py-0.5 text-[11px] font-bold text-[#312e81]">
                                                    {proy.bitacoras_grupo_a}
                                                    /16
                                                </span>
                                            </td>

                                            {/* Proyecto de Grado 2 (semana 17-32) */}
                                            <td className="border-l border-[#e5e5e5] px-4 py-3 text-center">
                                                <span className="inline-flex items-center gap-1 rounded-full bg-[#e0e7ff] px-2.5 py-0.5 text-[11px] font-bold text-[#312e81]">
                                                    {proy.bitacoras_grupo_b}
                                                    /16
                                                </span>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ))}

            {/* ============= Observaciones ============= */}
            {data &&
                expandedProject !== null &&
                (() => {
                    const proy = data.proyectos?.find(
                        (p) => p.id === expandedProject,
                    );
                    if (!proy) return null;

                    return (
                        <div className="rounded-lg border border-[#e5e5e5] bg-[#fafaf9]">
                            <div className="border-b border-[#e5e5e5] bg-white px-4 py-2.5">
                                <span className="text-sm font-semibold text-[#1c1917]">
                                    Observaciones — {proy.proyecto_nombre}
                                </span>
                                <span className="ml-2 text-xs text-[#78716c]">
                                    {proy.proyecto_codigo}
                                </span>
                            </div>
                            <div className="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2">
                                {proy.fases.map((fase) => {
                                    const key = `${proy.id}-${fase.key}`;
                                    const draftVal =
                                        obsDraft[key] ??
                                        getObservacion(proy, fase.key);
                                    const saving = savingObs[key];

                                    return (
                                        <div key={fase.key} className="flex flex-col gap-1">
                                            <span className="text-[10px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                                {fase.fase}
                                            </span>
                                            <div className="flex gap-1.5">
                                                <textarea
                                                    value={draftVal}
                                                    onChange={(e) =>
                                                        handleObsChange(
                                                            proy.id,
                                                            fase.key,
                                                            e.target.value,
                                                        )
                                                    }
                                                    rows={2}
                                                    className="min-h-[44px] flex-1 resize-y rounded-md border border-[#e5e5e5] bg-white px-2.5 py-1.5 text-xs text-[#1c1917] placeholder:text-[#a8a29e] transition-colors hover:border-[#c2410c] focus:border-[#c2410c] focus:outline-none focus:ring-1 focus:ring-[#c2410c]"
                                                    placeholder="Sin observaciones..."
                                                />
                                                <button
                                                    onClick={() =>
                                                        saveObservation(
                                                            proy.id,
                                                            fase.key,
                                                        )
                                                    }
                                                    disabled={saving || draftVal === getObservacion(proy, fase.key)}
                                                    className="inline-flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-md border border-[#e5e5e5] bg-white text-[#78716c] transition-colors hover:bg-[#c2410c] hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
                                                    title="Guardar"
                                                >
                                                    {saving ? (
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
                })()}
        </div>
    );
}
