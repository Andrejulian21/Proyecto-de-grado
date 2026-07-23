import { useNavigate, useParams } from 'react-router-dom';
import { RevisionBitacoraView } from '@/components/bitacoras/RevisionBitacoraView';
import { getMeetingById } from '@/mocks/bitacorasMock';

export default function RevisionBitacoraCoordinador() {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();
    const meeting = id ? getMeetingById(Number(id)) : undefined;

    if (!meeting) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-16">
                <p className="text-sm text-[#57534e]">Bitácora no encontrada.</p>
                <button
                    type="button"
                    onClick={() => navigate('/coordinador/bitacoras')}
                    className="text-sm font-semibold text-[#c2410c] hover:underline"
                >
                    Volver a proyectos
                </button>
            </div>
        );
    }

    return (
        <RevisionBitacoraView
            mode="coordinador"
            meeting={meeting}
            onBack={() => navigate(`/coordinador/bitacoras/proyectos/${meeting.projectId}`)}
        />
    );
}
