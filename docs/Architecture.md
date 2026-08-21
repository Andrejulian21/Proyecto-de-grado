# Guía de arquitectura del proyecto

> **Nota (port 2026-08-20):** la base de producto es `master` (entregas rediseñadas, seguimiento, cartas de aval, evaluador de master). La isla de IA se portó desde `Miguel-Cambios-220726`. Este documento describe la arquitectura de IA de Miguel y **no** sustituye el módulo de evaluadores ni las cartas de aval de master.

> Sistema Centralizado de Proyectos de Grado — UNAB, Ingeniería de Sistemas.
> Documento de referencia para desarrolladores nuevos. Describe **solo la arquitectura existente**.
>
> Documentos complementarios: [`Backend.md`](Backend.md) · [`Frontend.md`](Frontend.md) · [`ARQUITECTURA.md`](ARQUITECTURA.md) · [`DECISIONES.md`](DECISIONES.md)

---

## Vista general

El sistema es una **aplicación web desacoplada en dos capas**:

| Capa | Tecnología | Responsabilidad |
|------|------------|-----------------|
| Backend | Laravel 11, PHP 8.3+, PostgreSQL 16, Redis 7 | API REST, auth, reglas de negocio, persistencia |
| Frontend | React 18, Vite, TypeScript, Tailwind v4 | SPA, routing cliente, UI, consumo de API |
| IA (planificado) | FastAPI + pgvector + Azure OpenAI | Embeddings, chatbot, análisis de entregas (HMAC) |

**Importante:** el proyecto **no usa Inertia.js**. Laravel no renderiza páginas React con props server-side. El flujo real es **API JSON + SPA**. En los diagramas siguientes, el paso equivalente a “Inertia” es la **respuesta JSON** consumida por hooks o `apiFetch`.

---

## Estructura del proyecto

```
Proyecto-de-grado/
├── app/                          # Backend Laravel
│   ├── Enums/                    # Estados tipados (roles, fases, entregas, firmas)
│   ├── Events/                   # AuditEvent (auditoría desacoplada)
│   ├── Http/
│   │   ├── Controllers/          # Auth, Admin, Api
│   │   ├── Middleware/           # role, single_session, activity, password_changed
│   │   └── Requests/             # Form Requests de validación
│   └── Models/                   # 13 modelos Eloquent
├── routes/
│   ├── web.php                   # OAuth, health, SPA catch-all (view app)
│   └── api.php                   # Toda la lógica de negocio (/api/*)
├── database/
│   ├── migrations/               # Esquema PostgreSQL
│   ├── factories/                # Datos de prueba (Pest)
│   └── seeders/                  # Usuarios y datos demo
├── resources/js/                 # Frontend React
│   ├── app.tsx                   # Router + AuthProvider
│   ├── pages/                    # Vistas por rol
│   ├── components/               # UI y dominio reutilizable
│   ├── hooks/                    # Estado remoto + mutaciones API
│   ├── lib/utils.ts              # apiFetch, cn()
│   └── types/                    # Interfaces compartidas (mínimas)
├── tests/                        # Pest (backend)
├── openspec/changes/             # Artefactos SDD por change
├── docs/                         # Documentación técnica
└── docker-compose.yml            # PostgreSQL, Redis, servicios
```

**Convención de nombres:** código en inglés; UI y mensajes de error en español; rutas API en kebab-case español (`/api/admin/proyectos`).

---

## Flujo completo

### Diagrama canónico (arquitectura real)

```
Usuario (browser)
      ↓
Ruta SPA (React Router)          ← navegación client-side
      ↓
Hook / apiFetch                  ← GET/POST /api/* con cookie Sanctum + CSRF
      ↓
Ruta Laravel (routes/api.php)    ← middleware: sanctum, single_session, activity, role
      ↓
Controller                       ← validación, RBAC, orquestación
      ↓
Modelo (Eloquent)                ← relaciones, scopes, enums
      ↓
Base de datos (PostgreSQL)       ← migraciones, constraints
      ↓
JsonResponse { data: ... }       ← (equivalente funcional a “server props”)
      ↓
Página React                     ← useState / hook actualiza UI
      ↓
Componentes                      ← presentación y interacción local
```

### Flujo de carga inicial (primera visita autenticada)

```
1. Browser → GET /{ruta}           → Laravel sirve view('app') (HTML + bundle Vite)
2. React monta AuthProvider
3. GET /sanctum/csrf-cookie        → cookie XSRF-TOKEN
4. GET /api/auth/user              → usuario + rol (o 401 → redirect /login)
5. AppShell + Sidebar por rol
6. Página activa → hook/apiFetch   → datos del dominio
```

