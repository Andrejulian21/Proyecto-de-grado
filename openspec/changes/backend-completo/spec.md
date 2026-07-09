# Spec: Backend Completo — Capa de Persistencia y API

**Change**: `backend-completo` &nbsp;&nbsp;|&nbsp;&nbsp; **Status**: Full spec (greenfield — no existing specs to delta against)
**Stack**: Laravel 11 + Sanctum + PostgreSQL 16 + Redis 7 &nbsp;&nbsp;|&nbsp;&nbsp; **Coverage**: HU04–HU28, HU34–HU36, RF06–RF22, RF24–RF25, RF29–RF31, RF38–RF40
**Format**: EARS (WHEN/THEN/SHALL)

---

## Domain: `semestres`

> Trazabilidad: HU04, RF06; modelo fase2.md: `SEMESTRE(id, nombre, activo)`

### Requirement: CRUD de Semestres (Coordinador)

Semesters represent academic periods (e.g., `2025-1`). ONLY users with role `Coordinador` SHALL create, update, or delete semesters. All users SHALL read the active semester list.

- **WHEN** the coordinador creates a semester with `nombre` and `activo=true`
  **THEN** the system SHALL persist the record and return HTTP 201. (HU04, RF06)

- **WHEN** the coordinador updates a semester's `nombre` or `activo` flag
  **THEN** the system SHALL validate uniqueness of `nombre` and return HTTP 200. (HU04, RF06)

- **WHEN** a user with role `Estudiante` or `Director` attempts to create/update/delete a semester
  **THEN** the system SHALL reject with HTTP 403. (RF02)

### Requirement: Activation/Deactivation of Semesters

Only the active semester SHALL appear in director dashboards. Projects linked to an inactive semester SHALL be hidden from the director dashboard filter.

- **WHEN** the coordinador sets `activo=false` on a semester
  **THEN** projects of that semester SHALL be excluded from `GET /api/director/proyectos` default listing. (HU06, RF08)

- **WHEN** a third semester is set to `activo=true`
  **THEN** the system SHALL allow up to two active semesters simultaneously (projects span across consecutive semesters). (HU04, RF06)

### Requirement: Seeders for Historical Semesters

- **WHEN** `php artisan db:seed` runs
  **THEN** the system SHALL pre-populate semesters `2025-1`, `2025-2`, and `2026-1` with `activo` flags matching the current date. (HU04)

---

## Domain: `proyectos`

> Trazabilidad: HU04–HU08, RF06–RF10; modelo: `PROYECTO`, `PROYECTO_ESTUDIANTE`

### Requirement: CRUD de Proyectos (Coordinador)

ONLY `Coordinador` SHALL create, update, or delete a project. The project code SHALL be auto-generated and immutable.

- **WHEN** the coordinador creates a project with `titulo`, `semestre_id`, `director_id`, `fase_actual`, and `estado`
  **THEN** the system SHALL auto-assign a code in format `PG-{semestre}{correlativo}`, e.g., `PG-2026205`. (HU04, RF06)

- **WHEN** a project is created
  **THEN** the corresponding `director_id` SHALL be a `User` with role `Director`. If not, the system SHALL reject with HTTP 422. (HU21, RF24)

- **WHEN** any user queries `GET /api/proyectos/{id}`
  **THEN** the system SHALL eager-load `estudiantes`, `director`, `semestre`, and `entregas`. (RF06)

### Requirement: Student Assignment with Limits

- **WHEN** the coordinador assigns students to a project via `POST /api/admin/proyectos/{id}/estudiantes`
  **THEN** the system SHALL validate: 1–2 students always allowed; 3 students REQUIRE a non-empty `justificacion` field. (HU04, RF06)

- **IF** `justificacion` is empty with 3 students
  **THEN** the system SHALL reject with HTTP 422 and message "Tres estudiantes requieren una justificación escrita". (HU04, RF06)

### Requirement: Manual Phase Change

- **WHEN** the coordinador marks the last pending delivery of the current phase as `aprobada`
  **THEN** the system SHALL automatically transition the project to the next phase AND record the change in audit_logs (actor, project, old_phase, new_phase). (HU05, RF07)

- **WHEN** a delivery is marked `aprobada` but other deliveries in the same phase are still pending
  **THEN** the system SHALL NOT advance the phase. (HU05, RF07)

### Requirement: Project Statuses

Allowed statuses: `en_curso`, `en_riesgo`, `incumplimiento`, `completado`. Phases: `anteproyecto`, `presentacion_anteproyecto`, `desarrollo`, `presentacion_final`.

- **WHEN** a project has missed ≥2 of 3 entregas in a phase
  **THEN** the system SHALL auto-transition `estado` to `en_riesgo` and trigger a notification to estudiante, director, and coordinador. (HU08, RF10)

- **WHEN** all entregas of all phases are `aprobada`
  **THEN** the system SHALL transition `estado` to `completado`. A completado project SHALL be read-only for all roles except `Coordinador`. (HU08)

### Requirement: Dashboard KPIs

