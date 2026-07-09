# Design: Backend Completo — Capa de Persistencia y API

**Change**: `backend-completo` | **Status**: Design approved
**Stack**: Laravel 11 + Sanctum + PostgreSQL 16 + Redis 7 | **Baseline**: 151 tests (Sprint 1)

---

## Technical Approach

Materializar el modelo de datos de la Fase 2 (fase2.md §3) como migraciones Eloquent + APIs REST, respetando las convenciones establecidas en Sprint 1: `declare(strict_types=1)`, enums PHP 8.3 con string backing, `$fillable` + `casts()`, `AuditEvent::dispatch()` en cada mutación, paginación con `min(per_page, 200)`, y rutas agrupadas por rol con middleware `auth:sanctum`.

---

## Architecture Decisions

| # | Decisión | Alternativas | Rationale |
|---|----------|-------------|-----------|
| D1 | **Enums PHP nativos** (FaseProyecto, EstadoEntrega, EstadoFirma, EstadoInvitacion, CategoriaRecurso) con string backing | Integer enums, Spatie Enum | Consistencia con `UserRole` existente. JSON estable, legible en DB, `match` expressions. |
| D2 | **Código auto-generado** `PG-{semestre}{correlativo}` con atomic counter en tabla `semestres.next_proyecto_seq` | UUID, secuencia PostgreSQL, trigger DB | Atómico bajo concurrencia (lock row-level en `semestres`). Reversible. Inmutable tras creación. |
| D3 | **Avance de fase automático** en observer/modelo: cuando última entrega de fase actual = `aprobada`, avanzar `fase_actual` | Job asíncrono, trigger DB, cron | Síncrono en la transición de entrega (transacción). AuditEvent inmediato. Sin eventual consistency. |
| D4 | **State machine explícita** en modelo Entrega: `canTransitionTo(EstadoEntrega $target): bool` | Spatie Model States, if/else en controller | 5 estados, 7 transiciones válidas. Método en modelo = testable unitariamente. Sin dependencia externa. |
| D5 | **Evaluación por criterio** hardcodeada en config (`config/evaluacion.php`) con pesos por fase | Tabla dinámica `criterios`, Spatie Permission | MVP: 4 fases × 3 criterios. Hardcoded evita N+1 y simplifica. Migrar a tabla si cambian los criterios. |
| D6 | **Notificaciones in-app síncronas + email vía `ShouldQueue`** en Redis | Todo asíncrono, todo síncrono | In-app debe aparecer inmediato (<5s spec). Email puede esperar. Redis queue con 3 retries + backoff. |
| D7 | **Rutas por rol** (`/api/admin/`, `/api/director/`, `/api/estudiante/`, `/api/evaluador/`) | API versionada `/api/v1/`, rutas planas | Consistencia con ADR-008. Auto-documentadas. Middleware `role:X` por grupo. |

---

## Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                      CLIENT (React SPA)                          │
│              Sanctum cookie · X-XSRF-TOKEN · apiFetch()          │
└────────────────────────────┬────────────────────────────────────┘
                             │ HTTPS
