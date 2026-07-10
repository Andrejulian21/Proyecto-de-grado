import { useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatCard } from '@/components/ui/StatCard';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { Megaphone, Plus, Trash2, Pin, Send, Loader2, Eye, Pencil } from 'lucide-react';

interface Announcement {
    id: number;
    title: string;
    content: string;
    date: string;
    status: 'published' | 'draft';
    priority: 'high' | 'normal';
    views: number;
}

const MOCK_ANNOUNCEMENTS: Announcement[] = [
    { id: 1, title: 'Inicio de inscripciones 2026-01', content: 'Se informa a todos los estudiantes que las inscripciones para proyectos de grado del período 2026-01 estarán abiertas desde el 3 de febrero hasta el 28 de febrero.', date: '01/02/2026', status: 'published', priority: 'high', views: 145 },
    { id: 2, title: 'Cambio en formato de entregas', content: 'A partir de este semestre, todas las entregas deberán realizarse en formato PDF siguiendo la nueva plantilla disponible en Recursos.', date: '15/03/2026', status: 'published', priority: 'normal', views: 89 },
    { id: 3, title: 'Recordatorio: fecha límite avance 1', content: 'El plazo para la entrega del primer avance vence el 15 de abril. Recuerden subir su documento a través de la plataforma.', date: '01/04/2026', status: 'published', priority: 'high', views: 210 },
    { id: 4, title: 'Suspensión de atención administrativa', content: 'El jueves 20 de abril no habrá atención al público por capacitación del personal.', date: '10/04/2026', status: 'draft', priority: 'normal', views: 0 },
];

export default function AnunciosAdmin() {
    const [announcements, setAnnouncements] = useState(MOCK_ANNOUNCEMENTS);
    const [showNewForm, setShowNewForm] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<Announcement | null>(null);

    const [formTitle, setFormTitle] = useState('');
    const [formContent, setFormContent] = useState('');
    const [formPriority, setFormPriority] = useState<'normal' | 'high'>('normal');
    const [submitting, setSubmitting] = useState(false);

    async function handleCreate(e: React.FormEvent) {
        e.preventDefault();
        if (!formTitle.trim() || !formContent.trim()) return;
        setSubmitting(true);
        try {
            await new Promise((r) => setTimeout(r, 600));
            const newAnn: Announcement = {
                id: Date.now(),
                title: formTitle,
                content: formContent,
                date: new Date().toLocaleDateString('es-CO'),
                status: 'draft',
                priority: formPriority,
                views: 0,
            };
            setAnnouncements((prev) => [newAnn, ...prev]);
            setFormTitle('');
            setFormContent('');
            setFormPriority('normal');
            setShowNewForm(false);
        } finally {
            setSubmitting(false);
        }
    }

    function handleDelete() {
        if (!deleteTarget) return;
        setAnnouncements((prev) => prev.filter((a) => a.id !== deleteTarget.id));
        setDeleteTarget(null);
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
                        onClick={() => setShowNewForm(!showNewForm)}
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

            {/* New announcement form */}
            {showNewForm && (
                <form onSubmit={handleCreate} className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <h3 className="mb-4 text-base font-bold text-[#1c1917]">Nuevo Anuncio</h3>
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
                                    checked={formPriority === 'high'}
                                    onChange={(e) => setFormPriority(e.target.checked ? 'high' : 'normal')}
                                    className="rounded border-[#e5e5e5] text-[#c2410c] focus:ring-[#c2410c]"
                                />
                                <span className="text-sm text-[#1c1917]">Alta prioridad</span>
                            </label>
                        </div>
                        <div className="flex items-center gap-3">
                            <button
                                type="submit"
                                disabled={submitting}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                                {submitting ? 'Publicando...' : 'Publicar Anuncio'}
                            </button>
                            <button
                                type="button"
                                onClick={() => setShowNewForm(false)}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </form>
            )}

            {/* Announcement cards */}
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
