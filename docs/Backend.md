# Guía técnica del backend

> **Nota (port 2026-08-20):** la base de producto es `master`. La isla de IA (gateway, asistente, evaluación inteligente, ABET, perfiles académicos) se portó desde `Miguel-Cambios-220726`. Cartas de aval, seguimiento y el evaluador de master (`mis-asignaciones` / `evaluaciones_evaluador`) siguen siendo la fuente de verdad de producto.

> Sistema Centralizado de Proyectos de Grado — Laravel 11 + API JSON + React SPA (Sanctum cookie).
> Última revisión basada en `app/Models`, `app/Http/Controllers`, `routes/web.php` y `routes/api.php`.

---

## Resumen de arquitectura

El backend expone **API REST JSON** bajo el prefijo `/api`. El frontend React consume esos endpoints vía `apiFetch()` con autenticación Sanctum por cookie (SPA stateful). **No se usa Inertia.js**: Laravel solo sirve `view('app')` como catch-all para React Router.

| Capa | Responsabilidad |
|------|-----------------|
| `routes/web.php` | Health check, OAuth Google, archivos `/storage/*`, SPA catch-all |
| `routes/api.php` | Toda la lógica de negocio (auth, CRUD, dashboards) |
| Middleware | `auth:sanctum`, `single_session`, `activity` (8 h), `role:Coordinador` (admin) |
| Modelos | Dominio: proyectos, entregas, bitácoras, evaluaciones, usuarios |
| Enums | Estados tipados (`EstadoEntrega`, `EstadoProyecto`, `FaseProyecto`, etc.) |

**Patrón de autorización:** RBAC por rol (`UserRole`) + comprobaciones ad hoc en controladores (director/estudiante del proyecto, asignación evaluador).

**Patrón de entregas:** doble vínculo proyecto–entrega — FK directa `entrega.proyecto_id` y tabla pivote `entrega_proyecto`. Los scopes `Entrega::paraProyecto()` y helpers en `EntregaController` unifican ambos caminos.

---

# Modelos

## User

### Responsabilidad
Entidad de autenticación y RBAC. Soporta login Google OAuth (internos) y credenciales (evaluadores externos). Gestiona lockout por intentos fallidos y cambio obligatorio de contraseña.

### Tabla asociada
`users`

### Relaciones Eloquent

| Relación | Tipo | Propósito |
|----------|------|-----------|
| `auditLogs` | hasMany → AuditLog | Trazas de auditoría generadas por el usuario |
| `authorizedEmailsCreated` | hasMany → AuthorizedEmail | Emails whitelisteados por este coordinador |
| `proyectosDirigidos` | hasMany → Proyecto | Proyectos donde actúa como director (cupos) |

### Campos importantes
- `role` (enum `UserRole`): Estudiante, Director, Coordinador, EvaluadorExterno
- `es_externo`: distingue evaluadores con login por contraseña
- `google_id`, `avatar`: perfil OAuth
- `totp_secret`: reservado para firma TOTP (bitácoras)
- `max_capacity`, `areas`: cupo y áreas de expertise del director
- `codigo_estudiante`: identificador académico
- `failed_attempts`, `locked_until`, `last_failed_at`: política de lockout
- `password_changed_at`: fuerza cambio en primer login externo

### Métodos importantes
- `isLocked()`: cuenta bloqueada por ventana deslizante de intentos fallidos
- `mustChangePassword()`: externo sin cambio de contraseña previo
- `registerFailedLogin()` / `clearFailedLogin()`: gestión de lockout

### Scopes
Ninguno definido en el modelo.

### Accessors / Mutators
Ninguno personalizado. Casts: `role` → enum, `password` → hashed.

### Observaciones
Núcleo del módulo de auth. Coordinadores nunca se bloquean por lockout (solo aplica a `loginExterno`).

---

## Proyecto

### Responsabilidad
Unidad central del dominio: proyecto de grado con código auto-generado, fase, estado y miembros.

### Tabla asociada
`proyectos`

### Relaciones Eloquent

