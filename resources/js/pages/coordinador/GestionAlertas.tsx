import { useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { useAlertas, type Alerta } from '@/hooks/useAlertas';
import {
    AlertTriangle,
    Clock,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    AlertCircle,
    Info,
    Loader2,
    RefreshCw,
} from 'lucide-react';

const severityConfig: Record<string, { label: string; icon: typeof AlertCircle; color: string; bg: string }> = {
    alta: { label: 'Alta', icon: AlertCircle, color: 'text-[#dc2626]', bg: 'bg-[#fee2e2]' },
    media: { label: 'Media', icon: AlertTriangle, color: 'text-[#d97706]', bg: 'bg-[#fef3c7]' },
};

const tipoLabel: Record<string, string> = {
    bitacora_sin_firmar: 'Bitácora sin firmar',
    entrega_vencida: 'Entrega vencida',
    firmas_sospechosas: 'Firmas sospechosas',
};

export default function GestionAlertas() {
    const [activeTab, setActiveTab] = useState<'all' | 'active' | 'resolved'>('active');
    const [expandedAlert, setExpandedAlert] = useState<string | null>(null);
    const { data: alertas, loading, error, refetch } = useAlertas();

    // For now, all derived alerts are active (no resolve mechanism yet)
    const resolved: Alerta[] = [];
    const activeAlerts = alertas;

    const filtered = activeTab === 'all'
        ? alertas
        : activeTab === 'active'
            ? activeAlerts
            : resolved;

    const criticalCount = activeAlerts.filter((a) => a.severidad === 'alta').length;

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Coordinación"
                title="Alertas"
                subtitle="Monitoreo de alertas derivadas de bitácoras y entregas"
                actions={
                    <button
                        onClick={refetch}
                        disabled={loading}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98] disabled:opacity-60"
                        aria-label="Refrescar alertas"
                    >
                        <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        Refrescar
                    </button>
                }
            />

            {/* Stats */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard icon={AlertTriangle} label="Alertas activas" value={activeAlerts.length} variant="warning" />
                <StatCard icon={AlertCircle} label="Críticas" value={criticalCount} variant="warning" />
                <StatCard icon={CheckCircle2} label="Resueltas" value={resolved.length} variant="success" />
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
                            className="h-24 animate-pulse rounded-xl border border-[#e5e5e5] bg-[#f5f5f4]"
                        />
                    ))}
                </div>
            )}

            {/* Alert cards */}
            {!loading && !error && (
                <div className="flex flex-col gap-3">
                    {filtered.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 py-16 text-center">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#f5f5f4]">
                                <CheckCircle2 className="h-6 w-6 text-[#16a34a]" />
                            </div>
                            <h3 className="text-base font-semibold text-[#1c1917]">Sin alertas activas</h3>
                            <p className="text-sm text-[#57534e]">No se detectaron incidencias en este momento.</p>
                        </div>
                    ) : (
                        filtered.map((alert) => {
                            const sevConfig = severityConfig[alert.severidad] ?? severityConfig.media;
                            const SeverityIcon = sevConfig.icon;
                            const isExpanded = expandedAlert === alert.id;

                            return (
                                <div
                                    key={alert.id}
                                    className={`rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)] ${
                                        alert.severidad === 'alta' ? 'border-l-4 border-l-[#dc2626]' : ''
                                    }`}
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
                                            <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${sevConfig.bg}`}>
                                                <SeverityIcon className={`h-4 w-4 ${sevConfig.color}`} />
                                            </div>
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <h3 className="text-sm font-semibold text-[#1c1917]">
                                                        {tipoLabel[alert.tipo] ?? alert.tipo}
                                                    </h3>
                                                </div>
                                                <p className="text-xs text-[#57534e] mt-0.5">{alert.proyecto}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            <StatusBadge variant={alert.severidad === 'alta' ? 'error' : 'warning'}>
                                                {sevConfig.label}
                                            </StatusBadge>
                                            <div className="flex h-2 w-2 rounded-full bg-[#dc2626] animate-pulse" />
                                        </div>
                                    </button>
                                    {isExpanded && (
                                        <div className="border-t border-[#e5e5e5] px-5 py-4 bg-[#fafaf9]">
                                            <p className="text-sm text-[#57534e] mb-3">{alert.mensaje}</p>
                                            <div className="flex items-center text-xs text-[#78716c]">
                                                <Clock className="mr-1.5 h-3.5 w-3.5" />
                                                {new Date(alert.timestamp).toLocaleString('es-CO')}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })
                    )}
                </div>
            )}
        </div>
    );
}
