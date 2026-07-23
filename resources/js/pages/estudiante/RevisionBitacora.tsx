import { useNavigate, useParams } from 'react-router-dom';
import { RevisionBitacoraView } from '@/components/bitacoras/RevisionBitacoraView';
import { getMeetingById } from '@/mocks/bitacorasMock';

export default function RevisionBitacoraEstudiante() {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();
    const meeting = id ? getMeetingById(Number(id)) : undefined;

    if (!meeting) {
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
            meeting={meeting}
            onBack={() => navigate('/bitacora')}
        />
    );
}
