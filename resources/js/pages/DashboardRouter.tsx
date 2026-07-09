import { Navigate } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import { Loader2 } from 'lucide-react';

export default function DashboardRouter() {
    const { role, isLoading } = useAuth();

    if (isLoading) {
        return (
            <div className="flex min-h-[60vh] items-center justify-center">
                <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
        );
    }

    switch (role) {
        case 'Estudiante':
            return <Navigate to="/dashboard/estudiante" replace />;
        case 'Director':
            return <Navigate to="/dashboard/director" replace />;
        case 'Coordinador':
            return <Navigate to="/dashboard/coordinador" replace />;
        case 'EvaluadorExterno':
            return <Navigate to="/dashboard/evaluador-externo" replace />;
        default:
            return <Navigate to="/login" replace />;
    }
}