- **WHEN** the coordinador requests `GET /api/admin/dashboard`
  **THEN** the system SHALL return: `proyectos_activos`, `proyectos_en_riesgo`, `alertas_sin_revisar`, and `tasa_cumplimiento`. (HU06, RF08)

- **WHEN** querying proyectos for the director dashboard
  **THEN** the system SHALL filter by `semestre.activo=true` by default. (HU06)

---

## Domain: `entregas`

> Trazabilidad: HU09–HU14, RF11–RF16; modelo: `ENTREGA`, `VERSION_DOCUMENTO`

### Requirement: CRUD de Entregas

Entregas belong to a `proyecto` and a `fase`. They have a `fecha_limite` and a `nota_consolidada` (nullable).

- **WHEN** the coordinador creates an entrega for a project with `fase`, `fecha_limite`
  **THEN** the system SHALL create the record in `estado=pendiente`. (HU09, RF11)

- **WHEN** a student uploads a file for an entrega
  **THEN** the system SHALL validate: format is PDF or DOCX, size ≤50 MB. If invalid, HTTP 422 with message "Solo se aceptan archivos PDF o DOCX de máximo 50 MB". (HU09, RF11)

### Requirement: Delivery States and Transitions

States: `pendiente` → `enviada` → `revisada` → `aprobada` / `rechazada`.

- **WHEN** the estudiante transitions an entrega from `pendiente` to `enviada`
  **THEN** the director SHALL receive an in-app notification + email. (HU09, RF11)

- **WHEN** an entrega is `enviada`
  **THEN** only `Director` or `Coordinador` SHALL advance it to `revisada`. Estudiante attempt SHALL return HTTP 403. (HU12, RF14)

- **WHEN** the director sets an entrega to `revisada` with `observaciones_director`
  **THEN** the estudiante SHALL receive a notification. (HU11, RF13)

- **WHEN** the director transitions from `revisada` to `aprobada` or `rechazada`
  **THEN** `nota_consolidada` and `comentario` are REQUIRED; if missing, HTTP 422. (HU13, RF15)

- **WHEN** an entrega is `rechazada`
  **THEN** the student MAY upload a new version; the entrega resets to `pendiente`. (HU10, RF12)

### Requirement: Versioning

- **WHEN** a student uploads a new document for an entrega
  **THEN** the system SHALL auto-increment `numero_version` and store the file under `versiones_documento` with metadata including `ruta_archivo`, `subido_en`. (HU10, RF12)

- **IF** `numero_version` would exceed 4
  **THEN** the system SHALL reject with HTTP 422 and message "Máximo 4 versiones permitidas por entrega". (HU10, RF12)

### Requirement: Document Bank

- **WHEN** the coordinador queries `GET /api/admin/documentos-finales`
  **THEN** the system SHALL return all `VERSION_DOCUMENTO` records of entregas in `presentacion_final` with estado `aprobada`, filterable by `fecha`, `director`, and `estado`. (HU14, RF16)

---

## Domain: `bitacoras`

> Trazabilidad: HU15–HU19, RF17–RF22; modelo: `BITACORA`

### Requirement: CRUD de Bitácoras

- **WHEN** a student or director creates a bitácora with `tema`, `observaciones`, `fecha_reunion`, and optional `evidencia_archivo`
  **THEN** the system SHALL create the record in `estado_firma=pendiente`. (HU15, RF17)

- **WHEN** the bitácora is created
  **THEN** both the student and director linked to the project SHALL receive a notification. (RF22)

### Requirement: Signature States and Flow

States: `pendiente` → `firmada_estudiante` → `firmada_director` → `completada`. Also `sospechosa` (auto-detected).

- **WHEN** a student signs the bitácora
  **THEN** the system SHALL set `estado_firma=firmada_estudiante` and record `firma_estudiante_en` server-side timestamp. (HU16, RF18)

- **WHEN** the director signs the bitácora
  **THEN** the system SHALL set `estado_firma=completada` and record `firma_director_en`. (HU16, RF18)

- **WHEN** both parties have signed
  **THEN** the bitácora SHALL be immutable (no further edits). (HU16)

### Requirement: Suspicious Signature Detection

- **WHEN** the same director signs ≥3 bitácoras within any 5-minute window
  **THEN** the system SHALL auto-set the third (and subsequent) bitácoras' `estado_firma` to `sospechosa` and create a visible alert for the coordinador. (HU17, RF19)

- **WHEN** the coordinador views the alert dashboard
  **THEN** suspicious bitácoras SHALL be listed with link to detail view including timestamp pattern. (HU17, RF19)

### Requirement: Minimum Hours Control

- **WHEN** the coordinador queries `GET /api/admin/proyectos/{id}/horas-bitacora`
  **THEN** the system SHALL sum `duracion` across all bitácoras for the project and compare against the semester-configured minimum. (HU18, RF20)

- **IF** total hours are below the configured minimum at mid-semester
  **THEN** the system SHALL generate an alert visible on the coordinador dashboard. (HU18, RF20)

---

## Domain: `evaluaciones`

> Trazabilidad: HU34–HU36, RF38–RF40; modelo: `EVALUACION`

### Requirement: Evaluation by Criterion with Weight

