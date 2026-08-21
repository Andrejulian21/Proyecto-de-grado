import { Link } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { EmptyState } from '@/components/ui/EmptyState';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { useEvaluadorDashboard } from '@/hooks/useEvaluadorAsignaciones';
import { faseEvaluacionLabel, formatFechaCorta } from '@/components/evaluador/AsignacionEvaluadorCard';
import {
    ClipboardList,
    Clock,
    CheckCircle,
    Calendar,
    Mail,
    User,
    Loader2,
    AlertCircle,
    ArrowRight,
} from 'lucide-react';

export default function EvaluadorDashboard() {
    const { data, loading, error, refetch } = useEvaluadorDashboard();

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Dashboard"
                title={data ? `Hola, ${data.evaluador.name}` : 'Panel de Evaluador'}
                subtitle="Resumen de tus asignaciones de evaluación, con datos de la base de datos."
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
                <div className="flex items-center gap-2 text-sm text-[#78716c]">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Cargando panel…
                </div>
            )}

            {data && (
                <>
                    <section className="rounded-xl border border-[#e5e5e5] bg-white p-5">
                        <div className="flex flex-col gap-2 text-sm text-[#57534e]">
                            <p className="flex items-center gap-2 font-semibold text-[#1c1917]">
                                <User className="h-4 w-4 text-[#c2410c]" />
                                {data.evaluador.name}
                            </p>
                            <p className="flex items-center gap-2">
                                <Mail className="h-4 w-4 text-[#c2410c]" />
                                {data.evaluador.email}
                            </p>
                        </div>
                    </section>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard icon={ClipboardList} label="Proyectos asignados" value={data.resumen.asignadas} />
                        <StatCard icon={Clock} label="Evaluaciones pendientes" value={data.resumen.pendientes} variant="warning" />
                        <StatCard icon={CheckCircle} label="Evaluaciones realizadas" value={data.resumen.realizadas} variant="success" />
                    </div>

                    <section>
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-bold uppercase tracking-[0.05em] text-[#78716c]">
                                Próximas evaluaciones
                            </h2>
                            <Link to="/evaluador/calendario" className="text-xs font-semibold text-[#c2410c]">
                                Ver calendario
                            </Link>
                        </div>
                        {data.proximas.length === 0 ? (
                            <EmptyState
                                icon={Calendar}
                                title="No hay próximas evaluaciones con fecha"
                                description={
                                    data.resumen.sin_fecha > 0
                                        ? `Tienes ${data.resumen.sin_fecha} asignación(es) sin fecha programada.`
                                        : 'Cuando te asignen una fecha, aparecerá aquí.'
                                }
                            />
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {data.proximas.map((evento) => (
                                    <li key={evento.id}>
                                        <Link
                                            to={`/evaluador/asignaciones/${evento.id}`}
                                            className="flex items-center justify-between gap-3 rounded-xl border border-[#e5e5e5] bg-white px-4 py-3 hover:border-[#c2410c]"
                                        >
                                            <div>
                                                <p className="text-sm font-semibold text-[#1c1917]">{evento.proyecto?.titulo}</p>
                                                <p className="text-xs text-[#57534e]">
                                                    {evento.proyecto?.codigo} · {faseEvaluacionLabel(evento.fase)} · {formatFechaCorta(evento.fecha)}
                                                    {evento.hora_inicio ? ` ${evento.hora_inicio}` : ''}
                                                </p>
                                            </div>
                                            <StatusBadge variant="warning">Pendiente</StatusBadge>
                                            <ArrowRight className="h-4 w-4 text-[#c2410c]" />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </>
            )}
        </div>
    );
}
