import { useNavigate } from 'react-router-dom';
import { PageHeader } from '@/components/ui/PageHeader';
import { BitacoraProjectGrid } from '@/components/bitacoras/BitacoraProjectGrid';
import { getDirectorProjects } from '@/mocks/bitacorasMock';

export default function SeleccionProyectosBitacoras() {
    const navigate = useNavigate();
    const projects = getDirectorProjects();

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Director"
                title="Bitácoras por proyecto"
                subtitle="Seleccione un proyecto para revisar el historial de reuniones"
            />
            <BitacoraProjectGrid
                projects={projects}
                onSelect={(id) => navigate(`/bitacoras/proyectos/${id}`)}
                emptyMessage="No supervisa proyectos con bitácoras registradas."
            />
        </div>
    );
}
