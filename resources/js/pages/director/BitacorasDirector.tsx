import { Navigate } from 'react-router-dom';

/** @deprecated Use /bitacoras/proyectos hub */
export default function BitacorasDirector() {
    return <Navigate to="/bitacoras/proyectos" replace />;
}