| Relación | Tipo | Propósito |
|----------|------|-----------|
| `semestre` | belongsTo → Semestre | Periodo académico |
| `director` | belongsTo → User | Director asignado |
| `estudiantes` | belongsToMany → User (pivote `proyecto_estudiante`) | Integrantes del equipo |
| `entregas` | hasMany → Entrega | Entregas con FK directa |
| `entregasPivot` | belongsToMany → Entrega (pivote `entrega_proyecto`) | Entregas vinculadas por pivote |
| `bitacoras` | hasMany → Bitacora | Reuniones de supervisión |

### Campos importantes
- `code`: generado automáticamente (`PG-{semestre}{secuencial}`)
- `title`, `semester_id`, `director_id`
- `current_phase` (enum `FaseProyecto`)
- `status` (enum `EstadoProyecto`)
- `requires_group_justification`: obligatorio si hay 3 estudiantes
- `alert_count`: contador de alertas para KPIs

### Métodos importantes
Ninguno público además de relaciones.

### Scopes
- `enSemestresActivos()`: filtra proyectos cuyo semestre tiene `is_active = true`

### Accessors / Mutators
Ninguno.

### Observaciones
Al crear, el hook `booted::creating` asigna `code` según semestre y conteo. Modelo más referenciado del sistema.

---

## Entrega

### Responsabilidad
Entrega documental por fase del proyecto, con ciclo de vida (solicitud → habilitación → envío → revisión) y versionado de archivos.

### Tabla asociada
`entregas`

### Relaciones Eloquent

| Relación | Tipo | Propósito |
|----------|------|-----------|
| `proyecto` | belongsTo → Proyecto | Proyecto principal (FK directa) |
| `proyectos` | belongsToMany → Proyecto (pivote `entrega_proyecto`) | Proyectos adicionales vinculados |
| `semestre` | belongsTo → Semestre | Semestre/grupo de la entrega |
| `versiones` | hasMany → VersionDocumento | Archivos subidos |
| `evaluaciones` | hasMany → Evaluacion | Calificaciones de evaluadores |

### Campos importantes
- `phase`, `title`, `description`, `acceptance_criteria`
- `due_date`, `start_date`, `start_time`, `hora_maxima`: ventana de entrega
- `status` (enum `EstadoEntrega`)
- `consolidated_grade`, `evaluation_complete`: resultado de revisión del director
- `semester_id`: usado como `grupo_id` en filtros del frontend

### Métodos importantes
- `firstProyecto()`: resuelve proyecto desde pivote o FK directa

### Scopes
- `porFase(phase)`, `porEstado(status)`
- `paraProyecto(proyectoId)`: busca en FK y pivote (consulta reutilizable clave)

### Accessors
- `grupo_id` (appended): expone `semester_id` del proyecto o de la entrega

### Mutators
Ninguno.

### Observaciones
Segundo modelo más crítico. `EntregaController` concentra la mayor parte de la lógica transaccional (subida, revisión, avance de fase).

---

## Bitacora

### Responsabilidad
Registro de reuniones de supervisión con flujo de firmas estudiante → director.

### Tabla asociada
`bitacoras`

### Relaciones Eloquent

| Relación | Tipo | Propósito |
|----------|------|-----------|
| `proyecto` | belongsTo → Proyecto | Proyecto supervisado |

### Campos importantes
- `topic`, `notes`, `evidence_file`, `meeting_date`, `duration_hours`
- `signature_status` (enum `EstadoFirma`)
- `student_signed_at`, `director_signed_at`

### Scopes
- `porEstado(status)`

### Observaciones
Estados: Pendiente → FirmadaEstudiante → Completada (o firma directa del director). Detección de firmas sospechosas (>3 en 5 min) en el controlador.

---

## VersionDocumento

### Responsabilidad
Versión numerada de un archivo PDF/DOCX asociado a una entrega.

### Tabla asociada
`versiones_documento`

### Relaciones Eloquent

| Relación | Tipo | Propósito |
|----------|------|-----------|
| `entrega` | belongsTo → Entrega | Entrega padre |

### Campos importantes
- `version_number`, `file_path`, `file_size`, `original_name`
- `director_notes`: observaciones del director sobre esa versión
- `uploaded_at`

