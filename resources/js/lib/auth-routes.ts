const DASHBOARD_BY_ROLE: Record<string, string> = {
    Estudiante: '/dashboard/estudiante',
    Director: '/dashboard/director',
    Coordinador: '/dashboard/coordinador',
    EvaluadorExterno: '/dashboard/evaluador-externo',
};

export function dashboardPathForRole(role: string | null | undefined): string {
    if (!role) {
        return '/';
    }

    return DASHBOARD_BY_ROLE[role] ?? '/';
}

export async function bootstrapSanctumCsrf(): Promise<void> {
    await fetch('/sanctum/csrf-cookie', {
        method: 'GET',
        credentials: 'include',
    });
}

export function oauthErrorMessage(code: string | null): string | null {
    if (!code) {
        return null;
    }

    const messages: Record<string, string> = {
        access_denied: 'Acceso denegado. Tu correo @unab.edu.co debe estar autorizado por el coordinador.',
        oauth_error: 'No se pudo completar el inicio de sesión con Google. Intenta de nuevo.',
        hd_missing: 'Tu cuenta de Google no pertenece al dominio institucional @unab.edu.co.',
    };

    return messages[code] ?? 'No se pudo iniciar sesión. Intenta de nuevo o contacta al coordinador.';
}
