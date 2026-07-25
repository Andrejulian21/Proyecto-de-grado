import { NavLink, useLocation } from 'react-router-dom';
import { cn } from '@/lib/utils';
import {
    LayoutDashboard,
    Users,
    FolderKanban,
    Megaphone,
    ClipboardCheck,
    ScrollText,
    GraduationCap,
    X,
    UserCheck,
    Bell,
    Calendar,
    BarChart3,
} from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';

interface SidebarProps {
    open: boolean;
    onClose: () => void;
}

const navConfig: Record<string, { to: string; icon: typeof LayoutDashboard; label: string }[]> = {
    Coordinador: [
        { to: '/dashboard/coordinador', label: 'Panel de Control', icon: LayoutDashboard },
        { to: '/proyectos', label: 'Proyectos', icon: FolderKanban },
        { to: '/directores', label: 'Directores', icon: UserCheck },
        { to: '/evaluadores', label: 'Evaluadores', icon: ClipboardCheck },
        { to: '/coordinador/usuarios', label: 'Usuarios', icon: Users },
        { to: '/anuncios', label: 'Anuncios', icon: Megaphone },
        { to: '/anuncios/admin', label: 'Anuncios Admin', icon: Megaphone },
        { to: '/alertas', label: 'Alertas', icon: Bell },
        { to: '/coordinador/entregas', label: 'Entregas', icon: FolderKanban },

        { to: '/recursos/admin', label: 'Recursos Admin', icon: FolderKanban },
    ],
    Director: [
        { to: '/dashboard/director', label: 'Panel', icon: LayoutDashboard },
        { to: '/supervision', label: 'Supervisión', icon: FolderKanban },
        { to: '/evaluaciones', label: 'Evaluaciones', icon: ClipboardCheck },
        { to: '/anuncios', label: 'Anuncios', icon: Megaphone },
        { to: '/recursos', label: 'Recursos', icon: FolderKanban },
    ],
    Estudiante: [
        { to: '/dashboard/estudiante', label: 'Mi Proyecto', icon: GraduationCap },
        { to: '/bitacora', label: 'Bitácora', icon: ScrollText },
        { to: '/anuncios', label: 'Anuncios', icon: Megaphone },
        { to: '/recursos', label: 'Recursos', icon: FolderKanban },
        { to: '/analisis-entregas', label: 'Análisis de Entregas', icon: BarChart3 },
        { to: '/asistente', label: 'Asistente', icon: UserCheck },
    ],
    EvaluadorExterno: [
        { to: '/dashboard/evaluador-externo', label: 'Panel de Control', icon: LayoutDashboard },
        { to: '/dashboard/evaluador-externo', label: 'Evaluaciones', icon: ClipboardCheck },
        { to: '/anuncios', label: 'Anuncios', icon: Megaphone },
        { to: '/recursos', label: 'Recursos', icon: FolderKanban },
    ],
};

export function Sidebar({ open, onClose }: SidebarProps) {
    const { role } = useAuth();
    const location = useLocation();
    const isCoordinator = role === 'Coordinador';
    const items = navConfig[role ?? ''] ?? navConfig.EvaluadorExterno;

    return (
        <>
            {open && (
                <div
                    className="fixed inset-0 z-30 bg-black/40 lg:hidden"
                    onClick={onClose}
                    aria-hidden="true"
                />
            )}

            <aside
                aria-label="Navegación principal"
                className={cn(
                    'fixed inset-y-0 left-0 z-40 flex w-64 flex-col overflow-y-auto transition-transform duration-200',
                    isCoordinator ? 'bg-[#1c1917] text-white' : 'border-r border-border bg-surface text-text',
                    open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                )}
            >
                <div className={cn('flex h-16 items-center justify-between border-b px-5', isCoordinator ? 'border-white/10' : 'border-border')}>
                    <div className="flex items-center gap-3">
                        <div className={cn('flex h-9 w-9 items-center justify-center rounded-xl', isCoordinator ? 'bg-primary shadow-warm-sm' : 'bg-primary shadow-warm-sm')}>
                            <svg viewBox="0 0 40 40" className="h-5 w-5 text-white" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M20 4L4 12v6c0 8 6 16 16 20 10-4 16-12 16-20v-6L20 4z" />
                                <path d="M14 18l4 4 8-8" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </div>
                        <div>
                            <p className={cn('text-sm font-bold leading-tight', isCoordinator ? 'text-white' : 'text-text')}>UNAB</p>
                            <p className={cn('text-[10px] leading-tight', isCoordinator ? 'text-white/60' : 'text-text-muted')}>Sistema de Proyectos</p>
                        </div>
                    </div>
                    <button onClick={onClose} className={cn('lg:hidden', isCoordinator ? 'text-white/70 hover:text-white' : 'text-text-muted hover:text-text')}>
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <nav className="flex-1 overflow-y-auto p-3" aria-label="Secciones">
                    <ul className="space-y-1">
                        {items.map((item) => {
                            const isSupervisionActive = item.to === '/supervision' && location.pathname.startsWith('/bitacoras');
                            return (
                                <li key={`${item.to}-${item.label}`}>
                                    <NavLink
                                        to={item.to}
                                        end={['/', '/dashboard/director', '/dashboard/estudiante', '/dashboard/coordinador', '/dashboard/evaluador-externo', '/anuncios', '/anuncios/admin', '/evaluaciones'].includes(item.to)}
                                        onClick={onClose}
                                        className={({ isActive }) =>
                                            cn(
                                                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors duration-150',
                                                isCoordinator
                                                    ? (isActive || isSupervisionActive)
                                                        ? 'bg-primary/20 text-primary-container'
                                                        : 'text-white/70 hover:bg-white/10 hover:text-white'
                                                    : (isActive || isSupervisionActive)
                                                        ? 'bg-primary-container text-primary-on-container'
                                                        : 'text-text-muted hover:bg-surface-alt hover:text-text',
                                            )
                                        }
                                    >
                                        <item.icon className="h-4 w-4 shrink-0" />
                                        {item.label}
                                    </NavLink>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                <div className={cn('border-t p-4 text-xs', isCoordinator ? 'border-white/10 text-white/50' : 'border-border text-text-subtle')}>
                    &copy; {new Date().getFullYear()} UNAB &mdash; Ingeniería de Sistemas
                </div>
            </aside>
        </>
    );
}
