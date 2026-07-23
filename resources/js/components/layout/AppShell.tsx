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
    '/bitacoras/proyectos': 'Bitácoras por proyecto',
    '/coordinador/bitacoras/proyectos': 'Reuniones del proyecto',
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

    // 1. Exact match
    if (ROUTE_TITLES[location.pathname]) {
        return ROUTE_TITLES[location.pathname];
    }

    // 2. Prefix match with path boundary (avoid matching '/' for everything)
    const matchedKey = Object.keys(ROUTE_TITLES)
        .filter((k) => k !== '/') // exclude root — it would match everything
        .find((k) => location.pathname === k || location.pathname.startsWith(k + '/'));

    if (matchedKey) {
        return ROUTE_TITLES[matchedKey];
    }

    // 3. Fallback to root only for exact root match
    if (location.pathname === '/') {
        return ROUTE_TITLES['/'];
    }

    return 'Sistema Centralizado de Proyectos de Grado';
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
