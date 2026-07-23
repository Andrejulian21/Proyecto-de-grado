import { BitacorasProyectoList } from '@/pages/shared/BitacorasProyectoList';

export default function CoordinadorBitacorasProyecto() {
    return (
        <BitacorasProyectoList
            role="coordinador"
            backPath="/coordinador/bitacoras"
            revisionPath={(id) => `/coordinador/bitacoras/${id}/revision`}
        />
    );
}
