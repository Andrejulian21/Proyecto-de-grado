import { useState, useCallback } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { EmptyState } from '@/components/ui/EmptyState';
import { StatusBadge } from '@/components/ui/StatusBadge';
import {
    useDirectorCartas,
    descargarCarta,
    type ProyectoCartas,
    type EstudianteCartas,
    type TipoCarta,
} from '@/hooks/useDirectorCartas';
import {
    FileText,
    Download,
    ChevronDown,
    ChevronRight,
    Loader2,
    AlertCircle,
    Users,
    Info,
    ShieldCheck,
} from 'lucide-react';

/* ── Tooltip (RF-CA-01: botones deshabilitados con tooltip en español) ── */

function Tooltip({ id, text, children }: { id: string; text: string; children: React.ReactNode }) {
    return (
        <span className="group/tt relative inline-flex">
            {children}
            <span
                role="tooltip"
                id={id}
                className="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 w-56 -translate-x-1/2 rounded-md bg-[#1c1917] px-2.5 py-1.5 text-xs font-medium leading-snug text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover/tt:opacity-100 group-focus-within/tt:opacity-100"
            >
                {text}
                <span aria-hidden="true" className="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-[#1c1917]" />
            </span>
        </span>
    );
}

/* ── Helpers ── */

function tooltipText(proyecto: ProyectoCartas): string {
    if (proyecto.cierre_efectivo === null) {
        return 'No hay entregas en la fase de desarrollo para este semestre';
    }
    return 'Las cartas estarán disponibles cuando cierre la fase de desarrollo';
}

