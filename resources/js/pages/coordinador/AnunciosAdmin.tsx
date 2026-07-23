import { useState, useEffect } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { Megaphone, Plus, Trash2, Pin, Send, Loader2, Eye, Pencil } from 'lucide-react';
import { apiFetch } from '@/lib/utils';

interface Announcement {
    id: number;
    title: string;
    content: string;
    date: string;
    status: 'published' | 'draft';
    priority: 'high' | 'normal';
    views: number;
}

/** Shape returned by GET /api/anuncios and POST|PUT /api/admin/anuncios */
interface ApiAnnouncement {
    id: number;
    title: string;
    content: string;
    published_at: string | null;
    is_active: boolean;
}

function fromApi(a: ApiAnnouncement): Announcement {
    return {
        id: a.id,
        title: a.title,
        content: a.content,
        date: a.published_at
            ? new Date(a.published_at).toLocaleDateString('es-CO')
            : '—',
        status: a.is_active ? 'published' : 'draft',
        priority: 'normal',
        views: 0,
    };
}

export default function AnunciosAdmin() {
    const [announcements, setAnnouncements] = useState<Announcement[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [showNewForm, setShowNewForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Announcement | null>(null);

    const [formTitle, setFormTitle] = useState('');
    const [formContent, setFormContent] = useState('');
    const [formIsActive, setFormIsActive] = useState(true);
    const [submitting, setSubmitting] = useState(false);

    /** Fetch all announcements from the API */
    const fetchAnnouncements = async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await apiFetch('/api/anuncios');
            if (!res.ok) throw new Error('Error al cargar anuncios');
            const body = await res.json();
            setAnnouncements((body.data ?? []).map(fromApi));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Error desconocido');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchAnnouncements();
    }, []);

    /** Open the create form */
    const openCreateForm = () => {
        setEditingId(null);
        setFormTitle('');
        setFormContent('');
        setFormIsActive(true);
        setShowNewForm(true);
    };

    /** Open the edit form for an existing announcement */
    const openEditForm = (ann: Announcement) => {
        setEditingId(ann.id);
        setFormTitle(ann.title);
        setFormContent(ann.content);
        setFormIsActive(ann.status === 'published');
        setShowNewForm(true);
    };

    /** Close the form and reset fields */
    const closeForm = () => {
        setShowNewForm(false);
        setEditingId(null);
        setFormTitle('');
        setFormContent('');
        setFormIsActive(true);
    };

    /** Create or update an announcement */
    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!formTitle.trim() || !formContent.trim()) return;
        setSubmitting(true);
        try {
            const payload: Record<string, unknown> = {
                title: formTitle.trim(),
                content: formContent.trim(),
                is_active: formIsActive,
            };

            let res: Response;
            if (editingId !== null) {
                res = await apiFetch(`/api/admin/anuncios/${editingId}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
            } else {
                res = await apiFetch('/api/admin/anuncios', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
            }

            if (!res.ok) {
                const errBody = await res.json().catch(() => ({}));
                throw new Error(
                    errBody?.errors
                        ? Object.values(errBody.errors).flat().join(', ')
                        : 'Error al guardar',
                );
            }

            const body = await res.json();
            const updated = fromApi(body.data);

            setAnnouncements((prev) => {
                if (editingId !== null) {
                    return prev.map((a) => (a.id === editingId ? updated : a));
                }
                return [updated, ...prev];
            });

            closeForm();
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Error desconocido');
        } finally {
            setSubmitting(false);
        }
    }

    /** Delete an announcement */
    async function handleDelete() {
        if (!deleteTarget) return;
        setSubmitting(true);
        try {
            const res = await apiFetch(`/api/admin/anuncios/${deleteTarget.id}`, {
                method: 'DELETE',
            });
            if (!res.ok) throw new Error('Error al eliminar anuncio');
            setAnnouncements((prev) => prev.filter((a) => a.id !== deleteTarget.id));
            setDeleteTarget(null);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Error desconocido');
        } finally {
            setSubmitting(false);
        }
    }

    const publishedCount = announcements.filter((a) => a.status === 'published').length;
    const totalViews = announcements.reduce((s, a) => s + a.views, 0);

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Comunicación"
                title="Anuncios"
                subtitle="Gestione los anuncios y comunicaciones del sistema"
                actions={
                    <button
                        onClick={openCreateForm}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                    >
                        <Plus className="h-4 w-4" />
                        Nuevo Anuncio
                    </button>
                }
            />

            {/* Stats */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard icon={Megaphone} label="Total anuncios" value={announcements.length} />
                <StatCard icon={Send} label="Publicados" value={publishedCount} variant="success" />
                <StatCard icon={Eye} label="Vistas totales" value={totalViews} />
            </div>

            {/* Error banner */}
            {error && (
                <div className="rounded-xl border border-[#fee2e2] bg-[#fef2f2] p-4">
                    <p className="text-sm font-medium text-[#dc2626]">{error}</p>
                </div>
            )}

            {/* Loading skeleton */}
            {loading && (
                <div className="flex flex-col gap-4">
                    {[1, 2, 3].map((i) => (
                        <div key={i} className="animate-pulse rounded-xl border border-[#e5e5e5] bg-white p-5">
                            <div className="flex items-start gap-3">
                                <div className="h-9 w-9 rounded-lg bg-[#f5f5f4]" />
                                <div className="flex-1 space-y-2">
                                    <div className="h-4 w-48 rounded bg-[#f5f5f4]" />
                                    <div className="h-3 w-full rounded bg-[#f5f5f4]" />
                                    <div className="h-3 w-32 rounded bg-[#f5f5f4]" />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Create / Edit form */}
            {showNewForm && !loading && (
                <form onSubmit={handleSubmit} className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <h3 className="mb-4 text-base font-bold text-[#1c1917]">
                        {editingId !== null ? 'Editar Anuncio' : 'Nuevo Anuncio'}
                    </h3>
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="ann-title" className="text-sm font-semibold text-[#1c1917]">Título</label>
                            <input
                                id="ann-title"
                                type="text"
                                value={formTitle}
                                onChange={(e) => setFormTitle(e.target.value)}
                                placeholder="Título del anuncio"
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="ann-content" className="text-sm font-semibold text-[#1c1917]">Contenido</label>
                            <textarea
                                id="ann-content"
                                rows={4}
                                value={formContent}
                                onChange={(e) => setFormContent(e.target.value)}
                                placeholder="Escriba el contenido del anuncio..."
                                className="w-full min-h-[80px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y"
                                required
                            />
                        </div>
                        <div className="flex items-center gap-3">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={formIsActive}
                                    onChange={(e) => setFormIsActive(e.target.checked)}
                                    className="rounded border-[#e5e5e5] text-[#c2410c] focus:ring-[#c2410c]"
                                />
                                <span className="text-sm text-[#1c1917]">Publicar inmediatamente</span>
                            </label>
                        </div>
                        <div className="flex items-center gap-3">
                            <button
                                type="submit"
                                disabled={submitting}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                                {submitting
                                    ? 'Guardando...'
                                    : editingId !== null
                                        ? 'Actualizar Anuncio'
                                        : 'Publicar Anuncio'}
                            </button>
                            <button
                                type="button"
                                onClick={closeForm}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </form>
            )}

            {/* Announcement cards */}
            {!loading && (
                <div className="flex flex-col gap-4">
                    {announcements.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 py-16 text-center">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#f5f5f4]">
                                <Megaphone className="h-6 w-6 text-[#78716c]" />
                            </div>
                            <h3 className="text-base font-semibold text-[#1c1917]">No hay anuncios</h3>
                            <p className="text-sm text-[#57534e]">Cree el primer anuncio para los usuarios del sistema.</p>
                        </div>
                    ) : (
                        announcements.map((a) => (
                            <div
                                key={a.id}
                                className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex items-start gap-3 min-w-0">
                                        <div className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${
                                            a.priority === 'high' ? 'bg-[#fee2e2] text-[#dc2626]' : 'bg-[#f5f5f4] text-[#c2410c]'
                                        }`}>
                                            {a.priority === 'high' ? (
                                                <Pin className="h-4 w-4" />
                                            ) : (
                                                <Megaphone className="h-4 w-4" />
                                            )}
                                        </div>
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <h3 className="text-sm font-bold text-[#1c1917]">{a.title}</h3>
                                                <StatusBadge variant={a.status === 'published' ? 'success' : 'warning'}>
                                                    {a.status === 'published' ? 'Publicado' : 'Borrador'}
                                                </StatusBadge>
                                                {a.priority === 'high' && (
                                                    <span className="inline-flex items-center rounded-full bg-[#fee2e2] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.03em] text-[#7f1d1d]">
                                                        Alta prioridad
                                                    </span>
                                                )}
                                            </div>
                                            <p className="mt-1 text-sm text-[#57534e] line-clamp-2">{a.content}</p>
                                            <div className="mt-2 flex items-center gap-3 text-xs text-[#78716c]">
                                                <span>{a.date}</span>
                                                <span>{a.views} vistas</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 gap-0.5">
                                        <button
                                            onClick={() => openEditForm(a)}
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c] active:scale-[0.98]"
                                            aria-label="Editar anuncio"
                                            title="Editar"
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </button>
                                        <button
                                            onClick={() => setDeleteTarget(a)}
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626] active:scale-[0.98]"
                                            aria-label="Eliminar anuncio"
                                            title="Eliminar"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            )}

            <ConfirmDialog
                open={deleteTarget !== null}
                title="Eliminar anuncio"
                message={`¿Está seguro de eliminar "${deleteTarget?.title}"? Esta acción no se puede deshacer.`}
                confirmLabel="Eliminar"
                variant="danger"
                onConfirm={handleDelete}
                onCancel={() => setDeleteTarget(null)}
            />
        </div>
    );
}