### Flujo OAuth institucional (excepción: redirect server-side)

```
LoginInstitucional → GET /auth/redirect → Google → GET /auth/callback
→ Laravel valida whitelist + dominio UNAB
→ Auth::login() + purge sesiones previas
→ redirect /dashboard/{rol}
→ SPA continúa con sessionCheck
```

### Flujo evaluador externo

```
LoginExterno → POST /api/auth/externo/login
→ JsonResponse { user, must_change_password }
→ sessionStorage fallback + redirect dashboard
→ cambio contraseña obligatorio si aplica
```

---

## Cómo viajan los datos

| Etapa | Formato | Quién transforma |
|-------|---------|------------------|
| Request UI → API | JSON body, multipart (archivos), query params | Frontend (`apiFetch`, hooks) |
| Validación | Form Request / Validator | Backend (controlador) |
| Persistencia | Eloquent models + casts a Enums | Backend |
| Response | JSON `{ data: ... }` o paginación Laravel | Backend |
| Consumo UI | Interfaces TS en hooks o inline en páginas | Frontend |
| Estado UI | `useState`, `useReducer`, contexto `useAuth` | Frontend |

**Archivos binarios:** subida vía `multipart/form-data` → `Storage::disk('public')` → acceso por `/storage/{path}` (ruta web dedicada, no API).

**Notificaciones y auditoría:** escritura en backend (`Notificacion::create`, `AuditEvent::dispatch`); lectura en frontend vía endpoints dedicados.

---

## Organización de responsabilidades

### Backend (fuente de verdad)

| Responsabilidad | Dónde |
|-----------------|-------|
| Autenticación y sesión | `AuthController`, middleware Sanctum |
| Autorización (RBAC) | Middleware `role:`, comprobaciones en controladores |
| Reglas de negocio | Controladores + modelos (scopes, boot hooks) |
| Validación de input | Form Requests, Validator |
| Integridad de datos | Migraciones, FK, enums PHP |
| Auditoría inmutable | `AuditLog`, `AuditEvent` |
| Generación de códigos | `Proyecto` boot (PG-{semestre}{seq}) |
| Avance de fases | `EntregaController::autoAdvancePhase` |
| Lockout login | `User::registerFailedLogin`, `LoginAttemptPolicy` |
| Almacenamiento archivos | `EntregaController`, `RecursoController` |

### Frontend (presentación y orquestación UX)

| Responsabilidad | Dónde |
|-----------------|-------|
| Routing y guards | `app.tsx`, `ProtectedRoute` |
| Layout por rol | `AppShell`, `Sidebar`, `Header` |
| Llamadas API | `apiFetch`, hooks (`useProyectos`, etc.) |
| Estado de formularios | Páginas (useState local) |
| Mapeo API → UI | Páginas y hooks (transformación de campos) |
| Feedback visual | Componentes UI (`StatusBadge`, `DataTable`, toasts inline) |
| **No** autorización de seguridad | Solo oculta UI; el backend rechaza acceso |

**Regla de oro:** toda decisión de permiso o transición de estado crítica debe validarse en el backend. El frontend refleja el resultado.

---

## Principales módulos funcionales

### 1. Auth y acceso

| Aspecto | Detalle |
|---------|---------|
| **Propósito** | Login Google OAuth (UNAB), credenciales externas, sesión única, whitelist |
| **Modelos** | `User`, `AuthorizedEmail`, `AuditLog` |
| **Controladores** | `AuthController`, `UserController` (whitelist/evaluadores) |
| **Páginas** | `LoginInstitucional`, `LoginExterno`, `GestionUsuarios`, `AuditLog` |
| **Componentes** | — (UI inline en páginas auth/admin) |

---

### 2. Proyectos y semestres

| Aspecto | Detalle |
|---------|---------|
| **Propósito** | Alta de proyectos PG, asignación director/estudiantes, KPIs, fases |
| **Modelos** | `Proyecto`, `Semestre`, `User` (director, estudiantes pivote) |
| **Controladores** | `ProyectoController`, `SemestreController`, `DirectorCupoController` |
| **Páginas** | `GestionProyectos`, `CoordinadorDashboard`, `EstudianteDashboard`, `SupervisionProyectoDirector`, `SupervisionReadOnly`, `DirectoresPage` |
| **Componentes** | `PhaseStepper`, `GroupSelector`, `StudentAutocomplete`, `StatCard`, `DataTable` |

---

### 3. Entregas (documentos versionados)

