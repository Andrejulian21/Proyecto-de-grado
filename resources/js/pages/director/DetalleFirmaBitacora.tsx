import { Navigate, useParams } from 'react-router-dom';

/** @deprecated Firmar integrado en /bitacoras/:id/revision */
export default function DetalleFirmaBitacora() {
    const { id } = useParams<{ id: string }>();
    return <Navigate to={`/bitacoras/${id}/revision`} replace />;
}
