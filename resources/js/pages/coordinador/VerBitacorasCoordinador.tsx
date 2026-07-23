import { Navigate, useParams } from 'react-router-dom';

/** @deprecated Use /coordinador/bitacoras/proyectos/:proyectoId */
export default function VerBitacorasCoordinador() {
    const { proyectoId } = useParams<{ proyectoId: string }>();
    return <Navigate to={`/coordinador/bitacoras/proyectos/${proyectoId}`} replace />;
}