| Aspecto | Detalle |
|---------|---------|
| **Propósito** | Crear entregas por semestre, solicitar/habilitar, subir versiones, revisión director, banco finales |
| **Modelos** | `Entrega`, `VersionDocumento`, `Proyecto` (FK + pivote `entrega_proyecto`) |
| **Controladores** | `EntregaController`, `EstudianteController` |
| **Páginas** | `CoordinadorEntregas`, `DetalleEntregaEstudiante`, `RevisionEntregaDirector`, `DetalleEntregaCoordinador`, dashboards |
| **Componentes** | `DeliveryAccordion` (dashboard estudiante); `components/entregas/*` existen pero **no están conectados** — UI duplicada inline en páginas de detalle |

---

### 4. Bitácoras (supervisión firmada)

| Aspecto | Detalle |
|---------|---------|
| **Propósito** | Registro de reuniones, flujo de firmas estudiante → director, horas acumuladas |
| **Modelos** | `Bitacora`, `Proyecto`, `Notificacion` |
| **Controladores** | `BitacoraController`, endpoints en `DirectorController` |
| **Páginas** | `BitacorasEstudiante`, `NuevaBitacora`, `RevisionBitacora*`, `BitacorasDirector`, `BitacorasProyecto`, `VerBitacorasCoordinador`, `DetalleFirmaBitacora` |
| **Componentes** | `RevisionBitacoraView` (núcleo compartido), `TOTPInput`, `ConfirmDialog`, `DataTable` |

---

### 5. Evaluación

| Aspecto | Detalle |
|---------|---------|
| **Propósito** | Asignar evaluadores a proyectos, registrar criterios ponderados, consolidado por entrega |
| **Modelos** | `Evaluacion`, `EvaluadorProyecto`, `Entrega`, `User` |
| **Controladores** | `EvaluacionController`, `EvaluadorProyectoController`, `DirectorController`, `ReporteController` |
| **Páginas** | `AsignacionEvaluadores`, `EvaluacionesDirector`, `EvaluarProyecto`, `EvaluadorCalificar`, `EvaluadorDashboard` |
| **Componentes** | `CalendarGrid`, `ResultsTable`, `StatusBadge` |

---

### 6. Comunicación institucional

| Aspecto | Detalle |
|---------|---------|
| **Propósito** | Anuncios públicos y recursos informativos |
| **Modelos** | `Anuncio`, `RecursoInformativo` |
| **Controladores** | `AnuncioController`, `RecursoController` |
| **Páginas** | `AnunciosPublica`, `AnuncioDetalle`, `AnunciosAdmin`, `Recursos`, `RecursoDetalle`, `RecursosAdmin` |
| **Componentes** | `PageHeader`, `StatCard`, `ConfirmDialog` |

---

### 7. Notificaciones

| Aspecto | Detalle |
|---------|---------|
| **Propósito** | Alertas in-app (entrega revisada, bitácora firmada) |
| **Modelos** | `Notificacion` |
| **Controladores** | `NotificacionController` |
| **Páginas** | Badge en header; `GestionAlertas` (alertas derivadas client-side vía `useAlertas`) |
| **Componentes** | — |

---

### 8. Auditoría

| Aspecto | Detalle |
|---------|---------|
| **Propósito** | Trazabilidad append-only de acciones del sistema |
| **Modelos** | `AuditLog` |
| **Controladores** | `AuditLogController` |
| **Páginas** | `AuditLog` |
| **Componentes** | UI inline (tabla + filtros) |

---

### 9. Reportes

| Aspecto | Detalle |
|---------|---------|
| **Propósito** | Consolidado de notas por proyecto |
| **Modelos** | `Proyecto`, `Entrega`, `Evaluacion` |
| **Controladores** | `ReporteController`, `EvaluacionController::consolidado` |
| **Páginas** | Parcialmente en `AsignacionEvaluadores` (`ResultsTable`); reporte full vía API |
| **Componentes** | `ResultsTable` |

---

### 10. IA (asistente y análisis)

| Aspecto | Detalle |
|---------|---------|
| **Propósito** | Orientación estudiantil y análisis automático de entregas |
| **Estado actual** | **Mock en frontend** (`AsistenteOrientacion`, `AnalisisAutomaticoEntregas`); microservicio FastAPI planificado (ADR-007) |
| **Modelos** | — (embeddings en pgvector, futuro) |
| **Controladores** | — (pendiente integración HMAC) |
| **Páginas** | `AsistenteOrientacion`, `AnalisisAutomaticoEntregas` |

---

## Mapa de dependencias

### Módulos críticos (núcleo del sistema)

