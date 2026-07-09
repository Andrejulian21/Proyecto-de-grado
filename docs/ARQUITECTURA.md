# Arquitectura — Sistema Centralizado de Proyectos de Grado UNAB

> Fuente de verdad del diseño. Todo agente que trabaje aquí consulta este documento antes de
> proponer cambios arquitectónicos.

---

## Vista general

```
┌──────────────────────────────────────────────────────────────┐
│                     CLIENTE (Browser)                        │
│              React SPA · Vite · Tailwind · shadcn/ui          │
│              Sanctum cookie · CSRF token · axios/apiFetch     │
└──────────────────────────┬───────────────────────────────────┘
                           │ HTTP (same-origin o CORS)
┌──────────────────────────▼───────────────────────────────────┐
│                   LARAVEL 11 (Backend)                       │
│  ┌─────────────┐ ┌──────────────┐ ┌────────────────────────┐ │
│  │    Auth      │ │    Admin     │ │     API (Sanctum)      │ │
│  │  OAuth/login │ │  Proyectos   │ │  auth:sanctum + roles  │ │
│  │  logout/auth │ │  Usuarios    │ │  middleware por ruta    │ │
│  └─────────────┘ └──────────────┘ └────────────────────────┘ │
│  ┌─────────────┐ ┌──────────────┐ ┌────────────────────────┐ │
│  │   Events    │ │   Queue      │ │     Audit Log          │ │
│  │  AuditEvent │ │  Redis       │ │  Append-only, 5yr      │ │
│  └─────────────┘ └──────────────┘ └────────────────────────┘ │
└──────────┬────────────────────────────────┬──────────────────┘
           │ HMAC HTTP                      │
┌──────────▼──────────┐      ┌──────────────▼──────────────────┐
│   FASTAPI (IA)      │      │     POSTGRESQL 16 + pgvector    │
│  Sentence-Transform  │      │  users · proyectos · entregas  │
│  Azure OpenAI       │      │  bitacoras · evaluaciones ·     │
│  Embeddings API     │      │  audit_logs · semestres         │
│  Chatbot/Análisis   │      │  + embeddings (pgvector)       │
└─────────────────────┘      └────────────────────────────────┘
                              ┌──────────────────────────────┐
                              │        REDIS 7               │
                              │  Sessions · Cache · Queues   │
                              └──────────────────────────────┘
```

---

## Capas del Backend (Laravel 11)

### 1. Capa de Rutas — `routes/`

```
routes/
  api.php       → API REST (auth:sanctum + roles)
  web.php       → Web routes (OAuth redirect/callback)
```

Todas las rutas API se agrupan por middleware:

| Grupo | Middleware | Rutas |
|-------|-----------|-------|
| Auth | `auth:sanctum, single_session, activity` | `/api/auth/user`, `/api/auth/logout`, `/api/auth/change-password` |
| Admin | `auth:sanctum, role:Coordinador` | `/api/admin/usuarios`, `/api/admin/whitelist`, `/api/admin/evaluadores`, `/api/admin/audit-logs` |
| Público | — | `/auth/redirect`, `/auth/callback` (Google OAuth) |

### 2. Capa de Controladores — `app/Http/Controllers/`

```
Controllers/
  Auth/
    AuthController.php     → OAuth, loginExterno, logout, sessionCheck
  Admin/
    UserController.php     → CRUD usuarios, whitelist, evaluadores
    AuditLogController.php → Visor de auditoría (read-only)
```

Cada controlador:
- Valida input con Form Requests o `$request->validate()`
- Dispara `AuditEvent` en acciones mutantes
- Retorna `JsonResponse` con códigos HTTP estándar

### 3. Capa de Modelos — `app/Models/`

| Modelo | Tabla | Propósito |
|--------|-------|-----------|
| `User` | `users` | Usuarios con roles, Google OAuth, lockout |
| `AuthorizedEmail` | `authorized_emails` | Whitelist de correos autorizados |
| `AuditLog` | `audit_logs` | Log de auditoría inmutable |
| `Semestre` | `semestres` | Períodos académicos |
| `Proyecto` | `proyectos` | Proyectos de grado |

### 4. Capa de Middleware — `app/Http/Middleware/`

