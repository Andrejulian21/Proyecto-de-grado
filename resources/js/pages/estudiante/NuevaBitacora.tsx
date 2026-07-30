import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { ArrowLeft, Save, Loader2 } from 'lucide-react';
import { apiFetch } from '@/lib/utils';
import { SignatureCodeDisplay } from '@/components/bitacoras/SignatureCode';

const MAX_SEMANAS = 32;

interface BitacoraListItem {
    id: number;
    semana?: number | null;
}

export default function NuevaBitacora() {
    const navigate = useNavigate();

    const [date, setDate] = useState(new Date().toISOString().split('T')[0]);
    const [time, setTime] = useState('12:00');
    const [topic, setTopic] = useState('');
    const [description, setDescription] = useState('');
    const [duration, setDuration] = useState('1');
    const [semana, setSemana] = useState('1');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [createdBitacora, setCreatedBitacora] = useState<{
        id: number;
        code: string;
        expiresAt: string;
    } | null>(null);
    const [usedSemanas, setUsedSemanas] = useState<Set<number>>(new Set());
    const [loadingSemanas, setLoadingSemanas] = useState(false);

    // PR 4 — RF-WK-05: load the project's existing bitacoras so we can
    // hide weeks that are already taken. The endpoint requires
    // proyecto_id, so we resolve the student's project first.
    useEffect(() => {
        let cancelled = false;

        async function loadUsedSemanas() {
            setLoadingSemanas(true);
            try {
                const proyRes = await apiFetch('/api/estudiante/proyecto');
                if (!proyRes.ok) {
                    return;
                }
                const proyData = await proyRes.json();
                const proyectoId = proyData.data?.id;
                if (!proyectoId) {
                    return;
                }

                const res = await apiFetch(`/api/bitacoras?proyecto_id=${proyectoId}`);
                if (!res.ok) {
                    return;
                }
                const json = await res.json();
                const items: BitacoraListItem[] = Array.isArray(json.data) ? json.data : [];
                if (cancelled) {
                    return;
                }
                const taken = new Set<number>();
                for (const item of items) {
                    if (typeof item.semana === 'number') {
                        taken.add(item.semana);
                    }
                }
                setUsedSemanas(taken);
            } catch {
                // Soft-fail: the form is still usable without the suggestion
                // list. The unique constraint on the server is the source of
                // truth either way.
            } finally {
                if (!cancelled) {
                    setLoadingSemanas(false);
                }
            }
        }

        loadUsedSemanas();
        return () => {
            cancelled = true;
        };
    }, []);

    const availableSemanas = useMemo(
        () =>
            Array.from({ length: MAX_SEMANAS }, (_, i) => i + 1).filter(
                (n) => !usedSemanas.has(n),
            ),
        [usedSemanas],
    );

    const sortedUsedSemanas = useMemo(
        () => Array.from(usedSemanas).sort((a, b) => a - b),
        [usedSemanas],
    );

    // If the student's current selection collides with a freshly loaded
    // occupied week, jump them to the first available slot.
    useEffect(() => {
        const current = parseInt(semana, 10);
        if (Number.isInteger(current) && usedSemanas.has(current) && availableSemanas.length > 0) {
            setSemana(String(availableSemanas[0]));
        }
    }, [availableSemanas, semana, usedSemanas]);

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setError('');

        if (!date || !topic.trim() || !description.trim()) return;

        const semanaInt = parseInt(semana, 10);
        if (usedSemanas.has(semanaInt)) {
            setError(`La semana ${semanaInt} ya tiene una bitácora asociada.`);
            return;
        }

        setSubmitting(true);
        try {
            // Get the student's project first
            const proyRes = await apiFetch('/api/estudiante/proyecto');
            if (!proyRes.ok) {
                setError('No se pudo obtener tu proyecto. Verifica tu sesion.');
                return;
            }
            const proyData = await proyRes.json();
            const proyectoId = proyData.data?.id;
            if (!proyectoId) {
                setError('No tienes un proyecto asignado.');
                return;
            }

            // Create the bitacora
            const res = await apiFetch('/api/bitacoras', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    proyecto_id: proyectoId,
                    topic: topic.trim(),
                    notes: description.trim(),
                    meeting_date: `${date}T${time}:00`,
                    duration_hours: parseFloat(duration),
                    semana: semanaInt,
                }),
            });

            if (res.status === 201 || res.ok) {
                const body = await res.json();
                const data = body.data ?? body;
                if (data.signature_code_plain) {
                    setCreatedBitacora({
                        id: data.id,
                        code: data.signature_code_plain,
                        expiresAt: data.signature_code_expires_at,
                    });
                } else {
                    navigate('/bitacora');
                }
            } else {
                const body = await res.json().catch(() => ({}));
                // Laravel validation errors come back as { errors: { field: [msg] } };
                // surface the first one so the user sees what went wrong.
                const firstValidationError = body.errors
                    ? Object.values(body.errors).flat().find((m) => typeof m === 'string')
                    : null;
                setError(
                    body.error ||
                        body.message ||
                        firstValidationError ||
                        'Error al crear la bitacora.',
                );
            }
        } catch {
            setError('Error de conexion. Intente de nuevo.');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Bitacora"
                title="Nueva Bitacora"
                subtitle="Registra una nueva sesion de trabajo de tu proyecto de grado"
                actions={
                    <button
                        onClick={() => navigate('/bitacora')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver
                    </button>
                }
            />

            <form onSubmit={handleSubmit}>
                <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <div className="mb-5 flex items-center gap-2">
                        <svg viewBox="0 0 24 24" className="h-5 w-5 text-[#c2410c]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                        </svg>
                        <h2 className="text-lg font-bold text-[#1c1917]">Detalles de la sesion</h2>
                    </div>

                    {/* Error banner */}
                    {error && (
                        <div className="mb-4 rounded-lg border border-[#fee2e2] bg-[#fee2e2] px-4 py-3 text-sm text-[#7f1d1d]">
                            {error}
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="binnacle-date" className="text-sm font-semibold text-[#1c1917]">
                                Fecha <span className="text-[#dc2626]">*</span>
                            </label>
                            <input
                                id="binnacle-date"
                                type="date"
                                value={date}
                                onChange={(e) => setDate(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            />
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="binnacle-time" className="text-sm font-semibold text-[#1c1917]">
                                Hora <span className="text-[#dc2626]">*</span>
                            </label>
                            <input
                                id="binnacle-time"
                                type="time"
                                value={time}
                                onChange={(e) => setTime(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            />
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="binnacle-duration" className="text-sm font-semibold text-[#1c1917]">
                                Duracion (horas) <span className="text-[#dc2626]">*</span>
                            </label>
                            <input
                                id="binnacle-duration"
                                type="number"
                                min="0.5"
                                max="8"
                                step="0.5"
                                value={duration}
                                onChange={(e) => setDuration(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] tabular-nums"
                                required
                            />
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="binnacle-semana" className="text-sm font-semibold text-[#1c1917]">
                                Semana <span className="text-[#dc2626]">*</span>
                            </label>
                            <input
                                id="binnacle-semana"
                                type="number"
                                min={1}
                                max={MAX_SEMANAS}
                                step={1}
                                inputMode="numeric"
                                list="semanas-disponibles"
                                value={semana}
                                onChange={(e) => {
                                    const next = e.target.value;
                                    if (next === '') {
                                        setSemana('');
                                        return;
                                    }
                                    const n = parseInt(next, 10);
                                    if (Number.isInteger(n) && n >= 1 && n <= MAX_SEMANAS) {
                                        setSemana(String(n));
                                    }
                                }}
                                onBlur={() => {
                                    const n = parseInt(semana, 10);
                                    if (!Number.isInteger(n) || n < 1) {
                                        setSemana('1');
                                    } else if (n > MAX_SEMANAS) {
                                        setSemana(String(MAX_SEMANAS));
                                    }
                                }}
                                placeholder="1"
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] tabular-nums"
                                required
                            />
                            <datalist id="semanas-disponibles">
                                {availableSemanas.map((n) => (
                                    <option key={n} value={n} />
                                ))}
                            </datalist>
                            {loadingSemanas ? (
                                <span className="text-xs text-[#78716c]">Cargando semanas disponibles...</span>
                            ) : sortedUsedSemanas.length > 0 ? (
                                <span className="text-xs text-[#78716c]">
                                    Semanas ocupadas:{' '}
                                    <span className="font-semibold text-[#57534e] tabular-nums">
                                        {sortedUsedSemanas.join(', ')}
                                    </span>
                                </span>
                            ) : (
                                <span className="text-xs text-[#78716c]">Aún no hay bitácoras registradas.</span>
                            )}
                        </div>

                        <div className="flex flex-col gap-1.5 sm:col-span-2">
                            <label htmlFor="binnacle-topic" className="text-sm font-semibold text-[#1c1917]">
                                Tema de la sesion <span className="text-[#dc2626]">*</span>
                            </label>
                            <input
                                id="binnacle-topic"
                                type="text"
                                value={topic}
                                onChange={(e) => setTopic(e.target.value)}
                                placeholder="Ej: Revision de requisitos funcionales"
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            />
                        </div>

                        <div className="flex flex-col gap-1.5 sm:col-span-2">
                            <label htmlFor="binnacle-desc" className="text-sm font-semibold text-[#1c1917]">
                                Contenido <span className="text-[#dc2626]">*</span>
                            </label>
                            <textarea
                                id="binnacle-desc"
                                rows={5}
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                                placeholder="Describa las actividades realizadas durante la sesion, acuerdos, decisiones tomadas, etc."
                                className="w-full min-h-[100px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa] resize-y"
                                required
                            />
                            <span className="text-xs text-[#78716c] text-right tabular-nums">
                                {description.length} caracteres
                            </span>
                        </div>
                    </div>

                    <div className="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => navigate('/bitacora')}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={submitting}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {submitting ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Save className="h-4 w-4" />
                            )}
                            Guardar Bitacora
                        </button>
                    </div>
                </div>
            </form>

            {createdBitacora && (
                <SignatureCodeDisplay
                    bitacoraId={createdBitacora.id}
                    code={createdBitacora.code}
                    expiresAt={createdBitacora.expiresAt}
                    onClose={() => {
                        setCreatedBitacora(null);
                        navigate('/bitacora');
                    }}
                />
            )}
        </div>
    );
}