```
User ──► Auth (todo depende de sesión)
   │
Proyecto ◄── Semestre
   ├── Entrega ── VersionDocumento
   ├── Bitacora
   ├── EvaluadorProyecto ── Evaluacion
   └── User (director, estudiantes)
```

| Módulo | Por qué es crítico |
|--------|-------------------|
| **Auth + User** | Sin sesión válida no hay operación |
| **Proyecto** | Hub que conecta equipo, entregas, bitácoras y evaluación |
| **Entrega** | Mayor lógica transaccional y archivos |
| **Bitacora** | Supervisión académica obligatoria del PG |

### Módulos independientes (bajo acoplamiento)

| Módulo | Dependencias |
|--------|--------------|
| **Anuncios** | Solo `User` como autor |
| **Recursos** | Solo `User` como autor + Storage |
| **AuditLog** | Escucha eventos; no bloquea flujos |
| **Notificaciones** | Escritura reactiva desde entregas/bitácoras; lectura aislada |

### Módulos altamente acoplados

| Acoplamiento | Descripción | Riesgo |
|--------------|-------------|--------|
| **Entrega ↔ Proyecto** | Doble vínculo FK + pivote `entrega_proyecto`; scopes `paraProyecto` en todo el stack | Consultas duplicadas; bugs si solo se actualiza un camino |
| **Entrega ↔ Evaluación ↔ EvaluadorProyecto** | Evaluar requiere asignación previa y entrega aprobada | Cambios en fases afectan evaluación director |
| **Bitácora ↔ Proyecto ↔ User** | Firmas dependen de rol + membership en pivote estudiantes | Lógica RBAC repetida en controlador y páginas |
| **Frontend detalle entrega** | Tres páginas (~600 líneas c/u) sin componentes compartidos | Regresiones al cambiar flujo de versiones |
| **RevisionBitacoraView** | Un componente sirve estudiante, director y coordinador con `mode` | Cambio en un rol afecta a todos |
| **GestionUsuarios** | Whitelist + users + evaluadores en una sola página | Difícil de extender sin refactor |

### Diagrama de dependencias entre capas

```
                    ┌─────────────┐
                    │   Auth      │
                    └──────┬──────┘
                           │
     ┌─────────────────────┼─────────────────────┐
     ▼                     ▼                     ▼
┌─────────┐          ┌───────────┐         ┌───────────┐
│Proyecto │◄─────────│ Semestre  │         │ Anuncios  │
└────┬────┘          └───────────┘         │ Recursos  │
     │                                      └───────────┘
     ├── Entregas ── Evaluaciones
     ├── Bitácoras
     └── EvaluadorProyecto

Frontend hooks: useAuth → useProyectos / useEntregas / useDirector* → páginas
```

---

## Buenas prácticas existentes

### Patrones utilizados

| Patrón | Implementación |
|--------|----------------|
| **API REST + SPA** | Backend stateless JSON; frontend enruta con React Router |
| **Repository-like hooks** | `useProyectos`, `useEntregas` encapsulan fetch + reducer + mutaciones |
| **Enum-driven domain** | PHP Enums para roles, estados, fases — casts en modelos |
| **Event-driven audit** | `AuditEvent` desacopla logging de controladores |
| **Middleware pipeline** | Capas: Sanctum → single session → activity → role |
| **Form Requests** | Validación reutilizable en auth y admin |
| **Scopes Eloquent** | `paraProyecto`, `enSemestresActivos` — consultas nombradas |
| **Lazy loading rutas** | `React.lazy` para code splitting en páginas secundarias |
| **Design tokens** | Tailwind + componentes UI internos (`PageHeader`, `StatusBadge`) |
| **SDD workflow** | Cambios via `openspec/changes/` con proposal → tasks → verify |

### Convenciones del proyecto

| Área | Convención |
|------|------------|
| Commits | Conventional (`feat:`, `fix:`, `docs:`) |
| Ramas | `feature/*`, `fix/*` → PR a `main` |
| Tests | Pest (backend), baseline ~495 tests; TDD estricto |
| Archivos | Máximo ~500 líneas por archivo |
| UI | Español; código en inglés |
| API errors | JSON `{ error }` o `{ errors }` con HTTP semántico |
| Auth failures | Respuesta uniforme anti-enumeración (login externo) |
| Inmutabilidad audit | Modelo + builder bloquean UPDATE/DELETE |

---

## Puntos de extensión

Dónde agregar funcionalidad **sin romper** la arquitectura:

### Nuevo endpoint de dominio

