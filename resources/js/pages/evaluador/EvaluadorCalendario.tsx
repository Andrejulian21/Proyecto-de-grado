import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { EmptyState } from '@/components/ui/EmptyState';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { CalendarGrid, type CalendarAssignment } from '@/components/calendar/CalendarGrid';
import { useEvaluadorCalendario } from '@/hooks/useEvaluadorAsignaciones';
import { faseEvaluacionLabel, formatFechaCorta } from '@/components/evaluador/AsignacionEvaluadorCard';
import { AlertCircle, CalendarDays, Loader2 } from 'lucide-react';

export default function EvaluadorCalendario() {
    const { data, loading, error, refetch } = useEvaluadorCalendario();
    const navigate = useNavigate();

    const assignments: CalendarAssignment[] = data
        .filter((evento) => evento.fecha)
        .map((evento) => ({
            date: evento.fecha as string,
            label: `${evento.proyecto?.codigo ?? 'Proyecto'} · ${faseEvaluacionLabel(evento.fase)}`,
        }));

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Evaluador"
                title="Calendario de evaluaciones"
                subtitle="Fechas programadas de tus asignaciones. Si no hay fecha registrada, no se muestra un evento."
            />

            {error && (
                <div className="flex items-center gap-2 rounded-lg border border-[#fecaca] bg-[#fee2e2] px-4 py-3 text-sm text-[#dc2626]">
                    <AlertCircle className="h-4 w-4" />
                    {error}
                    <button type="button" onClick={() => void refetch()} className="ml-auto text-xs font-semibold">
                        Reintentar
                    </button>
                </div>
            )}

            {loading && (
                <div className="flex justify-center py-16">
                    <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
                </div>
            )}

            {!loading && !error && data.length === 0 && (
                <EmptyState
                    icon={CalendarDays}
                    title="Sin fechas programadas"
                    description="No hay evaluaciones con fecha registrada en tus asignaciones."
                />
            )}

            {!loading && !error && data.length > 0 && (
                <>
                    <CalendarGrid assignments={assignments} />
                    <div className="overflow-x-auto rounded-xl border border-[#e5e5e5] bg-white">
                            <table className="min-w-full text-sm">
                                <thead className="bg-[#fff7ed] text-[10px] font-bold uppercase tracking-wider text-[#57534e]">
                                    <tr>
                                        <th className="px-3 py-3 text-left">Proyecto</th>
                                        <th className="px-3 py-3 text-left">Tipo</th>
                                        <th className="px-3 py-3 text-left">Fecha</th>
                                        <th className="px-3 py-3 text-left">Hora</th>
                                        <th className="px-3 py-3 text-left">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.map((evento) => (
                                        <tr
                                            key={evento.id}
                                            className="cursor-pointer border-t border-[#e5e5e5] hover:bg-[#fafaf9]"
                                            onClick={() => navigate(`/evaluador/asignaciones/${evento.id}`)}
                                        >
                                            <td className="px-3 py-3">
                                                <p className="font-semibold text-[#1c1917]">{evento.proyecto?.titulo}</p>
                                                <p className="font-mono text-xs text-[#57534e]">{evento.proyecto?.codigo}</p>
                                            </td>
                                            <td className="px-3 py-3 text-[#57534e]">{faseEvaluacionLabel(evento.fase)}</td>
                                            <td className="px-3 py-3 tabular-nums">{formatFechaCorta(evento.fecha)}</td>
                                            <td className="px-3 py-3 tabular-nums text-[#57534e]">
                                                {evento.hora_inicio
                                                    ? `${evento.hora_inicio}${evento.hora_fin ? ` – ${evento.hora_fin}` : ''}`
                                                    : '—'}
                                            </td>
                                            <td className="px-3 py-3">
                                                <StatusBadge variant={evento.estado === 'evaluada' ? 'success' : 'warning'}>
                                                    {evento.estado === 'evaluada' ? 'Evaluada' : 'Pendiente'}
                                                </StatusBadge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                    </div>
                </>
            )}
        </div>
    );
}
