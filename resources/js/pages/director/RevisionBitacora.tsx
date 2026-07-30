import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Loader2, X } from 'lucide-react';
import { RevisionBitacoraView, type BitacoraDetail } from '@/components/bitacoras/RevisionBitacoraView';
import { SignatureCodeInput } from '@/components/bitacoras/SignatureCode';
import { apiFetch } from '@/lib/utils';
import { useAuth } from '@/hooks/useAuth';

export default function RevisionBitacoraDirector() {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();
    const { user } = useAuth();
    const [bitacora, setBitacora] = useState<BitacoraDetail | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [showSignModal, setShowSignModal] = useState(false);

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
                const projRes = await apiFetch(`/api/director/proyectos/${b.proyecto_id}`);
                if (!projRes.ok) {
                    throw new Error('Error al cargar los datos del proyecto.');
                }
                const projJson = await projRes.json();
                const project = projJson.data;

                const estudianteName =
                    project.estudiantes?.map((e: { name: string }) => e.name).join(', ') ?? '';
                const directorName = user?.name ?? 'Director';

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
    }, [id, user?.name]);

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
                    onClick={() => navigate('/supervision')}
                    className="text-sm font-semibold text-[#c2410c] hover:underline"
                >
                    Volver a supervisión
                </button>
            </div>
        );
    }

    return (
        <>
            <RevisionBitacoraView
                mode="director"
                bitacora={bitacora}
                onBack={() => navigate(`/supervision/${bitacora.projectId}/bitacoras`)}
                onSign={async () => {
                    setShowSignModal(true);
                    // Return a promise that never resolves to prevent
                    // RevisionBitacoraView from showing success state —
                    // SignatureCodeInput handles that.
                    return new Promise<never>(() => {});
                }}
                onRemoveSignature={() => {
                    // Sin endpoint para quitar firma aún — solo actualización local
                }}
            />

            {showSignModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                    onClick={() => setShowSignModal(false)}>
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-[0_20px_60px_rgba(28,25,23,0.15)]"
                        onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-bold text-[#1c1917]">Firmar Bitácora</h2>
                            <button onClick={() => setShowSignModal(false)}
                                className="rounded-lg p-1.5 text-[#57534e] hover:bg-[#f5f5f4]">
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <p className="mb-4 text-sm text-[#57534e]">
                            Ingrese el código de 6 dígitos que el estudiante le compartió:
                        </p>
                        <SignatureCodeInput
                            bitacoraId={Number(id!)}
                            onSuccess={() => {
                                setShowSignModal(false);
                                // Refetch to update the view
                                apiFetch(`/api/bitacoras/${id}`).then(async (res) => {
                                    if (!res.ok) return;
                                    const json = await res.json();
                                    const b = json.data;
                                    setBitacora((prev) => {
                                        if (!prev) return prev;
                                        return {
                                            ...prev,
                                            status: b.signature_status ?? 'Completada',
                                            signatures: prev.signatures.map((s) =>
                                                s.role === 'director'
                                                    ? { ...s, signed: true, signedAt: new Date().toLocaleString('es-CO') }
                                                    : s,
                                            ),
                                        };
                                    });
                                });
                            }}
                        />
                    </div>
                </div>
            )}
        </>
    );
}
