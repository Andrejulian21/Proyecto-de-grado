import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { ArrowLeft, Save, Loader2 } from 'lucide-react';
import { FRONTEND_VALIDATION_MODE, mockDelay } from '@/mocks/validationMode';
import { getEstudianteProyecto } from '@/mocks/estudianteMock';
import { apiFetch } from '@/lib/utils';

export default function NuevaBitacora() {
    const navigate = useNavigate();

    const [date, setDate] = useState(new Date().toISOString().split('T')[0]);
    const [time, setTime] = useState('12:00');
    const [topic, setTopic] = useState('');
    const [description, setDescription] = useState('');
    const [duration, setDuration] = useState('1');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setError('');

        if (!date || !topic.trim() || !description.trim()) return;

        setSubmitting(true);
        try {
            if (FRONTEND_VALIDATION_MODE) {
                await mockDelay(500);
                if (!getEstudianteProyecto()?.id) {
                    setError('No tienes un proyecto asignado.');
                    return;
                }
                navigate('/bitacora');
                return;
            }
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
                }),
            });

            if (res.status === 201 || res.ok) {
                navigate('/bitacora');
            } else {
                const body = await res.json().catch(() => ({}));
                setError(body.error || body.message || 'Error al crear la bitacora.');
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
                                Descripcion detallada <span className="text-[#dc2626]">*</span>
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
        </div>
    );
}
