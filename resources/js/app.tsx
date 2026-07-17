import { lazy, Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate, useParams } from 'react-router-dom';
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
import LandingPage from '@/pages/landing/LandingPage';
import BitacorasEstudiante from '@/pages/estudiante/BitacorasEstudiante';
import { Loader2 } from 'lucide-react';

const AnunciosPublica = lazy(() => import('@/pages/shared/AnunciosPublica'));
const AnuncioDetalle = lazy(() => import('@/pages/shared/AnuncioDetalle'));
const SupervisionReadOnly = lazy(() => import('@/components/supervision/SupervisionReadOnly'));
const Recursos = lazy(() => import('@/pages/shared/Recursos'));
const RecursoDetalle = lazy(() => import('@/pages/shared/RecursoDetalle'));
const NuevaBitacora = lazy(() => import('@/pages/estudiante/NuevaBitacora'));
const DetalleEntregaEstudiante = lazy(() => import('@/pages/estudiante/DetalleEntregaEstudiante'));
const SupervisionProyectoDirector = lazy(() => import('@/pages/director/SupervisionProyectoDirector'));
const SeleccionProyectosBitacoras = lazy(() => import('@/pages/director/SeleccionProyectosBitacoras'));
const BitacorasDirector = lazy(() => import('@/pages/director/BitacorasDirector'));
const DetalleFirmaBitacora = lazy(() => import('@/pages/director/DetalleFirmaBitacora'));
const RevisionEntregaDirector = lazy(() => import('@/pages/director/RevisionEntregaDirector'));
const BitacorasProyecto = lazy(() => import('@/pages/director/BitacorasProyecto'));
const RevisionBitacoraDirector = lazy(() => import('@/pages/director/RevisionBitacora'));
const RevisionBitacoraEstudiante = lazy(() => import('@/pages/estudiante/RevisionBitacora'));
const GestionProyectos = lazy(() => import('@/pages/coordinador/GestionProyectos'));
const AnunciosAdmin = lazy(() => import('@/pages/coordinador/AnunciosAdmin'));
const AsignacionEvaluadores = lazy(() => import('@/pages/coordinador/AsignacionEvaluadores'));
const CoordinadorEntregas = lazy(() => import('@/pages/coordinador/CoordinadorEntregas'));
const CoordinadorBitacoras = lazy(() => import('@/pages/coordinador/CoordinadorBitacoras'));
const GestionAlertas = lazy(() => import('@/pages/coordinador/GestionAlertas'));
const RecursosAdmin = lazy(() => import('@/pages/coordinador/RecursosAdmin'));
const DirectoresPage = lazy(() => import('@/pages/coordinador/DirectoresPage'));
const VerBitacorasCoordinador = lazy(() => import('@/pages/coordinador/VerBitacorasCoordinador'));
const RevisionBitacoraCoordinador = lazy(() => import('@/pages/coordinador/RevisionBitacoraCoordinador'));
const EvaluarProyecto = lazy(() => import('@/pages/evaluador/EvaluarProyecto'));
const EvaluadorCalificar = lazy(() => import('@/pages/evaluador/EvaluadorCalificar'));
const AnalisisAutomaticoEntregas = lazy(() => import('@/pages/estudiante/AnalisisAutomaticoEntregas'));
const AsistenteOrientacion = lazy(() => import('@/pages/estudiante/AsistenteOrientacion'));
const EvaluacionesDirector = lazy(() => import('@/pages/director/EvaluacionesDirector'));
const DetalleEntregaCoordinador = lazy(() => import('@/pages/coordinador/DetalleEntregaCoordinador'));

function SuspenseWrapper({ children }: { children: React.ReactNode }) {
    return (
        <Suspense fallback={<div className="flex items-center justify-center py-16"><Loader2 className="h-6 w-6 animate-spin text-primary" /></div>}>
            {children}
        </Suspense>
    );
}

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

function SupervisionReadOnlyWrapper() {
    const { id } = useParams<{ id: string }>();
    return <SupervisionReadOnly projectId={id ? Number(id) : undefined} />;
}