- **WHEN** an evaluador (Director or EvaluadorExterno) submits an evaluation for an entrega with `nota` and `comentario` (required)
  **THEN** the system SHALL persist the record and recalculate `nota_consolidada` by averaging all evaluador notes weighted by the configured `porcentaje` per criterion. (HU34, RF38)

- **WHEN** a second evaluador submits their note
  **THEN** the system SHALL update `evaluacion_completa=true` once all 2–3 assigned evaluadores have submitted. (HU34, RF38)

### Requirement: Evaluator Assignment per Project

- **WHEN** the coordinador assigns 2–3 evaluators to a project for the `presentacion_final` fase
  **THEN** each evaluador SHALL receive an invitation in-app + email. (HU22, RF25)

- **WHEN** the evaluador accepts via `POST /api/evaluador/invitacion/{id}/aceptar`
  **THEN** the `estado_invitacion` SHALL transition from `pendiente` to `aceptada`. (HU22, RF25)

### Requirement: Consolidated Report

- **WHEN** the coordinador requests `GET /api/admin/reporte-calificaciones?semestre_id={id}`
  **THEN** the system SHALL return per-project breakdown: `estudiantes`, `director`, notas per entrega, `nota_metodologia`, `nota_presentacion`, and `nota_final` (weighted average). (HU36, RF40)

---

## Domain: `anuncios`

> Trazabilidad: HU27, RF30; modelo: `ANUNCIO`

### Requirement: CRUD de Anuncios (Coordinador)

- **WHEN** the coordinador creates an anuncio with `titulo`, `contenido`, and `vigente=true`
  **THEN** the system SHALL persist it and make it visible for all roles. (HU27, RF30)

- **WHEN** any role queries `GET /api/anuncios`
  **THEN** the system SHALL return anuncios where `vigente=true`, ordered by `fecha_publicacion DESC`. (HU27, RF30)

- **WHEN** a non-coordinador attempts `POST/PUT/DELETE /api/admin/anuncios`
  **THEN** the system SHALL return HTTP 403. (RF30)

---

## Domain: `recursos`

> Trazabilidad: HU28, RF31; modelo: `RECURSO_INFORMATIVO`

### Requirement: CRUD de Recursos Informativos (Coordinador)

- **WHEN** the coordinador creates a recurso with `titulo`, `categoria` (enum: `reglamento|cronograma|plantilla|formato|otro`), and optional `ruta_archivo` or `contenido`
  **THEN** the system SHALL persist it with `contador_accesos=0`. (HU28, RF31)

- **WHEN** any role queries `GET /api/recursos?categoria={cat}&q={search}`
  **THEN** the system SHALL filter by category and/or keyword search in `titulo`. (HU28, RF31)

- **WHEN** a user accesses a recurso detail (`GET /api/recursos/{id}`)
  **THEN** the system SHALL increment `contador_accesos` by 1 atomically. (HU28, RF31)

---

## Domain: `notificaciones`

> Trazabilidad: HU26, RF29; modelo: `NOTIFICACION`

### Requirement: In-App Notifications

- **WHEN** an event occurs (entrega enviada/revisada, bitácora firmada, alerta generada)
  **THEN** the system SHALL create a `NOTIFICACION` record with `usuario_id` (recipient), `emisor_id` (nullable if system-generated), `tipo`, `contenido`, and `leida=false`. (HU26, RF29)

- **WHEN** the recipient queries `GET /api/notificaciones`
  **THEN** the system SHALL return unread notifications with a `count` header, ordered by `enviada_en DESC`. (HU26, RF29)

- **WHEN** the recipient marks notifications as read via `PATCH /api/notificaciones/read`
  **THEN** the system SHALL set `leida=true` for the specified IDs. (HU26)

### Requirement: Email Dispatch via Queue

- **WHEN** a notification is created for a user with `preferencias.email_notifications=true`
  **THEN** the system SHALL dispatch a `ShouldQueue` email job to Redis containing notification type and content. (HU26, RF29)

- **IF** the queue is unreachable
  **THEN** the notification SHALL still be persisted in-app; email delivery SHALL be retried up to 3 times with exponential backoff. (RF29)

---

## Domain: `evaluador_proyecto`

> Trazabilidad: HU22, RF25; modelo: `EVALUADOR_PROYECTO`

### Requirement: Assign Evaluators to Projects

- **WHEN** the coordinador assigns an evaluador (User with role `EvaluadorExterno` or `Director`) to a project via `POST /api/admin/proyectos/{id}/evaluadores`
  **THEN** the system SHALL create an `EVALUADOR_PROYECTO` record with `estado_invitacion=pendiente` and send an invitation email. (HU22, RF25)

- **WHEN** the evaluador accepts the invitation
  **THEN** `estado_invitacion` SHALL change to `aceptada`. (HU22, RF25)

- **WHEN** the evaluador declines
  **THEN** `estado_invitacion` SHALL change to `rechazada` and the coordinador SHALL be notified. (HU22, RF25)

- **WHEN** querying `GET /api/admin/proyectos/{id}/evaluadores`
  **THEN** the system SHALL return evaluators with their `estado_invitacion` and, if accepted, their evaluation progress. (HU22)
