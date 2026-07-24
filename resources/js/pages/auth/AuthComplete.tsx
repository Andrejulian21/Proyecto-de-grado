import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Loader2 } from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';

function dashboardPathForRole(role: string): string {
    switch (role) {
        case 'Estudiante':
            return '/dashboard/estudiante';
        case 'Director':
            return '/dashboard/director';
        case 'Coordinador':
            return '/dashboard/coordinador';
        case 'EvaluadorExterno':
            return '/dashboard/evaluador-externo';
        default:
            return '/login';
    }
}

/**
 * Public landing after Google OAuth callback.
 * Waits for AuthProvider sessionCheck, then routes to the role dashboard.
 * Avoids bouncing through ProtectedRoute before the session is confirmed.
 */
export default function AuthComplete() {
    const navigate = useNavigate();
    const { isAuthenticated, role, isLoading } = useAuth();

    useEffect(() => {
        if (isLoading) return;

        if (isAuthenticated && role) {
            navigate(dashboardPathForRole(role), { replace: true });
            return;
        }

        navigate('/login?error=session', { replace: true });
    }, [isLoading, isAuthenticated, role, navigate]);

    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-3 bg-[#fafaf9]">
            <Loader2 className="h-8 w-8 animate-spin text-[#c2410c]" />
            <p className="text-sm text-[#57534e]">Confirmando sesión...</p>
        </div>
    );
}
