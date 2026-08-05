import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { useEvaluadorAsignaciones } from '@/hooks/useEvaluadorAsignaciones';
import {
    ClipboardCheck,
    Users,
    UserCheck,
    Star,
    Loader2,
    AlertCircle,
    CheckCircle2,
    Clock,
    ArrowRight,
} from 'lucide-react';
import type { AsignacionEvaluador } from '@/types/entregas';

function faseLabel(fase: string): string {
    const labels: Record<string, string> = {
        anteproyecto: 'Anteproyecto',
        presentacion_anteproyecto: 'Presentación Anteproyecto',
        desarrollo: 'Desarrollo del proyecto',
        presentacion_final: 'Presentación Final',
        Anteproyecto: 'Anteproyecto',
        Final: 'Presentación Final',
    };
    return labels[fase] ?? fase;
}

function formatFecha(fecha: string | null): string {
    if (!fecha) return '—';
    try {
        return new Date(fecha).toLocaleDateString('es-CO', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return fecha;
    }
}

function AsignacionCard({ asignacion }: { asignacion: AsignacionEvaluador }) {
    const navigate = useNavigate();
    const { proyecto } = asignacion;
    const evaluada = asignacion.evaluado;

    return (
        <div
            data-testid="asignacion-card"
            className="flex flex-col rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)] transition-all hover:shadow-[0_4px_12px_rgba(194,65,12,0.1)]"
        >
            <div className="mb-3 flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-xs font-bold uppercase tracking-[0.05em] text-[#c2410c]">
                            {proyecto?.codigo ?? '—'}
                        </span>
                        <StatusBadge variant={evaluada ? 'success' : 'warning'}>
                            {evaluada ? 'Evaluada' : 'Pendiente'}
                        </StatusBadge>
                    </div>
                    <h3 className="mt-0.5 text-base font-bold text-[#1c1917] text-balance">
                        {proyecto?.titulo ?? 'Proyecto sin asignar'}
                    </h3>
                    <p className="mt-0.5 text-xs font-semibold text-[#57534e]">
                        Fase a calificar: {faseLabel(asignacion.fase)}
                    </p>
                </div>
            </div>

            <div className="mb-4 flex flex-col gap-2 text-xs text-[#57534e]">
                <span className="flex items-center gap-1.5">
                    <Users className="h-3.5 w-3.5 shrink-0 text-[#78716c]" aria-hidden="true" />
                    <span className="truncate">
                        {proyecto?.estudiantes.length
                            ? proyecto.estudiantes.map((e) => e.name).join(', ')
                            : 'Sin estudiantes'}
                    </span>
                </span>
                <span className="flex items-center gap-1.5">
                    <UserCheck className="h-3.5 w-3.5 shrink-0 text-[#78716c]" aria-hidden="true" />
                    <span className="truncate">
                        Director: {proyecto?.director?.name ?? '—'}
                    </span>
                </span>
                <span className="flex items-center gap-1.5">
                    <Clock className="h-3.5 w-3.5 shrink-0 text-[#78716c]" aria-hidden="true" />
                    Asignada: {formatFecha(asignacion.created_at)}
                </span>
            </div>

            {evaluada && asignacion.nota != null && (
                <div className="mb-4 flex items-center gap-1.5 text-sm font-semibold text-[#1c1917]">
                    <Star className="h-4 w-4 fill-[#d97706] text-[#d97706]" aria-hidden="true" />
                    <span className="tabular-nums">{Number(asignacion.nota).toFixed(2)} / 5.00</span>
                </div>
            )}

            {evaluada && asignacion.director_grade != null && (
                <div className="mb-4 flex items-center gap-1.5 text-xs text-[#57534e]">
                    <Star className="h-3.5 w-3.5 shrink-0 text-[#d97706]" aria-hidden="true" />
                    <span>
                        Nota del director (proyecto):{' '}
                        <span className="tabular-nums font-semibold text-[#1c1917]">
                            {Number(asignacion.director_grade).toFixed(2)} / 5.00
                        </span>
                    </span>
                </div>
            )}

            <button
                type="button"
                onClick={() => navigate(`/evaluador/asignaciones/${asignacion.id}`)}
                className="mt-auto inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2.5 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
            >
                {evaluada ? 'Ver' : 'Evaluar'}
                <ArrowRight className="h-4 w-4" aria-hidden="true" />
            </button>
        </div>
    );
}

export default function MisAsignaciones() {
    const { data, loading, error, refetch } = useEvaluadorAsignaciones();
    const [verEvaluadas, setVerEvaluadas] = useState(false);

    const visibles = useMemo(
        () => data.filter((a) => (verEvaluadas ? a.evaluado : !a.evaluado)),
        [data, verEvaluadas],
    );

    const pendientes = data.filter((a) => !a.evaluado).length;
    const evaluadas = data.length - pendientes;

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Evaluador"
                title="Mis Asignaciones"
                subtitle="Proyectos asignados para evaluar en el semestre activo"
            />

            {/* Toggle RF-EVA-04: evaluated assignments hidden by default */}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2 text-sm text-[#57534e]">
                    <span>
                        {pendientes} pendiente{pendientes !== 1 ? 's' : ''} · {evaluadas}{' '}
                        evaluada{evaluadas !== 1 ? 's' : ''}
                    </span>
                </div>
                <button
                    type="button"
                    onClick={() => setVerEvaluadas((v) => !v)}
                    aria-pressed={verEvaluadas}
                    className={`inline-flex min-h-[36px] items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-semibold transition-colors active:scale-[0.98] ${
                        verEvaluadas
                            ? 'border-[#c2410c] bg-[#fed7aa] text-[#c2410c]'
                            : 'border-[#e5e5e5] bg-white text-[#1c1917] hover:bg-[#f5f5f4]'
                    }`}
                >
                    <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                    Ver ya evaluados
                </button>
            </div>

            {error && (
                <div className="flex items-center gap-2 rounded-lg border border-[#fecaca] bg-[#fee2e2] px-4 py-3 text-sm text-[#dc2626]" role="alert">
                    <AlertCircle className="h-4 w-4 shrink-0" aria-hidden="true" />
                    {error}
                    <button
                        onClick={refetch}
                        className="ml-auto rounded-lg px-2 py-1 text-xs font-semibold text-[#dc2626] hover:bg-[#fecaca]"
                    >
                        Reintentar
                    </button>
                </div>
            )}

            {loading && (
                <div className="flex items-center justify-center py-16" role="status" aria-label="Cargando asignaciones">
                    <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
                </div>
            )}

            {!loading && !error && visibles.length === 0 && (
                <div className="flex flex-col items-center gap-3 py-16 text-center">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#f5f5f4]">
                        <ClipboardCheck className="h-6 w-6 text-[#78716c]" aria-hidden="true" />
                    </div>
                    <h3 className="text-base font-semibold text-[#1c1917]">
                        {verEvaluadas
                            ? 'Aún no has evaluado ninguna asignación'
                            : 'No tienes asignaciones pendientes'}
                    </h3>
                    <p className="text-sm text-[#57534e]">
                        {verEvaluadas
                            ? 'Las asignaciones que evalúes aparecerán aquí.'
                            : 'Activa "Ver ya evaluados" para consultar tus evaluaciones enviadas.'}
                    </p>
                </div>
            )}

            {!loading && !error && visibles.length > 0 && (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {visibles.map((a) => (
                        <AsignacionCard key={a.id} asignacion={a} />
                    ))}
                </div>
            )}
        </div>
    );
}