### Scopes
- `ultima()`: orden descendente por número de versión

---

## Evaluacion

### Responsabilidad
Calificación por criterio y porcentaje de un evaluador sobre una entrega.

### Tabla asociada
`evaluaciones`

### Relaciones Eloquent

| Relación | Tipo | Propósito |
|----------|------|-----------|
| `entrega` | belongsTo → Entrega | Entrega evaluada |
| `evaluador` | belongsTo → User | Evaluador (director externo o EvaluadorExterno) |

### Campos importantes
- `criterio`, `percentage`, `grade`, `comment`, `evaluated_at`

### Observaciones
La suma de `percentage` por evaluador/entrega no puede exceder 100 %.

---

## EvaluadorProyecto

### Responsabilidad
Asignación de evaluadores (2–3) a un proyecto con fecha, horario y fase de evaluación.

### Tabla asociada
`evaluador_proyecto`

### Relaciones Eloquent

| Relación | Tipo | Propósito |
|----------|------|-----------|
| `proyecto` | belongsTo → Proyecto | Proyecto a evaluar |
| `evaluador` | belongsTo → User | Usuario evaluador |

### Campos importantes
- `invitation_status` (enum `EstadoInvitacionEvaluador`)
- `fecha`, `hora_inicio`, `hora_fin`, `fase` (Anteproyecto | Final)
- `assigned_at`

---

## Semestre

### Responsabilidad
Periodo académico con ventana de fechas y flag de activo (máx. 2 activos simultáneos).

### Tabla asociada
`semestres`

### Relaciones Eloquent

| Relación | Tipo | Propósito |
|----------|------|-----------|
| `proyectos` | hasMany → Proyecto | Proyectos del periodo |

### Scopes
- `activos()`: `is_active = true`

---

## Anuncio

### Responsabilidad
Comunicados institucionales publicados por coordinadores.

### Tabla asociada
`anuncios`

### Relaciones Eloquent

| Relación | Tipo | Propósito |
|----------|------|-----------|
| `author` | belongsTo → User | Coordinador autor |

### Campos importantes
- `title`, `content`, `published_at`, `is_active`

---

## RecursoInformativo

### Responsabilidad
Material de apoyo (archivo o enlace) con contador de accesos.

### Tabla asociada
`recursos_informativos`

### Relaciones Eloquent

| Relación | Tipo | Propósito |
|----------|------|-----------|
| `author` | belongsTo → User | Autor (coordinador) |

### Accessors
- `file_size` (appended): tamaño legible calculado desde Storage

---

## Notificacion

### Responsabilidad
Alertas in-app generadas por eventos (bitácoras firmadas, entregas revisadas).

### Tabla asociada
`notificaciones`

### Relaciones Eloquent

| Relación | Tipo | Propósito |
|----------|------|-----------|
| `user` | belongsTo → User | Destinatario |
| `sender` | belongsTo → User | Emisor |

### Campos importantes
- `type`, `title`, `content`, `is_read`, `sent_at`

---

## AuthorizedEmail

### Responsabilidad
Whitelist de emails `@unab.edu.co` autorizados para OAuth Google, con rol preasignado.

### Tabla asociada
`authorized_emails` (SoftDeletes)

### Relaciones Eloquent

| Relación | Tipo | Propósito |
|----------|------|-----------|
| `creator` | belongsTo → User | Coordinador que agregó la entrada |

### Observaciones
Solo roles internos (Estudiante, Director, Coordinador). Evaluadores externos se crean directamente en `users`.

---

## AuditLog

### Responsabilidad
Registro append-only e inmutable de acciones del sistema (login, cambios de rol, entregas, etc.).

### Tabla asociada
`audit_logs` (sin `updated_at`)

### Relaciones Eloquent

| Relación | Tipo | Propósito |
|----------|------|-----------|
| `user` | belongsTo → User | Actor (nullable para eventos de sistema) |

### Scopes
- `forUser`, `forAction`, `betweenDates`, `ordered`

### Métodos importantes
- `save()` / `delete()` / builder custom: bloquean UPDATE y DELETE (inmutabilidad)

