import { useAuth } from '@/hooks/useAuth';
import { LogOut, Menu } from 'lucide-react';
import { cn } from '@/lib/utils';

interface HeaderProps {
    onMenuClick: () => void;
    title: string;
}

const roleBadgeStyles: Record<string, string> = {
    Coordinador: 'bg-primary/10 text-primary',
    Director: 'bg-secondary/10 text-secondary',
    Estudiante: 'bg-accent/10 text-accent',
    EvaluadorExterno: 'bg-warning/10 text-warning',
};

const roleLabels: Record<string, string> = {
    Coordinador: 'Coordinador',
    Director: 'Director',
    Estudiante: 'Estudiante',
    EvaluadorExterno: 'Evaluador Externo',
};

export function Header({ onMenuClick, title }: HeaderProps) {
    const { user, logout } = useAuth();

    if (!user) return null;

    const initials = user.name
        .split(' ')
        .map((w) => w[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    return (
        <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-border bg-surface px-4 shadow-warm-sm lg:px-6">
            <div className="flex items-center gap-3 min-w-0">
                {/* shrink-0: the identity chip on the right never shrinks, so without
                    this the menu button is the flex item that gets squeezed to zero
                    and disappears on narrow screens. */}
                <button
                    onClick={onMenuClick}
                    aria-label="Abrir menú de navegación"
                    className="shrink-0 rounded-lg p-2 text-text-muted hover:bg-surface-alt hover:text-text transition-colors lg:hidden"
                >
                    <Menu className="h-5 w-5" />
                </button>

                <h1 className="text-lg font-bold text-text truncate">{title}</h1>
            </div>

            <div className="flex items-center gap-3 shrink-0">
                <div className="flex items-center gap-3 rounded-lg bg-surface-alt px-3 py-1.5">
                    <div
                        className={cn(
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                            roleBadgeStyles[user.role] ?? 'bg-primary/10 text-primary',
                        )}
                    >
                        {initials}
                    </div>
                    {/* A long full name must truncate, not push the menu button off
                        the viewport. The chip stays readable down to ~1 char. */}
                    <div className="min-w-0 text-right">
                        <p className="truncate text-sm font-medium leading-tight text-text max-w-[7.5rem] sm:max-w-none">
                            {user.name || 'Usuario'}
                        </p>
                        <p className="truncate text-xs leading-tight text-text-muted max-w-[7.5rem] sm:max-w-none">
                            {roleLabels[user.role] ?? user.role}
                        </p>
                    </div>
                </div>

                <button
                    onClick={logout}
                    className="rounded-lg p-2 text-text-muted hover:bg-error-container hover:text-error transition-colors"
                    title="Cerrar sesión"
                >
                    <LogOut className="h-4 w-4" />
                </button>
            </div>
        </header>
    );
}
