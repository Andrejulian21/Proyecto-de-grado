import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/hooks/useAuth';
import { apiFetch } from '@/lib/utils';
import {
    Loader2,
    ChevronLeft,
    ChevronRight,
    Trash2,
    X,
    UserPlus,
    Pencil,
    RefreshCw,
} from 'lucide-react';

interface User {
    id: number;
    email: string;
    role: string;
    name?: string;
    created_by: { name: string } | null;
    created_at: string;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    total: number;
}

const ROLES = ['Estudiante', 'Director', 'Coordinador', 'EvaluadorExterno'] as const;
const ROLE_LABELS: Record<string, string> = {
    Estudiante: 'Estudiante',
    Director: 'Director',
    Coordinador: 'Coordinador',
    EvaluadorExterno: 'Evaluador Externo',
};

function formatDate(dateStr: string | null | undefined) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function genPassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    const array = new Uint32Array(14);
    crypto.getRandomValues(array);
    let pwd = '';
    for (let i = 0; i < 14; i++) {
        pwd += chars.charAt(array[i] % chars.length);
    }
    return pwd + '!';
}

export default function GestionUsuarios() {
    const { role } = useAuth();

    // ── Sección 1: Whitelist ──
    const [users, setUsers] = useState<User[]>([]);

    // ── Whitelist (tabla separada de users) ──
    const [whitelistEntries, setWhitelistEntries] = useState<User[]>([]);
    const [whitelistMeta, setWhitelistMeta] = useState<PaginationMeta | null>(null);
    const [whitelistPage, setWhitelistPage] = useState(1);
    const [whitelistLoading, setWhitelistLoading] = useState(false);

    // ── Sección 2: Crear evaluador ──
    const [evalNombre, setEvalNombre] = useState('');
    const [evalCorreo, setEvalCorreo] = useState('');
    const [evalPass, setEvalPass] = useState(genPassword());
    const [evalPass2, setEvalPass2] = useState(evalPass);

    // ── Sección 3: Agregar correos ──
    const [estCorreo, setEstCorreo] = useState('');
    const [estNombre, setEstNombre] = useState('');
    const [dirCorreo, setDirCorreo] = useState('');
    const [dirNombre, setDirNombre] = useState('');

    // ── Sección 4: Roles ──
    const [roleChanges, setRoleChanges] = useState<Record<number, string>>({});

    // ── Modal / message ──
    const [modalOpen, setModalOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [formEmail, setFormEmail] = useState('');
    const [formName, setFormName] = useState('');
    const [formRole, setFormRole] = useState('Estudiante');
    const [submitting, setSubmitting] = useState(false);

    const [deleteTarget, setDeleteTarget] = useState<User | null>(null);
    const [deleteIsWhitelist, setDeleteIsWhitelist] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [savingRoles, setSavingRoles] = useState(false);

    const fetchUsers = useCallback(async () => {
        try {
            const res = await apiFetch('/api/admin/usuarios?per_page=200');
            if (!res.ok) throw new Error('Error al cargar usuarios');
            const json = await res.json();
            setUsers(json.data ?? json);
        } catch {
            setMessage({ type: 'error', text: 'Error al cargar usuarios' });
        }
    }, []);

    const fetchWhitelist = useCallback(async () => {
        setWhitelistLoading(true);
        try {
            const params = new URLSearchParams({ page: String(whitelistPage), per_page: '20' });
            const res = await apiFetch(`/api/admin/whitelist?${params}`);
            if (!res.ok) throw new Error('Error al cargar whitelist');
            const json = await res.json();
            setWhitelistEntries(json.data);
            setWhitelistMeta(json.meta);
        } catch {
            setMessage({ type: 'error', text: 'Error al cargar whitelist' });
        } finally {
            setWhitelistLoading(false);
        }
    }, [whitelistPage]);

    useEffect(() => {
        fetchUsers();
    }, [fetchUsers]);

    useEffect(() => {
        fetchWhitelist();
    }, [fetchWhitelist]);

    function showMsg(type: 'success' | 'error', text: string) {
        setMessage({ type, text });
        setTimeout(() => setMessage(null), 4000);
    }

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        try {
            const url = editingUser ? `/api/admin/usuarios/${editingUser.id}` : '/api/admin/whitelist';
            const method = editingUser ? 'PUT' : 'POST';
            const body = editingUser ? { role: formRole } : { email: formEmail.trim(), name: formName.trim() || null, role: formRole };
            
            const res = await apiFetch(url, {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });

            if (!res.ok) {
                const err = await res.json().catch(() => null);
                throw new Error(err?.message || 'Error al guardar');
            }

            showMsg('success', editingUser ? 'Usuario actualizado' : 'Usuario creado');
            setModalOpen(false);
            setEditingUser(null);
            setFormEmail('');
            setFormRole('Estudiante');
            fetchUsers();
        } catch (err: any) {
            showMsg('error', err.message);
        } finally {
            setSubmitting(false);
        }
    }

    async function handleDelete() {
        if (!deleteTarget || deleting) return;
        setDeleting(true);
        try {
            const endpoint = deleteIsWhitelist
                ? `/api/admin/whitelist/${deleteTarget.id}`
                : `/api/admin/usuarios/${deleteTarget.id}`;
            const res = await apiFetch(endpoint, { method: 'DELETE' });
            if (!res.ok) throw new Error('Error al eliminar');
            showMsg('success', deleteIsWhitelist ? 'Correo eliminado de la whitelist' : 'Usuario eliminado');
            setDeleteTarget(null);
            setDeleteIsWhitelist(false);
            if (deleteIsWhitelist) {
                fetchWhitelist();
                fetchUsers();
            } else {
                fetchUsers();
            }
        } catch {
            showMsg('error', 'Error al eliminar');
        } finally {
            setDeleting(false);
        }
    }

    function openEdit(u: User) {
        setEditingUser(u);
        setFormEmail(u.email);
        setFormRole(u.role);
        setModalOpen(true);
    }

    async     function toggleBlock(_u: User) {
        // Función reservada para futura activación/desactivación de usuarios.
    }

    function handleRoleChange(userId: number, newRole: string) {
        setRoleChanges((prev) => {
            const next = { ...prev };
            if (next[userId] === newRole) {
                delete next[userId];
            } else {
                next[userId] = newRole;
            }
            return next;
        });
    }

    async function saveAllRoles() {
        setSavingRoles(true);
        try {
            for (const [userId, newRole] of Object.entries(roleChanges)) {
                const res = await apiFetch(`/api/admin/usuarios/${userId}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ role: newRole }),
                });
                if (!res.ok) throw new Error(`Error al actualizar usuario ${userId}`);
            }
            showMsg('success', 'Roles actualizados correctamente');
            setRoleChanges({});
            fetchUsers();
        } catch {
            showMsg('error', 'Error al guardar cambios de roles');
        } finally {
            setSavingRoles(false);
        }
    }

    async function handleCrearEvaluador(e: React.FormEvent) {
        e.preventDefault();
        try {
            const res = await apiFetch('/api/admin/evaluadores', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: evalCorreo.trim(),
                    name: evalNombre.trim(),
                }),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => null);
                throw new Error(err?.message || 'Error al crear evaluador');
            }
            showMsg('success', 'Evaluador creado exitosamente');
            setEvalNombre('');
            setEvalCorreo('');
            const newPw = genPassword();
            setEvalPass(newPw);
            setEvalPass2(newPw);
            fetchUsers();
        } catch (err: any) {
            showMsg('error', err.message);
        }
    }

    async function handleAgregarEstudiante(e: React.FormEvent) {
        e.preventDefault();
        try {
            const res = await apiFetch('/api/admin/whitelist', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: estCorreo.trim(), name: estNombre.trim() || null, role: 'Estudiante' }),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => null);
                throw new Error(err?.message || 'Error al agregar estudiante');
            }
            showMsg('success', 'Estudiante agregado');
            setEstCorreo('');
            setEstNombre('');
            fetchWhitelist();
        } catch (err: any) {
            showMsg('error', err.message);
        }
    }

    async function handleAgregarDirector(e: React.FormEvent) {
        e.preventDefault();
        try {
            const res = await apiFetch('/api/admin/whitelist', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: dirCorreo.trim(), name: dirNombre.trim() || null, role: 'Director' }),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => null);
                throw new Error(err?.message || 'Error al agregar director');
            }
            showMsg('success', 'Director agregado');
            setDirCorreo('');
            setDirNombre('');
            fetchWhitelist();
        } catch (err: any) {
            showMsg('error', err.message);
        }
    }

    function badgeClass(status: string) {
        switch (status) {
            case 'Activo': return 'bg-[#dcfce7] text-[#14532d]';
            case 'Inactivo': return 'bg-[#e7e5e4] text-[#57534e]';
            case 'Pendiente': return 'bg-[#fef3c7] text-[#78350f]';
            default: return 'bg-[#e7e5e4] text-[#57534e]';
        }
    }

    // Compute evaluadores from users
    const evaluadores = users.filter((u) => u.role === 'EvaluadorExterno');

    if (role !== 'Coordinador') {
        return (
            <div className="flex items-center justify-center rounded-xl border-2 border-dashed border-border p-12">
                <p className="text-text-muted">No tienes permisos para acceder a esta sección.</p>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            {message && (
                <div
                    className={`rounded-lg border px-4 py-3 text-sm font-medium ${
                        message.type === 'success'
                            ? 'border-[#dcfce7] bg-[#dcfce7] text-[#14532d]'
                            : 'border-[#fee2e2] bg-[#fee2e2] text-[#7f1d1d]'
                    }`}
                >
                    {message.text}
                </div>
            )}

            <div className="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <span className="inline-flex items-center gap-1 rounded-full bg-[#fed7aa] px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.05em] text-[#c2410c]">
                        Administración
                    </span>
                    <h2 className="mt-2 text-2xl font-bold text-text">Gestión de Usuarios y Accesos</h2>
                    <p className="mt-1 text-sm text-text-muted">
                        Administre los correos institucionales autorizados y cree cuentas para evaluadores externos.
                    </p>
                </div>
                <div className="flex items-center gap-2 flex-wrap">
                    <button
                        onClick={() => document.getElementById('crear-evaluador')?.scrollIntoView({ behavior: 'smooth' })}
                        className="inline-flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2.5 text-sm font-semibold text-text transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c]"
                    >
                        <UserPlus className="h-4 w-4" />
                        Crear evaluador
                    </button>

                </div>
            </div>

            {/* ═══ SECCIÓN 1: Correos Autorizados (whitelist) ═══ */}
            <section className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="mb-5 flex items-center gap-2 flex-wrap">
                    <svg viewBox="0 0 24 24" className="h-4 w-4 text-[#c2410c]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2" />
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                    </svg>
                    <h2 className="text-lg font-bold text-text">Correos Autorizados</h2>
                    <span className="ml-auto rounded-full bg-[#e7e5e4] px-2.5 py-0.5 text-xs font-semibold text-[#57534e]">
                        {whitelistMeta?.total ?? whitelistEntries.length} correos
                    </span>
                </div>

                <div className="w-full overflow-x-auto rounded-lg border border-[#e5e5e5] bg-white">
                    {whitelistLoading ? (
                        <div className="flex items-center justify-center py-16">
                            <Loader2 className="h-6 w-6 animate-spin text-[#c2410c]" />
                        </div>
                    ) : whitelistEntries.length === 0 ? (
                        <div className="py-16 text-center text-sm text-[#57534e]">
                            No hay correos autorizados. Agregue estudiantes o directores desde los formularios de abajo.
                        </div>
                    ) : (
                        <>
                            <table className="w-full text-left text-sm tabular-nums">
                                <thead className="bg-[#f5f5f4] text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                    <tr>
                                        <th className="whitespace-nowrap px-4 py-3">Correo</th>
                                        <th className="whitespace-nowrap px-4 py-3">Nombre</th>
                                        <th className="whitespace-nowrap px-4 py-3">Rol</th>
                                        <th className="whitespace-nowrap px-4 py-3">Fecha de alta</th>
                                        <th className="whitespace-nowrap px-4 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {whitelistEntries.map((w) => (
                                        <tr key={w.id} className="border-b border-[#e5e5e5] last:border-none">
                                            <td className="px-4 py-3 font-medium text-text">{w.email}</td>
                                            <td className="px-4 py-3 text-text-muted">
                                                {(w as any).name || (w as any).created_by?.name || '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex items-center rounded-full bg-[#f5f5f4] px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.03em] text-[#57534e]">
                                                    {ROLE_LABELS[w.role] || w.role}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-[#78716c] text-xs">{formatDate(w.created_at)}</td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="inline-flex gap-0.5">
                                                    <button
                                                        onClick={() => { setDeleteTarget(w); setDeleteIsWhitelist(true); }}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626]"
                                                        title="Eliminar de whitelist"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {whitelistMeta && (
                                <div className="flex items-center justify-between border-t border-[#e5e5e5] px-4 py-3 text-sm text-[#57534e] flex-wrap gap-3">
                                    <span>Mostrando {whitelistMeta.total > 0 ? (whitelistMeta.current_page - 1) * 20 + 1 : 0}–{Math.min(whitelistMeta.current_page * 20, whitelistMeta.total)} de {whitelistMeta.total} resultados</span>
                                    <div className="flex items-center gap-1">
                                        <button
                                            onClick={() => setWhitelistPage((p) => Math.max(1, p - 1))}
                                            disabled={whitelistPage <= 1}
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#e5e5e5] text-text transition-colors hover:bg-[#f5f5f4] disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                        </button>
                                        {whitelistMeta.last_page > 1 && Array.from({ length: whitelistMeta.last_page }, (_, i) => i + 1).map((p) => (
                                            <button
                                                key={p}
                                                onClick={() => setWhitelistPage(p)}
                                                className={`inline-flex h-8 min-w-[32px] items-center justify-center rounded-lg border px-2 text-sm font-semibold transition-colors ${
                                                    p === whitelistPage
                                                        ? 'border-[#c2410c] bg-[#c2410c] text-white'
                                                        : 'border-[#e5e5e5] text-text hover:bg-[#f5f5f4]'
                                                }`}
                                            >
                                                {p}
                                            </button>
                                        ))}
                                        <button
                                            onClick={() => setWhitelistPage((p) => Math.min(whitelistMeta.last_page, p + 1))}
                                            disabled={whitelistPage >= whitelistMeta.last_page}
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#e5e5e5] text-text transition-colors hover:bg-[#f5f5f4] disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            <ChevronRight className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </section>

            {/* ═══ SECCIÓN 2: Evaluadores Externos — Crear Cuentas ═══ */}
            <section id="crear-evaluador" className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="mb-5 flex items-center gap-2 flex-wrap">
                    <UserPlus className="h-4 w-4 text-[#c2410c]" />
                    <h2 className="text-lg font-bold text-text">Evaluadores Externos — Crear Cuentas</h2>
                    <span className="ml-auto rounded-full bg-[#e7e5e4] px-2.5 py-0.5 text-xs font-semibold text-[#57534e]">
                        {evaluadores.length} cuentas
                    </span>
                </div>

                <form onSubmit={handleCrearEvaluador}>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="eval-nombre" className="text-sm font-semibold text-text">
                                Nombre completo <span className="text-[#dc2626]">*</span>
                            </label>
                            <input
                                id="eval-nombre"
                                type="text"
                                value={evalNombre}
                                onChange={(e) => setEvalNombre(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-text outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                placeholder="Nombre del evaluador"
                                required
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="eval-correo" className="text-sm font-semibold text-text">
                                Correo electrónico <span className="text-[#dc2626]">*</span>
                            </label>
                            <input
                                id="eval-correo"
                                type="email"
                                value={evalCorreo}
                                onChange={(e) => setEvalCorreo(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-text outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                placeholder="evaluador@ejemplo.com"
                                required
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="eval-pass" className="text-sm font-semibold text-text">
                                Contraseña <span className="text-[#dc2626]">*</span>
                            </label>
                            <div className="flex gap-1">
                                <input
                                    id="eval-pass"
                                    type="text"
                                    value={evalPass}
                                    onChange={(e) => setEvalPass(e.target.value)}
                                    className="flex-1 min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm font-mono text-text outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setEvalPass(genPassword())}
                                    className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[#e5e5e5] text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c]"
                                    title="Generar contraseña"
                                >
                                    <RefreshCw className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="eval-pass2" className="text-sm font-semibold text-text">
                                Confirmar contraseña
                            </label>
                            <div className="flex gap-1">
                                <input
                                    id="eval-pass2"
                                    type="text"
                                    value={evalPass2}
                                    onChange={(e) => setEvalPass2(e.target.value)}
                                    className="flex-1 min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm font-mono text-text outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                />
                                <button
                                    type="button"
                                    onClick={() => {
                                        const p = genPassword();
                                        setEvalPass(p);
                                        setEvalPass2(p);
                                    }}
                                    className="inline-flex shrink-0 items-center gap-1 rounded-lg border border-[#e5e5e5] px-3 py-2 text-xs font-semibold text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c]"
                                >
                                    <RefreshCw className="h-3.5 w-3.5" />
                                    Generar
                                </button>
                            </div>
                        </div>
                    </div>
                    <div className="mt-5 flex items-center gap-3">
                        <button
                            type="submit"
                            className="inline-flex items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a]"
                        >
                            <UserPlus className="h-4 w-4" />
                            Crear cuenta de evaluador
                        </button>
                    </div>
                </form>

                <hr className="my-6 border-t border-[#e5e5e5]" />

                <div className="mb-4 flex items-center gap-2 flex-wrap">
                    <svg viewBox="0 0 24 24" className="h-4 w-4 text-[#c2410c]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    <h3 className="text-md font-bold text-text">Evaluadores Creados</h3>
                </div>

                <div className="w-full overflow-x-auto rounded-lg border border-[#e5e5e5] bg-white">
                    <table className="w-full text-left text-sm tabular-nums">
                        <thead className="bg-[#f5f5f4] text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                            <tr>
                                <th className="whitespace-nowrap px-4 py-3">Nombre</th>
                                <th className="whitespace-nowrap px-4 py-3">Correo</th>
                                <th className="whitespace-nowrap px-4 py-3">Usuario</th>
                                <th className="whitespace-nowrap px-4 py-3">Fecha creación</th>
                                <th className="whitespace-nowrap px-4 py-3">Último acceso</th>
                                <th className="whitespace-nowrap px-4 py-3">Estado</th>
                                <th className="whitespace-nowrap px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {evaluadores.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-12 text-center text-sm text-[#57534e]">
                                        No hay evaluadores externos registrados.
                                    </td>
                                </tr>
                            ) : (
                                evaluadores.map((ev) => {
                                    const isActive = ev.role !== 'Inactivo';
                                    const statusLabel = isActive ? 'Activo' : 'Inactivo';
                                    return (
                                        <tr key={ev.id} className="border-b border-[#e5e5e5] last:border-none">
                                            <td className="px-4 py-3 font-medium text-text">
                                                {(ev as any).name || (ev.created_by?.name ?? '—')}
                                            </td>
                                            <td className="px-4 py-3 text-text-muted">{ev.email}</td>
                                            <td className="px-4 py-3 font-mono text-xs text-[#78716c]">
                                                {ev.email.split('@')[0]}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-[#78716c]">{formatDate(ev.created_at)}</td>
                                            <td className="px-4 py-3 text-xs text-[#78716c]">—</td>
                                            <td className="px-4 py-3">
                                                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-[0.03em] ${badgeClass(statusLabel)}`}>
                                                    {statusLabel}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="inline-flex gap-0.5">
                                                    <button className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c]" title="Editar">
                                                        <Pencil className="h-4 w-4" />
                                                    </button>
                                                    <button className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c]" title="Restablecer contraseña">
                                                        <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /><path d="M12 15v-2" /><circle cx="12" cy="18" r="0.5" fill="currentColor" /></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            {/* ═══ SECCIÓN 3: Agregar Correos Autorizados ═══ */}
            <section className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="mb-5 flex items-center gap-2 flex-wrap">
                    <svg viewBox="0 0 24 24" className="h-4 w-4 text-[#c2410c]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2" />
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                    </svg>
                    <h2 className="text-lg font-bold text-text">Agregar Correos Autorizados</h2>
                    <span className="ml-auto rounded-full bg-[#e7e5e4] px-2.5 py-0.5 text-xs font-semibold text-[#57534e]">
                        Estudiantes y Directores
                    </span>
                </div>
                <p className="mb-4 text-sm text-[#57534e]">
                    Registre los correos institucionales de estudiantes y directores para que puedan acceder al sistema.
                </p>

                <div className="mt-5 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Estudiantes */}
                    <form onSubmit={handleAgregarEstudiante} className="rounded-2xl bg-[#e7e5e4] p-[2px]">
                        <div className="rounded-[22px] bg-white p-5" style={{ borderRadius: 'calc(24px - 2px)' }}>
                            <div className="mb-4 flex items-center gap-2">
                                <svg viewBox="0 0 24 24" className="h-5 w-5 text-[#c2410c]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                                    <path d="M6 12v5c0 1.1 2.7 3 6 3s6-1.9 6-3v-5" />
                                </svg>
                                <h3 className="text-md font-bold text-text m-0">Estudiantes</h3>
                            </div>
                            <div className="flex flex-col gap-1.5 mb-3">
                                <label htmlFor="correo-est" className="text-sm font-semibold text-text">Correo institucional</label>
                                <input
                                    id="correo-est"
                                    type="email"
                                    value={estCorreo}
                                    onChange={(e) => setEstCorreo(e.target.value)}
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-text outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    placeholder="ejemplo@unab.edu.co"
                                />
                                <span className="text-xs text-[#57534e]">El correo debe ser institucional @unab.edu.co</span>
                            </div>
                            <div className="flex flex-col gap-1.5 mb-4">
                                <label htmlFor="nombre-est" className="text-sm font-semibold text-text">Nombre completo</label>
                                <input
                                    id="nombre-est"
                                    type="text"
                                    value={estNombre}
                                    onChange={(e) => setEstNombre(e.target.value)}
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-text outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    placeholder="Ej: Juan Pérez"
                                />
                            </div>
                            <button
                                type="submit"
                                className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a]"
                            >
                                <UserPlus className="h-4 w-4" />
                                Agregar Estudiante
                            </button>
                        </div>
                    </form>

                    {/* Directores */}
                    <form onSubmit={handleAgregarDirector} className="rounded-2xl bg-[#e7e5e4] p-[2px]">
                        <div className="rounded-[22px] bg-white p-5" style={{ borderRadius: 'calc(24px - 2px)' }}>
                            <div className="mb-4 flex items-center gap-2">
                                <svg viewBox="0 0 24 24" className="h-5 w-5 text-[#4f46e5]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M6 9a6 6 0 0 1 12 0v6a6 6 0 0 1-12 0V9Z" />
                                    <path d="M12 3v3" />
                                    <path d="M8 21h8" />
                                </svg>
                                <h3 className="text-md font-bold text-text m-0">Directores</h3>
                            </div>
                            <div className="flex flex-col gap-1.5 mb-3">
                                <label htmlFor="correo-dir" className="text-sm font-semibold text-text">Correo institucional</label>
                                <input
                                    id="correo-dir"
                                    type="email"
                                    value={dirCorreo}
                                    onChange={(e) => setDirCorreo(e.target.value)}
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-text outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    placeholder="docente@unab.edu.co"
                                />
                                <span className="text-xs text-[#57534e]">Docentes y directores de proyecto</span>
                            </div>
                            <div className="flex flex-col gap-1.5 mb-4">
                                <label htmlFor="nombre-dir" className="text-sm font-semibold text-text">Nombre completo</label>
                                <input
                                    id="nombre-dir"
                                    type="text"
                                    value={dirNombre}
                                    onChange={(e) => setDirNombre(e.target.value)}
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-text outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    placeholder="Ej: Dr. Ricardo Gómez"
                                />
                            </div>
                            <button
                                type="submit"
                                className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#4f46e5] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#4338ca]"
                            >
                                <UserPlus className="h-4 w-4" />
                                Agregar Director
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            {/* ═══ SECCIÓN 4: Gestión de Roles ═══ */}
            <section className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="mb-5 flex items-center gap-2 flex-wrap">
                    <svg viewBox="0 0 24 24" className="h-4 w-4 text-[#c2410c]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <circle cx="12" cy="12" r="3" />
                        <circle cx="19" cy="5" r="2" />
                        <circle cx="5" cy="19" r="2" />
                        <path d="M10 14l2-2 4-4" />
                        <path d="M15 5l1-1 3 3" />
                        <path d="M5 15l-1 1 3 3" />
                    </svg>
                    <h2 className="text-lg font-bold text-text">Gestión de Roles</h2>
                    <span className="ml-auto rounded-full bg-[#e7e5e4] px-2.5 py-0.5 text-xs font-semibold text-[#57534e]">
                        {users.length} usuarios
                    </span>
                </div>
                <p className="mb-4 text-sm text-[#57534e]">
                    Asigne o cambie el rol de los usuarios registrados en el sistema.
                </p>

                <div className="w-full overflow-x-auto rounded-lg border border-[#e5e5e5] bg-white">
                    <table className="w-full text-left text-sm tabular-nums">
                        <thead className="bg-[#f5f5f4] text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                            <tr>
                                <th className="whitespace-nowrap px-4 py-3">Correo</th>
                                <th className="whitespace-nowrap px-4 py-3">Nombre</th>
                                <th className="whitespace-nowrap px-4 py-3">Rol Actual</th>
                                <th className="whitespace-nowrap px-4 py-3">Último Acceso</th>
                                <th className="whitespace-nowrap px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-12 text-center text-sm text-[#57534e]">
                                        No hay usuarios registrados.
                                    </td>
                                </tr>
                            ) : (
                                users.map((u) => (
                                    <tr key={u.id} className="border-b border-[#e5e5e5] last:border-none">
                                        <td className="px-4 py-3 font-medium text-text">{u.email}</td>
                                        <td className="px-4 py-3 text-text-muted">
                                            {(u as any).name || u.created_by?.name || '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <select
                                                value={roleChanges[u.id] ?? u.role}
                                                onChange={(e) => handleRoleChange(u.id, e.target.value)}
                                                className="min-w-[140px] rounded-lg border border-[#e5e5e5] bg-white px-2.5 py-1.5 text-xs font-semibold text-text outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                            >
                                                {ROLES.map((r) => (
                                                    <option key={r} value={r}>{ROLE_LABELS[r]}</option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-[#78716c]">{formatDate(u.created_at)}</td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="inline-flex gap-0.5">
                                                <button
                                                    onClick={() => {
                                                        const newRole = roleChanges[u.id] ?? u.role;
                                                        if (newRole !== u.role) {
                                                            handleRoleChange(u.id, u.role);
                                                            setRoleChanges((prev) => ({ ...prev, [u.id]: newRole }));
                                                            saveAllRoles();
                                                        }
                                                    }}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c]"
                                                    title="Guardar cambio de rol"
                                                >
                                                    <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                                                        <polyline points="17 21 17 13 7 13 7 21" />
                                                        <polyline points="7 3 7 8 15 8" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="mt-5 flex items-center justify-between gap-3 border-t border-[#e5e5e5] pt-4 flex-wrap">
                    <p className="flex items-center gap-2 text-xs text-[#57534e] m-0">
                        <svg viewBox="0 0 24 24" className="h-4 w-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="16" x2="12" y2="12" />
                            <line x1="12" y1="8" x2="12.01" y2="8" />
                        </svg>
                        Los cambios de rol se aplican inmediatamente después de guardar.
                    </p>
                    <button
                        onClick={saveAllRoles}
                        disabled={Object.keys(roleChanges).length === 0 || savingRoles}
                        className="inline-flex items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {savingRoles && <Loader2 className="h-4 w-4 animate-spin" />}
                        <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                            <polyline points="17 21 17 13 7 13 7 21" />
                            <polyline points="7 3 7 8 15 8" />
                        </svg>
                        Guardar cambios
                    </button>
                </div>
            </section>

            {/* ═══ MODAL: Nuevo / Editar usuario ═══ */}
            {modalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-[0_20px_60px_rgba(28,25,23,0.15)]">
                        <div className="mb-5 flex items-center justify-between">
                            <h2 className="text-lg font-bold text-text">
                                {editingUser ? 'Cambiar rol' : 'Nuevo usuario'}
                            </h2>
                            <button
                                onClick={() => { setModalOpen(false); setEditingUser(null); }}
                                className="rounded-lg p-1.5 text-text-muted transition hover:bg-[#f5f5f4] hover:text-text"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            {!editingUser && (
                                <>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-semibold text-text">Nombre completo</label>
                                        <input
                                            type="text"
                                            value={formName}
                                            onChange={(e) => setFormName(e.target.value)}
                                            placeholder="Ej: Juan Pérez"
                                            className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3.5 py-2.5 text-sm text-text outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-semibold text-text">Correo electrónico</label>
                                        <input
                                            type="email"
                                            value={formEmail}
                                            onChange={(e) => setFormEmail(e.target.value)}
                                            required
                                            placeholder="usuario@unab.edu.co"
                                            className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3.5 py-2.5 text-sm text-text outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                        />
                                    </div>
                                </>
                            )}
                            <div>
                                <label className="mb-1.5 block text-sm font-semibold text-text">Rol</label>
                                <select
                                    value={formRole}
                                    onChange={(e) => setFormRole(e.target.value)}
                                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3.5 py-2.5 text-sm text-text outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                >
                                    {ROLES.map((r) => (
                                        <option key={r} value={r}>{ROLE_LABELS[r]}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => { setModalOpen(false); setEditingUser(null); }}
                                    className="rounded-lg border border-[#e5e5e5] px-4 py-2.5 text-sm font-medium text-text transition hover:bg-[#f5f5f4]"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={submitting}
                                    className="inline-flex items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                                    {editingUser ? 'Guardar cambios' : 'Crear usuario'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ═══ MODAL: Confirmar eliminación ═══ */}
            {deleteTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow-[0_20px_60px_rgba(28,25,23,0.15)]">
                        <h2 className="mb-2 text-lg font-bold text-text">Confirmar eliminación</h2>
                        <p className="mb-5 text-sm text-[#57534e]">
                            ¿Estás seguro de que deseas eliminar a <strong>{deleteTarget.email}</strong>?
                            Esta acción no se puede deshacer.
                        </p>
                        <div className="flex justify-end gap-3">
                            <button
                                onClick={() => { setDeleteTarget(null); setDeleteIsWhitelist(false); }}
                                className="rounded-lg border border-[#e5e5e5] px-4 py-2.5 text-sm font-medium text-text transition hover:bg-[#f5f5f4]"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={handleDelete}
                                disabled={deleting}
                                className="inline-flex items-center gap-2 rounded-lg bg-[#dc2626] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#b91c1c] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {deleting && <Loader2 className="h-4 w-4 animate-spin" />}
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
