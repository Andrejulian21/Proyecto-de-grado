import { useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { StatCard } from '@/components/ui/StatCard';
import { UserPlus, Users, CalendarDays, Search, Loader2, Trash2, GraduationCap } from 'lucide-react';

interface Asignacion {
    id: number;
    project: string;
    student: string;
    evaluator: string;
    date: string;
    status: 'scheduled' | 'completed' | 'pending';
}

const MOCK_ASIGNACIONES: Asignacion[] = [
    { id: 1, project: 'PG-2026-014', student: 'Carlos Méndez', evaluator: 'Dr. Pedro Castillo', date: '20/05/2026', status: 'scheduled' },
    { id: 2, project: 'PG-2026-015', student: 'María Rincón', evaluator: 'Dra. Sofía Vargas', date: '22/05/2026', status: 'scheduled' },
    { id: 3, project: 'PG-2026-008', student: 'Andrés Torres', evaluator: 'Dr. Pedro Castillo', date: '25/05/2026', status: 'pending' },
    { id: 4, project: 'PG-2026-005', student: 'Diana Rojas', evaluator: 'Dra. Sofía Vargas', date: '15/04/2026', status: 'completed' },
    { id: 5, project: 'PG-2026-012', student: 'Juan Pérez', evaluator: 'Dr. Pedro Castillo', date: '10/04/2026', status: 'completed' },
];

const EVALUADORES = ['Dr. Pedro Castillo', 'Dra. Sofía Vargas', 'Dr. Miguel Ángel Ruiz'];

const statusConfig: Record<string, { label: string; variant: 'success' | 'warning' | 'en-curso' }> = {
    scheduled: { label: 'Agendado', variant: 'en-curso' },
    completed: { label: 'Completado', variant: 'success' },
    pending: { label: 'Pendiente', variant: 'warning' },
};

export default function AsignacionEvaluadores() {
    const [showRegisterForm, setShowRegisterForm] = useState(false);
    const [search, setSearch] = useState('');
    const [asignaciones, setAsignaciones] = useState(MOCK_ASIGNACIONES);

    const [formProject, setFormProject] = useState('');
    const [formEvaluator, setFormEvaluator] = useState('');
    const [formDate, setFormDate] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const filtered = asignaciones.filter((a) =>
        a.project.toLowerCase().includes(search.toLowerCase()) ||
        a.student.toLowerCase().includes(search.toLowerCase()) ||
        a.evaluator.toLowerCase().includes(search.toLowerCase())
    );

    const scheduledCount = asignaciones.filter((a) => a.status === 'scheduled').length;
    const completedCount = asignaciones.filter((a) => a.status === 'completed').length;

    async function handleRegister(e: React.FormEvent) {
        e.preventDefault();
        if (!formProject || !formEvaluator || !formDate) return;
        setSubmitting(true);
        try {
            await new Promise((r) => setTimeout(r, 600));
            const newAsig: Asignacion = {
                id: Date.now(),
                project: formProject,
                student: 'Estudiante asignado',
                evaluator: formEvaluator,
                date: new Date(formDate).toLocaleDateString('es-CO'),
                status: 'pending',
            };
            setAsignaciones((prev) => [newAsig, ...prev]);
            setFormProject('');
            setFormEvaluator('');
            setFormDate('');
            setShowRegisterForm(false);
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Evaluación"
                title="Asignación de Evaluadores"
                subtitle="Registre y gestione la asignación de evaluadores externos a proyectos"
                actions={
                    <button
                        onClick={() => setShowRegisterForm(!showRegisterForm)}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                    >
                        <UserPlus className="h-4 w-4" />
                        Registrar Asignación
                    </button>
                }
            />

            {/* Stats */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard icon={Users} label="Total asignaciones" value={asignaciones.length} />
                <StatCard icon={CalendarDays} label="Agendadas" value={scheduledCount} variant="warning" />
                <StatCard icon={GraduationCap} label="Completadas" value={completedCount} variant="success" />
            </div>

            {/* Register form */}
            {showRegisterForm && (
                <form onSubmit={handleRegister} className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                    <h3 className="mb-4 text-base font-bold text-[#1c1917]">Registrar Asignación</h3>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="asig-project" className="text-sm font-semibold text-[#1c1917]">Proyecto</label>
                            <select
                                id="asig-project"
                                value={formProject}
                                onChange={(e) => setFormProject(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            >
                                <option value="">Seleccione un proyecto</option>
                                <option value="PG-2026-014">PG-2026-014 — Carlos Méndez</option>
                                <option value="PG-2026-015">PG-2026-015 — María Rincón</option>
                                <option value="PG-2026-008">PG-2026-008 — Andrés Torres</option>
                            </select>
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="asig-evaluator" className="text-sm font-semibold text-[#1c1917]">Evaluador</label>
                            <select
                                id="asig-evaluator"
                                value={formEvaluator}
                                onChange={(e) => setFormEvaluator(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            >
                                <option value="">Seleccione evaluador</option>
                                {EVALUADORES.map((ev) => (
                                    <option key={ev} value={ev}>{ev}</option>
                                ))}
                            </select>
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="asig-date" className="text-sm font-semibold text-[#1c1917]">Fecha</label>
                            <input
                                id="asig-date"
                                type="date"
                                value={formDate}
                                onChange={(e) => setFormDate(e.target.value)}
                                className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-sm text-[#1c1917] outline-none transition-colors focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                                required
                            />
                        </div>
                    </div>
                    <div className="mt-4 flex items-center gap-3">
                        <button
                            type="submit"
                            disabled={submitting}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <UserPlus className="h-4 w-4" />}
                            Guardar Asignación
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowRegisterForm(false)}
                            className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:bg-[#f5f5f4] active:scale-[0.98]"
                        >
                            Cancelar
                        </button>
                    </div>
                </form>
            )}

            {/* Search */}
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Buscar asignaciones..."
                    className="w-full min-h-[40px] rounded-lg border border-[#e5e5e5] bg-white pl-9 pr-3 py-2 text-sm text-[#1c1917] outline-none transition-colors placeholder:text-[#78716c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
                />
            </div>

            {/* Table */}
            <div className="w-full overflow-x-auto rounded-lg border border-[#e5e5e5] bg-white">
                <table className="w-full text-left text-sm tabular-nums">
                    <thead className="bg-[#f5f5f4] text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                        <tr>
                            <th className="px-4 py-3">Proyecto</th>
                            <th className="px-4 py-3">Estudiante</th>
                            <th className="px-4 py-3">Evaluador</th>
                            <th className="px-4 py-3">Fecha</th>
                            <th className="px-4 py-3">Estado</th>
                            <th className="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="px-4 py-12 text-center text-sm text-[#57534e]">
                                    No se encontraron asignaciones.
                                </td>
                            </tr>
                        ) : (
                            filtered.map((a) => {
                                const config = statusConfig[a.status];
                                return (
                                    <tr key={a.id} className="border-b border-[#e5e5e5] last:border-none">
                                        <td className="px-4 py-3 font-medium text-[#1c1917]">{a.project}</td>
                                        <td className="px-4 py-3 text-[#57534e]">{a.student}</td>
                                        <td className="px-4 py-3 text-[#57534e]">{a.evaluator}</td>
                                        <td className="px-4 py-3 text-[#78716c]">{a.date}</td>
                                        <td className="px-4 py-3">
                                            <StatusBadge variant={config.variant}>{config.label}</StatusBadge>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <button
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#fee2e2] hover:text-[#dc2626] active:scale-[0.98]"
                                                aria-label="Eliminar asignación"
                                                title="Eliminar"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            {/* Agenda section */}
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="mb-4 flex items-center gap-2">
                    <CalendarDays className="h-5 w-5 text-[#c2410c]" />
                    <h3 className="text-base font-bold text-[#1c1917]">Agenda de Evaluaciones</h3>
                </div>
                <div className="flex flex-col gap-3">
                    {asignaciones
                        .filter((a) => a.status === 'scheduled')
                        .sort((a, b) => a.date.localeCompare(b.date))
                        .map((a) => (
                            <div key={a.id} className="flex items-center gap-4 rounded-lg border border-[#e5e5e5] bg-[#fafaf9] p-3.5">
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#e0e7ff]">
                                    <CalendarDays className="h-5 w-5 text-[#4f46e5]" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold text-[#1c1917]">{a.project} — {a.student}</p>
                                    <p className="text-xs text-[#57534e]">Evaluador: {a.evaluator}</p>
                                </div>
                                <div className="shrink-0 text-right">
                                    <p className="text-sm font-bold text-[#1c1917] tabular-nums">{a.date}</p>
                                </div>
                            </div>
                        ))}
                </div>
            </div>
        </div>
    );
}
