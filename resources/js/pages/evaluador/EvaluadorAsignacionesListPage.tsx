import { useEffect, useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { EmptyState } from '@/components/ui/EmptyState';
import { AsignacionEvaluadorCard } from '@/components/evaluador/AsignacionEvaluadorCard';
import { useEvaluadorAsignaciones } from '@/hooks/useEvaluadorAsignaciones';
import { AlertCircle, ClipboardCheck, Loader2, Search } from 'lucide-react';

interface EvaluadorAsignacionesListPageProps {
    modo: 'pendiente' | 'evaluada';
}

export default function EvaluadorAsignacionesListPage({ modo }: EvaluadorAsignacionesListPageProps) {
    const [q, setQ] = useState('');
    const [qDebounced, setQDebounced] = useState('');

    useEffect(() => {
        const handle = window.setTimeout(() => setQDebounced(q.trim()), 300);
        return () => window.clearTimeout(handle);
    }, [q]);

    const { data, loading, error, refetch } = useEvaluadorAsignaciones({
        q: qDebounced,
        estado: modo,
    });

    const esPendiente = modo === 'pendiente';

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Evaluador"
                title={esPendiente ? 'Evaluaciones pendientes' : 'Historial de evaluaciones'}
                subtitle={
                    esPendiente
                        ? 'Asignaciones que todavía no has calificado'
                        : 'Evaluaciones que ya enviaste'
                }
            />

            <label className="flex max-w-xl flex-col gap-1.5">
                <span className="text-xs font-semibold text-[#57534e]">Buscar</span>
                <div className="relative">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a8a29e]" />
                    <input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Proyecto, código o estudiante"
                        className="w-full rounded-lg border border-[#e5e5e5] bg-white py-2.5 pl-9 pr-3 text-sm outline-none focus:border-[#c2410c]"
                    />
                </div>
            </label>

            {error && (
                <div className="flex items-center gap-2 rounded-lg border border-[#fecaca] bg-[#fee2e2] px-4 py-3 text-sm text-[#dc2626]" role="alert">
                    <AlertCircle className="h-4 w-4 shrink-0" />
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
                    icon={ClipboardCheck}
                    title={esPendiente ? 'No tienes evaluaciones pendientes' : 'Aún no hay historial'}
                    description={
                        qDebounced
                            ? 'Ninguna evaluación coincide con la búsqueda.'
                            : esPendiente
                                ? 'Cuando te asignen un proyecto, aparecerá aquí.'
                                : 'Las evaluaciones que envíes se listarán en esta sección.'
                    }
                />
            )}

            {!loading && !error && data.length > 0 && (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {data.map((asignacion) => (
                        <AsignacionEvaluadorCard key={asignacion.id} asignacion={asignacion} />
                    ))}
                </div>
            )}
        </div>
    );
}