---

# Controladores

## AuthController (`Auth/`)

### Responsabilidad
Autenticación dual: Google OAuth (internos UNAB) y credenciales (evaluadores externos). Sesión Sanctum cookie, single-session y auditoría.

### Métodos públicos

| Método | Ruta | Propósito | Modelos | Validaciones | Página React |
|--------|------|-----------|---------|--------------|--------------|
| `redirectToGoogle` | GET `/auth/redirect` | Inicia OAuth Google con dominio `unab.edu.co` | — | Config Google en `.env` | `LoginInstitucional` |
| `handleGoogleCallback` | GET `/auth/callback` | Triple validación (hd, sufijo email, whitelist), crea/actualiza User, login, redirect dashboard | User, AuthorizedEmail | OAuth + whitelist | Dashboard por rol |
| `loginExterno` | POST `/api/auth/externo/login` | Login evaluador externo con lockout | User | `LoginExternoRequest` | `LoginExterno` |
| `changePassword` | POST `/api/auth/change-password` | Cambio de contraseña obligatorio/voluntario | User | `ChangePasswordRequest` | Flujo post-login externo |
| `logout` | POST `/api/auth/logout` | Cierra sesión, invalida cookie | User | — | Cualquiera |
| `sessionCheck` | GET `/api/auth/user` | Devuelve usuario autenticado | User | — | `useAuth` (global) |

---

## ProyectoController (`Admin/`)

### Responsabilidad
CRUD de proyectos y KPIs del coordinador. Solo accesible con rol Coordinador.

### Métodos públicos

| Método | Ruta | Propósito | Modelos / relaciones | Página React |
|--------|------|-----------|----------------------|--------------|
| `index` | GET `/api/admin/proyectos` | Lista con filtros `semestre_activo`, `search` | Proyecto + semestre, director, estudiantes | `GestionProyectos`, hooks `useProyectos` |
| `show` | GET `/api/admin/proyectos/{id}` | Detalle con entregas mergeadas (FK + pivote) | Proyecto + entregas, entregasPivot | `SupervisionReadOnly` |
| `store` | POST `/api/admin/proyectos` | Crea proyecto, adjunta estudiantes (máx. 3, únicos) | Proyecto, User | `GestionProyectos` |
| `kpis` | GET `/api/admin/proyectos/kpis` | Contadores activos, en riesgo, alertas, tasa cumplimiento | Proyecto (scope activos) | `CoordinadorDashboard` |

---

## EntregaController (`Admin/`)

### Responsabilidad
Ciclo completo de entregas: creación masiva por semestre, listado RBAC, versionado, revisión del director, banco de finales.

### Métodos públicos

| Método | Ruta | Propósito | RBAC / consultas clave | Página React |
|--------|------|-----------|------------------------|--------------|
| `index` | GET `/api/admin/entregas` | Lista filtrable por rol, grupo, proyecto, fase | Scope por Director/Estudiante/Coordinador | `CoordinadorEntregas`, dashboards |
| `show` | GET `/api/admin/entregas/{id}` | Detalle con versiones y estudiantes | Autorización director/estudiante/coordinador | `DetalleEntrega*`, `RevisionEntregaDirector` |
| `store` | POST `/api/admin/entregas` | Crea una entrega por proyecto activo del semestre | Solo Coordinador | `CoordinadorEntregas` |
| `update` | PUT `/api/admin/entregas/{id}` | Edita fechas, fase, criterios | Solo Coordinador | `CoordinadorEntregas` |
| `destroy` | DELETE `/api/admin/entregas/{id}` | Elimina entrega | Solo Coordinador | `CoordinadorEntregas` |
| `subirVersion` | POST `/api/entregas/{id}/versiones` | Sube PDF/DOCX (máx. 4), valida ventana temporal | Estudiante del proyecto | `DetalleEntregaEstudiante` |
| `solicitar` | POST `/api/entregas/{id}/solicitar` | Pasa de `creacion` → `solicitada` | Estudiante + AuditLog | `DetalleEntregaEstudiante` |
| `habilitar` | PUT `/api/admin/entregas/{id}/habilitar` | Pasa de `solicitada` → `pendiente` | Director del proyecto | `RevisionEntregaDirector` |
| `revisar` | PUT `/api/admin/entregas/{id}/revisar` | Aprueba/rechaza, notas, avance de fase automático | Director + Notificacion | `RevisionEntregaDirector` |
| `versiones` | GET `/api/entregas/{id}/versiones` | Historial de versiones | Estudiante (scoped) / otros roles | Vistas de entrega |
| `eliminarVersion` | DELETE `/api/entregas/{id}/versiones/{vid}` | Borra versión sin observaciones del director | Estudiante | `DetalleEntregaEstudiante` |
| `finales` | GET `/api/admin/entregas/finales` | Banco documentos aprobados paginado | Solo Coordinador | Reportes / banco documentos |