| Middleware | Propósito |
|-----------|-----------|
| `RoleMiddleware` | Valida rol del usuario contra ruta |
| `SingleSessionMiddleware` | Una sesión activa por usuario |
| `ActivityMiddleware` | Timeout por inactividad (1h) |
| `EnsurePasswordChanged` | Forzar cambio de contraseña temporal |

---

## Capas del Frontend (React + Vite)

```
resources/js/
  app.tsx                    → Router principal + AuthProvider
  hooks/
    useAuth.tsx              → Session check, login, logout, CSRF bootstrap
  lib/
    utils.ts                 → cn(), apiFetch() helper
  components/
    layout/
      AppShell.tsx           → Layout con sidebar + header
      Sidebar.tsx            → Navegación por rol (fija)
      Header.tsx             → Header con título dinámico + user chip
  pages/
    auth/
      LoginInstitucional.tsx → Google OAuth login
      LoginExterno.tsx       → Credenciales evaluadores externos
    coordinador/
      GestionUsuarios.tsx    → CRUD whitelist + evaluadores + roles
      AuditLog.tsx           → Visor de auditoría
    dashboard/
      CoordinadorDashboard.tsx → KPIs + tabla proyectos + alertas
      DirectorDashboard.tsx  → Dashboard del director
      EstudianteDashboard.tsx → Dashboard del estudiante
      EvaluadorDashboard.tsx → Dashboard del evaluador
```

---

## Flujo de Autenticación

### Institucional (Google OAuth)

```
1. Usuario → clic "Iniciar con Google"
2. Laravel → redirect a Google con hd=unab.edu.co
3. Google → callback con código de autorización
4. Laravel → Socialite exchange + triple validación:
   a. hd claim === unab.edu.co
   b. email endsWith @unab.edu.co
   c. email exists in authorized_emails
5. Laravel → findOrCreate User, Auth::login(), create Sanctum token
6. Laravel → redirect a SPA (/dashboard/{role})
7. SPA → sessionCheck con apiFetch a /api/auth/user
```

### Evaluador Externo

```
1. Usuario → POST /api/auth/externo/login con email + password
2. Laravel → validar credenciales + check es_externo=true
3. Laravel → single-session purge, create Sanctum token
4. Laravel → check password_changed_at (forzar cambio si temp)
5. Laravel → redirect a SPA con token en sessionStorage
```

---

## Estructura de Datos

### users
| Columna | Tipo | Notas |
|---------|------|-------|
| id | bigint AI | PK |
| name | string | |
| email | string | UNIQUE |
| password | string | NULLABLE (Google OAuth) |
| role | string (enum) | Estudiante/Director/Coordinador/EvaluadorExterno |
| es_externo | boolean | DEFAULT false |
| google_id | string | NULLABLE, UNIQUE |
| avatar | string | NULLABLE |
| last_activity_at | timestamp | NULLABLE |
| totp_secret | string | NULLABLE (Sprint 4) |
| password_changed_at | timestamp | NULLABLE |
| failed_attempts | int | DEFAULT 0 |
| locked_until | timestamp | NULLABLE |

### authorized_emails
| Columna | Tipo | Notas |
|---------|------|-------|
| id | bigint AI | PK |
| email | string | UNIQUE |
| name | string | NULLABLE |
| role | string | Whitelist role |
| created_by | bigint | FK→users |
| created_at | timestamp | |

### audit_logs
| Columna | Tipo | Notas |
|---------|------|-------|
| id | bigint AI | PK |
| user_id | bigint | FK→users, NULLABLE |
| action | string(64) | login.success, role.changed, etc. |
| description | text | NULLABLE |
| ip_address | string(45) | NULLABLE |
| user_agent | text | NULLABLE |
| metadata | json | NULLABLE |
| created_at | timestamp | |

---

## Seguridad

1. **Autenticación:** Sanctum cookie SPA + Google OAuth + credenciales
2. **Autorización:** Middleware `role:Coordinador` en rutas admin, Gates/Policies para lógica fina
3. **CSRF:** Sanctum XSRF-TOKEN + header X-XSRF-TOKEN vía `apiFetch()`
4. **Auditoría:** Append-only, eventos asíncronos, 5 años retención
5. **TOTP:** RFC 6238 para firmas de bitácoras (Sprint 4)
6. **Rate limiting:** Lockout tras 3 intentos fallidos de login (login externo)
