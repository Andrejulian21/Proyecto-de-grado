import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/hooks/useAuth';
import { cn } from '@/lib/utils';
import { FRONTEND_VALIDATION_MODE, mockDelay } from '@/mocks/validationMode';
import { getMockAuditLogs } from '@/mocks/coordinadorMock';
import { Search, Calendar, ChevronLeft, ChevronRight, Loader2, RotateCcw } from 'lucide-react';

interface AuditEntry {
    id: number;
    user: { name: string; email: string } | null;
    action: string;
    description: string;
    ip_address: string;
    user_agent: string;
    created_at: string;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    total: number;
}

const ACTION_LABELS: Record<string, string> = {
    'login.success': 'Inicio de sesión exitoso',
    'login.failed': 'Inicio de sesión fallido',
    'logout': 'Cierre de sesión',
    'user.created': 'Usuario creado',
    'user.updated': 'Usuario actualizado',
    'user.deleted': 'Usuario eliminado',
    'password.changed': 'Cambio de contraseña',
    'account.locked': 'Cuenta bloqueada',
};

function formatDateTime(dateStr: string) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

export default function AuditLog() {
    const { role } = useAuth();

    const [logs, setLogs] = useState<AuditEntry[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(true);

    const [actionFilter, setActionFilter] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [userId, setUserId] = useState('');

    const [filtersApplied, setFiltersApplied] = useState(false);

    const fetchLogs = useCallback(async () => {
        setLoading(true);
        try {
            if (FRONTEND_VALIDATION_MODE) {
                await mockDelay();
                const json = getMockAuditLogs(page, 50);
                setLogs(
                    json.data.map((row) => ({
                        id: row.id,
                        user: row.user_name ? { name: row.user_name, email: '' } : null,
                        action: row.action,
                        description: row.description,
                        ip_address: row.ip_address,
                        user_agent: 'Mock Browser',
                        created_at: row.created_at,
                    })),
                );
                setMeta(json.meta);
                return;
            }
            const params = new URLSearchParams({ page: String(page), per_page: '50' });
            if (actionFilter) params.set('action', actionFilter);
            if (dateFrom) params.set('date_from', dateFrom);
            if (dateTo) params.set('date_to', dateTo);
            if (userId.trim()) params.set('user_id', userId.trim());

            const res = await fetch(`/api/admin/audit-logs?${params}`, {
                credentials: 'include',
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) throw new Error('Error al cargar registros de auditoría');
            const json = await res.json();
            setLogs(json.data);
            setMeta(json.meta);
        } catch {
            setLogs([]);
            setMeta(null);
        } finally {
            setLoading(false);
        }
    }, [page, actionFilter, dateFrom, dateTo, userId]);

    useEffect(() => {
        fetchLogs();
    }, [fetchLogs]);

    function applyFilters() {
        setPage(1);
        setFiltersApplied(true);
    }

    function clearFilters() {
        setActionFilter('');
        setDateFrom('');
        setDateTo('');
        setUserId('');
        setPage(1);
        setFiltersApplied(false);
    }

    if (role !== 'Coordinador') {
        return (
            <div className="flex items-center justify-center rounded-xl border-2 border-dashed border-border p-12">
                <p className="text-text-muted">No tienes permisos para acceder a esta sección.</p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-text">Registro de Auditoría</h1>
                <p className="mt-1 text-sm text-text-muted">
                    Historial de actividades del sistema
                </p>
            </div>

            <div className="rounded-xl border border-border bg-surface p-4 shadow-warm-sm">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-text-muted">Acción</label>
                        <select
                            value={actionFilter}
                            onChange={(e) => setActionFilter(e.target.value)}
                            className="w-full rounded-lg border border-border px-3 py-2 text-sm text-text outline-none transition focus:border-primary focus:ring-1 focus:ring-primary"
                        >
                            <option value="">Todas</option>
                            {Object.entries(ACTION_LABELS).map(([k, v]) => (
                                <option key={k} value={k}>{v}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-text-muted">Fecha desde</label>
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="w-full rounded-lg border border-border px-3 py-2 text-sm text-text outline-none transition focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-text-muted">Fecha hasta</label>
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="w-full rounded-lg border border-border px-3 py-2 text-sm text-text outline-none transition focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-text-muted">ID de usuario</label>
                        <input
                            type="text"
                            value={userId}
                            onChange={(e) => setUserId(e.target.value)}
                            placeholder="Ej: 5"
                            className="w-full rounded-lg border border-border px-3 py-2 text-sm text-text outline-none transition placeholder:text-text-subtle focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                    </div>
                    <div className="flex items-end gap-2">
                        <button
                            onClick={applyFilters}
                            className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-hover"
                        >
                            <Search className="h-4 w-4" />
                            Filtrar
                        </button>
                        <button
                            onClick={clearFilters}
                            className="flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-text transition hover:bg-surface-alt"
                        >
                            <RotateCcw className="h-4 w-4" />
                            Limpiar
                        </button>
                    </div>
                </div>
            </div>

            <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-warm-sm">
                {loading ? (
                    <div className="flex items-center justify-center py-16">
                        <Loader2 className="h-6 w-6 animate-spin text-primary" />
                    </div>
                ) : logs.length === 0 ? (
                    <div className="px-6 py-16 text-center text-sm text-text-muted">
                        No se encontraron registros de auditoría.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-border bg-surface-alt text-xs font-semibold uppercase text-text-muted">
                                <tr>
                                    <th className="whitespace-nowrap px-4 py-3.5">Fecha / Hora</th>
                                    <th className="whitespace-nowrap px-4 py-3.5">Usuario</th>
                                    <th className="whitespace-nowrap px-4 py-3.5">Acción</th>
                                    <th className="px-4 py-3.5">Descripción</th>
                                    <th className="whitespace-nowrap px-4 py-3.5">IP</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {logs.map((entry) => (
                                    <tr key={entry.id} className="transition hover:bg-surface-alt/50">
                                        <td className="whitespace-nowrap px-4 py-3 text-xs text-text-subtle">
                                            {formatDateTime(entry.created_at)}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            {entry.user ? (
                                                <div>
                                                    <p className="font-medium text-text">{entry.user.name}</p>
                                                    <p className="text-xs text-text-muted">{entry.user.email}</p>
                                                </div>
                                            ) : (
                                                <span className="text-text-subtle">—</span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <span
                                                className={cn(
                                                    'inline-block rounded-md px-2 py-0.5 text-xs font-medium',
                                                    entry.action.startsWith('login.success') && 'bg-success-container text-success-on-container',
                                                    entry.action.startsWith('login.failed') && 'bg-error-container text-error-on-container',
                                                    entry.action.startsWith('user.') && 'bg-primary-container text-primary-on-container',
                                                    entry.action.startsWith('password') && 'bg-warning-container text-warning-on-container',
                                                    entry.action.startsWith('account') && 'bg-error-container text-error-on-container',
                                                )}
                                            >
                                                {ACTION_LABELS[entry.action] ?? entry.action}
                                            </span>
                                        </td>
                                        <td className="max-w-xs truncate px-4 py-3 text-text-muted">
                                            {entry.description}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-text-subtle">
                                            {entry.ip_address}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {meta && meta.last_page > 1 && (
                <div className="flex items-center justify-between text-sm">
                    <p className="text-text-muted">
                        Página {meta.current_page} de {meta.last_page} ({meta.total} registros)
                    </p>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                            disabled={page <= 1}
                            className="flex items-center gap-1 rounded-lg border border-border px-3 py-2 text-sm text-text transition hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ChevronLeft className="h-4 w-4" />
                            Anterior
                        </button>
                        <button
                            onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
                            disabled={page >= meta.last_page}
                            className="flex items-center gap-1 rounded-lg border border-border px-3 py-2 text-sm text-text transition hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Siguiente
                            <ChevronRight className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