**Helpers privados reutilizables:** `esEstudianteDeEntrega`, `esDirectorDeEntrega`, `autoAdvancePhase`.

---

## UserController (`Admin/`)

### Responsabilidad
Gestión de usuarios, whitelist OAuth y creación de evaluadores externos.

### Métodos públicos

| Método | Ruta | Propósito | Página React |
|--------|------|-----------|--------------|
| `usuarios` | GET `/api/admin/usuarios` | Lista paginada con búsqueda y filtro por rol | `GestionUsuarios` |
| `updateUsuario` | PUT `/api/admin/usuarios/{user}` | Cambia rol y código estudiante | `GestionUsuarios` |
| `destroyUsuario` | DELETE `/api/admin/usuarios/{user}` | Elimina user y entrada whitelist | `GestionUsuarios` |
| `storeExternal` | POST `/api/admin/evaluadores` | Crea evaluador externo con contraseña temporal | `AsignacionEvaluadores`, `GestionUsuarios` |
| `index` | GET `/api/admin/whitelist` | Lista whitelist | `GestionUsuarios` |
| `store` | POST `/api/admin/whitelist` | Agrega email UNAB + crea User placeholder | `GestionUsuarios` |
| `update` | PUT `/api/admin/whitelist/{id}` | Cambia rol whitelist y sincroniza User | `GestionUsuarios` |
| `destroy` | DELETE `/api/admin/whitelist/{id}` | Elimina whitelist y User asociado | `GestionUsuarios` |

---

## BitacoraController (`Api/`)

### Responsabilidad
CRUD de bitácoras, firma multi-actor, horas acumuladas y vista coordinador por proyecto.

### Métodos públicos

| Método | Ruta | Propósito | Página React |
|--------|------|-----------|--------------|
| `index` | GET `/api/bitacoras?proyecto_id=` | Lista bitácoras del proyecto | `BitacorasEstudiante`, `BitacorasDirector` |
| `store` | POST `/api/bitacoras` | Crea bitácora en estado Pendiente | `NuevaBitacora` |
| `show` | GET `/api/bitacoras/{id}` | Detalle | `RevisionBitacora*` |
| `update` | PUT `/api/bitacoras/{id}` | Edita solo si Pendiente | `RevisionBitacoraEstudiante` |
| `firmar` | POST `/api/bitacoras/{id}/firmar` | Flujo de firmas + notificaciones | `DetalleFirmaBitacora`, `RevisionBitacoraDirector` |
| `porProyecto` | GET `/api/admin/proyectos/{id}/bitacoras` | Vista admin con campos adaptados al frontend | `VerBitacorasCoordinador`, `DirectoresPage` |
| `horas` | GET `/api/director/proyectos/{id}/horas` | Suma `duration_hours` | Supervisión director |

**Helper:** `tieneAccesoAProyecto` (coordinador, director o estudiante del proyecto).

---

## EstudianteController (`Api/`)

### Responsabilidad
Datos agregados del proyecto activo del estudiante autenticado.

### Métodos públicos

