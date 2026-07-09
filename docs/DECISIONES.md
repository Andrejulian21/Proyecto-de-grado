# Decisiones de Arquitectura (ADRs)

> Formato: contexto → opciones → decisión → consecuencias.
> Estado: 2026-07-08 — Sprint 1 completado.

---

## ADR-001 — Backend Framework: Laravel 11 ✅

**Contexto:** Necesitamos un framework backend con ORM, auth, migraciones,队列, y ecosistema maduro.
**Opciones:** Laravel 11 · Symfony · Django · FastAPI (solo) · Node.js/Express
**Decisión:** **Laravel 11** con PHP 8.3+.
**Por qué:** ORM maduro (Eloquent), migraciones nativas, auth plug-and-play (Sanctum + Socialite),
ecosistema de paquetes (Pest, Pint, Sail), y experiencia del equipo.
**Consecuencia:** Para IA necesitamos un microservicio separado (FastAPI). Laravel no es ideal para
workloads de ML/embeddings.

## ADR-002 — Frontend: React + Vite + TypeScript ✅

**Contexto:** Necesitamos un frontend moderno, componentizado, con buen DX y ecosistema.
**Opciones:** React · Vue · Svelte · Alpine + Blade (Laravel tradicional)
**Decisión:** **React 18 + Vite + TypeScript + Tailwind CSS v4 + shadcn/ui**.
**Por qué:** React es el estándar de la industria; Vite da HMR ultrarrápido; Tailwind v4 es CSS-first;
shadcn/ui da componentes accesibles y customizables. TypeScript para type safety.
**Consecuencia:** Bundle inicial de ~260KB JS + ~19KB CSS gzipped. El stack es moderno y productivo.

## ADR-003 — Autenticación: Sanctum SPA + Google OAuth ✅

**Contexto:** Los usuarios institucionales usan Google Workspace (@unab.edu.co). Los evaluadores externos
necesitan credenciales separadas. Todo debe ser seguro.
**Opciones:** Sanctum (cookie SPA) · JWT (Laravel Passport/Sanctum tokens) · Laravel Jetstream + Livewire
**Decisión:** **Sanctum cookie SPA** para auth principal + **Google OAuth (Socialite)** para institucionales.
**Por qué:** Cookies HttpOnly son más seguras que JWT localStorage; Sanctum da CSRF built-in para SPA;
Socialite abstrae el flujo OAuth. Los evaluadores externos usan credenciales con el mismo Sanctum.
**Consecuencia:** Cookie auth requiere mismo dominio o CORS configurado. Sanctum SPA necesita
`/sanctum/csrf-cookie` antes de requests autenticados.

## ADR-004 — Roles: Enum en users, no tabla pivote ✅

**Contexto:** 4 roles fijos (Estudiante, Director, Coordinador, EvaluadorExterno). No hay herencia
de roles ni permisos granulares.
**Opciones:** PHP Enum en columna `users.role` · Tabla pivote `role_user` · Paquete Spatie Permission
**Decisión:** **PHP Enum en `users.role`** + Laravel Gates/Policies.
**Por qué:** 4 roles fijos = enum es suficiente. Gates y Policies dan la flexibilidad necesaria para
autorización. Menos queries que tabla pivote. Más simple.
**Consecuencia:** Cambiar un rol requiere migración. Si en el futuro hay roles dinámicos, toca migrar
a tabla pivote. Aceptable para el alcance actual.

## ADR-005 — Sesión Única + Timeout por Inactividad ✅

**Contexto:** RNF del proyecto requiere una sesión activa por usuario y timeout por inactividad.
**Opciones:** Middleware Sanctum · Middleware personalizado · Config SESSION_LIFETIME sola
**Decisión:** **Middleware personalizado** (SingleSession + Activity) + `SESSION_LIFETIME=60`.
**Por qué:** El middleware `SingleSession` elimina tokens previos al login. El `Activity` middleware
actualiza `last_activity_at` y fuerza logout tras 1h. `SESSION_LIFETIME` es respaldo.
**Consecuencia:** Dos middlewares en las rutas api. SingleSession puede romper sesiones si no se
configura correctamente (lección aprendida en PR 2).

## ADR-006 — Auditoría: Eventos + Listeners, tabla append-only ✅

