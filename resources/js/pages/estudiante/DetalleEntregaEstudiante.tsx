import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import {
    ArrowLeft,
    Download,
    FileText,
    Upload,
    AlertTriangle,
    MessageSquare,
    X,
    Trash2,
    Send,
    Save,
} from 'lucide-react';

/* ── Mock data ── */

const MOCK_DELIVERY = {
    title: 'Presentación Anteproyecto',
    description:
        'Elaborar una presentación en diapositivas (mínimo 12) que explique el problema, objetivos, metodología propuesta y cronograma del anteproyecto. Debe incluir referencias bibliográficas en formato APA y un anexo con la rúbrica de evaluación completada.',
    deadline: '15/08/2026',
    projectCode: 'PG-2026-014',
};

interface DirectorComment {
    title: string;
    comment: string;
}

interface DeliveryVersion {
    id: number;
    version: number;
    date: string;
    time: string;
    fileName: string;
    directorComment: DirectorComment | null;
}

const MOCK_VERSIONS: DeliveryVersion[] = [
    {
        id: 1,
        version: 1,
        date: '01/03/2026',
        time: '09:15',
        fileName: 'presentacion_anteproyecto_v1.pptx',
        directorComment: {
            title: 'Comentario — Versión 1',
            comment: 'La metodología está bien planteada, pero falta detallar el cronograma por sprints. Ajusta las diapositivas 8 y 9 antes de la siguiente entrega.',
        },
    },
    {
        id: 2,
        version: 2,
        date: '10/03/2026',
        time: '16:42',
        fileName: 'presentacion_anteproyecto_v2.pptx',
        directorComment: {
            title: 'Comentario — Versión 2',
            comment: 'Mejoró la estructura general. Revisa la ortografía en el slide de objetivos específicos.',
        },
    },
];

const MAX_VERSIONS = 4;

const cardClass = 'rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]';

function parseDeadline(deadline: string): Date {
    const [day, month, year] = deadline.split('/').map(Number);
    return new Date(year, month - 1, day, 23, 59, 59);
}

function ErrorAlert({ message }: { message: string }) {
    return (
        <div
            className="flex items-start gap-3 rounded-lg border border-[#dc2626]/20 bg-[#fee2e2] p-4 text-sm text-[#7f1d1d]"
            role="alert"
        >
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
            <span>{message}</span>
        </div>
    );
}