function App() {
    return (
        <Routes>
            <Route path="/login" element={<LoginInstitucional />} />
            <Route path="/login/externo" element={<LoginExterno />} />
            <Route path="/" element={<LandingPage />} />

            <Route
                path="/*"
                element={
                    <ProtectedRoute>
                        <AppShell>
                            <Routes>
                                <Route path="/" element={<DashboardRouter />} />
                                <Route path="/dashboard/estudiante" element={<ProtectedRoute allowedRoles={['Estudiante']}><EstudianteDashboard /></ProtectedRoute>} />
                                <Route path="/bitacora" element={<ProtectedRoute allowedRoles={['Estudiante']}><BitacorasEstudiante /></ProtectedRoute>} />
                                <Route path="/dashboard/director" element={<DirectorDashboard />} />
                                <Route path="/dashboard/coordinador" element={<CoordinadorDashboard />} />
                                <Route path="/dashboard/coordinador/proyecto/:id" element={
                                    <ProtectedRoute allowedRoles={['Coordinador']}>
                                        <SuspenseWrapper><SupervisionReadOnlyWrapper /></SuspenseWrapper>
                                    </ProtectedRoute>
                                } />
                                <Route path="/dashboard/evaluador-externo" element={<EvaluadorDashboard />} />
                                <Route path="/coordinador/usuarios" element={<ProtectedRoute allowedRoles={['Coordinador']}><GestionUsuarios /></ProtectedRoute>} />
                                <Route path="/coordinador/audit-log" element={<ProtectedRoute allowedRoles={['Coordinador']}><AuditLog /></ProtectedRoute>} />
                                <Route path="/anuncios" element={<SuspenseWrapper><AnunciosPublica /></SuspenseWrapper>} />
                                <Route path="/anuncios/:id" element={<SuspenseWrapper><AnuncioDetalle /></SuspenseWrapper>} />
                                <Route path="/recursos" element={<SuspenseWrapper><Recursos /></SuspenseWrapper>} />
                                <Route path="/recursos/:id" element={<SuspenseWrapper><RecursoDetalle /></SuspenseWrapper>} />
                                {/* PR5: Estudiante pages */}
                                <Route path="/bitacora/nueva" element={<ProtectedRoute allowedRoles={['Estudiante']}><SuspenseWrapper><NuevaBitacora /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/bitacora/:id/revision" element={<ProtectedRoute allowedRoles={['Estudiante']}><SuspenseWrapper><RevisionBitacoraEstudiante /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/mi-proyecto/entregas/:id" element={<ProtectedRoute allowedRoles={['Estudiante']}><SuspenseWrapper><DetalleEntregaEstudiante /></SuspenseWrapper></ProtectedRoute>} />
                                {/* PR6: Director pages */}
                                <Route path="/supervision" element={<ProtectedRoute allowedRoles={['Director']}><SuspenseWrapper><SupervisionProyectoDirector /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/supervision/:proyectoId" element={<ProtectedRoute allowedRoles={['Director']}><SuspenseWrapper><SupervisionProyectoDirector /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/supervision/:proyectoId/bitacoras" element={<ProtectedRoute allowedRoles={['Director']}><SuspenseWrapper><BitacorasProyecto /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/bitacoras/proyectos" element={<ProtectedRoute allowedRoles={['Director']}><SuspenseWrapper><SeleccionProyectosBitacoras /></SuspenseWrapper></ProtectedRoute>} />
                                {/* PR7: Director bitacoras */}
                                <Route path="/bitacoras" element={<ProtectedRoute allowedRoles={['Director']}><SuspenseWrapper><BitacorasDirector /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/bitacoras/:id/revision" element={<ProtectedRoute allowedRoles={['Director']}><SuspenseWrapper><RevisionBitacoraDirector /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/bitacoras/:id/firmar" element={<ProtectedRoute allowedRoles={['Director']}><SuspenseWrapper><DetalleFirmaBitacora /></SuspenseWrapper></ProtectedRoute>} />
                                {/* PR8: Director review */}
                                <Route path="/entregas/:id/revisar" element={<ProtectedRoute allowedRoles={['Director']}><SuspenseWrapper><RevisionEntregaDirector /></SuspenseWrapper></ProtectedRoute>} />
                                {/* PR3: Director evaluaciones */}
                                <Route path="/evaluaciones" element={<ProtectedRoute allowedRoles={['Director']}><SuspenseWrapper><EvaluacionesDirector /></SuspenseWrapper></ProtectedRoute>} />
                                {/* PR9: Coordinador proyectos */}
                                <Route path="/proyectos" element={<ProtectedRoute allowedRoles={['Coordinador']}><SuspenseWrapper><GestionProyectos /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/directores" element={<ProtectedRoute allowedRoles={['Coordinador']}><SuspenseWrapper><DirectoresPage /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/directores/proyectos/:proyectoId/bitacoras" element={<ProtectedRoute allowedRoles={['Coordinador']}><SuspenseWrapper><VerBitacorasCoordinador /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/directores/bitacoras/:id/revision" element={<ProtectedRoute allowedRoles={['Coordinador']}><SuspenseWrapper><RevisionBitacoraCoordinador /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/directores/proyectos/:proyectoId/entregas/:entregaId" element={<ProtectedRoute allowedRoles={['Coordinador']}><SuspenseWrapper><DetalleEntregaCoordinador /></SuspenseWrapper></ProtectedRoute>} />
                                {/* PR10: Coordinador admin */}
                                <Route path="/anuncios/admin" element={<ProtectedRoute allowedRoles={['Coordinador']}><SuspenseWrapper><AnunciosAdmin /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/evaluadores" element={<ProtectedRoute allowedRoles={['Coordinador']}><SuspenseWrapper><AsignacionEvaluadores /></SuspenseWrapper></ProtectedRoute>} />
                                {/* PR11: Coordinador seguimiento */}
                                <Route path="/coordinador/entregas" element={<ProtectedRoute allowedRoles={['Coordinador']}><SuspenseWrapper><CoordinadorEntregas /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/coordinador/bitacoras" element={<ProtectedRoute allowedRoles={['Coordinador']}><SuspenseWrapper><CoordinadorBitacoras /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/alertas" element={<ProtectedRoute allowedRoles={['Coordinador']}><SuspenseWrapper><GestionAlertas /></SuspenseWrapper></ProtectedRoute>} />
                                {/* PR12: Coordinador reports (removed) */}
                                <Route path="/recursos/admin" element={<ProtectedRoute allowedRoles={['Coordinador']}><SuspenseWrapper><RecursosAdmin /></SuspenseWrapper></ProtectedRoute>} />
                                {/* PR13: Evaluador */}
                                <Route path="/evaluaciones/:id" element={<ProtectedRoute allowedRoles={['Director', 'EvaluadorExterno']}><SuspenseWrapper><EvaluarProyecto /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/evaluaciones/:id/calificar" element={<ProtectedRoute allowedRoles={['EvaluadorExterno']}><SuspenseWrapper><EvaluadorCalificar /></SuspenseWrapper></ProtectedRoute>} />
                                {/* Estudiante detalle entrega */}
                                <Route path="/estudiante/entregas/:entregaId" element={<ProtectedRoute allowedRoles={['Estudiante']}><SuspenseWrapper><DetalleEntregaEstudiante /></SuspenseWrapper></ProtectedRoute>} />
                                {/* PR14: IA mock */}
                                <Route path="/analisis-entregas" element={<ProtectedRoute allowedRoles={['Estudiante']}><SuspenseWrapper><AnalisisAutomaticoEntregas /></SuspenseWrapper></ProtectedRoute>} />
                                <Route path="/asistente" element={<ProtectedRoute allowedRoles={['Estudiante']}><SuspenseWrapper><AsistenteOrientacion /></SuspenseWrapper></ProtectedRoute>} />
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