| Método | Ruta | Propósito | Relaciones cargadas | Página React |
|--------|------|-----------|---------------------|--------------|
| `proyecto` | GET `/api/estudiante/proyecto` | Proyecto + entregas (`paraProyecto` + versiones) | director, estudiantes, semestre, entregas.versiones | `EstudianteDashboard` |
| `entregas` | GET `/api/estudiante/entregas` | Lista resumida de entregas | versiones | `DetalleEntregaEstudiante`, hooks `useEntregas` |

---

## DirectorController (`Api/`)

### Responsabilidad
Dashboard y supervisión del director: proyectos, KPIs, entregas pendientes, bitácoras y rol evaluador.

### Métodos públicos

| Método | Ruta | Propósito | Página React |
|--------|------|-----------|--------------|
| `proyectos` | GET `/api/director/proyectos` | Proyectos en semestres activos | `DirectorDashboard`, `SeleccionProyectosBitacoras` |
| `kpis` | GET `/api/director/kpis` | Supervisando, pendientes, alertas, aprobadas mes | `DirectorDashboard` |
| `entregas` | GET `/api/director/entregas` | Top 20 entregas `enviada` urgentes | `DirectorDashboard` |
| `bitacoras` | GET `/api/director/proyectos/{id}/bitacoras` | Bitácoras del proyecto | `BitacorasProyecto` |
| `proyectoDetalle` | GET `/api/director/proyectos/{id}` | Detalle supervisión + entregas | `SupervisionProyectoDirector` |
| `evaluaciones` | GET `/api/director/evaluaciones` | Proyectos donde es evaluador (no director) | `EvaluacionesDirector` |
| `entregaFase` | GET `/api/director/proyectos/{id}/entrega-fase?fase=` | Entrega aprobada de una fase | `EvaluacionesDirector`, `EvaluadorCalificar` |

---

## EvaluacionController (`Api/`)

### Responsabilidad
Registro y consulta de evaluaciones por criterio; consolidado ponderado por entrega.

### Métodos públicos

| Método | Ruta | Propósito | Página React |
|--------|------|-----------|--------------|
| `index` | GET `/api/evaluaciones` | Lista propia o agrupada por proyecto | `EvaluacionesDirector`, evaluador |
| `store` | POST `/api/evaluaciones` | Crea criterio (valida asignación + suma ≤ 100 %) | `EvaluadorCalificar` |
| `consolidado` | GET `/api/evaluaciones/{entrega_id}/consolidado` | Promedio ponderado | Reportes, revisión |

---

## AnuncioController (`Api/`)

### Responsabilidad
Lectura pública de anuncios activos; CRUD admin solo coordinador.

| Método | Ruta | RBAC | Página React |
|--------|------|------|--------------|
| `index` | GET `/api/anuncios` | Todos autenticados | `AnunciosPublica` |
| `show` | GET `/api/anuncios/{id}` | Todos | `AnuncioDetalle` |
| `store/update/destroy` | `/api/admin/anuncios` | Coordinador | `AnunciosAdmin` |

---

## RecursoController (`Api/`)

### Responsabilidad
Recursos informativos con archivos en disco `public`.

| Método | Ruta | RBAC | Página React |
|--------|------|------|--------------|
| `index/show` | GET `/api/recursos` | Todos | `Recursos`, `RecursoDetalle` |
| `store/update/destroy` | `/api/admin/recursos` | Coordinador | `RecursosAdmin` |

`show` incrementa `access_count`.

---

## NotificacionController (`Api/`)

### Responsabilidad
Bandeja de notificaciones del usuario autenticado.

| Método | Ruta | Página React |
|--------|------|--------------|
| `index` | GET `/api/notificaciones` | Header / `useAlertas` |
| `noLeidas` | GET `/api/notificaciones/no-leidas` | Badge contador |
| `marcarLeida` | PUT `/api/notificaciones/{id}/leer` | Interacción UI |

---

## SemestreController (`Admin/`)

### Responsabilidad
CRUD de semestres con regla de máximo 2 activos.

| Método | Ruta | Página React |
|--------|------|--------------|
| `index/store/update/destroy` | `/api/admin/semestres` | `GestionProyectos`, formularios coordinador |

---

## EvaluadorProyectoController (`Admin/`)

