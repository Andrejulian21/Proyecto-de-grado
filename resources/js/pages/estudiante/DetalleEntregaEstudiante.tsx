import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ArrowLeft, Download, CheckCircle2, XCircle, Clock, FileText, Loader2 } from 'lucide-react';
import { apiFetch } from '@/lib/utils';

function toDate(d: string | undefined) {
    return d ? new Date(d).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
}

export default function DetalleEntregaEstudiante() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [versiones, setVersiones] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!id) return;
        let cancel = false;
        (async () => {
            try {
                const res = await apiFetch(`/api/entregas/${id}/versiones`);
                if (cancel) return;
                if (!res.ok) { setError('Error al cargar las versiones.'); setLoading(false); return; }
                const data = await res.json();
                setVersiones(data.data || []);
            } catch { if (!cancel) setError('Error de conexion.'); }
            finally { if (!cancel) setLoading(false); }
        })();
        return () => { cancel = true; };
    }, [id]);

    if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" /></div>;
    if (error) return <div className="flex flex-col items-center gap-4 py-20"><p className="text-sm text-[#dc2626]">{error}</p></div>;

    const ultimaVersion = versiones[versiones.length - 1];
    const fechaEntrega = ultimaVersion ? toDate(ultimaVersion.uploaded_at || ultimaVersion.created_at) : 'No entregado';
    const estado = ultimaVersion?.estado || 'pendiente';
    const badgeVar = estado === 'aprobado' ? 'success' : estado === 'rechazado' ? 'error' : 'warning';

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Entrega"
                title={`Entrega #${id}`}
                subtitle={`Ultima version: ${fechaEntrega}`}
                actions={
                    <button onClick={() => navigate('/dashboard/estudiante')}
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-[#e5e5e5] bg-transparent px-4 py-2 text-sm font-semibold text-[#1c1917] transition-colors hover:border-[#c2410c] hover:bg-[#fed7aa] hover:text-[#c2410c] active:scale-[0.98]">
                        <ArrowLeft className="h-4 w-4" /> Volver
                    </button>
                }
            />
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                <div className="lg:col-span-3">
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="mb-4 flex items-center justify-between gap-3 flex-wrap">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-[#c2410c]" />
                                <h3 className="text-base font-bold text-[#1c1917]">Versiones Subidas</h3>
                            </div>
                        </div>
                        {versiones.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 py-12 text-center text-sm text-[#78716c]">
                                <FileText className="h-10 w-10 text-[#d6d3d1]" />
                                <p>No has subido versiones para esta entrega.</p>
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-lg border border-[#e5e5e5]">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-[#f5f5f4] text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                                        <tr><th className="px-4 py-2.5">Version</th><th className="px-4 py-2.5">Fecha</th><th className="px-4 py-2.5">Archivo</th><th className="px-4 py-2.5">Tamanio</th><th className="px-4 py-2.5"></th></tr>
                                    </thead>
                                    <tbody>
                                        {versiones.map((v: any) => (
                                            <tr key={v.id || v.version_number} className="border-b border-[#e5e5e5] last:border-none">
                                                <td className="px-4 py-2.5 font-medium text-[#1c1917]">v{v.version_number}</td>
                                                <td className="px-4 py-2.5 text-[#57534e]">{toDate(v.uploaded_at || v.created_at)}</td>
                                                <td className="px-4 py-2.5 text-[#57534e]">{v.original_name || 'documento.pdf'}</td>
                                                <td className="px-4 py-2.5 text-[#57534e]">{v.file_size ? `${(v.file_size / 1024).toFixed(0)} KB` : '—'}</td>
                                                <td className="px-4 py-2.5">
                                                    <button className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] hover:bg-[#f5f5f4] hover:text-[#c2410c]" title="Descargar"><Download className="h-4 w-4" /></button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
                <div className="lg:col-span-2 flex flex-col gap-4">
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <div className="flex items-center gap-3">
                            <div className={`flex h-10 w-10 items-center justify-center rounded-xl ${estado === 'aprobado' ? 'bg-[#dcfce7] text-[#16a34a]' : estado === 'rechazado' ? 'bg-[#fee2e2] text-[#dc2626]' : 'bg-[#fef3c7] text-[#d97706]'}`}>
                                {estado === 'aprobado' ? <CheckCircle2 className="h-5 w-5" /> : estado === 'rechazado' ? <XCircle className="h-5 w-5" /> : <Clock className="h-5 w-5" />}
                            </div>
                            <div><p className="text-sm text-[#57534e]">Estado</p><StatusBadge variant={badgeVar}>{estado === 'aprobado' ? 'Aprobado' : estado === 'rechazado' ? 'Rechazado' : 'Pendiente'}</StatusBadge></div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                        <p className="mb-3 text-sm font-semibold text-[#57534e]">Versiones ({versiones.length}/4)</p>
                        <div className="flex gap-1.5">
                            {[1, 2, 3, 4].map((n) => (
                                <div key={n} className={`h-2 flex-1 rounded-full ${n <= versiones.length ? 'bg-[#16a34a]' : 'bg-[#e7e5e4]'}`} />
                            ))}
                        </div>
                        <p className="mt-2 text-xs text-[#78716c]">{versiones.length >= 4 ? 'Maximo alcanzado.' : `Restan ${4 - versiones.length} subidas.`}</p>
                    </div>
                    {ultimaVersion?.director_notes && (
                        <div className="rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]">
                            <p className="mb-2 text-sm font-semibold text-[#57534e]">Comentario del director</p>
                            <p className="text-sm text-[#1c1917]">{ultimaVersion.director_notes}</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