┌────────────────────────────▼────────────────────────────────────┐
│                    LARAVEL 11 (Backend)                          │
│                                                                  │
│  routes/api.php                                                  │
│    ├── /api/admin/*      → middleware: auth:sanctum + role:Coord │
│    ├── /api/director/*   → middleware: auth:sanctum + role:Dir   │
│    ├── /api/estudiante/* → middleware: auth:sanctum + role:Est   │
│    ├── /api/evaluador/*  → middleware: auth:sanctum + role:Eval  │
│    └── /api/shared/*     → middleware: auth:sanctum (any role)   │
│                                                                  │
│  Controller → FormRequest → Model → Eloquent                     │
│       │                        │                                 │
│       ├─ AuditEvent::dispatch  ├─ scopes + relationships         │
│       └─ Notification::create  └─ state machine (Entrega)        │
│                                │                                 │
│  Jobs (ShouldQueue):                                           │
│    SendNotificationEmail  →  Redis queue  →  Mail (SMTP)        │
└──────────┬──────────────────────┬───────────────────────────────┘
           │                      │
    ┌──────▼──────┐      ┌───────▼────────────────────────────────┐
    │  REDIS 7    │      │        POSTGRESQL 16 + pgvector        │
    │  sessions   │      │  semestres · proyectos · entregas      │
    │  cache      │      │  versiones_documento · bitacoras       │
    │  queues     │      │  evaluaciones · notificaciones         │
    └─────────────┘      │  anuncios · recursos_informativos      │
                         │  analisis_ia · sugerencia_director     │
    ┌──────────────┐     │  users · authorized_emails · audit_logs│
    │  FASTAPI     │     └────────────────────────────────────────┘
    │  (IA module) │
    │  HMAC HTTP   │──────► (Sprint futuro — fuera de scope)
    │  embeddings  │
    └──────────────┘
```

---

## File Changes

### Migrations (13 files, dependency order)

| # | File | Action | ~Lines | Description |
|---|------|--------|--------|-------------|
| 1 | `2026_07_XX_100001_create_semestres_table.php` | Create | 35 | `id, nombre(unique), activo(bool), next_proyecto_seq(int)` |
| 2 | `2026_07_XX_100002_create_proyectos_table.php` | Create | 50 | `id, codigo(unique), titulo, semestre_id(FK), director_id(FK→users), fase_actual(enum), estado(enum), created_at` |
| 3 | `2026_07_XX_100003_create_proyecto_estudiante_table.php` | Create | 35 | Pivot: `proyecto_id(FK), estudiante_id(FK→users), unique(proyecto_id,estudiante_id)` |
| 4 | `2026_07_XX_100004_create_evaluador_proyecto_table.php` | Create | 40 | Pivot: `proyecto_id(FK), evaluador_id(FK→users), estado_invitacion(enum), unique(proyecto_id,evaluador_id)` |
| 5 | `2026_07_XX_100005_create_entregas_table.php` | Create | 55 | `id, proyecto_id(FK), fase(enum), fecha_limite(date), estado(enum), nota_consolidada(decimal nullable), evaluacion_completa(bool)` |
| 6 | `2026_07_XX_100006_create_versiones_documento_table.php` | Create | 50 | `id, entrega_id(FK), numero_version(int), ruta_archivo, observaciones_director(text nullable), subido_en(timestamp)` |
| 7 | `2026_07_XX_100007_create_bitacoras_table.php` | Create | 55 | `id, proyecto_id(FK), tema, observaciones, evidencia_archivo(nullable), fecha_reunion(date), duracion(decimal nullable), estado_firma(enum), firma_estudiante_en, firma_director_en` |
| 8 | `2026_07_XX_100008_create_evaluaciones_table.php` | Create | 45 | `id, entrega_id(FK), evaluador_id(FK→users), nota(decimal), comentario(text), evaluado_en` |
| 9 | `2026_07_XX_100009_create_notificaciones_table.php` | Create | 40 | `id, usuario_id(FK), emisor_id(FK nullable), tipo, contenido(text), leida(bool), enviada_en` |
| 10 | `2026_07_XX_100010_create_anuncios_table.php` | Create | 35 | `id, autor_id(FK→users), titulo, contenido(text), fecha_publicacion, vigente(bool)` |
| 11 | `2026_07_XX_100011_create_recursos_informativos_table.php` | Create | 40 | `id, autor_id(FK→users), titulo, categoria(enum), ruta_archivo(nullable), contenido(text nullable), contador_accesos(int default 0)` |
| 12 | `2026_07_XX_100012_create_analisis_ia_table.php` | Create | 40 | `id, entrega_id(FK unique), embedding(vector pgvector), retroalimentacion_generada(text), score_coherencia(decimal), generado_en` |
| 13 | `2026_07_XX_100013_create_sugerencia_director_table.php` | Create | 35 | `id, estudiante_id(FK→users), propuesta_descrita(text), directores_sugeridos(json), generado_en` |

### Enums (5 files)

| File | Action | ~Lines | Values |
|------|--------|--------|--------|
| `app/Enums/FaseProyecto.php` | Create | 25 | `Anteproyecto, PresentacionAnteproyecto, Desarrollo, PresentacionFinal` |
| `app/Enums/EstadoEntrega.php` | Create | 35 | `Pendiente, Enviada, Revisada, Aprobada, Rechazada` + `canTransitionTo()` |
| `app/Enums/EstadoFirma.php` | Create | 20 | `Pendiente, FirmadaEstudiante, FirmadaDirector, Completada, Sospechosa` |
| `app/Enums/EstadoInvitacion.php` | Create | 15 | `Pendiente, Aceptada, Rechazada` |
| `app/Enums/CategoriaRecurso.php` | Create | 15 | `Reglamento, Cronograma, Plantilla, Formato, Otro` |

### Models (12 files)

| File | Action | ~Lines | Key relations |
|------|--------|--------|---------------|
| `app/Models/Semestre.php` | Create | 60 | `HasMany<Project>` |
| `app/Models/Proyecto.php` | Create | 130 | `BelongsTo<Semestre,User>`, `BelongsToMany<User>(estudiantes)`, `HasMany<Entrega,Bitacora>`, `BelongsToMany<User>(evaluadores)`. Scopes: `scopeActivos`, `scopeEnRiesgo`. Method: `avanzarFaseSiCorresponde()` |
| `app/Models/Entrega.php` | Create | 100 | `BelongsTo<Proyecto>`, `HasMany<VersionDocumento,Evaluacion>`. State machine: `canTransitionTo()`, `transicionar()` |
| `app/Models/VersionDocumento.php` | Create | 45 | `BelongsTo<Entrega>` |
| `app/Models/Bitacora.php` | Create | 80 | `BelongsTo<Proyecto>`. Method: `firmar(User)`, `detectarSospechosa()` |
| `app/Models/Evaluacion.php` | Create | 55 | `BelongsTo<Entrega,User>`. Method: `recalcularNotaConsolidada()` |
| `app/Models/Notificacion.php` | Create | 50 | `BelongsTo<User>(destinatario,emisor)`. Scope: `scopeUnread` |
| `app/Models/Anuncio.php` | Create | 40 | `BelongsTo<User>(autor)`. Scope: `scopeVigentes` |
| `app/Models/RecursoInformativo.php` | Create | 45 | `BelongsTo<User>(autor)`. Cast: `categoria→CategoriaRecurso` |
| `app/Models/AnalisisIa.php` | Create | 40 | `BelongsTo<Entrega>`. Cast: `embedding→vector` |
| `app/Models/SugerenciaDirector.php` | Create | 35 | `BelongsTo<User>(estudiante)`. Cast: `directores_sugeridos→array` |
| `app/Models/EvaluadorProyecto.php` | Create | 40 | Pivot model. `BelongsTo<Proyecto,User>`. Cast: `estado_invitacion→EstadoInvitacion` |

### Controllers (7 files)

| File | Action | ~Lines | Endpoints |
|------|--------|--------|-----------|
| `app/Http/Controllers/Admin/ProyectoController.php` | Create | 180 | CRUD proyectos, asignar estudiantes/evaluadores, dashboard KPIs, documentos finales, reporte calificaciones |
| `app/Http/Controllers/Admin/SemestreController.php` | Create | 80 | CRUD semestres |
| `app/Http/Controllers/EntregaController.php` | Create | 150 | CRUD entregas, subir versión, transicionar estados |
| `app/Http/Controllers/BitacoraController.php` | Create | 120 | CRUD bitácoras, firmar, horas acumuladas |
| `app/Http/Controllers/EvaluacionController.php` | Create | 80 | CRUD evaluaciones, aceptar/rechazar invitación |
| `app/Http/Controllers/AnuncioController.php` | Create | 70 | CRUD anuncios (admin), list (all roles) |
| `app/Http/Controllers/RecursoController.php` | Create | 70 | CRUD recursos (admin), list+filter (all), increment access |

### Form Requests (~15 files)

| File | Action | ~Lines |
|------|--------|--------|
| `app/Http/Requests/StoreProyectoRequest.php` | Create | 35 |
| `app/Http/Requests/UpdateProyectoRequest.php` | Create | 25 |
| `app/Http/Requests/AsignarEstudiantesRequest.php` | Create | 30 |
| `app/Http/Requests/StoreSemestreRequest.php` | Create | 25 |
| `app/Http/Requests/StoreEntregaRequest.php` | Create | 30 |
| `app/Http/Requests/SubirVersionRequest.php` | Create | 30 |
| `app/Http/Requests/TransicionarEntregaRequest.php` | Create | 25 |
| `app/Http/Requests/StoreBitacoraRequest.php` | Create | 30 |
| `app/Http/Requests/StoreEvaluacionRequest.php` | Create | 25 |
| `app/Http/Requests/StoreAnuncioRequest.php` | Create | 25 |
| `app/Http/Requests/StoreRecursoRequest.php` | Create | 25 |
| `app/Http/Requests/MarkNotificationsReadRequest.php` | Create | 20 |

### Routes + Seeders + Other

| File | Action | ~Lines | Description |
|------|--------|--------|-------------|
| `routes/api.php` | Modify | +180 | 4 grupos por rol + shared routes |
| `database/seeders/SemestreSeeder.php` | Create | 30 | 2025-1, 2025-2, 2026-1 |
| `database/seeders/DatabaseSeeder.php` | Modify | +5 | Call SemestreSeeder |
| `config/evaluacion.php` | Create | 30 | Pesos por fase y criterio |
| `database/factories/` (6 files) | Create | ~250 | Semestre, Proyecto, Entrega, Bitacora, Evaluacion, Notificacion |

---

## Interfaces / Contracts

### Admin routes (`/api/admin/*` — middleware: `auth:sanctum, role:Coordinador`)

| Method | URI | Controller#action | Description |
|--------|-----|-------------------|-------------|
| GET | `/admin/dashboard` | ProyectoController@dashboard | KPIs: activos, riesgo, alertas, tasa |
| GET | `/admin/semestres` | SemestreController@index | List semestres |
| POST | `/admin/semestres` | SemestreController@store | Create semestre |
| PUT | `/admin/semestres/{id}` | SemestreController@update | Update semestre |
| DELETE | `/admin/semestres/{id}` | SemestreController@destroy | Delete semestre |
| GET | `/admin/proyectos` | ProyectoController@index | List proyectos (paginated, filtered) |
| POST | `/admin/proyectos` | ProyectoController@store | Create proyecto (auto-código) |
| GET | `/admin/proyectos/{id}` | ProyectoController@show | Show with eager-load |
| PUT | `/admin/proyectos/{id}` | ProyectoController@update | Update proyecto |
| DELETE | `/admin/proyectos/{id}` | ProyectoController@destroy | Delete proyecto |
| POST | `/admin/proyectos/{id}/estudiantes` | ProyectoController@asignarEstudiantes | Assign 1-3 estudiantes |
| DELETE | `/admin/proyectos/{id}/estudiantes/{uid}` | ProyectoController@removerEstudiante | Remove estudiante |
| POST | `/admin/proyectos/{id}/evaluadores` | ProyectoController@asignarEvaluadores | Assign 2-3 evaluadores |
| GET | `/admin/proyectos/{id}/evaluadores` | ProyectoController@evaluadores | List evaluadores + status |
| GET | `/admin/proyectos/{id}/horas-bitacora` | BitacoraController@horasAcumuladas | Sum horas vs mínimo |
| GET | `/admin/documentos-finales` | ProyectoController@documentosFinales | Banco de documentos |
| GET | `/admin/reporte-calificaciones` | ProyectoController@reporteCalificaciones | Per-project breakdown |
| POST | `/admin/entregas` | EntregaController@store | Create entrega for proyecto |
| PUT | `/admin/entregas/{id}` | EntregaController@update | Update entrega (fecha, fase) |
| POST | `/admin/anuncios` | AnuncioController@store | Create anuncio |
| PUT | `/admin/anuncios/{id}` | AnuncioController@update | Update anuncio |
| DELETE | `/admin/anuncios/{id}` | AnuncioController@destroy | Delete anuncio |
| POST | `/admin/recursos` | RecursoController@store | Create recurso |
| PUT | `/admin/recursos/{id}` | RecursoController@update | Update recurso |
| DELETE | `/admin/recursos/{id}` | RecursoController@destroy | Delete recurso |

### Estudiante routes (`/api/estudiante/*` — middleware: `auth:sanctum, role:Estudiante`)

| Method | URI | Controller#action | Description |
|--------|-----|-------------------|-------------|
| GET | `/estudiante/proyectos` | ProyectoController@miProyecto | Own project with relations |
| GET | `/estudiante/entregas` | EntregaController@misEntregas | Own entregas by phase |
| POST | `/estudiante/entregas/{id}/versiones` | EntregaController@subirVersion | Upload document version |
| PUT | `/estudiante/entregas/{id}/enviar` | EntregaController@enviar | Transition pendiente→enviada |
| POST | `/estudiante/bitacoras` | BitacoraController@store | Create bitácora |
| PUT | `/estudiante/bitacoras/{id}/firmar` | BitacoraController@firmar | Sign bitácora |
| GET | `/estudiante/bitacoras` | BitacoraController@misBitacoras | Own bitácoras |

### Director routes (`/api/director/*` — middleware: `auth:sanctum, role:Director`)

| Method | URI | Controller#action | Description |
|--------|-----|-------------------|-------------|
| GET | `/director/proyectos` | ProyectoController@proyectosDirector | Projects directed (filtered by active semestre) |
| GET | `/director/proyectos/{id}/entregas` | EntregaController@entregasDirector | Deliverables for directed project |
| PUT | `/director/entregas/{id}/revisar` | EntregaController@revisar | Transition enviada→revisada |
| PUT | `/director/entregas/{id}/aprobar` | EntregaController@aprobar | Transition revisada→aprobada/rechazada |
| POST | `/director/bitacoras` | BitacoraController@store | Create bitácora |
| PUT | `/director/bitacoras/{id}/firmar` | BitacoraController@firmar | Sign bitácora |
| GET | `/director/bitacoras` | BitacoraController@misBitacoras | Own bitácoras |

### Evaluador routes (`/api/evaluador/*` — middleware: `auth:sanctum, role:EvaluadorExterno|Director`)

| Method | URI | Controller#action | Description |
|--------|-----|-------------------|-------------|
| GET | `/evaluador/invitaciones` | EvaluacionController@invitaciones | Pending invitations |
| POST | `/evaluador/invitaciones/{id}/aceptar` | EvaluacionController@aceptar | Accept invitation |
| POST | `/evaluador/invitaciones/{id}/rechazar` | EvaluacionController@rechazar | Decline invitation |
| GET | `/evaluador/proyectos` | ProyectoController@proyectosEvaluador | Assigned projects |
| POST | `/evaluador/entregas/{id}/evaluaciones` | EvaluacionController@store | Submit evaluation |
| GET | `/evaluador/evaluaciones` | EvaluacionController@misEvaluaciones | Own evaluations |

### Shared routes (`/api/*` — middleware: `auth:sanctum`)

| Method | URI | Controller#action | Description |
|--------|-----|-------------------|-------------|
| GET | `/proyectos/{id}` | ProyectoController@show | Any role, eager-loaded |
| GET | `/anuncios` | AnuncioController@index | Vigentes, ordered DESC |
| GET | `/recursos` | RecursoController@index | Filter by categoria, search |
| GET | `/recursos/{id}` | RecursoController@show | Increment contador_accesos |
| GET | `/notificaciones` | NotificacionController@index | Unread + count header |
| PATCH | `/notificaciones/read` | NotificacionController@markRead | Mark specified as read |

**Total: ~42 endpoints**

---

## Testing Strategy

| Layer | What | Approach |
|-------|------|----------|
| **Unit** | Enums (`canTransitionTo`), Model logic (`avanzarFaseSiCorresponde`, `recalcularNotaConsolidada`, `detectarSospechosa`), auto-código generation | Pest unit tests. Table-driven: each state transition valid/invalid. |
| **Feature** | All ~42 endpoints | Pest feature tests with `RefreshDatabase`. Assert HTTP status, JSON structure, DB state, AuditEvent dispatched, Notification created. Assert query count ≤4 for show endpoints (eager load). |
| **Integration** | Notification email queue, file upload validation, version limit (4 max), phase auto-advance cascade | Test `ShouldQueue` jobs with `Queue::fake()`. Test file upload rejects >50MB, non-PDF/DOCX. Test 5th version → 422. |
| **Regression** | 151 baseline tests must remain green | CI gate: `php artisan test` all green before merge. |

---

## Migration / Rollout

### Migration order (4 layers)

1. **Base**: `semestres` → depends on nothing new
2. **Business**: `proyectos` → `proyecto_estudiante` → `evaluador_proyecto` → `entregas`
3. **Documental**: `versiones_documento` → `bitacoras` → `evaluaciones`
4. **System**: `notificaciones` → `anuncios` → `recursos_informativos` → `analisis_ia` → `sugerencia_director`

### Rollback

`php artisan migrate:rollback --step=13` (reverse order). Foreign keys with `onDelete('cascade')` clean child rows.

### Feature flags

None required. All tables are additive — no existing tables modified.

### Reversibility

All 13 migrations implement `down()` with `Schema::dropIfExists()`. Data loss on rollback is acceptable per proposal (branch-isolated, no production data yet).

---

## Open Questions

- [ ] `analisis_ia.embedding` uses pgvector `vector(384)` dimension — confirm model output size matches `paraphrase-multilingual-MiniLM-L12-v2` (384 dims).
- [ ] `bitacoras.duracion` — is this in hours or minutes? Spec says "sum duracion" vs "minimum hours". Assume decimal hours.
- [ ] Auto-código counter: is row-level lock on `semestres.next_proyecto_seq` sufficient, or do we need `SELECT ... FOR UPDATE` in a transaction? (Likely sufficient for single-VM.)

---

## Next Step

Ready for tasks (sdd-tasks).