### Responsabilidad
Asignación de 2–3 evaluadores por proyecto con validación de conflictos horarios.

| Método | Ruta | Página React |
|--------|------|--------------|
| `index` | GET `/api/admin/evaluador-proyecto` | `AsignacionEvaluadores` |
| `store` | POST | Crea 2–3 filas con misma fecha/fase | `AsignacionEvaluadores` |
| `update` | PUT `{id}` | Actualiza grupo por `proyecto_id` | `AsignacionEvaluadores` |
| `destroy` | DELETE `{id}` | Elimina todos los evaluadores del proyecto | `AsignacionEvaluadores` |

---

## DirectorCupoController (`Admin/`)

### Responsabilidad
Cupos de supervisión y listado de directores para el coordinador.

| Método | Ruta | Página React |
|--------|------|--------------|
| `index` | GET `/api/admin/directores/cupos` | Dashboard coordinador, cupos |
| `directores` | GET `/api/admin/directores` | `DirectoresPage` |
| `directorProyectos` | GET `/api/admin/directores/{id}/proyectos` | `DirectoresPage` |
| `update` | PUT `/api/admin/directores/{id}/cupo` | Gestión cupos |

---

## AuditLogController (`Admin/`)

### Responsabilidad
Visor paginado del log inmutable (solo coordinador).

| Método | Ruta | Filtros | Página React |
|--------|------|---------|--------------|
| `index` | GET `/api/admin/audit-logs` | user_id, action, date_from, date_to | `AuditLog` |

---

## ReporteController (`Admin/`)

### Responsabilidad
Reporte consolidado de notas por proyecto.

| Método | Ruta | Página React |
|--------|------|--------------|
| `consolidado` | GET `/api/admin/reportes/consolidado?proyecto_id=` | Reportes coordinador |

---

# Flujo Laravel → Frontend React

> **Nota:** El proyecto **no usa Inertia.js**. El flujo real es API JSON + SPA. La sección siguiente describe ese patrón equivalente al diagrama solicitado.

```
Ruta web (catch-all)
        ↓
view('app') → React Router monta la página
        ↓
Hook / página llama apiFetch('/api/...')
        ↓
Controller API → Eloquent (+ scopes/with)
        ↓
JsonResponse { data: ... }
        ↓
React renderiza estado local
```

### Ejemplo: dashboard estudiante

| Paso | Componente |
|------|------------|
| Ruta SPA | `/dashboard/estudiante` → `EstudianteDashboard` |
| Petición | `GET /api/estudiante/proyecto` |
| Controller | `EstudianteController@proyecto` |
| Consulta | `Proyecto` whereHas estudiantes + `Entrega::paraProyecto()` |
| Relaciones | director, estudiantes, semestre, entregas.versiones |
| Respuesta | JSON con proyecto y entregas ordenadas por `due_date` |

### Ejemplo: revisión de entrega (director)

| Paso | Componente |
|------|------------|
| Ruta SPA | `/director/entregas/:id` → `RevisionEntregaDirector` |
| Petición lectura | `GET /api/admin/entregas/{id}` |
| Petición acción | `PUT /api/admin/entregas/{id}/revisar` |
| Efectos colaterales | Actualiza `VersionDocumento.director_notes`, crea `Notificacion`, puede avanzar `Proyecto.current_phase` |

### Rutas web relevantes (`routes/web.php`)

| Ruta | Función |
|------|---------|
| `GET /up` | Health check |
| `GET /auth/redirect`, `/auth/callback` | OAuth Google |
| `GET /storage/{path}` | Sirve archivos públicos |
| `GET /{any?}` | SPA React (excluye `/api` y `/storage`) |

### Grupos middleware API (`routes/api.php`)

| Grupo | Middleware | Alcance |
|-------|------------|---------|
| Guest | `throttle:login` | Login externo |
| Autenticado | sanctum + single_session + activity | Mayoría de endpoints |
| Admin | + `role:Coordinador` | CRUD coordinador |
| Entregas admin | sanctum (sin role) | RBAC interno en controller |

---

# Mapa de reutilización

## Consultas reutilizables

