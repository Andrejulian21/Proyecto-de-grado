import { useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Upload, FileText, Download, Trash2, Plus, Loader2, Link2, BookOpen, FileSpreadsheet, FileImage, Video } from 'lucide-react';

interface Resource {
    id: number;
    title: string;
    description: string;
    type: 'document' | 'spreadsheet' | 'image' | 'video' | 'link' | 'other';
    fileSize?: string;
    uploadedAt: string;
    downloads: number;
}

const MOCK_RESOURCES: Resource[] = [
    { id: 1, title: 'Plantilla de Anteproyecto', description: 'Formato oficial para la presentación del anteproyecto de grado.', type: 'document', fileSize: '245 KB', uploadedAt: '01/02/2026', downloads: 120 },
    { id: 2, title: 'Rúbrica de Evaluación — Avance 1', description: 'Criterios de evaluación para el primer avance del proyecto.', type: 'document', fileSize: '180 KB', uploadedAt: '01/02/2026', downloads: 89 },
    { id: 3, title: 'Guía de Estilo y Formato', description: 'Normas de presentación y formato para documentos de proyecto de grado.', type: 'document', fileSize: '1.2 MB', uploadedAt: '15/01/2026', downloads: 210 },
    { id: 4, title: 'Matriz de Seguimiento', description: 'Hoja de cálculo para seguimiento de avances del proyecto.', type: 'spreadsheet', fileSize: '56 KB', uploadedAt: '20/01/2026', downloads: 67 },
    { id: 5, title: 'Calendario Académico 2026-01', description: 'Fechas importantes del semestre para proyectos de grado.', type: 'document', fileSize: '320 KB', uploadedAt: '10/01/2026', downloads: 340 },
    { id: 6, title: 'Tutorial de uso de la plataforma', description: 'Video explicativo sobre el uso del sistema de proyectos de grado.', type: 'video', fileSize: '45 MB', uploadedAt: '05/02/2026', downloads: 56 },
];

const typeConfig: Record<string, { icon: typeof FileText; bgClass: string; iconColor: string; label: string }> = {
    document: { icon: FileText, bgClass: 'bg-[#fed7aa]', iconColor: 'text-[#c2410c]', label: 'Documento' },
    spreadsheet: { icon: FileSpreadsheet, bgClass: 'bg-[#dcfce7]', iconColor: 'text-[#16a34a]', label: 'Hoja de cálculo' },
    image: { icon: FileImage, bgClass: 'bg-[#dbeafe]', iconColor: 'text-[#2563eb]', label: 'Imagen' },
    video: { icon: Video, bgClass: 'bg-[#e0e7ff]', iconColor: 'text-[#4f46e5]', label: 'Video' },
    link: { icon: Link2, bgClass: 'bg-[#fef3c7]', iconColor: 'text-[#d97706]', label: 'Enlace' },
    other: { icon: FileText, bgClass: 'bg-[#f5f5f4]', iconColor: 'text-[#78716c]', label: 'Otro' },
};

