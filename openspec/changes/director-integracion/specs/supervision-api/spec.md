# Delta for Supervision API

## ADDED Requirements

### Requirement: Director Project Detail Access

El director DEBE poder ver el detalle completo de cualquier proyecto que supervisa (`director_id === auth()->id()`), incluyendo entregas y bitácoras asociadas.

#### Scenario: Director ve detalle de su proyecto

- GIVEN el director autenticado es `director_id` del proyecto PG-2026-014
- WHEN navega a `/supervision/1` (proyecto id=1)
- THEN el frontend llama `GET /api/admin/proyectos/1` (endpoint existente)
- AND recibe `{data: {id, code, title, director, estudiantes, entregas, current_phase, status}}`
- AND se renderiza la vista `SupervisionProyectoDirector` con datos reales

#### Scenario: Director intenta ver proyecto de otro director

- GIVEN el director A intenta acceder a un proyecto del director B
- WHEN llama `GET /api/admin/proyectos/{id}`
- THEN el endpoint devuelve 200 (no oculta el proyecto — es read-only por diseño)
- PERO las acciones de aprobar/rechazar/firmar fallan con 403 porque el backend verifica `director_id` en mutaciones

### Requirement: Delivery Review (Aprobar / Solicitar Correcciones / Rechazar)

El director DEBE poder revisar una entrega, asignar nota consolidada, agregar observaciones y cambiar el estado a `aprobada`, `correcciones`, o `rechazada`.

> **Reuse**: El endpoint `PUT /api/admin/entregas/{id}/revisar` (EntregaController@revisar) ya existe con verificación de director (`esDirectorDeEntrega`). Se debe conectar el frontend a este endpoint.

#### Endpoint (existente)

```
PUT /api/admin/entregas/{id}/revisar
Body: { status: "aprobada"|"rechazada", consolidated_grade: 85, director_notes: "Buen trabajo, mejorar referencias" }
```

#### Scenario: Director aprueba entrega con nota

- GIVEN una entrega en estado `enviada` de un proyecto que supervisa
- WHEN el director envía `{status: "aprobada", consolidated_grade: 92, director_notes: "Excelente"}` a `PUT /api/admin/entregas/{id}/revisar`
- THEN la entrega se actualiza con `status: "aprobada"`, `consolidated_grade: 92`, `evaluation_complete: true`
- AND la última versión del documento recibe `director_notes: "Excelente"`
- AND se notifica a los estudiantes del proyecto

#### Scenario: Director solicita correcciones

- GIVEN una entrega en estado `enviada`
- WHEN el director envía `{status: "rechazada", consolidated_grade: 55, director_notes: "Falta sección de análisis"}`
- THEN la entrega pasa a `rechazada` y los estudiantes son notificados

#### Scenario: No-director intenta revisar

- GIVEN un usuario cuyo `id !== proyecto.director_id`
- WHEN intenta `PUT /api/admin/entregas/{id}/revisar`
- THEN recibe 403 `{"error": "No eres el director de este proyecto."}`

### Requirement: Director Bitácoras por Proyecto

El director DEBE poder listar todas las bitácoras de un proyecto específico que supervisa.

#### Endpoint (nuevo)

```
GET /api/director/proyectos/{proyectoId}/bitacoras
Response: { data: [{ id, topic, notes, meeting_date, signature_status, duration_hours, created_at }] }
```

> **Nota**: `BitacoraController::porProyecto` ya existe pero está en grupo admin (solo Coordinador). Se DEBE agregar una ruta equivalente para director con verificación de `proyecto->director_id === auth()->id()`.

#### Scenario: Director lista bitácoras de su proyecto

- GIVEN el proyecto PG-2026-014 tiene 5 bitácoras
- WHEN el director llama `GET /api/director/proyectos/1/bitacoras`
- THEN recibe las 5 bitácoras con sus estados de firma

#### Scenario: Director intenta ver bitácoras de proyecto ajeno

- GIVEN el proyecto pertenece a otro director
- WHEN intenta `GET /api/director/proyectos/999/bitacoras`
- THEN recibe 403

### Requirement: Firmar Bitácora (Sin TOTP)

El director DEBE poder firmar una bitácora cambiando su estado a `signed` (equivalente a `Completada` en el enum `EstadoFirma`). La firma cambia el estado inmediatamente — TOTP se implementa después.

> **Reuse**: El endpoint `POST /api/bitacoras/{id}/firmar` (BitacoraController@firmar) ya maneja el flujo de firma del director cuando el estado actual es `FirmadaEstudiante`. Para bitácoras donde solo firma el director (sin flujo estudiante previo), se DEBE permitir firmar desde `Pendiente` también si el usuario es el director.

#### Scenario: Director firma bitácora pendiente

- GIVEN una bitácora con `signature_status: "pendiente"`
- WHEN el director del proyecto llama `POST /api/bitacoras/{id}/firmar`
- THEN `signature_status` cambia a `Completada`
- AND `director_signed_at` se establece a `now()`

#### Scenario: Director firma bitácora ya firmada por estudiante

- GIVEN una bitácora con `signature_status: "firmada_estudiante"`
- WHEN el director llama `POST /api/bitacoras/{id}/firmar`
- THEN `signature_status` cambia a `Completada`
- AND se notifica a los estudiantes

#### Scenario: No-director intenta firmar

- GIVEN un usuario que no es el director del proyecto
- WHEN intenta firmar
- THEN recibe 403

### Requirement: Supervision Proyecto List

El director DEBE ver una lista de cards con todos sus proyectos supervisados (activos), cada uno con botones "Ver Proyecto" y "Ver Bitácora".

#### Frontend Data Flow

- `GET /api/director/proyectos` → renderiza cards en `SupervisionProyectoDirector.tsx`
- Cada card muestra: `code`, `title`, `estudiantes`, `status`, `current_phase`
- Botón "Ver Proyecto" → navega a `/supervision/{id}`
- Botón "Ver Bitácora" → navega a bitácoras del proyecto

#### Scenario: Lista de proyectos del director

- GIVEN el director supervisa 3 proyectos en semestres activos
- WHEN carga la página de supervisión
- THEN ve 3 cards con datos reales del backend
- AND cada card tiene ambos botones de acción

#### Scenario: Sin proyectos

- GIVEN el director no tiene proyectos en semestres activos
- WHEN carga la página de supervisión
- THEN ve un estado vacío: "No tienes proyectos supervisados en el semestre activo"
