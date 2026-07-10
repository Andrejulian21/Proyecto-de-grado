import { useState, type ReactNode } from 'react';
import { useLocation } from 'react-router-dom';
import { Sidebar } from './Sidebar';
import { Header } from './Header';

interface AppShellProps {
    children: ReactNode;
}

const ROUTE_TITLES: Record<string, string> = {
    '/': 'Panel de Control',
    '/dashboard/estudiante': 'Panel del Estudiante',
    '/dashboard/director': 'Panel del Director',
    '/dashboard/coordinador': 'Panel del Coordinador',
    '/dashboard/evaluador-externo': 'Panel del Evaluador',
    '/coordinador/usuarios': 'Gestión de Usuarios y Accesos',
    '/coordinador/audit-log': 'Auditoría',
    '/proyectos': 'Proyectos',
    '/anuncios': 'Anuncios',
    '/anuncios/admin': 'Gestión de Anuncios',
    '/evaluaciones': 'Evaluaciones',
    '/evaluadores': 'Asignación de Evaluadores',
    '/mi-proyecto': 'Mi Proyecto',
    '/bitacora': 'Bitácora',
    '/bitacora/nueva': 'Nueva Bitácora',
    '/bitacoras': 'Bitácoras',
    '/bitacoras/proyectos': 'Seleccionar Proyecto',
    '/recursos': 'Recursos',
    '/recursos/admin': 'Gestión de Recursos',
    '/analisis-entregas': 'Análisis de Entregas',
    '/asistente': 'Asistente',
    '/alertas': 'Alertas',
    '/semestre': 'Semestre',
    '/reportes': 'Reportes',
    '/directores': 'Directores',
    '/coordinador/entregas': 'Entregas',
    '/coordinador/bitacoras': 'Bitácoras',
    '/supervision': 'Supervisión',
};

function usePageTitle(): string {
    const location = useLocation();
    // Try exact match first, then prefix match (for nested routes).
    return ROUTE_TITLES[location.pathname]
        ?? ROUTE_TITLES[Object.keys(ROUTE_TITLES).find((k) => location.pathname.startsWith(k)) ?? '']
        ?? 'Sistema Centralizado de Proyectos de Grado';
}

export function AppShell({ children }: AppShellProps) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const title = usePageTitle();

    return (
        <div className="flex h-screen overflow-hidden bg-[#fafaf9]">
            <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />

            <div className="flex flex-1 flex-col lg:ml-64 min-w-0">
                <Header onMenuClick={() => setSidebarOpen(true)} title={title} />

                <main className="flex-1 overflow-y-auto p-4 lg:p-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
