import { useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { TOTPInput } from '@/components/ui/TOTPInput';
import { DataTable, type Column } from '@/components/ui/DataTable';
import {
    ArrowLeft,
    Calendar,
    User,
    FileText,
    ShieldCheck,
    PenSquare,
    Loader2,
    Trash2,
    Pencil,
    Save,
} from 'lucide-react';
import type { BitacoraDetail, BitacoraSignature } from '@/lib/mock/project-data';
import { bitacoraStatusEmoji, bitacoraStatusLabel } from '@/lib/mock/project-data';

const cardClass = 'rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]';

export interface RevisionBitacoraViewProps {
    mode: 'director' | 'student';
    bitacora: BitacoraDetail;
    onBack: () => void;
    onSign: (totpCode: string) => Promise<void>;
    onRemoveSignature?: () => void;
    onSaveContent?: (content: string, weeklySummary: string) => void;
    currentStudentName?: string;
}

export function RevisionBitacoraView({
    mode,
    bitacora: initialBitacora,
    onBack,
    onSign,
    onRemoveSignature,
    onSaveContent,
    currentStudentName = 'Ana Martínez',
}: RevisionBitacoraViewProps) {
    const [bitacora, setBitacora] = useState(initialBitacora);
    const [content, setContent] = useState(bitacora.content);
    const [weeklySummary, setWeeklySummary] = useState(bitacora.weeklySummary);
    const [editing, setEditing] = useState(false);
    const [totpCode, setTotpCode] = useState('');
    const [totpError, setTotpError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const directorSignature = bitacora.signatures.find((s) => s.role === 'director');
    const directorSigned = directorSignature?.signed ?? false;
    const currentUserSigned =
        mode === 'director'
            ? (directorSignature?.signed ?? false)
            : (bitacora.signatures.find((s) => s.role === 'student' && s.name === currentStudentName)?.signed ?? false);
    const canEditContent = mode === 'student' && !directorSigned;

    const signatureColumns: Column<BitacoraSignature & { id: string }>[] = [
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

    const tableData = bitacora.signatures.map((s, i) => ({ ...s, id: `${s.role}-${i}` }));

    function handleTOTPComplete(code: string) {
        setTotpCode(code);
        setTotpError('');
    }

    async function handleSign() {
        if (totpCode.length !== 6) {
            setTotpError('Debe ingresar el código TOTP de 6 dígitos.');
            return;
        }
        setSubmitting(true);
        try {
            await onSign(totpCode);
            setBitacora((prev) => ({
                ...prev,
                status: mode === 'director' ? 'signed' : prev.status,
                signatures: prev.signatures.map((s) => {
                    if (mode === 'director' && s.role === 'director') {
                        return { ...s, signed: true, signedAt: new Date().toLocaleString('es-CO') };
                    }
                    if (mode === 'student' && s.role === 'student' && s.name === currentStudentName) {
                        return { ...s, signed: true, signedAt: new Date().toLocaleString('es-CO') };
                    }
                    return s;
                }),
            }));
            setTotpCode('');
        } catch {
            setTotpError('Error al firmar. Intente de nuevo.');
        } finally {
            setSubmitting(false);
        }
    }

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
        onSaveContent?.(content, weeklySummary);
        setBitacora((prev) => ({ ...prev, content, weeklySummary }));
        setEditing(false);
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Bitácora"
                title="Revisar Bitácora"
                subtitle={`${bitacora.projectCode} · ${bitacora.date}`}
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
                            <StatusBadge variant={bitacora.status === 'signed' ? 'success' : 'warning'}>
                                {bitacoraStatusEmoji(bitacora.status)} {bitacoraStatusLabel(bitacora.status)}
                            </StatusBadge>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <Calendar className="h-5 w-5 text-[#c2410c]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Fecha</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">{bitacora.date}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <User className="h-5 w-5 text-[#4f46e5]" />
                                <div>
                                    <p className="text-xs text-[#78716c]">Autor</p>
                                    <p className="text-sm font-semibold text-[#1c1917]">{bitacora.author}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Content */}
                    <div className={cardClass}>
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <h3 className="text-base font-bold text-[#1c1917]">Contenido</h3>
                            {canEditContent && !editing && (
                                <button
                                    type="button"
                                    onClick={() => setEditing(true)}
                                    className="inline-flex min-h-[36px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-3 py-1.5 text-xs font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4]"
                                >
                                    <Pencil className="h-3.5 w-3.5" />
                                    Editar
                                </button>
                            )}
                        </div>

                        {directorSigned && mode === 'student' && (
                            <p className="mb-3 text-xs text-[#78716c]">
                                El contenido está bloqueado porque el director ya firmó esta bitácora.
                            </p>
                        )}

                        {editing ? (
                            <div className="flex flex-col gap-4">
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
                                <div>
                                    <label htmlFor="bitacora-summary" className="mb-1.5 block text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                        Resumen semanal
                                    </label>
                                    <textarea
                                        id="bitacora-summary"
                                        rows={2}
                                        value={weeklySummary}
                                        onChange={(e) => setWeeklySummary(e.target.value)}
                                        className="w-full rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={handleSaveContent}
                                        className="inline-flex min-h-[36px] items-center gap-2 rounded-lg bg-[#c2410c] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#9a330a]"
                                    >
                                        <Save className="h-3.5 w-3.5" />
                                        Guardar
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setContent(bitacora.content);
                                            setWeeklySummary(bitacora.weeklySummary);
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
                                <div>
                                    <p className="text-xs font-bold uppercase tracking-[0.05em] text-[#57534e]">Resumen semanal</p>
                                    <p className="mt-1 text-sm text-[#57534e]">{weeklySummary}</p>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Signatures table */}
                    <div className={cardClass}>
                        <h3 className="mb-4 text-base font-bold text-[#1c1917]">Tabla de firmas</h3>
                        <DataTable
                            columns={signatureColumns}
                            data={tableData}
                            getRowKey={(row) => row.id}
                        />
                    </div>
                </div>

                {/* TOTP panel */}
                <div className="lg:col-span-2">
                    <div className="sticky top-20 rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-5 flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#e0e7ff]">
                                <ShieldCheck className="h-5 w-5 text-[#4f46e5]" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-[#1c1917]">Firma Digital</h3>
                                <p className="text-xs text-[#57534e]">Verificación TOTP (mock)</p>
                            </div>
                        </div>

                        {currentUserSigned ? (
                            <div className="flex flex-col gap-3">
                                <div className="rounded-lg border border-[#dcfce7] bg-[#dcfce7]/40 p-4 text-sm text-[#14532d]">
                                    Ya has firmado esta bitácora.
                                </div>
                                {mode === 'director' && onRemoveSignature && (
                                    <button
                                        type="button"
                                        onClick={handleRemoveSignature}
                                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#dc2626] bg-white px-4 py-3 text-sm font-semibold text-[#dc2626] transition-colors hover:bg-[#fee2e2]"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        Quitar firma
                                    </button>
                                )}
                            </div>
                        ) : (
                            <>
                                <p className="mb-4 text-xs text-[#57534e]">
                                    Ingrese el código de 6 dígitos para firmar esta bitácora.
                                </p>
                                <TOTPInput
                                    onComplete={handleTOTPComplete}
                                    error={totpError}
                                    disabled={submitting}
                                />
                                <button
                                    type="button"
                                    onClick={handleSign}
                                    disabled={submitting || totpCode.length !== 6}
                                    className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#c2410c] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {submitting ? (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <PenSquare className="h-4 w-4" />
                                    )}
                                    Firmar Bitácora
                                </button>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
