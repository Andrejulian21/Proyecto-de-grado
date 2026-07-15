import { useNavigate, useParams } from 'react-router-dom';
import { RevisionBitacoraView } from '@/components/bitacoras/RevisionBitacoraView';
import { getBitacoraDetail } from '@/lib/mock/project-data';

export default function RevisionBitacoraDirector() {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();
    const bitacora = getBitacoraDetail(Number(id));

    if (!bitacora) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-16">
                <p className="text-sm text-[#57534e]">Bitácora no encontrada.</p>
                <button
                    type="button"
                    onClick={() => navigate('/bitacoras')}
                    className="text-sm font-semibold text-[#c2410c] hover:underline"
                >
                    Volver a bitácoras
                </button>
            </div>
        );
    }

    return (
        <RevisionBitacoraView
            mode="director"
            bitacora={bitacora}
            onBack={() => navigate(`/bitacoras/${bitacora.projectId}/firmar`)}
            onSign={async () => {
                await new Promise((r) => setTimeout(r, 800));
            }}
            onRemoveSignature={() => {
                // Mock: firma removida localmente
            }}
        />
    );
}
