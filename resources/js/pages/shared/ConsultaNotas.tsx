import { useEffect, useMemo, useState, useCallback } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { EmptyState } from '@/components/ui/EmptyState';
import { apiFetch, cn } from '@/lib/utils';
import { useAuth } from '@/hooks/useAuth';
import type {
    EntregaNota,
    PesoConfig,
    ProyectoNotas,
    ProyectoNotasCoordinador,
    SemestreOpcionNotas,
} from '@/types/notas';
import { useExportNotas } from '@/hooks/useExportNotas';
import {
    Loader2,
    AlertCircle,
    RefreshCw,
    ChevronDown,
    ChevronRight,
    Search,
    Star,
    Edit,
    Download,
} from 'lucide-react';

/* ── Constants ─────────────────────────────────────────────────────── */

const FASE_LABEL: Record<string, string> = {
    anteproyecto: 'Anteproyecto',
    presentacion_anteproyecto: 'Presentación Anteproyecto',
    desarrollo: 'Desarrollo',
    presentacion_final: 'Presentación Final',
};

const TIPO_LABEL: Record<string, string> = {
    pg1: 'Proyecto de Grado 1',
    pg2: 'Proyecto de Grado 2',
};

/* ── Helpers ───────────────────────────────────────────────────────── */

function formatNota(nota: number | null, estado: EntregaNota['estado_nota']): string {
    if (estado === 'sin_calificar' || nota === null) {
        return 'Sin calificar';
    }
    return nota.toFixed(2);
}

function formatPonderada(nota: number | null): string {
    if (nota === null) return '—';
    return nota.toFixed(2);
}

/* ── Shared: Non-coordinator table ────────────────────────────────── */