function formatCierre(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-CO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

const NOMBRE_CARTA_1 = 'Aval Sustentacion Publica';
const NOMBRE_CARTA_2 = 'Carta de Aval Entrega a Jurados';

/* ── Página ── */

export default function CartasAval() {
    const { data: proyectos, loading, error, refetch } = useDirectorCartas();

    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [downloadingKey, setDownloadingKey] = useState<string | null>(null);
    const [downloadError, setDownloadError] = useState<string | null>(null);

    const toggleExpand = useCallback((id: number) => {
        setExpandedId((current) => (current === id ? null : id));
    }, []);

    const handleDownload = useCallback(
        async (proyecto: ProyectoCartas, estudiante: EstudianteCartas, tipo: TipoCarta) => {
            const key = `${proyecto.id}-${estudiante.id}-${tipo}`;
            setDownloadingKey(key);
            setDownloadError(null);

            const prefijo = tipo === 'aval' ? NOMBRE_CARTA_1 : NOMBRE_CARTA_2;

            try {
                await descargarCarta(proyecto.id, estudiante.id, tipo, `${prefijo} [${estudiante.name}].docx`);
            } catch (err) {
                setDownloadError(
                    err instanceof Error
                        ? err.message
                        : 'No se pudo descargar la carta. Inténtelo de nuevo.',
                );
            } finally {
                setDownloadingKey(null);
            }
        },
        [],
    );

    if (loading) {
        return (
            <div className="flex min-h-[50vh] items-center justify-center">
                <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex flex-col items-start gap-3">
                <PageHeader
                    eyebrow="Director"
                    title="Cartas de Aval"
                    subtitle="Genera y descarga las cartas de aval de sustentación pública y entrega a jurados."
                />
                <div role="alert" className="flex w-full items-center gap-2 rounded-lg border border-[#fee2e2] bg-[#fef2f2] px-4 py-3 text-sm text-[#7f1d1d]">
                    <AlertCircle className="h-4 w-4 shrink-0" />
                    <span>{error}</span>
                    <button type="button" onClick={refetch} className="ml-auto font-semibold underline underline-offset-2">
                        Reintentar
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Director"
                title="Cartas de Aval"
                subtitle="Selecciona un proyecto para descargar las cartas de cada estudiante. Las cartas se habilitan al cerrar la fase de desarrollo del semestre."
            />

            {downloadError && (
                <div role="alert" className="flex items-center gap-2 rounded-lg border border-[#fee2e2] bg-[#fef2f2] px-4 py-3 text-sm text-[#7f1d1d]">
                    <AlertCircle className="h-4 w-4 shrink-0" />
                    <span>{downloadError}</span>
                </div>
            )}

            {proyectos.length === 0 ? (
                <EmptyState
                    icon={FileText}
                    title="Sin proyectos"
                    description="No tienes proyectos asignados en semestres activos."
                />
            ) : (
                <div className="overflow-x-auto rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_3px_rgba(28,25,23,0.08),0_1px_2px_rgba(28,25,23,0.06)]">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-[#e5e5e5] bg-[#fafaf9] text-[#1c1917]">
                            <tr>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider">
                                    Proyecto
                                </th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider">
                                    Cierre fase desarrollo
                                </th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider">
                                    Estado
                                </th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wider">
                                    Estudiantes
                                </th>
                                <th scope="col" className="whitespace-nowrap px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {proyectos.map((proyecto) => {
                                const isExpanded = expandedId === proyecto.id;
                                return (
                                    <FragmentProyecto
                                        key={proyecto.id}
                                        proyecto={proyecto}
                                        isExpanded={isExpanded}
                                        onToggle={toggleExpand}
                                        downloadingKey={downloadingKey}
                                        onDownload={handleDownload}
                                    />
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

/* ── Fila de proyecto (con fila expandida de estudiantes) ── */

function FragmentProyecto({
    proyecto,
    isExpanded,
    onToggle,
    downloadingKey,
    onDownload,
}: {
    proyecto: ProyectoCartas;
    isExpanded: boolean;
    onToggle: (id: number) => void;
    downloadingKey: string | null;
    onDownload: (proyecto: ProyectoCartas, estudiante: EstudianteCartas, tipo: TipoCarta) => void;
}) {
    const habilitadas = proyecto.cartas_habilitadas;
    const tieneEstudiantes = proyecto.estudiantes.length > 0;
    const tooltipId = `tt-${proyecto.id}`;
    const expandLabel = isExpanded ? 'Contraer estudiantes' : 'Ver estudiantes';

    return (
        <>
            <tr className="group border-b border-[#e5e5e5] transition-colors hover:bg-[#fafaf9] last:border-b-0">
                <td className="px-4 py-3 align-top">
                    <div className="flex flex-col gap-0.5">
                        <span className="text-sm font-semibold text-[#1c1917]">{proyecto.title}</span>
                        <span className="text-xs text-[#78716c]">{proyecto.code}</span>
                    </div>
                </td>
                <td className="whitespace-nowrap px-4 py-3 align-top text-sm text-[#1c1917]">
                    {formatCierre(proyecto.cierre_efectivo)}
                </td>
                <td className="px-4 py-3 align-top">
                    {habilitadas ? (
                        <StatusBadge variant="success">
                            <ShieldCheck className="mr-1 h-3 w-3" /> Habilitado
                        </StatusBadge>
                    ) : (
                        <StatusBadge variant="warning">
                            <Info className="mr-1 h-3 w-3" /> Pendiente
                        </StatusBadge>
                    )}
                </td>
                <td className="px-4 py-3 align-top text-center">
                    <span className="inline-flex items-center gap-1 text-sm text-[#1c1917]">
                        <Users className="h-4 w-4 text-[#78716c]" />
                        {proyecto.estudiantes.length}
                    </span>
                </td>
                <td className="whitespace-nowrap px-4 py-3 align-top text-right">
                    <div className="flex items-center justify-end gap-2">
                        <Tooltip id={tooltipId} text={tooltipText(proyecto)}>
                            <button
                                type="button"
                                disabled={!habilitadas}
                                aria-disabled={!habilitadas}
                                aria-describedby={habilitadas ? undefined : tooltipId}
                                onClick={() => onToggle(proyecto.id)}
                                className="inline-flex items-center gap-1.5 rounded-lg bg-[#1c1917] px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-[#292524] disabled:cursor-not-allowed disabled:bg-[#e7e5e4] disabled:text-[#a8a29e]"
                            >
                                <FileText className="h-3.5 w-3.5" />
                                Generar cartas
                            </button>
                        </Tooltip>
                        <button
                            type="button"
                            onClick={() => onToggle(proyecto.id)}
                            aria-expanded={isExpanded}
                            aria-label={expandLabel}
                            className="rounded p-0.5 text-[#78716c] transition-colors hover:bg-[#fed7aa] hover:text-[#c2410c]"
                        >
                            {isExpanded ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                        </button>
                    </div>
                </td>
            </tr>

            {isExpanded && (
                <tr className="border-b border-[#e5e5e5] bg-[#fafaf9] last:border-b-0">
                    <td colSpan={5} className="px-4 py-4">
                        {!tieneEstudiantes ? (
                            <EmptyState
                                icon={Users}
                                title="Este proyecto no tiene estudiantes asignados"
                                description="Contacta al coordinador para asignar estudiantes al proyecto antes de generar las cartas."
                            />
                        ) : (
                            <ul className="flex flex-col gap-3">
                                {proyecto.estudiantes.map((estudiante) => {
                                    const keyAval = `${proyecto.id}-${estudiante.id}-aval`;
                                    const keyJurados = `${proyecto.id}-${estudiante.id}-jurados`;
                                    return (
                                        <li key={estudiante.id} className="flex flex-col gap-2 rounded-lg border border-[#e5e5e5] bg-white p-3">
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div className="flex flex-col gap-0.5">
                                                    <span className="text-sm font-semibold text-[#1c1917]">{estudiante.name}</span>
                                                    <span className="text-xs text-[#78716c]">
                                                        ID UNAB: {estudiante.codigo_estudiante || '—'}
                                                    </span>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Tooltip id={`${keyAval}-tt`} text={tooltipText(proyecto)}>
                                                        <button
                                                            type="button"
                                                            disabled={!habilitadas || downloadingKey === keyAval}
                                                            aria-disabled={!habilitadas}
                                                            aria-describedby={habilitadas ? undefined : `${keyAval}-tt`}
                                                            onClick={() => onDownload(proyecto, estudiante, 'aval')}
                                                            className="inline-flex items-center gap-1.5 rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-xs font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] disabled:cursor-not-allowed disabled:bg-[#e7e5e4] disabled:text-[#a8a29e]"
                                                        >
                                                            {downloadingKey === keyAval ? (
                                                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                            ) : (
                                                                <Download className="h-3.5 w-3.5" />
                                                            )}
                                                            Carta 1 · Aval Sustentación
                                                        </button>
                                                    </Tooltip>
                                                    <Tooltip id={`${keyJurados}-tt`} text={tooltipText(proyecto)}>
                                                        <button
                                                            type="button"
                                                            disabled={!habilitadas || downloadingKey === keyJurados}
                                                            aria-disabled={!habilitadas}
                                                            aria-describedby={habilitadas ? undefined : `${keyJurados}-tt`}
                                                            onClick={() => onDownload(proyecto, estudiante, 'jurados')}
                                                            className="inline-flex items-center gap-1.5 rounded-lg border border-[#e5e5e5] bg-white px-3 py-2 text-xs font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] disabled:cursor-not-allowed disabled:bg-[#e7e5e4] disabled:text-[#a8a29e]"
                                                        >
                                                            {downloadingKey === keyJurados ? (
                                                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                            ) : (
                                                                <Download className="h-3.5 w-3.5" />
                                                            )}
                                                            Carta 2 · Aval Jurados
                                                        </button>
                                                    </Tooltip>
                                                </div>
                                            </div>

                                            {estudiante.warnings.length > 0 && (
                                                <div className="flex flex-col gap-1.5">
                                                    {estudiante.warnings.map((warning) => (
                                                        <div
                                                            key={warning}
                                                            role="status"
                                                            className="flex items-start gap-2 rounded-md border border-[#fef3c7] bg-[#fffbeb] px-3 py-2 text-xs text-[#78350f]"
                                                        >
                                                            <AlertCircle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                                            <span>{warning}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </td>
                </tr>
            )}
        </>
    );
}
