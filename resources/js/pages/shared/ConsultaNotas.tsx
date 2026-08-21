import { useEffect, useMemo, useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { EmptyState } from '@/components/ui/EmptyState';
import { apiFetch, cn } from '@/lib/utils';
import { useAuth } from '@/hooks/useAuth';
import type { EntregaNota, ProyectoNotas, SemestreOpcionNotas } from '@/types/notas';
import {
    Loader2,
    AlertCircle,
    RefreshCw,
    ChevronDown,
    ChevronRight,
    Search,
    Star,
} from 'lucide-react';

const FASE_LABEL: Record<string, string> = {
    anteproyecto: 'Anteproyecto',
    presentacion_anteproyecto: 'Presentación Anteproyecto',
    desarrollo: 'Desarrollo',
    presentacion_final: 'Presentación Final',
};

function formatNota(nota: number | null, estado: EntregaNota['estado_nota']): string {
    if (estado === 'sin_calificar' || nota === null) {
        return 'Sin calificar';
    }

    return nota.toFixed(2);
}

export default function ConsultaNotas() {
    const { role } = useAuth();
    const [semestres, setSemestres] = useState<SemestreOpcionNotas[]>([]);
    const [semestreId, setSemestreId] = useState<number | null>(null);
    const [q, setQ] = useState('');
    const [qDebounced, setQDebounced] = useState('');
    const [estadoNota, setEstadoNota] = useState('');
    const [proyectos, setProyectos] = useState<ProyectoNotas[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [expanded, setExpanded] = useState<number | null>(null);

    useEffect(() => {
        const handle = window.setTimeout(() => setQDebounced(q.trim()), 300);
        return () => window.clearTimeout(handle);
    }, [q]);

    const fetchNotas = async () => {
        setLoading(true);
        setError(null);

        const params = new URLSearchParams();
        if (semestreId) params.set('semestre_id', String(semestreId));
        if (qDebounced) params.set('q', qDebounced);
        if (estadoNota) params.set('estado_nota', estadoNota);

        try {
            const res = await apiFetch(`/api/notas?${params.toString()}`);
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(json.error ?? `Error ${res.status}`);
            }
            const data = json.data ?? json;
            setSemestres(data.semestres ?? []);
            setProyectos(data.proyectos ?? []);
            if (semestreId === null && (data.semestres ?? []).length === 1) {
                setSemestreId(data.semestres[0].id);
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'No se pudieron cargar las notas.');
            setProyectos([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void fetchNotas();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [semestreId, qDebounced, estadoNota]);

    const subtitle = useMemo(() => {
        if (role === 'Estudiante') return 'Notas de las entregas de tu proyecto';
        if (role === 'Director') return 'Notas de las entregas de los proyectos que diriges';
        if (role === 'EvaluadorExterno') return 'Notas de los proyectos que tienes asignados';
        return 'Consulta de notas por proyecto y entrega';
    }, [role]);

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Calificaciones"
                title="Notas"
                subtitle={subtitle}
                actions={
                    <button
                        type="button"
                        onClick={() => void fetchNotas()}
                        disabled={loading}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] hover:bg-[#fafaf9] disabled:opacity-60"
                    >
                        <RefreshCw className={cn('h-4 w-4', loading && 'animate-spin')} />
                        Refrescar
                    </button>
                }
            />

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
            </div>

            {error && (
                <div className="flex items-center gap-2 rounded-lg border border-[#fecaca] bg-[#fef2f2] px-4 py-3 text-sm text-[#991b1b]">
                    <AlertCircle className="h-4 w-4 shrink-0" />
                    {error}
                </div>
            )}

            {loading && (
                <div className="flex items-center gap-2 text-sm text-[#78716c]">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Cargando notas…
                </div>
            )}

            {!loading && !error && proyectos.length === 0 && (
                <EmptyState
                    icon={Star}
                    title="No hay notas para mostrar"
                    description="No se encontraron proyectos en tu ámbito con los filtros actuales."
                />
            )}

            {!loading && proyectos.length > 0 && (
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
            )}
        </div>
    );
}
