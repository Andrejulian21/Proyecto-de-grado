import { useState, useEffect, useMemo } from 'react';
import { cn, apiFetch } from '@/lib/utils';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { SignatureCodeInput } from '@/components/bitacoras/SignatureCode';
import { DataTable, type Column } from '@/components/ui/DataTable';
import {
    ArrowLeft,
    Calendar,
    User,
    FileText,
    ShieldCheck,
    Loader2,
    Pencil,
    Save,
} from 'lucide-react';
/* --- Tipos locales (reemplazo de mock) --- */

export interface BitacoraSignature {
    role: 'director' | 'student';
    name: string;
    signed: boolean;
    signedAt: string | null;
}

export interface BitacoraDetail {
    id: number;
    content: string;
    topic: string;
    projectCode: string;
    date: string;
    createdAt: string;
    status: string;
    author: string;
    projectId: number;
    semana?: number;
    signatures: BitacoraSignature[];
}

function bitacoraStatusLabel(status: string): string {
    switch (status) {
        case 'Completada':
            return 'Completada';
        case 'FirmadaEstudiante':
            return 'Firmada por Estudiante';
        case 'FirmadaDirector':
            return 'Firmada por Director';
        case 'Sospechosa':
            return 'Sospechosa';
        default:
            return 'Pendiente';
    }
}

const cardClass = 'rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]';

const EDIT_WINDOW_MS = 15 * 60 * 1000;

