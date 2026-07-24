# Tasks: Fix Google login on first attempt

## Phase 1 — Backend

- [x] T-001 **Callback session harden + redirect**. Tras `Auth::login`: regenerate, purge otras sesiones (excluir id actual), `session()->save()`, redirect `/auth/complete`. *Archivos: `AuthController.php`*
- [x] T-002 **Tests Pest**. Callback autenticado redirige a `/auth/complete`; `GET /api/auth/user` OK; purge no borra sesión actual. *Archivos: `tests/Feature/Auth/SingleSessionOnLoginTest.php`*

## Phase 2 — Frontend

- [x] T-003 **AuthComplete page**. Ruta pública: espera auth y navega al dashboard; falla → login con error. *Archivos: `pages/auth/AuthComplete.tsx`, `app.tsx`*
- [x] T-004 **useAuth cleanup**. Eliminar reintentos `setTimeout`; bootstrap csrf + `/api/auth/user` una vez; mantener fallback sessionStorage para externo. *Archivos: `hooks/useAuth.tsx`*
- [x] T-005 **LoginInstitucional**. Leer `?error=`; si ya autenticado, redirigir al dashboard. *Archivos: `LoginInstitucional.tsx`*

## Phase 3 — Verify

- [x] T-006 Pest auth en verde. → SingleSessionOnLoginTest 4/4; suite Auth ejecutada.
- [x] T-007 `npm run build` OK.
- [x] T-008 Confirmado: sin `setTimeout` en `useAuth.tsx`.

## Out of scope

- Cambiar Sanctum architecture, mocks, features no-auth.
