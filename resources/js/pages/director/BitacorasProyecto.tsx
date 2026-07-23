import { BitacorasProyectoList } from '@/pages/shared/BitacorasProyectoList';

export default function BitacorasProyectoDirector() {
    return (
        <BitacorasProyectoList
            role="director"
            backPath="/bitacoras/proyectos"
            revisionPath={(id) => `/bitacoras/${id}/revision`}
        />
    );
}
