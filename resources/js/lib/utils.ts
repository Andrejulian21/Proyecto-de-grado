import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Handle 401 Unauthenticated responses — session expired.
 * Clears auth state and redirects to login, unless the request
 * is itself an auth/login endpoint or we are already on the login page.
 */
function handleSessionExpired(): void {
    // Avoid redirect loops: already on login or the request is auth-related.
    if (window.location.pathname.startsWith('/login')) return;

    sessionStorage.removeItem('auth_user');
    localStorage.removeItem('user_role');
    window.location.href = '/login';
}

/**
 * Read the XSRF-TOKEN cookie set by `/sanctum/csrf-cookie`.
 * Returns the raw (URL-encoded) token value, or empty string.
 */
function getXsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Wrapper around `fetch` for Sanctum SPA auth.
 *
 * - Sets `credentials: 'include'` for cookie-based auth.
 * - Automatically adds the `X-XSRF-TOKEN` header for state-changing
 *   methods (POST, PUT, PATCH, DELETE) so Sanctum CSRF checks pass.
 * - Defaults `Accept` to `application/json`.
 * - Does NOT set `Content-Type` automatically — pass it in `headers`
 *   when sending a body.
 */
export function apiFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const { headers = {}, method = 'GET', ...rest } = options;

    const extraHeaders: Record<string, string> = {
        Accept: 'application/json',
    };

    // State-changing methods need the CSRF token header.
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
        const token = getXsrfToken();
        if (token) {
            extraHeaders['X-XSRF-TOKEN'] = token;
        }
    }

    return fetch(url, {
        method,
        credentials: 'include',
        headers: { ...extraHeaders, ...(headers as Record<string, string>) },
        ...rest,
    }).then(async (res) => {
        // Session expired → redirect to login (skip auth endpoints).
        if (res.status === 401 && !url.includes('/api/auth/')) {
            handleSessionExpired();
        }
        return res;
    });
}
