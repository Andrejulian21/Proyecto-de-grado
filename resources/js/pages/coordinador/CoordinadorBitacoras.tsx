import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { BitacoraProjectGrid } from '@/components/bitacoras/BitacoraProjectGrid';
import { getAllProjects } from '@/mocks/bitacorasMock';

export default function CoordinadorBitacoras() {
    const navigate = useNavigate();

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Coordinación"
                title="Bitácoras por proyecto"
                subtitle="Consulte el historial de reuniones de todos los proyectos registrados"
            />
            <BitacoraProjectGrid
                projects={getAllProjects()}
                onSelect={(id) => navigate(`/coordinador/bitacoras/proyectos/${id}`)}
            />
        </div>
    );
}
