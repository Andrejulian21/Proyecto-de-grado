import { useNavigate } from 'react-router-dom';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { Users, UserCheck, Star, Clock, ArrowRight, Calendar } from 'lucide-react';
import type { AsignacionEvaluador } from '@/types/entregas';

const FASE_LABEL: Record<string, string> = {
    anteproyecto: 'Anteproyecto',
    presentacion_anteproyecto: 'Presentación Anteproyecto',
    desarrollo: 'Desarrollo del proyecto',
    presentacion_final: 'Presentación Final',
    Anteproyecto: 'Anteproyecto',
    Final: 'Presentación Final',
};

export function faseEvaluacionLabel(fase: string): string {
    return FASE_LABEL[fase] ?? fase;
}

export function formatFechaCorta(fecha: string | null | undefined): string {
    if (!fecha) return 'Sin fecha programada';
    try {
        return new Date(`${fecha}T00:00:00`).toLocaleDateString('es-CO', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return fecha;
    }
}

export function AsignacionEvaluadorCard({ asignacion }: { asignacion: AsignacionEvaluador }) {
    const navigate = useNavigate();
    const { proyecto } = asignacion;
    const evaluada = asignacion.evaluado;

    return (
        <div
            data-testid="asignacion-card"
            className="flex flex-col rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]"
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
                        Tipo: {faseEvaluacionLabel(asignacion.fase)}
                    </p>
                </div>
            </div>

            <div className="mb-4 flex flex-col gap-2 text-xs text-[#57534e]">
                <span className="flex items-center gap-1.5">
                    <Users className="h-3.5 w-3.5 shrink-0 text-[#78716c]" />
                    <span className="truncate">
                        {proyecto?.estudiantes.length
                            ? proyecto.estudiantes.map((e) => e.name).join(', ')
                            : 'Sin estudiantes'}
                    </span>
                </span>
                <span className="flex items-center gap-1.5">
                    <UserCheck className="h-3.5 w-3.5 shrink-0 text-[#78716c]" />
                    Director: {proyecto?.director?.name ?? '—'}
                </span>
                <span className="flex items-center gap-1.5">
                    <Calendar className="h-3.5 w-3.5 shrink-0 text-[#78716c]" />
                    {formatFechaCorta(asignacion.fecha)}
                    {asignacion.hora_inicio ? ` · ${asignacion.hora_inicio}` : ''}
                </span>
                <span className="flex items-center gap-1.5">
                    <Clock className="h-3.5 w-3.5 shrink-0 text-[#78716c]" />
                    Asignada: {formatFechaCorta(asignacion.created_at)}
                </span>
            </div>

            {evaluada && asignacion.nota != null && (
                <div className="mb-4 flex items-center gap-1.5 text-sm font-semibold text-[#1c1917]">
                    <Star className="h-4 w-4 fill-[#d97706] text-[#d97706]" />
                    <span className="tabular-nums">{Number(asignacion.nota).toFixed(2)} / 5.00</span>
                </div>
            )}

            <button
                type="button"
                onClick={() => navigate(`/evaluador/asignaciones/${asignacion.id}`)}
                className="mt-auto inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2.5 text-sm font-semibold text-[#1c1917] hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
            >
                {evaluada ? 'Ver resultado' : 'Evaluar'}
                <ArrowRight className="h-4 w-4" />
            </button>
        </div>
    );
}
