import { useState, useCallback, useRef } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { useRecursos, type Recurso } from '@/hooks/useRecursos';
import {
    Upload,
    FileText,
    Download,
    Trash2,
    Plus,
    Loader2,
    Link2,
    FileSpreadsheet,
    FileImage,
    Video,
    AlertCircle,
    Pencil,
    X,
    Eye,
    CheckCircle2,
    ExternalLink,
} from 'lucide-react';

const typeConfig: Record<string, { icon: typeof FileText; bgClass: string; iconColor: string; label: string }> = {
    document: { icon: FileText, bgClass: 'bg-[#fed7aa]', iconColor: 'text-[#c2410c]', label: 'Documento' },
    spreadsheet: { icon: FileSpreadsheet, bgClass: 'bg-[#dcfce7]', iconColor: 'text-[#16a34a]', label: 'Hoja de cálculo' },
    image: { icon: FileImage, bgClass: 'bg-[#dbeafe]', iconColor: 'text-[#2563eb]', label: 'Imagen' },
    video: { icon: Video, bgClass: 'bg-[#e0e7ff]', iconColor: 'text-[#4f46e5]', label: 'Video' },
    link: { icon: Link2, bgClass: 'bg-[#fef3c7]', iconColor: 'text-[#d97706]', label: 'Enlace' },
    other: { icon: FileText, bgClass: 'bg-[#f5f5f4]', iconColor: 'text-[#78716c]', label: 'Otro' },
};