function StandardTable({
    proyectos,
    role,
    expanded,
    setExpanded,
}: {
    proyectos: ProyectoNotas[];
    role: string;
    expanded: number | null;
    setExpanded: (id: number | null) => void;
}) {
    return (
        <div className="overflow-x-auto rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <table className="min-w-full text-left text-sm">
                <thead className="bg-[#fff7ed] text-[10px] font-bold uppercase tracking-wider text-[#57534e]">
                    <tr>
                        <th className="w-10 px-3 py-3" />
                        <th className="px-3 py-3">Proyecto</th>
                        <th className="px-3 py-3">Código</th>
                        <th className="px-3 py-3">Estudiantes</th>
                        <th className="px-3 py-3">Director</th>
                        {role === 'EvaluadorExterno' && (
                            <th className="px-3 py-3">Tu evaluación</th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {proyectos.map((proyecto) => {
                        const open = expanded === proyecto.id;
                        return (
                            <tr key={proyecto.id} className="border-t border-[#e5e5e5] align-top">
                                <td className="px-3 py-3" colSpan={role === 'EvaluadorExterno' ? 6 : 5}>
                                    <button
                                        type="button"
                                        onClick={() => setExpanded(open ? null : proyecto.id)}
                                        className="flex w-full items-start gap-3 text-left"
                                    >
                                        {open ? (
                                            <ChevronDown className="mt-0.5 h-4 w-4 shrink-0 text-[#c2410c]" />
                                        ) : (
                                            <ChevronRight className="mt-0.5 h-4 w-4 shrink-0 text-[#a8a29e]" />
                                        )}
                                        <div className="grid flex-1 grid-cols-1 gap-2 sm:grid-cols-4">
                                            <span className="font-semibold text-[#1c1917]">{proyecto.titulo}</span>
                                            <span className="font-mono text-xs text-[#57534e]">{proyecto.codigo}</span>
                                            <span className="text-xs text-[#57534e]">{proyecto.estudiantes || '—'}</span>
                                            <span className="text-xs text-[#57534e]">{proyecto.director || '—'}</span>
                                        </div>
                                        {role === 'EvaluadorExterno' && (
                                            <span className="shrink-0 text-xs font-semibold tabular-nums text-[#1c1917]">
                                                {proyecto.nota_evaluador === null
                                                    ? 'Sin calificar'
                                                    : proyecto.nota_evaluador.toFixed(2)}
                                            </span>
                                        )}
                                    </button>
                                    {open && (
                                        <div className="mt-3 ml-7 overflow-hidden rounded-lg border border-[#e5e5e5]">
                                            <table className="min-w-full text-sm">
                                                <thead className="bg-[#fafaf9] text-[10px] font-bold uppercase tracking-wider text-[#78716c]">
                                                    <tr>
                                                        <th className="px-3 py-2">Entrega</th>
                                                        <th className="px-3 py-2">Fase</th>
                                                        <th className="px-3 py-2">Nota</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {proyecto.entregas.length === 0 ? (
                                                        <tr>
                                                            <td colSpan={3} className="px-3 py-3 text-xs text-[#a8a29e]">
                                                                Este proyecto no tiene entregas registradas.
                                                            </td>
                                                        </tr>
                                                    ) : (
                                                        proyecto.entregas.map((entrega) => (
                                                            <tr key={entrega.id} className="border-t border-[#e5e5e5]">
                                                                <td className="px-3 py-2 text-[#1c1917]">{entrega.titulo}</td>
                                                                <td className="px-3 py-2 text-xs text-[#57534e]">
                                                                    {FASE_LABEL[entrega.fase] ?? entrega.fase}
                                                                </td>
                                                                <td className="px-3 py-2">
                                                                    <span
                                                                        className={cn(
                                                                            'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold',
                                                                            entrega.estado_nota === 'sin_calificar'
                                                                                ? 'border-[#fde68a] bg-[#fffbeb] text-[#b45309]'
                                                                                : 'border-[#bbf7d0] bg-[#f0fdf4] text-[#15803d]',
                                                                        )}
                                                                    >
                                                                        {entrega.estado_nota === 'calificada' && (
                                                                            <Star className="h-3 w-3" />
                                                                        )}
                                                                        {formatNota(entrega.nota, entrega.estado_nota)}
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        ))
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

/* ── Coordinator: Edit Percentages Modal ──────────────────────────── */

function EditPesosModal({
    open,
    pesos,
    tipo,
    onClose,
    onSave,
}: {
    open: boolean;
    pesos: PesoConfig[];
    tipo: 'pg1' | 'pg2';
    onClose: () => void;
    onSave: (pesos: { peso_entregas: number; peso_evaluadores: number; peso_presentacion: number }) => void;
}) {
    const current = pesos.find((p) => p.tipo === tipo);
    const [entregas, setEntregas] = useState(current?.peso_entregas ?? 40);
    const [evaluadores, setEvaluadores] = useState(current?.peso_evaluadores ?? 30);
    const [presentacion, setPresentacion] = useState(current?.peso_presentacion ?? 30);
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            const c = pesos.find((p) => p.tipo === tipo);
            setEntregas(c?.peso_entregas ?? 40);
            setEvaluadores(c?.peso_evaluadores ?? 30);
            setPresentacion(c?.peso_presentacion ?? 30);
            setSaveError(null);
        }
    }, [open, pesos, tipo]);

    const sum = entregas + evaluadores + presentacion;
    const isValid = sum === 100;

    const handleSave = useCallback(async () => {
        if (!isValid) return;
        setSaving(true);
        setSaveError(null);
        try {
            const res = await apiFetch('/api/admin/notas/pesos', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    semestre_id: current?.semestre_id,
                    tipo,
                    peso_entregas: entregas,
                    peso_evaluadores: evaluadores,
                    peso_presentacion: presentacion,
                }),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.error ?? `Error ${res.status}`);
            onSave({ peso_entregas: entregas, peso_evaluadores: evaluadores, peso_presentacion: presentacion });
            onClose();
        } catch (err) {
            setSaveError(err instanceof Error ? err.message : 'No se pudieron guardar los porcentajes.');
        } finally {
            setSaving(false);
        }
    }, [isValid, current, tipo, entregas, evaluadores, presentacion, onSave, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            {/* Backdrop */}
            <div className="absolute inset-0 bg-black/40" onClick={onClose} />
            {/* Dialog */}
            <div className="relative z-10 w-full max-w-md rounded-2xl border border-[#e5e5e5] bg-white p-6 shadow-xl">
                <h3 className="mb-1 text-lg font-bold text-[#1c1917]">
                    Editar porcentajes — {TIPO_LABEL[tipo]}
                </h3>
                <p className="mb-5 text-xs text-[#78716c]">
                    Ajusta los pesos para cada componente de la nota final. La suma debe ser 100%.
                </p>

                {saveError && (
                    <div className="mb-4 flex items-center gap-2 rounded-lg border border-[#fecaca] bg-[#fef2f2] px-3 py-2 text-xs text-[#991b1b]">
                        <AlertCircle className="h-3.5 w-3.5 shrink-0" />
                        {saveError}
                    </div>
                )}

                <div className="space-y-4">
                    {/* Entregas */}
                    <div>
                        <div className="mb-1 flex items-center justify-between">
                            <label className="text-xs font-semibold text-[#57534e]">Entregas</label>
                            <span className="text-xs font-bold tabular-nums text-[#1c1917]">{entregas}%</span>
                        </div>
                        <input
                            type="range"
                            min={0}
                            max={100}
                            step={5}
                            value={entregas}
                            onChange={(e) => setEntregas(Number(e.target.value))}
                            className="w-full accent-[#c2410c]"
                        />
                    </div>

                    {/* Evaluadores */}
                    <div>
                        <div className="mb-1 flex items-center justify-between">
                            <label className="text-xs font-semibold text-[#57534e]">Evaluadores</label>
                            <span className="text-xs font-bold tabular-nums text-[#1c1917]">{evaluadores}%</span>
                        </div>
                        <input
                            type="range"
                            min={0}
                            max={100}
                            step={5}
                            value={evaluadores}
                            onChange={(e) => setEvaluadores(Number(e.target.value))}
                            className="w-full accent-[#c2410c]"
                        />
                    </div>

                    {/* Presentación */}
                    <div>
                        <div className="mb-1 flex items-center justify-between">
                            <label className="text-xs font-semibold text-[#57534e]">Presentación</label>
                            <span className="text-xs font-bold tabular-nums text-[#1c1917]">{presentacion}%</span>
                        </div>
                        <input
                            type="range"
                            min={0}
                            max={100}
                            step={5}
                            value={presentacion}
                            onChange={(e) => setPresentacion(Number(e.target.value))}
                            className="w-full accent-[#c2410c]"
                        />
                    </div>

                    {/* Sum validation */}
                    <div className="flex items-center justify-between rounded-lg bg-[#fafaf9] px-3 py-2">
                        <span className="text-xs font-semibold text-[#57534e]">Total</span>
                        <span
                            className={cn(
                                'text-xs font-bold tabular-nums',
                                isValid ? 'text-[#15803d]' : 'text-[#dc2626]',
                            )}
                        >
                            {sum}%
                        </span>
                    </div>
                </div>

                {/* Actions */}
                <div className="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={saving}
                        className="rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#57534e] hover:bg-[#fafaf9] disabled:opacity-60"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        onClick={() => void handleSave()}
                        disabled={!isValid || saving}
                        className="inline-flex items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white hover:bg-[#9a3412] disabled:opacity-50"
                    >
                        {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    );
}

/* ── Coordinator: Table ───────────────────────────────────────────── */

function CoordinatorTable({
    proyectos,
    expanded,
    setExpanded,
    expandedDeliveries,
    setExpandedDeliveries,
}: {
    proyectos: ProyectoNotasCoordinador[];
    expanded: number | null;
    setExpanded: (id: number | null) => void;
    expandedDeliveries: number | null;
    setExpandedDeliveries: (id: number | null) => void;
}) {
    const renderNotaCell = (nota: number | null) => {
        if (nota === null) {
            return <span className="text-xs text-[#a8a29e]">—</span>;
        }
        return nota.toFixed(2);
    };

    const renderNotaFinal = (nota: number | null) => {
        if (nota === null) {
            return (
                <span className="text-xs font-semibold text-[#b45309]">Sin calificar</span>
            );
        }
        return (
            <span className="text-sm font-bold tabular-nums text-[#1c1917]">
                {nota.toFixed(2)}
            </span>
        );
    };

    const sorted = useMemo(
        () => [...proyectos].sort((a, b) => a.codigo.localeCompare(b.codigo)),
        [proyectos],
    );

    return (
        <div className="overflow-x-auto rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <table className="min-w-full text-sm">
                <thead className="bg-[#fff7ed] text-[10px] font-bold uppercase tracking-wider text-[#57534e]">
                    <tr>
                        <th className="w-8 px-2 py-3 text-center" />
                        <th className="px-2 py-3 text-center">Proyecto</th>
                        <th className="px-2 py-3 text-center">Código</th>
                        <th className="px-2 py-3 text-center">Estudiantes</th>
                        <th className="px-2 py-3 text-center">Director</th>
                        <th className="px-2 py-3 text-center">
                            Notas Entregas
                            {sorted.length > 0 && <span className="block text-[10px] font-normal text-[#a8a29e]">({sorted[0].pesos.entregas}%)</span>}
                        </th>
                        <th className="px-2 py-3 text-center">
                            Notas Evaluadores
                            {sorted.length > 0 && <span className="block text-[10px] font-normal text-[#a8a29e]">({sorted[0].pesos.evaluadores}%)</span>}
                        </th>
                        <th className="px-2 py-3 text-center">
                            Nota Director
                            {sorted.length > 0 && <span className="block text-[10px] font-normal text-[#a8a29e]">({sorted[0].pesos.presentacion}%)</span>}
                        </th>
                        <th className="rounded-r-xl bg-[#fff7ed] px-2 py-3 text-center">Nota Final</th>
                    </tr>
                </thead>
                <tbody>
                    {sorted.map((proyecto) => {
                        const open = expanded === proyecto.id;
                        const isPG1 = proyecto.tipo === 'pg1';

                        const entregasPonderadas = isPG1
                            ? proyecto.notas_entregas_anteproyecto
                            : proyecto.notas_entregas_desarrollo;

                        const notaEntregasPonderada = isPG1
                            ? proyecto.nota_entregas_ponderada
                            : proyecto.nota_entregas_desarrollo_ponderada;

                        const notaEvaluadores = isPG1
                            ? proyecto.nota_evaluadores_anteproyecto
                            : proyecto.nota_evaluadores_presentacion_final;

                        const notaDirector = isPG1
                            ? proyecto.nota_presentacion_anteproyecto
                            : proyecto.nota_director_presentacion_final;

                        const notaFinal = isPG1 ? proyecto.nota_final_pg1 : proyecto.nota_final_pg2;

                        const deliveryExpanded = expandedDeliveries === proyecto.id;

                        return (
                            <tr key={proyecto.id} className="border-t border-[#e5e5e5]">
                                <td className="px-2 py-3 text-center">
                                    <button
                                        type="button"
                                        onClick={() => setExpanded(open ? null : proyecto.id)}
                                        className="inline-flex"
                                    >
                                        {open ? (
                                            <ChevronDown className="h-4 w-4 text-[#c2410c]" />
                                        ) : (
                                            <ChevronRight className="h-4 w-4 text-[#a8a29e]" />
                                        )}
                                    </button>
                                </td>
                                <td className="px-2 py-3 text-center">
                                    <span className="font-semibold text-[#1c1917]">{proyecto.titulo}</span>
                                </td>
                                <td className="px-2 py-3 text-center">
                                    <span className="font-mono text-xs text-[#57534e]">{proyecto.codigo}</span>
                                </td>
                                <td className="px-2 py-3 text-center">
                                    <span className="text-xs text-[#57534e]">{proyecto.estudiantes || '—'}</span>
                                </td>
                                <td className="px-2 py-3 text-center">
                                    <span className="text-xs text-[#57534e]">{proyecto.director || '—'}</span>
                                </td>
                                {/* Notas Entregas — compact summary */}
                                <td className="px-2 py-3 text-center">
                                    {entregasPonderadas.length === 0 ? (
                                        <span className="text-xs text-[#a8a29e]">—</span>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => setExpandedDeliveries(deliveryExpanded ? null : proyecto.id)}
                                            className="inline-flex items-center gap-1 text-sm font-semibold tabular-nums text-[#1c1917] hover:underline"
                                        >
                                            {formatPonderada(notaEntregasPonderada)}
                                            {deliveryExpanded ? (
                                                <ChevronDown className="h-3 w-3" />
                                            ) : (
                                                <ChevronRight className="h-3 w-3" />
                                            )}
                                        </button>
                                    )}
                                </td>
                                {/* Notas Evaluadores */}
                                <td className="px-2 py-3 text-center">
                                    {renderNotaCell(notaEvaluadores)}
                                </td>
                                {/* Nota Director */}
                                <td className="px-2 py-3 text-center">
                                    {renderNotaCell(notaDirector)}
                                </td>
                                {/* Nota Final */}
                                <td className="rounded-r-xl bg-[#fff7ed] px-2 py-3 text-center">
                                    {renderNotaFinal(notaFinal)}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

/* ── Coordinator: Expanded Delivery Details ───────────────────────── */

function DeliveryDetails({
    proyecto,
}: {
    proyecto: ProyectoNotasCoordinador;
}) {
    const isPG1 = proyecto.tipo === 'pg1';
    const entregas = isPG1
        ? proyecto.notas_entregas_anteproyecto
        : proyecto.notas_entregas_desarrollo;

    if (entregas.length === 0) return null;

    return (
        <div className="overflow-hidden rounded-xl border border-[#e5e5e5] bg-[#fafaf9]">
            <table className="min-w-full text-sm">
                <thead className="text-[10px] font-bold uppercase tracking-wider text-[#78716c]">
                    <tr>
                        <th className="px-4 py-2 text-center">Entrega</th>
                        <th className="px-4 py-2 text-center">Nota</th>
                        <th className="px-4 py-2 text-center">Peso</th>
                    </tr>
                </thead>
                <tbody>
                    {entregas.map((e, i) => (
                        <tr key={i} className="border-t border-[#e5e5e5]">
                            <td className="px-4 py-2 text-center text-[#1c1917]">
                                {e.titulo}
                            </td>
                            <td className="px-4 py-2 text-center">
                                {e.nota === null ? (
                                    <span className="inline-block rounded-full border border-[#fde68a] bg-[#fffbeb] px-2 py-0.5 text-[11px] font-semibold text-[#b45309]">
                                        Sin calificar
                                    </span>
                                ) : (
                                    <span className="inline-block rounded-full border border-[#bbf7d0] bg-[#f0fdf4] px-2 py-0.5 text-[11px] font-semibold text-[#15803d]">
                                        {e.nota.toFixed(2)}
                                    </span>
                                )}
                            </td>
                            <td className="px-4 py-2 text-center text-xs font-semibold text-[#57534e]">
                                {e.peso}%
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/* ── Main Component ───────────────────────────────────────────────── */

export default function ConsultaNotas() {
    const { role } = useAuth();

    /* Shared state */
    const [semestres, setSemestres] = useState<SemestreOpcionNotas[]>([]);
    const [semestreId, setSemestreId] = useState<number | null>(null);
    const [q, setQ] = useState('');
    const [qDebounced, setQDebounced] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [expanded, setExpanded] = useState<number | null>(null);

    /* Non-coordinator state */
    const [estadoNota, setEstadoNota] = useState('');
    const [proyectos, setProyectos] = useState<ProyectoNotas[]>([]);

    /* Coordinator state */
    const [tipoProyecto, setTipoProyecto] = useState('');
    const [proyectosCoord, setProyectosCoord] = useState<ProyectoNotasCoordinador[]>([]);
    const [pesos, setPesos] = useState<PesoConfig[]>([]);
    const [showEditModal, setShowEditModal] = useState(false);
    const [expandedDeliveries, setExpandedDeliveries] = useState<number | null>(null);

    const isCoordinator = role === 'Coordinador';

    /* Export hook */
    const { exporting, error: exportError, exportar } = useExportNotas();

    /* Debounced search */
    useEffect(() => {
        const handle = window.setTimeout(() => setQDebounced(q.trim()), 300);
        return () => window.clearTimeout(handle);
    }, [q]);

    /* Fetch data */
    const fetchNotas = useCallback(async () => {
        setLoading(true);
        setError(null);

        const params = new URLSearchParams();
        if (semestreId) params.set('semestre_id', String(semestreId));
        if (qDebounced) params.set('q', qDebounced);

        try {
            if (isCoordinator) {
                if (tipoProyecto) params.set('tipo', tipoProyecto);
                const res = await apiFetch(`/api/notas?${params.toString()}`);
                const json = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(json.error ?? `Error ${res.status}`);
                const data = json.data ?? json;
                setSemestres(data.semestres ?? []);
                setProyectosCoord(data.proyectos ?? []);
                setPesos(data.pesos ?? []);
            } else {
                if (estadoNota) params.set('estado_nota', estadoNota);
                const res = await apiFetch(`/api/notas?${params.toString()}`);
                const json = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(json.error ?? `Error ${res.status}`);
                const data = json.data ?? json;
                setSemestres(data.semestres ?? []);
                setProyectos(data.proyectos ?? []);
            }

            if (semestreId === null && (semestres.length === 1 || !isCoordinator)) {
                /* auto-select semester handled below */
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'No se pudieron cargar las notas.');
            setProyectos([]);
            setProyectosCoord([]);
        } finally {
            setLoading(false);
        }
    }, [semestreId, qDebounced, isCoordinator, tipoProyecto, estadoNota, semestres.length]);

    useEffect(() => {
        void fetchNotas();
    }, [fetchNotas]);

    /* Auto-select semester */
    useEffect(() => {
        if (semestreId === null && semestres.length === 1) {
            setSemestreId(semestres[0].id);
        }
    }, [semestreId, semestres]);

    /* Subtitle */
    const subtitle = useMemo(() => {
        if (isCoordinator) return 'Gestión centralizada de notas por proyecto';
        if (role === 'Estudiante') return 'Notas de las entregas de tu proyecto';
        if (role === 'Director') return 'Notas de las entregas de los proyectos que diriges';
        if (role === 'EvaluadorExterno') return 'Notas de los proyectos que tienes asignados';
        return 'Consulta de notas por proyecto y entrega';
    }, [role, isCoordinator]);

    /* Coordinator: on peso save, update local state and refetch to recalculate nota final */
    const handlePesosSave = useCallback(
        (updated: { peso_entregas: number; peso_evaluadores: number; peso_presentacion: number }) => {
            const currentTipo = tipoProyecto === 'pg1' || tipoProyecto === 'pg2' ? tipoProyecto : 'pg1';
            setPesos((prev) =>
                prev.map((p) =>
                    p.tipo === currentTipo
                        ? { ...p, ...updated }
                        : p,
                ),
            );
            // Refetch to get updated nota_final from backend
            void fetchNotas();
        },
        [tipoProyecto, fetchNotas],
    );

    /* ── Render ─────────────────────────────────────────────────────── */

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Calificaciones"
                title="Notas"
                subtitle={subtitle}
                actions={
                    <>
                        {isCoordinator && (
                            <button
                                type="button"
                                onClick={() => {
                                    if (semestreId && tipoProyecto) {
                                        void exportar(semestreId, tipoProyecto);
                                    }
                                }}
                                disabled={loading || !semestreId || !tipoProyecto || exporting}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] hover:bg-[#fafaf9] disabled:opacity-60"
                            >
                                {exporting ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Download className="h-4 w-4" />
                                )}
                                Exportar
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => void fetchNotas()}
                            disabled={loading}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] hover:bg-[#fafaf9] disabled:opacity-60"
                        >
                            <RefreshCw className={cn('h-4 w-4', loading && 'animate-spin')} />
                            Refrescar
                        </button>
                    </>
                }
            />

            {/* ── Filters ─────────────────────────────────────────── */}
            <div className="flex flex-wrap items-end gap-3">
                {semestres.length > 0 && (
                    <label className="flex max-w-xs flex-col gap-1.5">
                        <span className="text-xs font-semibold text-[#57534e]">Semestre</span>
                        <select
                            value={semestreId ?? ''}
                            onChange={(e) => setSemestreId(e.target.value ? Number(e.target.value) : null)}
                            className="rounded-lg border border-[#e5e5e5] bg-white px-3 py-2.5 text-sm font-semibold text-[#1c1917] outline-none focus:border-[#c2410c]"
                        >
                            <option value="">Todos los visibles</option>
                            {semestres.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.nombre}
                                </option>
                            ))}
                        </select>
                    </label>
                )}

                <label className="flex min-w-[220px] flex-1 flex-col gap-1.5">
                    <span className="text-xs font-semibold text-[#57534e]">Proyecto</span>
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a8a29e]" />
                        <input
                            type="search"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Código o título"
                            className="w-full rounded-lg border border-[#e5e5e5] bg-white py-2.5 pl-9 pr-3 text-sm text-[#1c1917] outline-none focus:border-[#c2410c]"
                        />
                    </div>
                </label>

                {isCoordinator ? (
                    /* Fase del proyecto filter for coordinator */
                    <label className="flex max-w-xs flex-col gap-1.5">
                        <span className="text-xs font-semibold text-[#57534e]">Elegir fase del proyecto</span>
                        <select
                            value={tipoProyecto}
                            onChange={(e) => {
                                setTipoProyecto(e.target.value);
                                setExpandedDeliveries(null);
                            }}
                            className="rounded-lg border border-[#e5e5e5] bg-white px-3 py-2.5 text-sm font-semibold text-[#1c1917] outline-none focus:border-[#c2410c]"
                        >
                            <option value="">Seleccionar fase</option>
                            <option value="pg1">Proyecto de Grado 1</option>
                            <option value="pg2">Proyecto de Grado 2</option>
                        </select>
                    </label>
                ) : (
                    /* Estado nota filter for other roles */
                    <label className="flex max-w-xs flex-col gap-1.5">
                        <span className="text-xs font-semibold text-[#57534e]">Estado de nota</span>
                        <select
                            value={estadoNota}
                            onChange={(e) => setEstadoNota(e.target.value)}
                            className="rounded-lg border border-[#e5e5e5] bg-white px-3 py-2.5 text-sm font-semibold text-[#1c1917] outline-none focus:border-[#c2410c]"
                        >
                            <option value="">Todas</option>
                            <option value="calificada">Calificadas</option>
                            <option value="sin_calificar">Sin calificar</option>
                        </select>
                    </label>
                )}

                {isCoordinator && (
                    <button
                        type="button"
                        onClick={() => setShowEditModal(true)}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#c2410c] bg-[#fff7ed] px-4 py-2 text-sm font-semibold text-[#c2410c] hover:bg-[#fed7aa]"
                    >
                        <Edit className="h-4 w-4" />
                        Editar porcentajes
                    </button>
                )}
            </div>

            {/* ── Error ───────────────────────────────────────────── */}
            {error && (
                <div className="flex items-center gap-2 rounded-lg border border-[#fecaca] bg-[#fef2f2] px-4 py-3 text-sm text-[#991b1b]">
                    <AlertCircle className="h-4 w-4 shrink-0" />
                    {error}
                </div>
            )}

            {exportError && (
                <div className="flex items-center gap-2 rounded-lg border border-[#fecaca] bg-[#fef2f2] px-4 py-3 text-sm text-[#991b1b]">
                    <AlertCircle className="h-4 w-4 shrink-0" />
                    {exportError}
                </div>
            )}

            {/* ── Loading ─────────────────────────────────────────── */}
            {loading && (
                <div className="flex items-center gap-2 text-sm text-[#78716c]">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Cargando notas…
                </div>
            )}

            {/* ── Empty ───────────────────────────────────────────── */}
            {!loading && !error && (
                isCoordinator ? proyectosCoord.length === 0 : proyectos.length === 0
            ) && (
                <EmptyState
                    icon={Star}
                    title="No hay notas para mostrar"
                    description="No se encontraron proyectos en tu ámbito con los filtros actuales."
                />
            )}

            {/* ── Non-coordinator table ───────────────────────────── */}
            {!loading && !isCoordinator && proyectos.length > 0 && (
                <StandardTable
                    proyectos={proyectos}
                    role={role ?? ''}
                    expanded={expanded}
                    setExpanded={setExpanded}
                />
            )}

            {/* ── Coordinator table + expanded details ────────────── */}
            {!loading && isCoordinator && proyectosCoord.length > 0 && (
                <div className="flex flex-col gap-3">
                    <CoordinatorTable
                        proyectos={proyectosCoord}
                        expanded={expanded}
                        setExpanded={setExpanded}
                        expandedDeliveries={expandedDeliveries}
                        setExpandedDeliveries={setExpandedDeliveries}
                    />

                    {/* Expanded delivery details below the main table */}
                    {expandedDeliveries !== null && (
                        (() => {
                            const p = proyectosCoord.find((proj) => proj.id === expandedDeliveries);
                            if (!p) return null;
                            return <DeliveryDetails proyecto={p} />;
                        })()
                    )}
                </div>
            )}

            {/* ── Edit Pesos Modal ────────────────────────────────── */}
            <EditPesosModal
                open={showEditModal}
                pesos={pesos}
                tipo={tipoProyecto === 'pg1' || tipoProyecto === 'pg2' ? tipoProyecto : 'pg1'}
                onClose={() => setShowEditModal(false)}
                onSave={handlePesosSave}
            />
        </div>
    );
}
