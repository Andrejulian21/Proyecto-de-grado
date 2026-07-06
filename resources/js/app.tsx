/**
 * Root React component.
 *
 * PR 1 — Foundation: minimal placeholder that proves the React + Vite
 * pipeline is wired up. The full login pages, role-based routing, and
 * shadcn/ui shell arrive in PR 3.
 */
export function App(): JSX.Element {
    return (
        <main className="mx-auto max-w-2xl px-4 py-12 font-sans text-slate-900">
            <h1 className="text-3xl font-bold text-primary">
                Sistema Centralizado de Proyectos de Grado
            </h1>
            <p className="mt-4 text-slate-600">
                Frontend React + Vite + Tailwind listo. Las páginas de inicio
                de sesión, dashboards y CRUD de whitelist se montan en
                <span className="ml-1 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-sm">
                    PR 3
                </span>
                .
            </p>
            <p className="mt-6 text-sm text-slate-500">
                PR 1 (Foundation) cubre migraciones, Sanctum, Socialite, Pest y
                esta shell. PR 2 implementa el backend de auth (OAuth +
                triple validación + RBAC + audit log). PR 3 trae las páginas.
            </p>
        </main>
    );
}

export default App;