export default function RecursosAdmin() {
    const {
        data: recursos,
        loading,
        error,
        refetch,
        crear,
        actualizar,
        eliminar,
        mutationLoading,
        mutationError,
    } = useRecursos();

    const fileInputRef = useRef<HTMLInputElement>(null);
    const editFileInputRef = useRef<HTMLInputElement>(null);

    // Upload form state
    const [showUploadForm, setShowUploadForm] = useState(false);
    const [formTitle, setFormTitle] = useState('');
    const [formDesc, setFormDesc] = useState('');
    const [formType, setFormType] = useState<string>('document');
    const [formFile, setFormFile] = useState<File | null>(null);
    const [formPreview, setFormPreview] = useState<string | null>(null);
    const [formError, setFormError] = useState<string | null>(null);
    const [uploadSuccess, setUploadSuccess] = useState(false);

    // Edit form state
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editTitle, setEditTitle] = useState('');
    const [editDesc, setEditDesc] = useState('');
    const [editType, setEditType] = useState('document');
    const [editFile, setEditFile] = useState<File | null>(null);
    const [editPreview, setEditPreview] = useState<string | null>(null);
    const [editError, setEditError] = useState<string | null>(null);

    // Delete state
    const [deleteTarget, setDeleteTarget] = useState<Recurso | null>(null);

    // Toast / notification
    const [toastMsg, setToastMsg] = useState<string | null>(null);

    const showToast = useCallback((msg: string) => {
        setToastMsg(msg);
        setTimeout(() => setToastMsg(null), 4000);
    }, []);

    const handleFileSelect = useCallback(
        (file: File | null, setFile: (f: File | null) => void, setPreview: (s: string | null) => void) => {
            setFile(file);
            if (file) {
                const objectUrl = URL.createObjectURL(file);
                setPreview(objectUrl);
            } else {
                setPreview(null);
            }
        },
        [],
    );

    const resetForm = useCallback(() => {
        setFormTitle('');
        setFormDesc('');
        setFormType('document');
        setFormFile(null);
        setFormPreview(null);
        setFormError(null);
        setUploadSuccess(false);
        if (fileInputRef.current) fileInputRef.current.value = '';
    }, []);

    const handleUpload = useCallback(
        async (e: React.FormEvent) => {
            e.preventDefault();
            if (!formTitle.trim() || !formDesc.trim()) return;
            setFormError(null);
            setUploadSuccess(false);
            try {
                await crear({
                    title: formTitle.trim(),
                    description: formDesc.trim(),
                    category: formType,
                    file: formFile ?? undefined,
                });
                setUploadSuccess(true);
                showToast('Recurso subido correctamente');
                resetForm();
                setShowUploadForm(false);
            } catch (err) {
                const msg = err instanceof Error ? err.message : 'Error al subir recurso';
                setFormError(msg);
                showToast(msg);
            }
        },
        [formTitle, formDesc, formType, formFile, crear, resetForm, showToast],
    );

    const startEditing = useCallback((res: Recurso) => {
        setEditingId(res.id);
        setEditTitle(res.title);
        setEditDesc(res.description);
        setEditType(res.type);
        setEditFile(null);
        setEditPreview(null);
        setEditError(null);
    }, []);

    const cancelEditing = useCallback(() => {
        setEditingId(null);
        setEditFile(null);
        setEditPreview(null);
        setEditError(null);
    }, []);

    const handleEdit = useCallback(
        async (e: React.FormEvent) => {
            e.preventDefault();
            if (!editingId || !editTitle.trim() || !editDesc.trim()) return;
            setEditError(null);
            try {
                await actualizar(editingId, {
                    title: editTitle.trim(),
                    description: editDesc.trim(),
                    category: editType,
                    file: editFile ?? undefined,
                });
                showToast('Recurso actualizado correctamente');
                cancelEditing();
            } catch (err) {
                const msg = err instanceof Error ? err.message : 'Error al actualizar recurso';
                setEditError(msg);
                showToast(msg);
            }
        },
        [editingId, editTitle, editDesc, editType, editFile, actualizar, cancelEditing, showToast],
    );

    const handleDelete = useCallback(async () => {
        if (!deleteTarget) return;
        try {
            const id = deleteTarget.id;
            setDeleteTarget(null);
            await eliminar(id);
            showToast('Recurso eliminado correctamente');
        } catch (err) {
            const msg = err instanceof Error ? err.message : 'Error al eliminar recurso';
            showToast(msg);
        }
    }, [deleteTarget, eliminar, showToast]);

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Recursos"
                title="Gestión de Recursos"
                subtitle="Administre los recursos y documentos disponibles para los usuarios"
                actions={
                    <button
                        onClick={() => {
                            setShowUploadForm(!showUploadForm);
                            if (!showUploadForm) resetForm();
                        }}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                    >
                        <Upload className="h-4 w-4" />
                        Subir Recurso
                    </button>
                }
            />

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

            {/* Toast notification */}
            {toastMsg && (
                <div className="fixed bottom-4 right-4 z-50 flex items-center gap-2 rounded-lg bg-[#1c1917] px-4 py-3 text-sm text-white shadow-lg">
                    {mutationError ? (
                        <AlertCircle className="h-4 w-4 shrink-0 text-[#dc2626]" />
                    ) : (
                        <CheckCircle2 className="h-4 w-4 shrink-0 text-[#16a34a]" />
                    )}
                    {toastMsg}
                </div>
            )}

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
                            <label className="flex min-h-[40px] cursor-pointer items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#57534e] transition-colors hover:bg-[#fafaf9]">
                                <Upload className="h-4 w-4" />
                                <span>{formFile ? formFile.name : 'Seleccionar archivo...'}</span>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    className="sr-only"
                                    onChange={(e) =>
                                        handleFileSelect(e.target.files?.[0] ?? null, setFormFile, setFormPreview)
                                    }
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.mp4,.webm,.zip"
                                />
                            </label>
                        </div>

                        {/* File preview */}
                        {formPreview && (
                            <div className="sm:col-span-2">
                                <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] px-4 py-3">
                                    <Eye className="h-5 w-5 text-[#c2410c]" />
                                    <span className="text-sm text-[#1c1917]">{formFile?.name}</span>
                                    <span className="text-xs text-[#78716c]">
                                        {(formFile && `${(formFile.size / 1024).toFixed(0)} KB`) ?? ''}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => handleFileSelect(null, setFormFile, setFormPreview)}
                                        className="ml-auto rounded-lg p-1 text-[#57534e] hover:bg-[#e5e5e5]"
                                        aria-label="Quitar archivo"
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>

                    {formError && (
                        <div className="mt-3 flex items-center gap-2 rounded-lg bg-[#fee2e2] px-4 py-2 text-sm text-[#dc2626]">
                            <AlertCircle className="h-4 w-4 shrink-0" />
                            {formError}
                        </div>
                    )}

                    {uploadSuccess && (
                        <div className="mt-3 flex items-center gap-2 rounded-lg bg-[#dcfce7] px-4 py-2 text-sm text-[#14532d]">
                            <CheckCircle2 className="h-4 w-4 shrink-0" />
                            Recurso subido correctamente
                        </div>
                    )}

                    <div className="mt-5 flex items-center gap-3">
                        <button
                            type="submit"
                            disabled={mutationLoading || !formTitle.trim() || !formDesc.trim()}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {mutationLoading ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Upload className="h-4 w-4" />
                            )}
                            Subir Recurso
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setShowUploadForm(false);
                                resetForm();
                            }}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                        >
                            Cancelar
                        </button>
                    </div>
                </form>
            )}

            {/* Loading skeleton */}
            {loading && (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {[1, 2, 3].map((i) => (
                        <div
                            key={i}
                            className="h-40 animate-pulse rounded-xl border border-[#e5e5e5] bg-[#f5f5f4]"
                        />
                    ))}
                </div>
            )}

            {/* Resource cards */}
            {!loading && !error && (
                <>
                    {recursos.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 py-16 text-center">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#f5f5f4]">
                                <FileText className="h-6 w-6 text-[#78716c]" />
                            </div>
                            <h3 className="text-base font-semibold text-[#1c1917]">Sin recursos</h3>
                            <p className="text-sm text-[#57534e]">No hay recursos disponibles. Suba el primer recurso.</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {recursos.map((res) => {
                                const config = typeConfig[res.type] || typeConfig.other;
                                const Icon = config.icon;

                                // Edit mode inline
                                if (editingId === res.id) {
                                    return (
                                        <div
                                            key={res.id}
                                            className="rounded-xl border border-[#c2410c] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]"
                                        >
                                            <form onSubmit={handleEdit} className="flex flex-col gap-3">
                                                <input
                                                    type="text"
                                                    value={editTitle}
                                                    onChange={(e) => setEditTitle(e.target.value)}
                                                    className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm outline-none focus:border-[#c2410c]"
                                                    placeholder="Título"
                                                    required
                                                />
                                                <textarea
                                                    value={editDesc}
                                                    onChange={(e) => setEditDesc(e.target.value)}
                                                    rows={2}
                                                    className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm outline-none focus:border-[#c2410c] resize-y"
                                                    placeholder="Descripción"
                                                    required
                                                />
                                                <select
                                                    value={editType}
                                                    onChange={(e) => setEditType(e.target.value)}
                                                    className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm outline-none focus:border-[#c2410c]"
                                                >
                                                    <option value="document">Documento</option>
                                                    <option value="spreadsheet">Hoja de cálculo</option>
                                                    <option value="image">Imagen</option>
                                                    <option value="video">Video</option>
                                                    <option value="link">Enlace</option>
                                                    <option value="other">Otro</option>
                                                </select>
                                                <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#57534e] hover:bg-[#fafaf9]">
                                                    <Upload className="h-4 w-4" />
                                                    <span>{editFile ? editFile.name : 'Cambiar archivo...'}</span>
                                                    <input
                                                        ref={editFileInputRef}
                                                        type="file"
                                                        className="sr-only"
                                                        onChange={(e) =>
                                                            handleFileSelect(
                                                                e.target.files?.[0] ?? null,
                                                                setEditFile,
                                                                setEditPreview,
                                                            )
                                                        }
                                                    />
                                                </label>
                                                {editPreview && (
                                                    <div className="flex items-center gap-2 text-xs text-[#78716c]">
                                                        <Eye className="h-3 w-3" />
                                                        {editFile?.name}
                                                    </div>
                                                )}
                                                {editError && (
                                                    <span className="text-xs text-[#dc2626]">{editError}</span>
                                                )}
                                                <div className="flex gap-2">
                                                    <button
                                                        type="submit"
                                                        disabled={mutationLoading}
                                                        className="inline-flex items-center gap-1 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#9a330a] disabled:opacity-60"
                                                    >
                                                        {mutationLoading ? (
                                                            <Loader2 className="h-3 w-3 animate-spin" />
                                                        ) : (
                                                            'Guardar'
                                                        )}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={cancelEditing}
                                                        className="rounded-lg px-3 py-1.5 text-xs font-semibold text-[#57534e] hover:bg-[#f5f5f4]"
                                                    >
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    );
                                }

                                // Normal card view
                                return (
                                    <div
                                        key={res.id}
                                        className="group rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)] transition-all hover:shadow-[0_4px_12px_rgba(28,25,23,0.08)]"
                                    >
                                        <div className="flex items-start justify-between gap-3 mb-4">
                                            <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${config.bgClass}`}>
                                                <Icon className={`h-5 w-5 ${config.iconColor}`} />
                                            </div>
                                            <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-all">
                                                <button
                                                    onClick={() => startEditing(res)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-all hover:bg-[#f5f5f4] hover:text-[#c2410c] active:scale-[0.98]"
                                                    aria-label={`Editar ${res.title}`}
                                                    title="Editar"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button
                                                    onClick={() => setDeleteTarget(res)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-all hover:bg-[#fee2e2] hover:text-[#dc2626] active:scale-[0.98]"
                                                    aria-label={`Eliminar ${res.title}`}
                                                    title="Eliminar"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                        <h3 className="text-sm font-bold text-[#1c1917]">{res.title}</h3>
                                        <p className="mt-1 text-xs text-[#57534e] line-clamp-2">{res.description}</p>
                                        {res.file_path && (
                                            <a
                                                href={`/storage/${res.file_path}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-[#c2410c] transition-colors hover:text-[#9a330a]"
                                            >
                                                <ExternalLink className="h-3.5 w-3.5" />
                                                Ver archivo
                                            </a>
                                        )}
                                        <div className="mt-3 flex items-center justify-between gap-2 border-t border-[#e5e5e5] pt-3">
                                            <div className="flex items-center gap-2 text-xs text-[#78716c]">
                                                <Download className="h-3 w-3" />
                                                <span className="tabular-nums">{res.downloads}</span>
                                                {res.file_size && (
                                                    <>
                                                        <span className="text-[#e5e5e5]">|</span>
                                                        <span>{res.file_size}</span>
                                                    </>
                                                )}
                                            </div>
                                            <span className="text-[10px] text-[#78716c]">{res.uploaded_at}</span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </>
            )}

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


