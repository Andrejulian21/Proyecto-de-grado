import { useNavigate, useParams } from 'react-router-dom';
import { RevisionBitacoraView } from '@/components/bitacoras/RevisionBitacoraView';
import { getBitacoraDetail } from '@/lib/mock/project-data';

export default function RevisionBitacoraEstudiante() {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();
    const bitacora = getBitacoraDetail(Number(id));

    if (!bitacora) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-16">
                <p className="text-sm text-[#57534e]">Bitácora no encontrada.</p>
                <button
                    type="button"
                    onClick={() => navigate('/bitacora')}
                    className="text-sm font-semibold text-[#c2410c] hover:underline"
                >
                    Volver a bitácoras
                </button>
            </div>
        );
    }

    return (
        <RevisionBitacoraView
            mode="student"
            bitacora={bitacora}
            onBack={() => navigate('/bitacora')}
            onSign={async () => {
                await new Promise((r) => setTimeout(r, 800));
            }}
            onSaveContent={() => {
                // Mock: contenido guardado localmente
            }}
        />
    );
}