export default function DetalleEntregaEstudiante() {
    const navigate = useNavigate();

    const [versions, setVersions] = useState<DeliveryVersion[]>(MOCK_VERSIONS);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [savedFile, setSavedFile] = useState<{ name: string } | null>(null);
    const [saveError, setSaveError] = useState<string | null>(null);
    const [sendError, setSendError] = useState<string | null>(null);
    const [activeComment, setActiveComment] = useState<DirectorComment | null>(null);

    const deadlineDate = useMemo(() => parseDeadline(MOCK_DELIVERY.deadline), []);
    const isDeadlineExpired = useMemo(() => new Date() > deadlineDate, [deadlineDate]);
    const canUpload = versions.length < MAX_VERSIONS && !isDeadlineExpired;
    const maxVersionsReached = versions.length >= MAX_VERSIONS;

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0] ?? null;
        setSelectedFile(file);
        setSaveError(null);
        setSendError(null);
    }

    function handleRemovePendingFile() {
        setSelectedFile(null);
        setSavedFile(null);
        setSaveError(null);
        setSendError(null);
    }

    function handleSave() {
        setSaveError(null);
        setSendError(null);

        if (!selectedFile) {
            setSaveError('Debes seleccionar un archivo antes de guardar.');
            return;
        }

        setSavedFile({ name: selectedFile.name });
    }

    function handleSend() {
        setSendError(null);

        if (!savedFile) {
            setSendError('Debes guardar un archivo antes de enviar la entrega.');
            return;
        }

        const nextVersion = versions.length + 1;
        const now = new Date();

        setVersions((prev) => [
            ...prev,
            {
                id: nextVersion,
                version: nextVersion,
                date: now.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' }),
                time: now.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', hour12: false }),
                fileName: savedFile.name,
                directorComment: null,
            },
        ]);

        setSelectedFile(null);
        setSavedFile(null);
    }

    function handleDownload(fileName: string) {
        // Mock: sin backend aún
        window.alert(`Descarga simulada: ${fileName}`);
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Entrega"
                title={MOCK_DELIVERY.title}
                subtitle={`${MOCK_DELIVERY.projectCode} · Límite ${MOCK_DELIVERY.deadline}`}
                actions={
                    <button
                        type="button"
                        onClick={() => navigate('/dashboard/estudiante')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            {/* ── Información de la entrega ── */}
            <div className={cardClass}>
                <div className="mb-4 flex items-center gap-2">
                    <FileText className="h-5 w-5 text-[#c2410c]" />
                    <h3 className="text-base font-bold text-[#1c1917]">Información de la entrega</h3>
                </div>
                <div className="flex flex-col gap-4">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">Título</p>
                        <p className="mt-1 text-sm font-semibold text-[#1c1917]">{MOCK_DELIVERY.title}</p>
                    </div>
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                            Descripción / Rúbrica
                        </p>
                        <p className="mt-1 text-sm leading-relaxed text-[#57534e]">{MOCK_DELIVERY.description}</p>
                    </div>
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                            Fecha límite de entrega
                        </p>
                        <p className="mt-1 text-sm font-semibold text-[#1c1917]">{MOCK_DELIVERY.deadline}</p>
                        {isDeadlineExpired && (
                            <p className="mt-1 text-xs text-[#dc2626]">
                                La fecha límite ha expirado. Ya no puedes modificar ni enviar nuevas versiones.
                            </p>
                        )}
                    </div>
                </div>
            </div>

            {/* ── Historial de versiones ── */}
            <div className={cardClass}>
                <div className="mb-4 flex items-center justify-between gap-3 flex-wrap">
                    <div className="flex items-center gap-2">
                        <FileText className="h-5 w-5 text-[#c2410c]" />
                        <h3 className="text-base font-bold text-[#1c1917]">Historial de versiones</h3>
                    </div>
                    <span className="text-xs text-[#78716c]">
                        {versions.length} de {MAX_VERSIONS} versiones
                    </span>
                </div>

                {versions.length > 0 ? (
                    <div className="flex flex-col gap-3">
                        {versions.map((v) => (
                            <div
                                key={v.id}
                                className="flex flex-col gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="flex flex-col gap-1 min-w-0">
                                    <span className="text-sm font-semibold text-[#1c1917]">
                                        Versión {v.version}
                                    </span>
                                    <span className="text-xs text-[#57534e]">
                                        {v.date} · {v.time}
                                    </span>
                                    <span className="truncate text-xs text-[#78716c]">{v.fileName}</span>
                                </div>
                                <div className="flex flex-wrap items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        onClick={() => handleDownload(v.fileName)}
                                        className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                                    >
                                        <Download className="h-3.5 w-3.5" />
                                        Descargar
                                    </button>
                                    {v.directorComment && (
                                        <button
                                            type="button"
                                            onClick={() => setActiveComment(v.directorComment)}
                                            className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                                        >
                                            <MessageSquare className="h-3.5 w-3.5" />
                                            Ver comentario
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-[#78716c]">Aún no has enviado ninguna versión.</p>
                )}
            </div>

            {/* ── Área de carga ── */}
            {maxVersionsReached ? (
                <div className={cardClass}>
                    <div className="flex items-start gap-3 rounded-lg border border-[#fef3c7] bg-[#fef3c7]/40 p-4">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-[#d97706]" />
                        <p className="text-sm text-[#78350f]">
                            Has alcanzado el máximo de {MAX_VERSIONS} versiones permitidas para esta entrega.
                        </p>
                    </div>
                </div>
            ) : isDeadlineExpired ? (
                <div className={cardClass}>
                    <p className="text-sm text-[#78716c]">
                        El plazo de entrega ha finalizado. No puedes cargar ni enviar nuevas versiones.
                    </p>
                </div>
            ) : (
                <div className={cardClass}>
                    <div className="mb-4 flex items-center gap-2">
                        <Upload className="h-5 w-5 text-[#c2410c]" />
                        <h3 className="text-base font-bold text-[#1c1917]">Cargar nueva versión</h3>
                    </div>

                    <div className="flex flex-col gap-4">
                        <div className="flex flex-col gap-2">
                            <label
                                htmlFor="delivery-file"
                                className="text-sm font-semibold text-[#1c1917]"
                            >
                                Archivo
                            </label>
                            <input
                                id="delivery-file"
                                type="file"
                                accept=".pdf,.doc,.docx,.ppt,.pptx,.zip"
                                onChange={handleFileChange}
                                className="block w-full text-sm text-[#57534e] file:mr-4 file:rounded-lg file:border-0 file:bg-[#fed7aa] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#c2410c] hover:file:bg-[#fdba74]"
                            />
                            {selectedFile && (
                                <p className="text-xs text-[#57534e]">
                                    Seleccionado: <span className="font-medium text-[#1c1917]">{selectedFile.name}</span>
                                </p>
                            )}
                            {savedFile && (
                                <p className="text-xs text-[#16a34a]">
                                    Guardado (pendiente de envío):{' '}
                                    <span className="font-medium">{savedFile.name}</span>
                                </p>
                            )}
                        </div>

                        {saveError && <ErrorAlert message={saveError} />}
                        {sendError && <ErrorAlert message={sendError} />}

                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                onClick={handleSave}
                                disabled={!canUpload}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Save className="h-4 w-4" />
                                Guardar
                            </button>

                            <button
                                type="button"
                                onClick={handleSend}
                                disabled={!canUpload || !savedFile}
                                className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#c2410c] bg-white px-4 py-2.5 text-sm font-semibold text-[#c2410c] transition-colors hover:bg-[#fed7aa] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Send className="h-4 w-4" />
                                Enviar entrega
                            </button>

                            {(selectedFile || savedFile) && (
                                <button
                                    type="button"
                                    onClick={handleRemovePendingFile}
                                    className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2.5 text-sm font-semibold text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#1c1917]"
                                >
                                    <Trash2 className="h-4 w-4" />
                                    Quitar archivo
                                </button>
                            )}
                        </div>

                        <p className="text-xs text-[#78716c]">
                            Flujo: selecciona un archivo → Guardar → Enviar entrega. Puedes reemplazar el archivo
                            pendiente antes del cierre.
                        </p>
                    </div>
                </div>
            )}

            {/* ── Overlay comentario del director ── */}
            {activeComment && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                    onClick={(e) => {
                        if (e.target === e.currentTarget) setActiveComment(null);
                    }}
                    role="dialog"
                    aria-modal="true"
                    aria-label={activeComment.title}
                >
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-[0_20px_60px_rgba(28,25,23,0.15)]">
                        <div className="mb-4 flex items-start justify-between gap-3">
                            <div className="flex items-start gap-3">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#f5f5f4] text-[#c2410c]">
                                    <MessageSquare className="h-5 w-5" />
                                </div>
                                <h2 className="text-lg font-bold text-[#1c1917]">{activeComment.title}</h2>
                            </div>
                            <button
                                type="button"
                                onClick={() => setActiveComment(null)}
                                className="rounded-lg p-1.5 text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#1c1917]"
                                aria-label="Cerrar"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <p className="text-sm leading-relaxed text-[#57534e]">{activeComment.comment}</p>
                        <div className="mt-6 flex justify-end">
                            <button
                                type="button"
                                onClick={() => setActiveComment(null)}
                                className="inline-flex items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a]"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
