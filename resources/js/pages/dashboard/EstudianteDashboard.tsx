import { useState, useEffect } from 'react';
import {
    GraduationCap,
    CloudUpload,
    Lock,
    CheckCircle2,
    Clock,
    ChevronDown,
    ChevronUp,
    AlertCircle,
    User,
    Loader2,
    AlertTriangle,
} from 'lucide-react';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { PageHeader } from '@/components/ui/PageHeader';
import { apiFetch } from '@/lib/utils';

/* ── Types ── */

interface ProyectoResponse {
    data: {
        id: number;
        code: string;
        title: string;
        current_phase: string;
        status: string;
        director: { id: number; name: string; email: string };
        estudiantes: { id: number; name: string; email: string }[];
        semestre: { id: number; name: string };
        entregas: EntregaRaw[];
    };
}

interface EntregaRaw {
    id: number;
    fase: string;
    titulo?: string;
    title?: string;
    descripcion?: string;
    description?: string;
    fecha_limite?: string;
    due_date?: string;
    estado?: string;
    status?: string;
    nota?: number | null;
    consolidated_grade?: number | null;
    total_versiones?: number;
    versiones?: VersionRaw[];
}

interface VersionRaw {
    id: number;
    numero_version?: number;
    estado?: string;
    status?: string;
    subido_en?: string;
    created_at?: string;
    ruta_archivo?: string;
}

interface EntregaData {
    id: number;
    fase: string;
    label: string;
    status: 'approved' | 'pending' | 'locked' | 'enviada';
    deadline: string;
    grade: number | null;
    versions: VersionData[];
}

interface VersionData {
    version: number;
    date: string;
    status: 'approved' | 'pending' | 'rejected';
    fileName: string;
}

interface PhaseStep {
    id: string;
    label: string;
    status: 'done' | 'current' | 'future';
}

/* ── Constants ── */

const PHASES: { id: string; label: string }[] = [
    { id: 'anteproyecto', label: 'Anteproyecto' },
    { id: 'presentacion_anteproyecto', label: 'Presentacion' },
    { id: 'desarrollo', label: 'Desarrollo' },
    { id: 'presentacion_final', label: 'Final' },
];

const FASE_LABELS: Record<string, string> = {
    anteproyecto: 'Documento de Anteproyecto',
    presentacion_anteproyecto: 'Presentacion Anteproyecto',
    desarrollo: 'Informe de Avance',
    presentacion_final: 'Presentacion Final',
};

/* ── Helpers ── */

function buildPhases(current: string): PhaseStep[] {
    const idx = PHASES.findIndex((p) => p.id === current);
    return PHASES.map((p, i) => ({
        ...p,
        status: i < idx ? 'done' : i === idx ? 'current' : 'future',
    })) as PhaseStep[];
}

function mapStatus(status: string | undefined): 'approved' | 'pending' | 'locked' | 'enviada' {
    switch (status) {
        case 'Aprobada':
        case 'aprobada':
            return 'approved';
        case 'Enviada':
        case 'enviada':
            return 'enviada';
        case 'Revisada':
        case 'revisada':
        case 'Pendiente':
        case 'pendiente':
            return 'pending';
        default:
            return 'locked';
    }
}

function mapVersionStatus(s: string | undefined): 'approved' | 'pending' | 'rejected' {
    if (s === 'Aprobada' || s === 'aprobada') return 'approved';
    if (s === 'Rechazada' || s === 'rechazada') return 'rejected';
    return 'pending';
}

function formatDate(date: string | undefined): string {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
}

function extractFileName(path: string | undefined): string {
    if (!path) return 'documento.pdf';
    const parts = path.split('/');
    return parts[parts.length - 1] || 'documento.pdf';
}

/* ── Subcomponents ── */

