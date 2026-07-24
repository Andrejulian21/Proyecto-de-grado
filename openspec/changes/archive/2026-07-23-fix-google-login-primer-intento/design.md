# Design: Fix Google login on first attempt

## Analysis summary (FASE 1)

| # | Pregunta | Hallazgo |
|---|----------|----------|
| 1 | Inicio Google | `LoginInstitucional` → `GET /auth/redirect` → Socialite |
| 2 | Callback | `GET /auth/callback` → `handleGoogleCallback` |
| 3 | Creación sesión | `Auth::login()` tras purge de sesiones previas |
| 4 | Lectura usuario | SPA: `GET /api/auth/user` (`sessionCheck`) |
| 5 | AuthProvider | Monta con `sessionCheck()` en `useEffect` |
| 6 | ProtectedRoute | Si `!isLoading && !isAuthenticated` → `/login` |
| 7 | Sesión activa | Cookie Sanctum SPA + `/api/auth/user` 200 |
| 8 | Race | **Sí**: redirect a dashboard protegido antes de confirmar sesión en contexto React |
| 9 | Origen | Frontend (navegación prematura) + bootstrap frágil; backend debe asegurar save/regenerate |
| 10 | Cookies/CSRF | Driver `database`, `secure=false` local, SameSite=lax; CSRF vía `/sanctum/csrf-cookie` |
| 11 | Evidencia | `useAuth` ya tenía retries `setTimeout` (síntoma); LoginInstitucional no lee `?error=` |

## Correct flow (target)

```
Google OK → Auth::login + regenerate + save + purge others
         → 302 /auth/complete   (público, sin ProtectedRoute)
         → AuthProvider sessionCheck (csrf + /api/auth/user)
         → Navigate /dashboard/{rol}
```

## Solution

| Layer | Change |
|-------|--------|
| Backend | Tras login: `session()->regenerate()`, purge excluyendo sesión actual, `session()->save()`, redirect `/auth/complete` |
| Frontend | Página `AuthComplete` pública: espera `isLoading`/`isAuthenticated` y navega al dashboard |
| useAuth | Quitar loops `setTimeout`; un bootstrap limpio (csrf + user una vez) |
| LoginInstitucional | Mostrar `?error=`; si ya hay sesión, ir al dashboard |

## Risks

| Risk | Mitigation |
|------|------------|
| Double sessionCheck | AuthComplete solo observa estado del AuthProvider |
| Externo regresa | No tocar LoginExterno salvo compatibilidad sessionCheck |
| Purge borra sesión nueva | Excluir `session()->getId()` actual |

## Rollback

Revertir redirect a `/dashboard/{rol}` y restaurar `useAuth` previo.
