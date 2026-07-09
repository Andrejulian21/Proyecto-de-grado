# Proposal: Backend Completo — Capa de Persistencia y API

## Intent

Materializar el modelo de datos de la Fase 2 como migraciones reversibles, modelos Eloquent, enums, controladores y ~40 endpoints REST protegidos por Sanctum + RBAC. Este change es el cimiento para dashboards, entregas, bitácoras y evaluación.

## Scope

### In Scope
- **13 migraciones** reversibles en 4 capas de dependencia
- **12 modelos** Eloquent con relaciones, casts y scopes
- **5 enums** PHP nativos (FaseProyecto, EstadoEntrega, EstadoFirma, EstadoInvitacion, CategoriaRecurso)
- **~7 controladores** (Proyecto, Entrega, Bitacora, Evaluacion, Anuncio, Recurso, Notificacion)
- **~40 endpoints** con validación, paginación, filtros, JSON estandarizado
- **Rutas por rol**: `/api/admin/`, `/api/director/`, `/api/estudiante/`, `/api/evaluador/`
- **Eventos AuditEvent** en cada operación mutante
- **Form Requests** para validación de entrada

### Out of Scope
- Frontend React (wireframes, pantallas) → Sprint 4
- Suite completa de tests backend → Sprint 3 (se aplica TDD aquí, la suite formal es otro sprint)
- CI/CD, Docker refinado, despliegue Azure → Sprints 6–7
- Módulo IA (FastAPI, embeddings, Azure OpenAI) → sprint futuro
- TOTP para firmas de bitácoras → Sprint 4

## Capabilities

### New Capabilities
- `academic-semester`: Gestión de semestres activos
- `project-management`: CRUD proyectos, asignación estudiantes/directores, códigos auto-generados
- `deliverable-management`: Entregas por fase, versionado de documentos, flujo de estados
- `meeting-log`: Bitácoras, estados de firma
- `evaluation`: Evaluaciones por criterio, asignación evaluadores, notas consolidadas
- `notification`: Notificaciones in-app + despacho de correo vía queue
- `announcement-resource`: Anuncios y recursos informativos por categoría

### Modified Capabilities
- `user-management`: Extender endpoints Admin para asignar evaluadores/directores a proyectos

## Decisiones de Modelado

| # | Contexto | Decisión | Consecuencia |
|---|----------|----------|--------------|
| 1 | Identificador único legible por período académico | **Código auto-generado** `PG-{semestre}{numero}` (ej: `PG-2026205`) | Inmutable tras creación. Requiere contador atómico por semestre. |
| 2 | Alertar usuarios sin depender solo del correo institucional | **Notificaciones in-app + correo electrónico** vía Laravel Queue (Redis) | Tabla `notificaciones` crece rápido; job de archivado diferido. |
| 3 | Ciclo de vida definido de entregas en el ERS | **Estados**: pendiente → enviada → revisada → aprobada/rechazada | Transiciones controladas server-side. Solo director/coordinador avanza desde "enviada". |
| 4 | Re-subir documentos corregidos tras retroalimentación | **Máximo 4 versiones** por entrega | Validación 422 en Form Request. Almacenamiento físico versionado en ruta. |
| 5 | Calificación por rúbrica con pesos variables | **Evaluación por criterio** con ponderación por fase | Criterios por fase hardcodeados en MVP (config/enum). No tabla dinámica aún. |

## Approach

**Migraciones en 4 capas de dependencia:**
1. **Base**: `semestres` (nuevo), tablas existentes `users`, `authorized_emails`, `audit_logs`
2. **Negocio**: `proyectos`, `proyecto_estudiante`, `evaluador_proyecto`, `entregas`
3. **Documental**: `versiones_documento`, `bitacoras`, `evaluaciones`
4. **Sistema**: `notificaciones`, `anuncios`, `recursos_informativos`, `analisis_ia`, `sugerencia_director`

