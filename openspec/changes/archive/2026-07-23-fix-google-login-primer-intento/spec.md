# Spec: Fix Google login on first attempt

**Change**: `2026-07-23-fix-google-login-primer-intento`  
**Type**: Bugfix  
**ADRs**: ninguno nuevo (Sanctum SPA cookie permanece)

## Purpose

Garantizar autenticación Google en un solo intento, con sesión Laravel/Sanctum válida y redirección al dashboard del rol, sin parches temporales.

## Requirements

| ID | Requirement | Scenarios |
|----|-------------|-----------|
| **GL-001** | WHEN Google OAuth succeeds, THE system SHALL establish a web session (`Auth::login`) and redirect to a **public** completion path (`/auth/complete`), not directly into a `ProtectedRoute` dashboard. | **Happy**: callback → 302 `/auth/complete` + authenticated session. |
| **GL-002** | WHEN `/auth/complete` loads, THE SPA SHALL wait until `sessionCheck` finishes; IF authenticated, THE SPA SHALL navigate to the role dashboard; IF not, THE SPA SHALL send the user to `/login` with a clear error. | **Happy**: user lands on dashboard. **Edge**: 401 → `/login?error=session`. |
| **GL-003** | THE backend SHALL regenerate and save the session after login, and SHALL purge other sessions for the user **excluding** the current session id. | **Happy**: prior device sessions removed; current remains. |
| **GL-004** | THE frontend SHALL NOT use `setTimeout`/artificial reloads/duplicate redirects to “fix” auth timing. | **Happy**: `useAuth` bootstrap has no retry delay loop. |
| **GL-005** | Existing Sanctum cookie SPA auth, logout, refresh, and external login SHALL keep working. | **Happy**: refresh keeps session; externo still logs in. |

## Acceptance criteria (EARS)

- WHEN a whitelisted UNAB user completes Google OAuth once, THE system SHALL land them on their role dashboard authenticated.
- WHEN the user logs out and logs in with Google again, THE system SHALL succeed in one attempt.
- WHEN the user refreshes or opens a new tab, THE session SHALL remain valid.
- THE system SHALL NOT enter a redirect loop between `/login` and dashboards.
