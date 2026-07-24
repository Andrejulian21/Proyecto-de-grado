# Proposal: Fix Google login on first attempt

## Intent

Corregir el flujo OAuth Google para que el usuario quede autenticado y llegue al dashboard en el **primer** intento, sin repetir el login.

## Root cause

1. `handleGoogleCallback` redirige directamente a `/dashboard/{rol}`, ruta envuelta en `ProtectedRoute`.
2. Tras el full-page load, `AuthProvider` arranca con `user=null` e `isLoading=true`, luego `sessionCheck` consulta `/api/auth/user`.
3. Si la confirmación de sesión aún no está lista o falla el bootstrap, `ProtectedRoute` interpreta “no autenticado” y envía a `/login` — aunque el callback ya haya hecho `Auth::login()`.
4. `useAuth` contiene reintentos con `setTimeout` (parche sintomático) que no resuelven la causa y están prohibidos como solución.
5. Login externo mitiga esto con `sessionStorage` + redirect; Google no tiene ese puente.

## Scope

### In Scope
- Redirigir el callback a una ruta pública de completado OAuth.
- Confirmar sesión vía `AuthProvider`/`sessionCheck` y navegar al dashboard.
- Persistir/regenerar sesión de forma explícita en el callback.
- Purgar sesiones previas **excluyendo** la sesión actual.
- Eliminar reintentos artificiales con `setTimeout` en `useAuth`.
- Tests del redirect y de autenticación post-callback.

### Out of Scope
- Cambios a login externo (salvo no romperlo).
- Rediseño de pantallas de login.
- Cambiar Sanctum/arquitectura cookie SPA.

## Success Criteria

- [ ] Primer login Google → dashboard del rol sin segundo intento.
- [ ] Logout + login → un solo intento.
- [ ] Refresh / nueva pestaña mantienen sesión.
- [ ] Sin bucles de redirección.
- [ ] Sin `setTimeout` para “arreglar” auth.