function DeliveryAccordion({ delivery }: { delivery: EntregaData }) {
    const [expanded, setExpanded] = useState(false);

    const statusIcon = {
        approved: <CheckCircle2 className="h-5 w-5 text-[#16a34a]" />,
        pending: <Clock className="h-5 w-5 text-[#d97706]" />,
        enviada: <Clock className="h-5 w-5 text-[#0891b2]" />,
        locked: <Lock className="h-5 w-5 text-[#78716c]" />,
    };

    const statusLabel: Record<string, string> = {
        approved: 'Aprobado',
        pending: 'Pendiente',
        enviada: 'Enviada',
        locked: 'Bloqueado',
    };

    const badgeVariant: Record<string, 'success' | 'warning' | 'inactivo' | 'info'> = {
        approved: 'success',
        pending: 'warning',
        enviada: 'info',
        locked: 'inactivo',
    };

    return (
        <div className="rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
            <button
                onClick={() => setExpanded(!expanded)}
                className="flex w-full items-center gap-3 p-4 text-left transition-colors hover:bg-[#f5f5f4]"
                aria-expanded={expanded}
            >
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#f5f5f4]">
                    {statusIcon[delivery.status]}
                </div>
                <div className="flex flex-1 flex-col gap-0.5 min-w-0">
                    <span className="text-sm font-semibold text-[#1c1917]">{delivery.label}</span>
                    <span className="text-xs text-[#57534e]">
                        {delivery.status === 'locked' ? `Disponible: ${delivery.deadline}` : `Limite: ${delivery.deadline}`}
                    </span>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <StatusBadge variant={badgeVariant[delivery.status]}>
                        {statusLabel[delivery.status]}
                    </StatusBadge>
                    {expanded ? <ChevronUp className="h-4 w-4 text-[#57534e]" /> : <ChevronDown className="h-4 w-4 text-[#57534e]" />}
                </div>
            </button>

            {expanded && delivery.versions.length > 0 && (
                <div className="border-t border-[#e5e5e5]">
                    <table className="w-full text-left text-sm tabular-nums">
                        <thead className="bg-[#f5f5f4] text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                            <tr>
                                <th className="px-4 py-2.5">Version</th>
                                <th className="px-4 py-2.5">Fecha</th>
                                <th className="px-4 py-2.5">Archivo</th>
                                <th className="px-4 py-2.5">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {delivery.versions.map((v) => (
                                <tr key={v.version} className="border-b border-[#e5e5e5] last:border-none">
                                    <td className="px-4 py-2.5 font-medium text-[#1c1917]">v{v.version}</td>
                                    <td className="px-4 py-2.5 text-[#57534e]">{v.date}</td>
                                    <td className="px-4 py-2.5 text-[#57534e]">{v.fileName}</td>
                                    <td className="px-4 py-2.5">
                                        <StatusBadge variant={v.status === 'approved' ? 'success' : v.status === 'rejected' ? 'error' : 'warning'}>
                                            {v.status === 'approved' ? 'Aprobado' : v.status === 'rejected' ? 'Rechazado' : 'Pendiente'}
                                        </StatusBadge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {expanded && delivery.versions.length === 0 && (
                <div className="border-t border-[#e5e5e5] px-4 py-6 text-center text-sm text-[#78716c]">
                    {delivery.status === 'pending'
                        ? 'Aun no has subido ninguna version.'
                        : 'Esta entrega no esta disponible aun.'}
                </div>
            )}
        </div>
    );
}

/* ── Main Component ── */

export default function EstudianteDashboard() {
    const [proyecto, setProyecto] = useState<ProyectoResponse['data'] | null>(null);
    const [entregas, setEntregas] = useState<EntregaData[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function fetchData() {
            try {
                const [proyRes, entrRes] = await Promise.all([
                    apiFetch('/api/estudiante/proyecto'),
                    apiFetch('/api/estudiante/entregas'),
                ]);

                if (cancelled) return;

                if (!proyRes.ok) {
                    const body = await proyRes.json().catch(() => ({}));
                    setError(body.error || 'Error al cargar el proyecto.');
                    return;
                }
                if (!entrRes.ok) {
                    const body = await entrRes.json().catch(() => ({}));
                    setError(body.error || 'Error al cargar las entregas.');
                    return;
                }

                const proyData: ProyectoResponse = await proyRes.json();
                const entrData: { data: EntregaRaw[] } = await entrRes.json();

                setProyecto(proyData.data);

                const mapped: EntregaData[] = (entrData.data || []).map((e) => {
                    const versions: VersionData[] = (e.versiones || []).map((v) => ({
                        version: v.numero_version ?? 0,
                        date: formatDate(v.subido_en || v.created_at),
                        status: mapVersionStatus(v.estado || v.status),
                        fileName: extractFileName(v.ruta_archivo),
                    }));

                    const status = mapStatus(e.estado || e.status);

                    return {
                        id: e.id,
                        fase: e.fase,
                        label: FASE_LABELS[e.fase] || e.titulo || e.title || `Entrega #${e.id}`,
                        status,
                        deadline: formatDate(e.fecha_limite || e.due_date),
                        grade: e.nota ?? e.consolidated_grade ?? null,
                        versions,
                    };
                });

                setEntregas(mapped);
            } catch (err) {
                if (!cancelled) setError('Error de conexion. Verifica tu red.');
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        fetchData();
        return () => { cancelled = true; };
    }, []);

    /* ── Loading state ── */

    if (loading) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-20">
                <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
                <p className="text-sm text-[#78716c]">Cargando tu proyecto...</p>
            </div>
        );
    }

    /* ── Error state ── */

    if (error) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-20">
                <AlertTriangle className="h-8 w-8 text-[#dc2626]" />
                <p className="text-sm font-semibold text-[#1c1917]">{error}</p>
                <button
                    onClick={() => { setLoading(true); setError(null); window.location.reload(); }}
                    className="rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white hover:bg-[#9a330a]"
                >
                    Reintentar
                </button>
            </div>
        );
    }

    /* ── Empty project state ── */

    if (!proyecto) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-20">
                <GraduationCap className="h-12 w-12 text-[#d6d3d1]" />
                <p className="text-sm text-[#78716c]">No tienes un proyecto de grado asignado.</p>
            </div>
        );
    }

    /* ── Data loaded ── */

    const phases = buildPhases(proyecto.current_phase);

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Proyecto Activo"
                title="Mi Proyecto de Grado"
                subtitle="Gestiona las entregas y el progreso de tu proyecto de grado"
            />

            {/* ── Hero card ── */}
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-4">
                        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#fed7aa]">
                            <GraduationCap className="h-7 w-7 text-[#c2410c]" />
                        </div>
                        <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="text-xs font-bold uppercase tracking-[0.05em] text-[#c2410c]">
                                    {proyecto.code}
                                </span>
                                <StatusBadge variant="en-curso">En Curso</StatusBadge>
                            </div>
                            <h3 className="text-lg font-bold text-[#1c1917]">{proyecto.title}</h3>
                            <div className="flex items-center gap-4 text-sm text-[#57534e] flex-wrap">
                                <span className="flex items-center gap-1.5">
                                    <User className="h-3.5 w-3.5" />
                                    Director: {proyecto.director.name}
                                </span>
                                {proyecto.estudiantes.length > 0 && (
                                    <span className="flex items-center gap-1.5">
                                        Estudiantes: {proyecto.estudiantes.map((e) => e.name).join(', ')}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* ── Phase stepper ── */}
            <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                <h3 className="mb-4 text-sm font-bold uppercase tracking-[0.05em] text-[#57534e]">
                    Fases del Proyecto
                </h3>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    {phases.map((phase, idx) => (
                        <div key={phase.id} className="flex items-center gap-3 sm:flex-1 sm:flex-col sm:items-center sm:text-center">
                            <div className="flex items-center gap-3 sm:flex-col sm:items-center sm:gap-1">
                                <div
                                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold transition-colors ${
                                        phase.status === 'done'
                                            ? 'bg-[#dcfce7] text-[#16a34a]'
                                            : phase.status === 'current'
                                              ? 'bg-[#fed7aa] text-[#c2410c]'
                                              : 'bg-[#e7e5e4] text-[#78716c]'
                                    }`}
                                >
                                    {phase.status === 'done' ? <CheckCircle2 className="h-5 w-5" /> : <span>{idx + 1}</span>}
                                </div>
                                <span className={`text-sm font-semibold ${phase.status === 'current' ? 'text-[#c2410c]' : 'text-[#57534e]'}`}>
                                    {phase.label}
                                </span>
                            </div>
                            {idx < phases.length - 1 && <div className="hidden h-px flex-1 bg-[#e5e5e5] sm:block" />}
                        </div>
                    ))}
                </div>
            </div>

            {/* ── Upload zone + Delivery accordions ── */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                <div className="lg:col-span-2">
                    <div className="flex h-full flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-[#d6d3d1] bg-white p-8 text-center transition-colors hover:border-[#c2410c] hover:bg-[#fff7ed]">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#f5f5f4]">
                            <CloudUpload className="h-6 w-6 text-[#c2410c]" />
                        </div>
                        <div className="flex flex-col gap-1">
                            <span className="text-sm font-semibold text-[#1c1917]">Subir nueva entrega</span>
                            <span className="text-xs text-[#78716c]">Arrastra tu archivo o haz clic para seleccionar</span>
                            <span className="text-[10px] text-[#78716c]">PDF, DOCX, ZIP — Max. 20 MB</span>
                        </div>
                        <button
                            type="button"
                            className="inline-flex items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a]"
                        >
                            <CloudUpload className="h-4 w-4" />
                            Seleccionar archivo
                        </button>
                    </div>
                </div>

                <div className="flex flex-col gap-3 lg:col-span-3">
                    <h3 className="text-sm font-bold uppercase tracking-[0.05em] text-[#57534e]">
                        Entregas ({entregas.length})
                    </h3>
                    {entregas.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-[#e5e5e5] bg-white py-12 text-sm text-[#78716c]">
                            <FileText className="h-8 w-8 text-[#d6d3d1]" />
                            No hay entregas registradas para este proyecto.
                        </div>
                    ) : (
                        entregas.map((del) => <DeliveryAccordion key={del.id} delivery={del} />)
                    )}
                </div>
            </div>
        </div>
    );
}