**Backend-first con Strict TDD:**
- Por entidad: test Pest que falla → migración + modelo → endpoint mínimo → refactor.
- 151 tests baseline se mantienen; cada nuevo endpoint requiere test verde antes de commit.
- Eager load (`with()`) obligatorio en endpoints con relaciones para evitar N+1.
- Form Requests con mensajes de error en español.
- `AuditEvent` disparado en todos los métodos mutantes.

## Affected Areas

| Área | Impacto | Descripción | Estimación (líneas) |
|------|---------|-------------|---------------------|
| `database/migrations/` | Nuevo | 13 migraciones en 4 capas | ~650 |
| `app/Models/` | Nuevo | 12 modelos Eloquent | ~900 |
| `app/Enums/` | Nuevo | 5 enums PHP 8.3 | ~120 |
| `app/Http/Controllers/` | Nuevo | ~7 controladores REST | ~1400 |
| `app/Http/Requests/` | Nuevo | ~15–20 Form Requests | ~500 |
| `app/Events/` | Modificado | Extender AuditEvent | ~50 |
| `routes/api.php` | Modificado | Grupos por rol | ~200 |
| `database/factories/` | Nuevo | Factories para tests | ~400 |

## Risks

| Riesgo | Probabilidad | Mitigación |
|--------|-------------|------------|
| N+1 query en endpoints con relaciones complejas | Media | Eager load obligatorio; debugbar en dev; tests que aserten count de queries. |
| Bypass de transiciones de estado por requests malformadas | Baja | State machine en modelo Entrega (`canTransitionTo()`); Form Request delega al modelo; test unitario por transición inválida. |
| Notificaciones síncronas ralentizando respuesta API | Media | Correos en Laravel Queue (Redis, `ShouldQueue`); in-app síncrono solo si usuario online. |

## Rollback Plan

1. Revertir migraciones: `php artisan migrate:rollback --step=13`.
2. Eliminar modelos, controladores y rutas nuevos; verificar que 151 tests baseline pasen.
3. Si quedan datos huérfanos, foreign keys con `onDelete('cascade')` los limpian.
4. Branch aislado `feature/backend-completo`; cerrar PR sin merge si `verify` falla.

## Dependencies

- PostgreSQL 16 + extensión `pgvector` (ya en Docker Compose).
- Redis 7 para queues de notificaciones (ya disponible).
- Usuarios base creados por Sprint 1 para probar relaciones en tests.

## Success Criteria (EARS)

- WHEN el coordinador crea un proyecto THEN el sistema SHALL generar automáticamente un código único en formato `PG-{semestre}{numero}`.
- WHEN un estudiante sube una entrega THEN el sistema SHALL validar que la fase de la entrega coincida con la `fase_actual` del proyecto.
- WHEN una entrega está en estado "pendiente" THEN el estudiante SHALL poder cambiar su estado a "enviada"; el director/evaluador SHALL poder cambiarla a "revisada", y desde "revisada" a "aprobada" o "rechazada".
- WHEN un estudiante intenta subir una quinta versión de documento para una entrega THEN el sistema SHALL rechazar la solicitud con HTTP 422 y mensaje en español.
- WHEN se asignan evaluadores a un proyecto THEN cada evaluador SHALL recibir una notificación in-app y un correo electrónico vía queue.
- WHEN un evaluador registra una evaluación THEN el sistema SHALL calcular la nota consolidada de la entrega ponderando cada criterio según la fase.
- WHEN se invoca cualquier endpoint de escritura THEN el sistema SHALL disparar un `AuditEvent` y el log de auditoría SHALL registrar la acción de forma append-only.
- WHEN el coordinador publica un anuncio THEN todos los usuarios activos del semestre actual SHALL recibir una notificación in-app dentro de los 5 segundos siguientes.
- WHEN se consulta un proyecto con sus relaciones THEN el sistema SHALL responder en menos de 300ms sin ejecutar más de 4 queries (medido en tests de feature).
- WHEN se ejecuta `php artisan migrate:fresh --seed` THEN todas las 13 migraciones nuevas SHALL aplicarse sin errores y los factories SHALL poblar datos de prueba coherentes.