1. Migración + modelo en `app/Models/`
2. Enum si hay estados fijos
3. Controlador en `Admin/` o `Api/` según rol
4. Ruta en `routes/api.php` con middleware correcto
5. Hook en `resources/js/hooks/use{Nombre}.ts`
6. Página en `resources/js/pages/{rol}/`
7. Entrada en `Sidebar` + `app.tsx` route
8. Tests Pest + criterios en `openspec/changes/`

### Nueva pantalla para rol existente

1. Página en `pages/{rol}/`
2. Ruta en `app.tsx` con `ProtectedRoute allowedRoles`
3. Reutilizar `PageHeader`, hooks existentes
4. **No** duplicar lógica de permisos — confiar en API

### Nueva acción sobre entidad existente

1. Método en controlador existente (p. ej. `EntregaController`)
2. Validación + RBAC en backend
3. `AuditEvent` si es mutación significativa
4. Extender hook o `apiFetch` en página correspondiente

### Integración IA (FastAPI)

1. Endpoint FastAPI con autenticación HMAC
2. Cliente Laravel (service class futuro) — **no** lógica ML en controladores Laravel
3. Reemplazar mocks en `AsistenteOrientacion` y `AnalisisAutomaticoEntregas`
4. Embeddings en pgvector vía migración dedicada

### Refactors recomendados (deuda técnica conocida)

| Área | Extensión segura |
|------|------------------|
| Detalle entrega | Extraer componentes de `components/entregas/` y conectar páginas |
| Tipos TS | Recrear `types/entregas.ts` centralizado desde hooks |
| Hooks huérfanos | Conectar `useDirectorEvaluaciones`, `useUnifiedUsers` |
| TOTP firma | Conectar `DetalleFirmaBitacora` a API real + `User.totp_secret` |
| Páginas sin ruta | Registrar o eliminar `DetalleEntregaDirector`, `CoordinadorBitacorasProyecto` |

---

## Stack e infraestructura

| Componente | Uso |
|------------|-----|
| PostgreSQL 16 | Datos relacionales + pgvector (IA) |
| Redis 7 | Sesiones, cache, colas |
| Sanctum | Cookie SPA auth |
| Socialite | Google OAuth |
| Vite | Build frontend HMR/producción |
| Docker Compose | Dev local (DB, Redis) |
| Pest | Tests backend |
| Playwright | E2E (planificado Sprint 6) |

---

## Middleware y seguridad (referencia rápida)

| Middleware | Efecto |
|------------|--------|
| `auth:sanctum` | Usuario autenticado vía cookie |
| `single_session` | Invalida sesiones/tokens previos |
| `activity` | Timeout por inactividad (~8 h en código actual) |
| `role:Coordinador` | Solo coordinadores en grupo `/api/admin/*` |
| `throttle:login` | Rate limit login externo |

Autorización fina adicional en controladores: director del proyecto, estudiante del pivote, evaluador asignado.

---

## Estado de integración frontend ↔ backend

| Módulo | Integración |
|--------|-------------|
| Auth, usuarios, proyectos, entregas admin, bitácoras API | ✅ Completa |
| Dashboards por rol | ✅ Completa |
| Anuncios, recursos | ✅ Completa |
| Evaluador externo calificar | ⚠️ Parcial / mock |
| IA asistente y análisis | ⚠️ Mock |
| TOTP firma bitácora | ⚠️ Mock UI |
| CoordinadorBitacoras list | ⚠️ Mock local |

Sprint 5 (plan maestro): reemplazar mocks restantes con `apiFetch` a endpoints reales.

---

## Lectura recomendada para onboarding

| Orden | Documento | Contenido |
|-------|-----------|-----------|
| 1 | `AGENTS.md` | Punto de entrada, stack, workflow SDD |
| 2 | **Este documento** | Arquitectura global |
| 3 | `docs/Backend.md` | Modelos, controladores, endpoints |
| 4 | `docs/Frontend.md` | Páginas, hooks, componentes |
| 5 | `constitution.md` | Reglas inviolables (EARS) |
| 6 | `docs/DECISIONES.md` | ADRs con contexto de decisiones |
| 7 | Change activo en `openspec/changes/` | Tarea concreta a implementar |

---

## Glosario

| Término | Significado en este proyecto |
|---------|------------------------------|
| PG | Proyecto de Grado (código `PG-{semestre}{seq}`) |
| Grupo | Semestre académico (`Semestre`, filtro `grupo_id`) |
| Fase | Etapa del PG (`FaseProyecto`: anteproyecto → presentación final) |
| Entrega | Documento obligatorio por fase con versionado |
| Bitácora | Acta de reunión de supervisión con firmas |
| Whitelist | `authorized_emails` — emails UNAB autorizados para OAuth |