**Contexto:** RNF05 requiere log inmutable de todas las acciones significativas del sistema.
**Opciones:** Eventos + Listeners de Laravel · Eloquent Observers · Spatie Activitylog
**Decisión:** **Eventos + Listeners propios** con tabla `audit_logs` append-only.
**Por qué:** Eventos desacoplan la lógica de negocio del logging. La tabla es append-only (no UPDATE/DELETE).
Spatie Activitylog es overkill para lo que necesitamos.
**Consecuencia:** Cada acción importante dispara un `AuditEvent`. Los listeners son `ShouldQueue`
para no bloquear requests. 5 años de retención con comando `audit:archive`.

## ADR-007 — IA: Microservicio FastAPI separado ✅

**Contexto:** Los módulos de IA (asistente de orientación, análisis automático de entregas) necesitan
Sentence-Transformers, pgvector, y Azure OpenAI. Esto no corre bien en Laravel.
**Opciones:** FastAPI separado · Laravel con shell exec a Python · Laravel con Redis queue + Python worker
**Decisión:** **FastAPI separado** con comunicación HMAC vía HTTP.
**Por qué:** FastAPI es ideal para APIs de ML (async, tipo, documentación automática). Separar el
microservicio permite escalar independientemente. HMAC da seguridad sin complejidad OAuth.
**Consecuencia:** Dos deploys en lugar de uno. Dependencia de red entre servicios.

## ADR-008 — API Routes Structure ✅

**Contexto:** Las rutas de API deben ser consistentes, RESTful, y fáciles de mantener.
**Opciones:** API versionada (`/api/v1/`) · Por rol (`/api/admin/`, `/api/estudiante/`) · Plana
**Decisión:** **Prefijo por rol** `/api/admin/` para coordinador, `/api/auth/` para auth.
**Por qué:** Simplicidad. No necesitamos versionado de API en MVP. Las rutas por rol son auto-documentadas.
**Consecuencia:** Para agregar rutas de estudiante/director en el futuro, se crean grupos similares.

## ADR-009 — Sesiones: Redis sobre BD ✅

**Contexto:** Necesitamos sesiones rápidas y expiración automática para el timeout de inactividad.
**Opciones:** Redis · Database (PostgreSQL) · File
**Decisión:** **Redis** para sesiones.
**Por qué:** Redis da expiración automática (TTL), es rápido, y ya está en el stack para cache.
**Consecuencia:** Dependencia de Redis corriendo. Sail lo incluye por defecto.

## ADR-010 — CSRF en SPA: Sanctum + apiFetch helper ✅

**Contexto:** Sanctum SPA requiere X-XSRF-TOKEN en requests POST/PUT/DELETE. fetch() no lo manda automático.
**Opciones:** Axios con interceptors · fetch() manual · Helper apiFetch
**Decisión:** **Helper `apiFetch()`** en `lib/utils.ts` que lee la cookie XSRF-TOKEN y la manda como header.
**Por qué:** No queremos agregar Axios solo para esto. El helper es 20 líneas, funciona con fetch nativo.
**Consecuencia:** Todas las mutaciones deben usar `apiFetch()` en vez de `fetch()`.

## ADR-011 — Diseño Visual: Design tokens de Open Design ✅

**Contexto:** La UI debe ser consistente con los wireframes aprobados en Open Design.
**Opciones:** Tailwind classes directas · CSS custom properties · Design tokens en Tailwind config
**Decisión:** **CSS custom properties** (tokens.css) + **Tailwind config** mapeando los tokens.
**Por qué:** Los wireframes usan CSS custom properties como fuente de verdad. Mapearlos a Tailwind
permite usar ambas convenciones.
**Consecuencia:** Los colores y espaciados deben coincidir con `shared/tokens.css` de Open Design.
Burnt orange `#c2410c`, indigo `#4f46e5`, Open Sans.

## ADR-012 — Entrega Continua: 7 días, 1 dev ✅

**Contexto:** El cronograma original de 60 días se comprimió a 7 días con 1 desarrollador.
**Opciones:** 12 sprints detallados · 7 días intensivos · Priorización estricta
**Decisión:** **7 días con orden de sacrificio explícito.** Ver `docs/ROADMAP.md`.
**Por qué:** Realista dadas las limitaciones de tiempo. El orden de sacrificio asegura que lo crítico
se entrega primero.
**Consecuencia:** Chat, IA y TOTP son los primeros en sacrificarse si no alcanza el tiempo.
Auth, proyectos, entregas, bitácoras y evaluación NO se sacrifican.