| Patrón | Dónde vive | Usado en |
|--------|------------|----------|
| `Proyecto::enSemestresActivos()` | scope Proyecto | DirectorController, ProyectoController KPIs |
| `Entrega::paraProyecto($id)` | scope Entrega | EstudianteController, DirectorController, EntregaController |
| `Semestre::activos()` | scope Semestre | Filtros de periodo |
| `AuditLog::ordered()` + filtros | scopes AuditLog | AuditLogController |
| `esEstudianteDeEntrega` / `esDirectorDeEntrega` | EntregaController privados | Todos los métodos de entrega |
| `tieneAccesoAProyecto` | BitacoraController privado | CRUD bitácoras |
| Merge entregas FK + pivote | ProyectoController@show, EntregaController@autoAdvancePhase | Detalle proyecto, avance fase |

## Relaciones reutilizables

| Carga típica | Consumidores |
|--------------|--------------|
| `proyecto.semestre`, `proyecto.director`, `proyecto.estudiantes` | ProyectoController, DirectorController, ReporteController |
| `entrega.versiones` (orderByDesc version_number) | EntregaController@show, EstudianteController |
| `evaluadorProyecto.proyecto.estudiantes` | EvaluadorProyectoController, DirectorController@evaluaciones |

## Controladores principales

| Controlador | Rol en el sistema |
|-------------|-------------------|
| **AuthController** | Puerta de entrada; sesión y auditoría de login |
| **EntregaController** | Mayor lógica transaccional (versiones, revisión, fases) |
| **ProyectoController** | Alta y consulta de proyectos + KPIs globales |
| **BitacoraController** | Supervisión académica y firmas |
| **DirectorController** | Agregación para rol director |
| **UserController** | Identidad, whitelist y evaluadores externos |

## Modelos principales

| Modelo | Centralidad |
|--------|-------------|
| **User** | Auth, RBAC, directores, evaluadores |
| **Proyecto** | Hub del dominio; conecta semestre, equipo, entregas, bitácoras |
| **Entrega** | Ciclo documental y evaluación |
| **Bitacora** | Trazabilidad de supervisión |
| **Evaluacion** + **EvaluadorProyecto** | Calificación externa/interna |

---

# Referencia rápida de enums

| Enum | Valores clave |
|------|---------------|
| `EstadoEntrega` | creacion → solicitada → pendiente → enviada → revisada/aprobada/rechazada |
| `EstadoProyecto` | en_curso, en_riesgo, incumplimiento, completado |
| `FaseProyecto` | anteproyecto → presentacion_anteproyecto → desarrollo → presentacion_final |
| `EstadoFirma` | Pendiente → FirmadaEstudiante → Completada (+ Sospechosa) |
| `UserRole` | Estudiante, Director, Coordinador, EvaluadorExterno |

---

# Índice de endpoints por dominio

| Dominio | Prefijo API | Controlador |
|---------|-------------|-------------|
| Auth | `/api/auth/*`, `/auth/*` | AuthController |
| Proyectos | `/api/admin/proyectos` | ProyectoController |
| Entregas | `/api/admin/entregas`, `/api/entregas` | EntregaController |
| Bitácoras | `/api/bitacoras` | BitacoraController |
| Estudiante | `/api/estudiante` | EstudianteController |
| Director | `/api/director` | DirectorController |
| Evaluaciones | `/api/evaluaciones` | EvaluacionController |
| Usuarios / Whitelist | `/api/admin/usuarios`, `/api/admin/whitelist` | UserController |
| Semestres | `/api/admin/semestres` | SemestreController |
| Evaluadores | `/api/admin/evaluador-proyecto` | EvaluadorProyectoController |
| Directores / Cupos | `/api/admin/directores` | DirectorCupoController |
| Anuncios | `/api/anuncios`, `/api/admin/anuncios` | AnuncioController |
| Recursos | `/api/recursos`, `/api/admin/recursos` | RecursoController |
| Notificaciones | `/api/notificaciones` | NotificacionController |
| Auditoría | `/api/admin/audit-logs` | AuditLogController |
| Reportes | `/api/admin/reportes` | ReporteController |
