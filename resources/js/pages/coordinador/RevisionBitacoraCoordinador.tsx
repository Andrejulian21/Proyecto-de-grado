import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Loader2 } from 'lucide-react';
import { RevisionBitacoraView, type BitacoraDetail } from '@/components/bitacoras/RevisionBitacoraView';
import { apiFetch } from '@/lib/utils';

export default function RevisionBitacoraCoordinador() {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();
    const [bitacora, setBitacora] = useState<BitacoraDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            if (!id) return;
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch(`/api/bitacoras/${id}`);
                if (!res.ok) {
                    throw new Error(
                        res.status === 404 ? 'Bitácora no encontrada.' : 'Error al cargar la bitácora.',
                    );
                }
                const json = await res.json();
                const b = json.data;

                // Fetch project details (code, student names)
                const projRes = await apiFetch(`/api/admin/proyectos/${b.proyecto_id}`);
                if (!projRes.ok) {
                    throw new Error('Error al cargar los datos del proyecto.');
                }
                const projJson = await projRes.json();
                const project = projJson.data;

                const estudianteName =
                    project.estudiantes?.map((e: { name: string }) => e.name).join(', ') ?? '';
                const directorName = project.director?.name ?? 'Director';

                const mapped: BitacoraDetail = {
                    id: b.id,
                    content: b.notes ?? '',
                    topic: b.topic ?? '',
                    projectCode: project.code ?? '',
                    date: b.meeting_date ?? '',
                    createdAt: b.created_at ?? '',
                    status: b.signature_status ?? 'Pendiente',
                    author: estudianteName,
                    projectId: b.proyecto_id,
                    signatures: [
                        {
                            role: 'director',
                            name: directorName,
                            signed: !!b.director_signed_at,
                            signedAt: b.director_signed_at
                                ? new Date(b.director_signed_at).toLocaleString('es-CO')
                                : null,
                        },
                    ],
                };

                if (!cancelled) {
                    setBitacora(mapped);
                    setLoading(false);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err instanceof Error ? err.message : 'Error desconocido');
                    setLoading(false);
                }
            }
        }

        load();
        return () => {
            cancelled = true;
        };
    }, [id]);

    if (loading) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-16">
                <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
                <p className="text-sm text-[#57534e]">Cargando bitácora...</p>
            </div>
        );
    }

    if (error || !bitacora) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-16">
                <p className="text-sm text-[#57534e]">{error ?? 'Bitácora no encontrada.'}</p>
                <button
                    type="button"
                    onClick={() => navigate('/directores')}
                    className="text-sm font-semibold text-[#c2410c] hover:underline"
                >
                    Volver a directores
                </button>
            </div>
        );
    }

    return (
        <RevisionBitacoraView
            mode="director"
            bitacora={bitacora}
            onBack={() => navigate(-1)}
            onSign={async () => {
                // El coordinador no puede firmar — mostrar mensaje
                throw new Error('Solo el director del proyecto puede firmar bitácoras.');
            }}
            onRemoveSignature={() => {
                // No-op: coordinador no puede quitar firmas
            }}
            disableSigning={true}
        />
    );
}