export default function RecursosAdmin() {
    const [resources, setResources] = useState(MOCK_RESOURCES);
    const [showUploadForm, setShowUploadForm] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<Resource | null>(null);

    const [formTitle, setFormTitle] = useState('');
    const [formDesc, setFormDesc] = useState('');
    const [formType, setFormType] = useState<string>('document');
    const [submitting, setSubmitting] = useState(false);

    async function handleUpload(e: React.FormEvent) {
        e.preventDefault();
        if (!formTitle.trim() || !formDesc.trim()) return;
        setSubmitting(true);
        try {
            await new Promise((r) => setTimeout(r, 800));
            const newRes: Resource = {
                id: Date.now(),
                title: formTitle,
                description: formDesc,
                type: formType as Resource['type'],
                fileSize: '—',
                uploadedAt: new Date().toLocaleDateString('es-CO'),
                downloads: 0,
            };
            setResources((prev) => [newRes, ...prev]);
            setFormTitle('');
            setFormDesc('');
            setFormType('document');
            setShowUploadForm(false);
        } finally {
            setSubmitting(false);
        }
    }

    function handleDelete() {
        if (!deleteTarget) return;
        setResources((prev) => prev.filter((r) => r.id !== deleteTarget.id));
        setDeleteTarget(null);
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Recursos"
                title="Gestión de Recursos"
                subtitle="Administre los recursos y documentos disponibles para los usuarios"
                actions={
                    <button
                        onClick={() => setShowUploadForm(!showUploadForm)}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                    >
                        <Upload className="h-4 w-4" />
                        Subir Recurso
                    </button>
                }
            />

            {/* Upload form */}
            {showUploadForm && (
                <form onSubmit={handleUpload} className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <h3 className="mb-4 text-base font-bold text-[#1c1917]">Subir Nuevo Recurso</h3>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="flex flex-col gap-1.5 sm:col-span-2">
                            <label htmlFor="res-title" className="text-sm font-semibold text-[#1c1917]">Título</label>
                            <input
                                id="res-title"
                                type="text"
                                value={formTitle}
                                onChange={(e) => setFormTitle(e.target.value)}
                                placeholder="Título del recurso"
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            />
                        </div>
                        <div className="flex flex-col gap-1.5 sm:col-span-2">
                            <label htmlFor="res-desc" className="text-sm font-semibold text-[#1c1917]">Descripción</label>
                            <textarea
                                id="res-desc"
                                rows={3}
                                value={formDesc}
                                onChange={(e) => setFormDesc(e.target.value)}
                                placeholder="Breve descripción del recurso"
                                className="w-full min-h-[60px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y"
                                required
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="res-type" className="text-sm font-semibold text-[#1c1917]">Tipo</label>
                            <select
                                id="res-type"
                                value={formType}
                                onChange={(e) => setFormType(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            >
                                <option value="document">Documento</option>
                                <option value="spreadsheet">Hoja de cálculo</option>
                                <option value="image">Imagen</option>
                                <option value="video">Video</option>
                                <option value="link">Enlace</option>
                                <option value="other">Otro</option>
                            </select>
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <label className="text-sm font-semibold text-[#1c1917]">Archivo</label>
                            <div className="flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#57534e] cursor-pointer hover:bg-[#fafaf9]">
                                <Upload className="h-4 w-4" />
                                <span>Seleccionar archivo...</span>
                            </div>
                        </div>
                    </div>
                    <div className="mt-5 flex items-center gap-3">
                        <button
                            type="submit"
                            disabled={submitting}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
                            Subir Recurso
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowUploadForm(false)}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                        >
                            Cancelar
                        </button>
                    </div>
                </form>
            )}

            {/* Resource cards */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {resources.map((res) => {
                    const config = typeConfig[res.type] || typeConfig.other;
                    const Icon = config.icon;
                    return (
                        <div
                            key={res.id}
                            className="group rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)] transition-all hover:shadow-[0_4px_12px_rgba(28,25,23,0.08)]"
                        >
                            <div className="flex items-start justify-between gap-3 mb-4">
                                <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${config.bgClass}`}>
                                    <Icon className={`h-5 w-5 ${config.iconColor}`} />
                                </div>
                                <button
                                    onClick={() => setDeleteTarget(res)}
                                    className="opacity-0 group-hover:opacity-100 inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-all hover:bg-[#fee2e2] hover:text-[#dc2626] active:scale-[0.98]"
                                    aria-label={`Eliminar ${res.title}`}
                                    title="Eliminar"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </div>
                            <h3 className="text-sm font-bold text-[#1c1917]">{res.title}</h3>
                            <p className="mt-1 text-xs text-[#57534e] line-clamp-2">{res.description}</p>
                            <div className="mt-3 flex items-center justify-between gap-2 border-t border-[#e5e5e5] pt-3">
                                <div className="flex items-center gap-2 text-xs text-[#78716c]">
                                    <span className="tabular-nums">{res.downloads}</span>
                                    <Download className="h-3 w-3" />
                                    <span>{res.fileSize}</span>
                                </div>
                                <span className="text-[10px] text-[#78716c]">{res.uploadedAt}</span>
                            </div>
                        </div>
                    );
                })}
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                title="Eliminar recurso"
                message={`¿Está seguro de eliminar "${deleteTarget?.title}"? Los usuarios ya no podrán acceder a este recurso.`}
                confirmLabel="Eliminar"
                variant="danger"
                onConfirm={handleDelete}
                onCancel={() => setDeleteTarget(null)}
            />
        </div>
    );
}
