import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import {
    ArrowLeft,
    Download,
    FileText,
    MessageSquare,
    X,
    Pencil,
    Save,
} from 'lucide-react';
import { getDeliveryById, getProjectById, type DirectorComment } from '@/lib/mock/project-data';

const cardClass = 'rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]';

export default function DetalleEntregaDirector() {
    const navigate = useNavigate();
    const { proyectoId, id } = useParams<{ proyectoId: string; id: string }>();
    const delivery = getDeliveryById(Number(id));
    const project = getProjectById(Number(proyectoId));

    const [grade, setGrade] = useState<number | null>(delivery?.grade ?? null);
    const [editingGrade, setEditingGrade] = useState(false);
    const [gradeInput, setGradeInput] = useState(String(delivery?.grade ?? ''));

    const [comments, setComments] = useState<Record<number, DirectorComment>>(
        () => {
            const initial: Record<number, DirectorComment> = {};
            delivery?.versions.forEach((v) => {
                if (v.directorComment) initial[v.id] = v.directorComment;
            });
            return initial;
        },
    );
    const [editingCommentId, setEditingCommentId] = useState<number | null>(null);
    const [commentDraft, setCommentDraft] = useState('');
    const [activeComment, setActiveComment] = useState<DirectorComment | null>(null);

    const backPath = `/supervision/${proyectoId}`;

    const hasGrade = grade !== null;

    function handleDownload(fileName: string) {
        window.alert(`Descarga simulada: ${fileName}`);
    }

    function handleSaveGrade() {
        const parsed = Number(gradeInput);
        if (!Number.isNaN(parsed) && parsed >= 0 && parsed <= 100) {
            setGrade(parsed);
            setEditingGrade(false);
        }
    }

    function handleSaveComment(versionId: number, versionNum: number) {
        if (!commentDraft.trim()) return;
        setComments((prev) => ({
            ...prev,
            [versionId]: {
                title: `Comentario — Versión ${versionNum}`,
                comment: commentDraft.trim(),
            },
        }));
        setEditingCommentId(null);
        setCommentDraft('');
    }

    function startEditComment(versionId: number) {
        const existing = comments[versionId];
        setCommentDraft(existing?.comment ?? '');
        setEditingCommentId(versionId);
    }

    if (!delivery || !project) {
        return (
            <div className="flex flex-col gap-6">
                <PageHeader eyebrow="Entrega" title="Entrega no encontrada" subtitle="La entrega solicitada no existe." />
                <button
                    type="button"
                    onClick={() => navigate('/dashboard/director')}
                    className="inline-flex min-h-[40px] items-center gap-2 self-start rounded-lg border border-[#e5e5e5] px-4 py-2 text-sm font-semibold"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Volver
                </button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Entrega"
                title={delivery.label}
                subtitle={`${project.code} · ${project.students.join(', ')} · Límite ${delivery.deadline}`}
                actions={
                    <button
                        type="button"
                        onClick={() => navigate(backPath)}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            <div className={cardClass}>
                <div className="mb-4 flex items-center gap-2">
                    <FileText className="h-5 w-5 text-[#c2410c]" />
                    <h3 className="text-base font-bold text-[#1c1917]">Información de la entrega</h3>
                </div>
                <div className="flex flex-col gap-4">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">Título</p>
                        <p className="mt-1 text-sm font-semibold text-[#1c1917]">{delivery.label}</p>
                    </div>
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">Descripción / Rúbrica</p>
                        <p className="mt-1 text-sm leading-relaxed text-[#57534e]">{delivery.description}</p>
                    </div>
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">Fecha límite</p>
                        <p className="mt-1 text-sm font-semibold text-[#1c1917]">{delivery.deadline}</p>
                    </div>
                </div>
            </div>

            {/* Grade */}
            <div className={cardClass}>
                <div className="mb-4 flex items-center justify-between gap-3">
                    <h3 className="text-base font-bold text-[#1c1917]">Nota asignada</h3>
                    {hasGrade && !editingGrade && (
                        <button
                            type="button"
                            onClick={() => {
                                setGradeInput(String(grade));
                                setEditingGrade(true);
                            }}
                            className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] hover:bg-[#f5f5f4]"
                        >
                            <Pencil className="h-3.5 w-3.5" />
                            Editar
                        </button>
                    )}
                </div>

                {editingGrade || !hasGrade ? (
                    <div className="flex flex-wrap items-end gap-3">
                        <div>
                            <label htmlFor="grade-input" className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                Nota (0–100)
                            </label>
                            <input
                                id="grade-input"
                                type="number"
                                min={0}
                                max={100}
                                value={gradeInput}
                                onChange={(e) => setGradeInput(e.target.value)}
                                className="w-24 min-h-[40px] rounded-lg border border-[#e5e5e5] px-3 py-2 text-lg font-bold tabular-nums outline-none focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                            />
                        </div>
                        <button
                            type="button"
                            onClick={handleSaveGrade}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white hover:bg-[#9a330a]"
                        >
                            <Save className="h-4 w-4" />
                            {hasGrade ? 'Guardar cambios' : 'Asignar nota'}
                        </button>
                        {hasGrade && (
                            <button
                                type="button"
                                onClick={() => setEditingGrade(false)}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] px-4 py-2 text-sm font-semibold text-[#57534e] hover:bg-[#f5f5f4]"
                            >
                                Cancelar
                            </button>
                        )}
                    </div>
                ) : (
                    <p className="text-3xl font-bold tabular-nums text-[#1c1917]">{grade}<span className="text-lg text-[#78716c]">/100</span></p>
                )}
            </div>

            {/* Versions */}
            <div className={cardClass}>
                <div className="mb-4 flex items-center gap-2">
                    <FileText className="h-5 w-5 text-[#c2410c]" />
                    <h3 className="text-base font-bold text-[#1c1917]">Versiones entregadas</h3>
                </div>

                {delivery.versions.length > 0 ? (
                    <div className="flex flex-col gap-4">
                        {delivery.versions.map((v) => {
                            const comment = comments[v.id];
                            const isEditing = editingCommentId === v.id;

                            return (
                                <div key={v.id} className="rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-4">
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="flex flex-col gap-1 min-w-0">
                                            <span className="text-sm font-semibold text-[#1c1917]">Versión {v.version}</span>
                                            <span className="text-xs text-[#57534e]">{v.date} · {v.time}</span>
                                            <span className="truncate text-xs text-[#78716c]">{v.fileName}</span>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => handleDownload(v.fileName)}
                                            className="inline-flex min-h-[36px] shrink-0 items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] hover:bg-[#f5f5f4]"
                                        >
                                            <Download className="h-3.5 w-3.5" />
                                            Descargar
                                        </button>
                                    </div>

                                    <div className="mt-4 border-t border-[#e5e5e5] pt-4">
                                        <div className="mb-2 flex items-center justify-between gap-2">
                                            <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">Comentario del director</p>
                                            {!isEditing && (
                                                <button
                                                    type="button"
                                                    onClick={() => startEditComment(v.id)}
                                                    className="inline-flex items-center gap-1 text-xs font-semibold text-[#c2410c] hover:underline"
                                                >
                                                    <Pencil className="h-3 w-3" />
                                                    {comment ? 'Editar' : 'Agregar'}
                                                </button>
                                            )}
                                        </div>

                                        {isEditing ? (
                                            <div className="flex flex-col gap-2">
                                                <textarea
                                                    rows={3}
                                                    value={commentDraft}
                                                    onChange={(e) => setCommentDraft(e.target.value)}
                                                    placeholder="Escriba sus observaciones para esta versión..."
                                                    className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm outline-none focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                                />
                                                <div className="flex gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => handleSaveComment(v.id, v.version)}
                                                        className="inline-flex min-h-[32px] items-center gap-1 rounded-lg bg-[#c2410c] px-3 py-1 text-xs font-semibold text-white hover:bg-[#9a330a]"
                                                    >
                                                        <Save className="h-3 w-3" />
                                                        Guardar
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setEditingCommentId(null);
                                                            setCommentDraft('');
                                                        }}
                                                        className="inline-flex min-h-[32px] items-center gap-1 rounded-lg border border-[#e5e5e5] px-3 py-1 text-xs font-semibold text-[#57534e] hover:bg-[#f5f5f4]"
                                                    >
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </div>
                                        ) : comment ? (
                                            <button
                                                type="button"
                                                onClick={() => setActiveComment(comment)}
                                                className="inline-flex items-center gap-2 text-sm text-[#57534e] hover:text-[#c2410c]"
                                            >
                                                <MessageSquare className="h-4 w-4" />
                                                Ver comentario
                                            </button>
                                        ) : (
                                            <p className="text-xs text-[#78716c]">Sin comentarios aún.</p>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <p className="text-sm text-[#78716c]">El estudiante aún no ha enviado versiones para esta entrega.</p>
                )}
            </div>

            {activeComment && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                    onClick={(e) => { if (e.target === e.currentTarget) setActiveComment(null); }}
                    role="dialog"
                    aria-modal="true"
                >
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-[0_20px_60px_rgba(28,25,23,0.15)]">
                        <div className="mb-4 flex items-start justify-between gap-3">
                            <div className="flex items-start gap-3">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#f5f5f4] text-[#c2410c]">
                                    <MessageSquare className="h-5 w-5" />
                                </div>
                                <h2 className="text-lg font-bold text-[#1c1917]">{activeComment.title}</h2>
                            </div>
                            <button type="button" onClick={() => setActiveComment(null)} className="rounded-lg p-1.5 hover:bg-[#f5f5f4]">
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <p className="text-sm leading-relaxed text-[#57534e]">{activeComment.comment}</p>
                    </div>
                </div>
            )}
        </div>
    );
}
