import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from '@/hooks/useAuth';
import { AppShell } from '@/components/layout/AppShell';
import LoginInstitucional from '@/pages/auth/LoginInstitucional';
import LoginExterno from '@/pages/auth/LoginExterno';
import GestionUsuarios from '@/pages/coordinador/GestionUsuarios';
import AuditLog from '@/pages/coordinador/AuditLog';
import DashboardRouter from '@/pages/DashboardRouter';
import EstudianteDashboard from '@/pages/dashboard/EstudianteDashboard';
import DirectorDashboard from '@/pages/dashboard/DirectorDashboard';
import CoordinadorDashboard from '@/pages/dashboard/CoordinadorDashboard';
import EvaluadorDashboard from '@/pages/dashboard/EvaluadorDashboard';
import { Loader2 } from 'lucide-react';

function ProtectedRoute({ children, allowedRoles }: {
    children: React.ReactNode;
    allowedRoles?: string[];
}) {
    const { isAuthenticated, isLoading, role } = useAuth();

    if (isLoading) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-surface-alt">
                <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
        );
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" replace />;
    }

    if (allowedRoles && role && !allowedRoles.includes(role)) {
        return <Navigate to="/" replace />;
    }

    return <>{children}</>;
}

function App() {
    return (
        <Routes>
            <Route path="/login" element={<LoginInstitucional />} />
            <Route path="/login/externo" element={<LoginExterno />} />

            <Route
                path="/*"
                element={
                    <ProtectedRoute>
                        <AppShell>
                            <Routes>
                                <Route path="/" element={<DashboardRouter />} />
                                <Route path="/dashboard/estudiante" element={<EstudianteDashboard />} />
                                <Route path="/dashboard/director" element={<DirectorDashboard />} />
                                <Route path="/dashboard/coordinador" element={<CoordinadorDashboard />} />
                                <Route path="/dashboard/evaluador-externo" element={<EvaluadorDashboard />} />
                                <Route path="/coordinador/usuarios" element={<ProtectedRoute allowedRoles={['Coordinador']}><GestionUsuarios /></ProtectedRoute>} />
                                <Route path="/coordinador/audit-log" element={<ProtectedRoute allowedRoles={['Coordinador']}><AuditLog /></ProtectedRoute>} />
                                <Route path="*" element={<Navigate to="/" replace />} />
                            </Routes>
                        </AppShell>
                    </ProtectedRoute>
                }
            />
        </Routes>
    );
}

const root = document.getElementById('app');

if (root) {
    createRoot(root).render(
        <BrowserRouter>
            <AuthProvider>
                <App />
            </AuthProvider>
        </BrowserRouter>,
    );
}

export default App;
