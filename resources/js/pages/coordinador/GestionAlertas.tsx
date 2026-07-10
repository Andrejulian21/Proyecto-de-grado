import { useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { AlertTriangle, Clock, CheckCircle2, ChevronDown, ChevronRight, AlertCircle, Info } from 'lucide-react';

interface Alert {
    id: number;
    title: string;
    description: string;
    date: string;
    severity: 'critical' | 'warning' | 'info';
    status: 'active' | 'resolved';
    project: string;
}

const MOCK_ALERTS: Alert[] = [
    { id: 1, title: 'Entrega final sin presentar', description: 'El proyecto PG-2026-010 no ha realizado la entrega final. La fecha límite venció hace 5 días.', date: '05/05/2026', severity: 'critical', status: 'active', project: 'PG-2026-010' },
    { id: 2, title: 'Múltiples correcciones pendientes', description: 'El Avance 2 del proyecto PG-2026-014 lleva 3 semanas en estado de correcciones sin re-entrega.', date: '03/05/2026', severity: 'warning', status: 'active', project: 'PG-2026-014' },
    { id: 3, title: 'Bitácora sin firmar por 15 días', description: 'La bitácora del 15/04/2026 del proyecto PG-2026-008 está pendiente de firma del director.', date: '30/04/2026', severity: 'warning', status: 'active', project: 'PG-2026-008' },
    { id: 4, title: 'Cupo de dirección excedido', description: 'El Dr. Ricardo Gómez ha alcanzado el 100% de su cupo de dirección para este semestre.', date: '28/04/2026', severity: 'warning', status: 'active', project: 'General' },
    { id: 5, title: 'Evaluador no asignado', description: '3 proyectos de la cohorte actual no tienen evaluador externo asignado a 30 días de la evaluación final.', date: '25/04/2026', severity: 'critical', status: 'active', project: 'General' },
    { id: 6, title: 'Proyecto sin actividad', description: 'El proyecto PG-2026-003 no registra actividad en los últimos 30 días.', date: '20/04/2026', severity: 'info', status: 'resolved', project: 'PG-2026-003' },
    { id: 7, title: 'Formulario de inscripción incompleto', description: 'El proyecto PG-2026-011 tiene documentación pendiente por completar.', date: '15/04/2026', severity: 'info', status: 'resolved', project: 'PG-2026-011' },
];

const severityConfig: Record<string, { label: string; variant: 'error' | 'warning' | 'info' }> = {
    critical: { label: 'Crítica', variant: 'error' },
    warning: { label: 'Advertencia', variant: 'warning' },
    info: { label: 'Informativa', variant: 'info' },
};

export default function GestionAlertas() {
    const [activeTab, setActiveTab] = useState<'all' | 'active' | 'resolved'>('active');
    const [expandedAlert, setExpandedAlert] = useState<number | null>(null);

    const filtered = MOCK_ALERTS.filter((a) => {
        if (activeTab === 'all') return true;
        return a.status === activeTab;
    });

    const activeCount = MOCK_ALERTS.filter((a) => a.status === 'active').length;
    const criticalCount = MOCK_ALERTS.filter((a) => a.severity === 'critical' && a.status === 'active').length;

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Coordinación"
                title="Alertas"
                subtitle="Monitoreo de alertas y notificaciones del sistema"
            />

            {/* Stats */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard icon={AlertTriangle} label="Alertas activas" value={activeCount} variant="warning" />
                <StatCard icon={AlertCircle} label="Críticas" value={criticalCount} variant="warning" />
                <StatCard icon={CheckCircle2} label="Resueltas" value={MOCK_ALERTS.filter((a) => a.status === 'resolved').length} variant="success" />
            </div>

            {/* Tabs */}
            <div className="flex items-center gap-1 rounded-lg border border-[#e5e5e5] bg-[#f5f5f4] p-1 w-fit">
                {(['active', 'all', 'resolved'] as const).map((tab) => (
                    <button
                        key={tab}
                        onClick={() => setActiveTab(tab)}
                        className={`rounded-md px-4 py-1.5 text-xs font-semibold transition-colors ${
                            activeTab === tab ? 'bg-white text-[#1c1917] shadow-sm' : 'text-[#57534e] hover:text-[#1c1917]'
                        }`}
                    >
                        {tab === 'active' ? 'Activas' : tab === 'all' ? 'Todas' : 'Resueltas'}
                    </button>
                ))}
            </div>

            {/* Alert cards */}
            <div className="flex flex-col gap-3">
                {filtered.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 py-16 text-center">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#f5f5f4]">
                            <CheckCircle2 className="h-6 w-6 text-[#16a34a]" />
                        </div>
                        <h3 className="text-base font-semibold text-[#1c1917]">No hay alertas</h3>
                        <p className="text-sm text-[#57534e]">Todas las alertas han sido gestionadas.</p>
                    </div>
                ) : (
                    filtered.map((alert) => {
                        const isExpanded = expandedAlert === alert.id;
                        return (
                            <div
                                key={alert.id}
                                className={`rounded-xl border bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)] ${
                                    alert.status === 'resolved' ? 'opacity-60' : 'border-[#e5e5e5]'
                                } ${alert.severity === 'critical' && alert.status === 'active' ? 'border-l-4 border-l-[#dc2626]' : ''}`}
                            >
                                <button
                                    onClick={() => setExpandedAlert(isExpanded ? null : alert.id)}
                                    className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition-colors hover:bg-[#fafaf9]"
                                >
                                    <div className="flex items-center gap-3 min-w-0">
                                        {isExpanded ? (
                                            <ChevronDown className="h-4 w-4 shrink-0 text-[#78716c]" />
                                        ) : (
                                            <ChevronRight className="h-4 w-4 shrink-0 text-[#78716c]" />
                                        )}
                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#f5f5f4]">
                                            {alert.severity === 'critical' ? (
                                                <AlertCircle className="h-4 w-4 text-[#dc2626]" />
                                            ) : alert.severity === 'warning' ? (
                                                <AlertTriangle className="h-4 w-4 text-[#d97706]" />
                                            ) : (
                                                <Info className="h-4 w-4 text-[#2563eb]" />
                                            )}
                                        </div>
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <h3 className="text-sm font-semibold text-[#1c1917]">{alert.title}</h3>
                                            </div>
                                            <p className="text-xs text-[#57534e] mt-0.5">{alert.project}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                        <StatusBadge variant={severityConfig[alert.severity].variant}>
                                            {severityConfig[alert.severity].label}
                                        </StatusBadge>
                                        {alert.status === 'active' ? (
                                            <div className="flex h-2 w-2 rounded-full bg-[#dc2626] animate-pulse" />
                                        ) : (
                                            <CheckCircle2 className="h-4 w-4 text-[#16a34a]" />
                                        )}
                                    </div>
                                </button>
                                {isExpanded && (
                                    <div className="border-t border-[#e5e5e5] px-5 py-4 bg-[#fafaf9]">
                                        <p className="text-sm text-[#57534e] mb-3">{alert.description}</p>
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex items-center gap-2 text-xs text-[#78716c]">
                                                <Clock className="h-3.5 w-3.5" />
                                                {alert.date}
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {alert.status === 'active' && (
                                                    <button className="inline-flex min-h-[32px] items-center gap-1.5 rounded-lg bg-[#c2410c] px-3 py-1 text-xs font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]">
                                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                                        Marcar como resuelta
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })
                )}
            </div>
        </div>
    );
}