function formatRemaining(ms: number): string {
    if (ms <= 0) return '0:00';
    const totalSeconds = Math.floor(ms / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

export interface RevisionBitacoraViewProps {
    mode: 'director' | 'student';
    bitacora: BitacoraDetail;
    onBack: () => void;
    onSign?: () => Promise<void>;
    onRemoveSignature?: () => void;
    onSaveContent?: (content: string) => void;
    currentStudentName?: string;
    disableSigning?: boolean;
}

export function RevisionBitacoraView({
    mode,
    bitacora: initialBitacora,
    onBack,
    onSign,
    onRemoveSignature,
    onSaveContent,
    currentStudentName = 'Ana Martínez',
    disableSigning = false,
}: RevisionBitacoraViewProps) {
    const [bitacora, setBitacora] = useState(initialBitacora);
    const [content, setContent] = useState(bitacora.content);
    const [editingTopic, setEditingTopic] = useState(bitacora.topic);
    const [editingDate, setEditingDate] = useState(bitacora.date ? bitacora.date.split('T')[0] : '');
    const [editingSemana, setEditingSemana] = useState(bitacora.semana ?? 1);
    const [editing, setEditing] = useState(false);
    const [signedOk, setSignedOk] = useState(false);
    const [now, setNow] = useState(() => new Date());

    // PR 4 — RF-WK-04: edits are only allowed inside a 15-minute window
    // from creation. Compute the deadline once and tick `now` every 30 s
    // so the remaining-time indicator stays roughly accurate without
    // burning cycles on a per-second update.
    const editableUntil = useMemo(() => {
        if (!bitacora.createdAt) return null;
        const createdMs = new Date(bitacora.createdAt).getTime();
        if (Number.isNaN(createdMs)) return null;
        return new Date(createdMs + EDIT_WINDOW_MS);
    }, [bitacora.createdAt]);

    useEffect(() => {
        if (!editableUntil) return;
        const id = setInterval(() => setNow(new Date()), 30_000);
        return () => clearInterval(id);
    }, [editableUntil]);

    const isWithinEditWindow = editableUntil ? now < editableUntil : false;
    const remainingMs = editableUntil ? editableUntil.getTime() - now.getTime() : 0;
    const remainingLabel = formatRemaining(remainingMs);

    // If the window expires while the student is mid-edit, force-close the
    // editor so they cannot keep typing into a form the server will reject.
    useEffect(() => {
        if (editing && !isWithinEditWindow) {
            setContent(bitacora.content);
            setEditingTopic(bitacora.topic);
            setEditingDate(bitacora.date ? bitacora.date.split('T')[0] : '');
            setEditingSemana(bitacora.semana ?? 1);
            setEditing(false);
        }
    }, [editing, isWithinEditWindow, bitacora.content, bitacora.topic, bitacora.date, bitacora.semana]);

    const directorSignature = bitacora.signatures.find((s) => s.role === 'director');
    const directorSigned = directorSignature?.signed ?? false;
    const currentUserSigned =
        mode === 'director'
            ? ((directorSignature?.signed ?? false) || signedOk)
            : (bitacora.signatures.find((s) => s.role === 'student' && s.name === currentStudentName)?.signed ?? false);
    const canEditContent = mode === 'student' && isWithinEditWindow;

    const signatureColumns: Column<BitacoraSignature & { id: string }>[] = [
        {
            key: 'semana',
            label: 'Semana',
            className: 'text-[#57534e] tabular-nums whitespace-nowrap',
            render: () => (
                <span className="text-[#57534e] tabular-nums">
                    {bitacora.semana != null ? `Sem ${bitacora.semana}` : '—'}
                </span>
            ),
        },
        {
            key: 'role',
            label: 'Rol',
            render: (row) => (
                <span className="font-medium text-[#1c1917]">
                    {row.role === 'director' ? 'Director' : 'Estudiante'}
                </span>
            ),
        },
        { key: 'name', label: 'Nombre', className: 'text-[#57534e]' },
        {
            key: 'signed',
            label: 'Estado',
            render: (row) => (
                <StatusBadge variant={row.signed ? 'success' : 'warning'}>
                    {row.signed ? 'Firmado' : 'Pendiente'}
                </StatusBadge>
            ),
        },
        {
            key: 'signedAt',
            label: 'Fecha firma',
            className: 'text-[#57534e] tabular-nums whitespace-nowrap',
            render: (row) => row.signedAt ?? '—',
        },
    ];

    const tableData = bitacora.signatures
        .filter((s) => s.role === 'director')
        .map((s, i) => ({ ...s, id: `${s.role}-${i}` }));


    function handleRemoveSignature() {
        onRemoveSignature?.();
        setBitacora((prev) => ({
            ...prev,
            status: 'pending_director',
            signatures: prev.signatures.map((s) =>
                s.role === 'director' ? { ...s, signed: false, signedAt: null } : s,
            ),
        }));
    }

    function handleSaveContent() {
        onSaveContent?.(content);
        setBitacora((prev) => ({ ...prev, content }));
        setEditing(false);
    }

    async function handleSaveFull() {
        const res = await apiFetch(`/api/bitacoras/${bitacora.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                topic: editingTopic,
                meeting_date: editingDate,
                semana: editingSemana,
                notes: content,
            }),
        });
        if (res.ok) {
            setBitacora((prev) => ({ ...prev, topic: editingTopic, date: editingDate, semana: editingSemana, content }));
            setEditing(false);
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Bitácora"
                title={bitacora.topic || 'Revisar Bitácora'}
                subtitle={`${bitacora.projectCode} · ${bitacora.date ? new Date(bitacora.date).toLocaleString('es-CO') : '—'}`}
                actions={
                    <button
                        type="button"
                        onClick={onBack}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                <div className="lg:col-span-3 flex flex-col gap-6">
                    {/* Metadata */}
                    <div className={cardClass}>
                        <div className="mb-4 flex flex-wrap items-center gap-3">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Detalle de la sesión</h3>
                            </div>
                            <StatusBadge variant={bitacora.status === 'Completada' || bitacora.status === 'FirmadaDirector' ? 'success' : 'warning'}>
                                {bitacoraStatusLabel(bitacora.status)}
                            </StatusBadge>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <Calendar className="h-5 w-5 text-[#c2410c]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Fecha de reunión</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">{bitacora.date ? new Date(bitacora.date).toLocaleString('es-CO') : '—'}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <Calendar className="h-5 w-5 text-[#4f46e5]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Creada el</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">{bitacora.createdAt ? new Date(bitacora.createdAt).toLocaleString('es-CO') : '—'}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <User className="h-5 w-5 text-[#4f46e5]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Autor</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">{bitacora.author}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <Calendar className="h-5 w-5 text-[#c2410c]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Semana</p>
                                    <p className="text-sm font-semibold text-[#1c1917] tabular-nums">
                                        {bitacora.semana != null ? `Semana ${bitacora.semana}` : '—'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Content */}
                    <div className={cardClass}>
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <h3 className="text-base font-bold text-[#1c1917]">Contenido</h3>
                            {mode === 'student' && !directorSigned && (
                                isWithinEditWindow ? (
                                    <span
                                        className="inline-flex items-center gap-1.5 rounded-full bg-[#dbeafe] px-2.5 py-1 text-xs font-semibold text-[#1e40af] tabular-nums"
                                        title="Tiempo restante para editar"
                                    >
                                        Puedes editar {remainingLabel}
                                    </span>
                                ) : (
                                    <span
                                        className="inline-flex items-center gap-1.5 rounded-full bg-[#f5f5f4] px-2.5 py-1 text-xs font-semibold text-[#57534e]"
                                        title="La ventana de edición de 15 minutos ya cerró"
                                    >
                                        Edición cerrada (15 min)
                                    </span>
                                )
                            )}
                            {canEditContent && !editing && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setEditingTopic(bitacora.topic);
                                        setEditingDate(bitacora.date ? bitacora.date.split('T')[0] : '');
                                        setEditingSemana(bitacora.semana ?? 1);
                                        setEditing(true);
                                    }}
                                    className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                                >
                                    <Pencil className="h-3.5 w-3.5" />
                                    Editar
                                </button>
                            )}
                        </div>

                        {editing ? (
                            <div className="flex flex-col gap-4">
                                {mode === 'student' && (
                                    <>
                                        <div>
                                            <label htmlFor="bitacora-topic" className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                                Tema
                                            </label>
                                            <input
                                                id="bitacora-topic"
                                                type="text"
                                                value={editingTopic}
                                                onChange={(e) => setEditingTopic(e.target.value)}
                                                className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                            />
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label htmlFor="bitacora-date" className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                                    Fecha reunión
                                                </label>
                                                <input
                                                    id="bitacora-date"
                                                    type="date"
                                                    value={editingDate}
                                                    onChange={(e) => setEditingDate(e.target.value)}
                                                    className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                                />
                                            </div>
                                            <div>
                                                <label htmlFor="bitacora-semana" className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                                    Semana
                                                </label>
                                                <input
                                                    id="bitacora-semana"
                                                    type="number"
                                                    min={1}
                                                    max={32}
                                                    value={editingSemana}
                                                    onChange={(e) => setEditingSemana(Number(e.target.value))}
                                                    className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                                />
                                            </div>
                                        </div>
                                    </>
                                )}
                                <div>
                                    <label htmlFor="bitacora-content" className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                        Contenido
                                    </label>
                                    <textarea
                                        id="bitacora-content"
                                        rows={5}
                                        value={content}
                                        onChange={(e) => setContent(e.target.value)}
                                        className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={handleSaveFull}
                                        className="inline-flex min-h-[36px] items-center gap-2 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#9a330a]"
                                    >
                                        <Save className="h-3.5 w-3.5" />
                                        Guardar
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setContent(bitacora.content);
                                            setEditingTopic(bitacora.topic);
                                            setEditingDate(bitacora.date ? bitacora.date.split('T')[0] : '');
                                            setEditingSemana(bitacora.semana ?? 1);
                                            setEditing(false);
                                        }}
                                        className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#57534e] hover:bg-[#f5f5f4]"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-4">
                                <p className="text-sm leading-relaxed text-[#57534e]">{content}</p>
                            </div>
                        )}
                    </div>

                    {/* Signatures table */}
                    <div className={cardClass}>
                        <h3 className="mb-4 text-base font-bold text-[#1c1917]">Información sobre la firma</h3>
                        <DataTable
                            columns={signatureColumns}
                            data={tableData}
                            getRowKey={(row) => row.id}
                        />
                    </div>
                </div>

                {/* Signature panel — hidden for coordinator */}
                <div className={cn("lg:col-span-2", (disableSigning || mode === 'student') && "hidden")}>
                    <div className="sticky top-20 rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-5 flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#e0e7ff]">
                                <ShieldCheck className="h-5 w-5 text-[#4f46e5]" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-[#1c1917]">Firma Digital</h3>
                                <p className="text-xs text-[#57534e]">Código de firma</p>
                            </div>
                        </div>

                        {mode === 'director' && (
                            currentUserSigned ? (
                                <div className="rounded-lg border border-[#dcfce7] bg-[#dcfce7]/40 p-4 text-sm text-[#14532d]">
                                    Has firmado esta bitácora correctamente.
                                </div>
                            ) : (
                                <SignatureCodeInput
                                    bitacoraId={bitacora.id}
                                    onSuccess={() => {
                                        setSignedOk(true);
                                        setBitacora((prev) => ({
                                            ...prev,
                                            signatures: prev.signatures.map((s) =>
                                                s.role === 'director'
                                                    ? { ...s, signed: true, signedAt: new Date().toLocaleString('es-CO') }
                                                    : s,
                                            ),
                                        }));
                                        onSign?.().then(() => {}).catch(() => {});
                                    }}
                                />
                            )
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
