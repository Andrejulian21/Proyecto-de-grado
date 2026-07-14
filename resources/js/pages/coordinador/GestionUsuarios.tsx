import { useState, useCallback } from 'react';
import { useAuth } from '@/hooks/useAuth';
import { useUnifiedUsers, type UnifiedUser, type UnifiedRole } from '@/hooks/useUnifiedUsers';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageHeader } from '@/components/ui/PageHeader';
import {
    Loader2,
    UserPlus,
    Save,
    Trash2,
    Users,
    RefreshCw,
    X,
    AlertCircle,
} from 'lucide-react';

const ROLES: UnifiedRole[] = ['Estudiante', 'Director', 'Coordinador', 'EvaluadorExterno'];
const ROLE_LABELS: Record<string, string> = {
    Estudiante: 'Estudiante',
    Director: 'Director',
    Coordinador: 'Coordinador',
    EvaluadorExterno: 'Evaluador Externo',
    Pendiente: 'Pendiente',
};

function formatDate(dateStr: string | null | undefined): string {
    if (!dateStr) return '—';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('es-CO', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '—';
    }
}

export default function GestionUsuarios() {
    const { user } = useAuth();
    const {
        data: users,
        loading,
        error,
        refetch,
        addToWhitelist,
        updateRole,
        deleteUser,
    } = useUnifiedUsers();

    // Role change tracking
    const [roleChanges, setRoleChanges] = useState<Record<string, string>>({});
    const [savingRoles, setSavingRoles] = useState<Set<string>>(new Set());

    // Delete state
    const [deleteTarget, setDeleteTarget] = useState<UnifiedUser | null>(null);
    const [deleting, setDeleting] = useState(false);

    // Add user form state
    const [showAddForm, setShowAddForm] = useState(false);
    const [addEmail, setAddEmail] = useState('');
    const [addName, setAddName] = useState('');
    const [adding, setAdding] = useState(false);
    const [addError, setAddError] = useState<string | null>(null);

    // Banner
    const [banner, setBanner] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

    function showBanner(type: 'success' | 'error', text: string) {
        setBanner({ type, text });
        setTimeout(() => setBanner(null), 4000);
    }

    // Derive a stable row key from email (unique across all sources)
    const getRowKey = useCallback((row: UnifiedUser) => row.email, []);

    // ── Role change handlers ──

    function handleRoleChange(email: string, newRole: string) {
        setRoleChanges((prev) => {
            const next = { ...prev };
            if (next[email] === newRole) {
                delete next[email];
            } else {
                next[email] = newRole;
            }
            return next;
        });
    }

    async function handleSaveRole(row: UnifiedUser) {
        const newRole = roleChanges[row.email];
        if (!newRole || row.id === null) return;

        const key = row.email;
        setSavingRoles((prev) => new Set(prev).add(key));

        try {
            await updateRole(row.id, newRole);
            setRoleChanges((prev) => {
                const next = { ...prev };
                delete next[key];
                return next;
            });
            showBanner('success', `Rol actualizado a "${ROLE_LABELS[newRole] || newRole}"`);
        } catch (err: any) {
            showBanner('error', err.message ?? 'Error al actualizar rol');
        } finally {
            setSavingRoles((prev) => {
                const next = new Set(prev);
                next.delete(key);
                return next;
            });
        }
    }

    // ── Delete handler ──

    async function handleDelete() {
        if (!deleteTarget) return;

        setDeleting(true);
        try {
            await deleteUser(deleteTarget);
            showBanner('success', 'Usuario eliminado correctamente');
            setDeleteTarget(null);
        } catch (err: any) {
            showBanner('error', err.message ?? 'Error al eliminar');
        } finally {
            setDeleting(false);
        }
    }

    // ── Add whitelist handler ──

    async function handleAddUser(e: React.FormEvent) {
        e.preventDefault();
        if (adding || !addEmail.trim()) return;

        setAdding(true);
        setAddError(null);

        try {
            await addToWhitelist(addEmail.trim(), addName.trim() || undefined);
            showBanner('success', 'Usuario agregado a la whitelist');
            setAddEmail('');
            setAddName('');
            setShowAddForm(false);
        } catch (err: any) {
            setAddError(err.message);
        } finally {
            setAdding(false);
        }
    }

    // ── Columns ──

    const isSelf = useCallback(
        (row: UnifiedUser) => user?.email?.toLowerCase() === row.email.toLowerCase(),
        [user?.email],
    );

    const columns: Column<UnifiedUser>[] = [
        {
            key: 'name',
            label: 'Nombre',
            render: (row) => (
                <span className="font-medium text-[#1c1917]">
                    {row.name || '—'}
                </span>
            ),
        },
        {
            key: 'email',
            label: 'Correo',
        },
        {
            key: 'role',
            label: 'Rol',
            className: 'min-w-[160px]',
            render: (row) => {
                const self = isSelf(row);
                const saving = savingRoles.has(row.email);
                const hasChange = row.email in roleChanges;
                const currentRole = roleChanges[row.email] ?? row.role;

                return (
                    <div className="flex items-center gap-2">
                        <select
                            value={currentRole}
                            onChange={(e) => handleRoleChange(row.email, e.target.value)}
                            disabled={self || row.role === 'Pendiente' || saving}
                            className="min-w-[140px] rounded-lg border border-[#e5e5e5] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] disabled:cursor-not-allowed disabled:opacity-50"
                            aria-label={`Cambiar rol de ${row.email}`}
                        >
                            {row.role === 'Pendiente' ? (
                                <option value="Pendiente">Pendiente</option>
                            ) : (
                                ROLES.map((r) => (
                                    <option key={r} value={r}>
                                        {ROLE_LABELS[r]}
                                    </option>
                                ))
                            )}
                        </select>

                        {hasChange && !self && (
                            <button
                                onClick={() => handleSaveRole(row)}
                                disabled={saving}
                                className="inline-flex h-7 w-7 items-center justify-center rounded-lg text-[#c2410c] transition-colors hover:bg-[#fed7aa] disabled:opacity-50"
                                title="Guardar cambio de rol"
                                aria-label={`Guardar cambio de rol para ${row.email}`}
                            >
                                {saving ? (
                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                ) : (
                                    <Save className="h-3.5 w-3.5" />
                                )}
                            </button>
                        )}

                        {self && (
                            <span className="text-[10px] font-medium text-[#dc2626] whitespace-nowrap">
                                No puedes cambiar tu propio rol
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            key: 'last_access',
            label: 'Último acceso',
            render: (row) => (
                <span className="text-xs text-[#78716c]">{formatDate(row.last_access)}</span>
            ),
        },
        {
            key: 'actions',
            label: 'Acciones',
            className: 'text-right',
            render: (row) => (
                <div className="inline-flex gap-0.5">
                    <button
                        onClick={() => setDeleteTarget(row)}
                        disabled={isSelf(row)}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626] disabled:cursor-not-allowed disabled:opacity-30"
                        title="Eliminar usuario"
                        aria-label={`Eliminar ${row.email}`}
                    >
                        <Trash2 className="h-4 w-4" />
                    </button>
                </div>
            ),
        },
    ];

    // ── Role check ──

    if (!user || user.role !== 'Coordinador') {
        return (
            <div className="flex items-center justify-center rounded-xl border-2 border-dashed border-[#e5e5e5] p-12">
                <p className="text-sm text-[#57534e]">
                    No tienes permisos para acceder a esta sección.
                </p>
            </div>
        );
    }

    // ── Render ──

    return (
        <div className="flex flex-col gap-6">
            {/* Banner */}
            {banner && (
                <div
                    className={`rounded-lg border px-4 py-3 text-sm font-medium ${
                        banner.type === 'success'
                            ? 'border-[#dcfce7] bg-[#dcfce7] text-[#14532d]'
                            : 'border-[#fee2e2] bg-[#fee2e2] text-[#7f1d1d]'
                    }`}
                    role="alert"
                >
                    {banner.text}
                </div>
            )}

            {/* Error banner with retry */}
            {error && !loading && (
                <div className="flex items-center gap-3 rounded-lg border border-[#fee2e2] bg-[#fee2e2] px-4 py-3 text-sm text-[#7f1d1d]">
                    <AlertCircle className="h-5 w-5 shrink-0" />
                    <span className="flex-1">{error}</span>
                    <button
                        onClick={refetch}
                        className="inline-flex shrink-0 items-center gap-1 rounded-lg border border-[#fca5a5] bg-white px-3 py-1.5 text-xs font-semibold text-[#7f1d1d] transition-colors hover:bg-[#fef2f2]"
                    >
                        <RefreshCw className="h-3.5 w-3.5" />
                        Reintentar
                    </button>
                </div>
            )}

            {/* Page header */}
            <PageHeader
                eyebrow="Administración"
                title="Gestión de Usuarios"
                subtitle="Administre los usuarios del sistema, asigne roles y gestione la lista de correos autorizados."
                actions={
                    !showAddForm && (
                        <button
                            onClick={() => setShowAddForm(true)}
                            className="inline-flex items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a]"
                        >
                            <UserPlus className="h-4 w-4" />
                            Agregar usuario
                        </button>
                    )
                }
            />

            {/* Inline add form */}
            {showAddForm && (
                <form
                    onSubmit={handleAddUser}
                    className="rounded-xl border border-[#e5e5e5] bg-white p-4 shadow-[0_1px_2px_rgba(28,25,23,0.05)]"
                >
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div className="flex-1">
                            <label
                                htmlFor="add-email"
                                className="mb-1 block text-xs font-semibold text-[#1c1917]"
                            >
                                Correo electrónico <span className="text-[#dc2626]">*</span>
                            </label>
                            <input
                                id="add-email"
                                type="email"
                                value={addEmail}
                                onChange={(e) => setAddEmail(e.target.value)}
                                placeholder="usuario@unab.edu.co"
                                required
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            />
                        </div>
                        <div className="flex-1">
                            <label
                                htmlFor="add-name"
                                className="mb-1 block text-xs font-semibold text-[#1c1917]"
                            >
                                Nombre completo
                            </label>
                            <input
                                id="add-name"
                                type="text"
                                value={addName}
                                onChange={(e) => setAddName(e.target.value)}
                                placeholder="Ej: Juan Pérez"
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="submit"
                                disabled={adding || !addEmail.trim()}
                                className="inline-flex items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {adding && <Loader2 className="h-4 w-4 animate-spin" />}
                                <UserPlus className="h-4 w-4" />
                                Agregar
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setShowAddForm(false);
                                    setAddEmail('');
                                    setAddName('');
                                    setAddError(null);
                                }}
                                className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2.5 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                            >
                                <X className="h-4 w-4" />
                                Cancelar
                            </button>
                        </div>
                    </div>
                    {addError && (
                        <p className="mt-2 text-xs font-medium text-[#dc2626]" role="alert">
                            {addError}
                        </p>
                    )}
                </form>
            )}

            {/* Users table */}
            <DataTable
                columns={columns}
                data={users}
                loading={loading}
                emptyMessage="No hay usuarios registrados."
                getRowKey={getRowKey}
            />

            {/* Delete confirmation dialog */}
            <ConfirmDialog
                open={deleteTarget !== null}
                title="Eliminar usuario"
                message={
                    deleteTarget
                        ? `¿Estás seguro de que deseas eliminar a ${deleteTarget.name || deleteTarget.email}? Esta acción no se puede deshacer.`
                        : ''
                }
                confirmLabel={deleting ? 'Eliminando…' : 'Eliminar'}
                cancelLabel="Cancelar"
                onConfirm={handleDelete}
                onCancel={() => {
                    if (!deleting) setDeleteTarget(null);
                }}
                variant="danger"
            />
        </div>
    );
}
