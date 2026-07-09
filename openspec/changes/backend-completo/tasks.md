# Tasks: Backend Completo — Capa de Persistencia y API

## Review Workload Forecast

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
800-line budget risk: High

~3,400 lines across 13 migrations, 12 models, 5 enums, 7 controllers, 15 requests, 6 factories. Exceeds 800-line budget.

### Suggested Work Units

| Unit | Goal | PR | Base |
|------|------|----|------|
| 1 | Foundation (Semestres+Proyectos+KPIs) | PR 1 | main |
| 2 | Documentos (Entregas+versionado+flujo) | PR 2 | main |
| 3 | Seguimiento (Bitácoras+firmas+horas) | PR 3 | main |
| 4 | Evaluación (Evaluaciones+reportes) | PR 4 | main |
| 5 | Comunicación (Anuncios+Recursos+Notifs) | PR 5 | main |

## Batch 1: Foundation — Semestres, Proyectos, KPIs (PR 1)

- [ ] **T-001** Migración `semestres` (nombre unique, activo, next_proyecto_seq), modelo `Semestre` (HasMany), seeder 3 semestres
- [ ] **T-002** Migraciones `proyectos` (codigo unique, FK, fase_actual/estado enum) + `proyecto_estudiante` (pivot); enums `FaseProyecto`, `EstadoProyecto`; modelo `Proyecto` (scopes, avanzarFaseSiCorresponde)
- [ ] **T-003** CRUD Semestres: SemestreController, Store/UpdateSemestreRequest, rutas admin, tests (CRUD + 403)
- [ ] **T-004** CRUD Proyectos: ProyectoController (index/store/show/update/destroy), Store/UpdateProyectoRequest, asignar/remover estudiantes (3→justificacion), rutas admin+shared, tests
- [ ] **T-005** Auto-código `PG-{semestre}{correlativo}`: contador atómico row-level lock, test concurrencia
- [ ] **T-006** Dashboard KPIs: GET /api/admin/dashboard (activos, riesgo, alertas, tasa), test feature

## Batch 2: Documentos — Entregas, Versionado, Banco (PR 2)

- [ ] **T-007** Migraciones `entregas` (FK, fase, estado, nota_consolidada, evaluacion_completa) + `versiones_documento` (FK, numero_version, ruta); enum `EstadoEntrega` (canTransitionTo); modelos Entrega (state machine), VersionDocumento
- [ ] **T-008** CRUD Entregas+versionado: EntregaController (admin CRUD, subir/enviar), StoreEntregaRequest, SubirVersionRequest (PDF/DOCX ≤50MB, max 4 versiones→422), rutas admin+estudiante, tests
- [ ] **T-009** Flujo transiciones (pendiente→enviada→revisada→aprobada/rechazada), TransicionarEntregaRequest, observer avance automático fase, notificaciones, tests unitarios
- [ ] **T-010** Banco docs: GET /api/admin/documentos-finales (versiones presentacion_final aprobadas, filtros), test

## Batch 3: Seguimiento — Bitácoras, Firmas, Horas (PR 3)

- [ ] **T-011** Migración `bitacoras` (FK, tema, observaciones, evidencia, duracion, estado_firma, timestamps); enum `EstadoFirma`; modelo Bitacora (firmar, detectarSospechosa)
- [ ] **T-012** CRUD Bitácoras+firmas: BitacoraController (CRUD, firmar, misBitacoras), StoreBitacoraRequest, rutas estudiante+director, tests (firma doble→inmutable)
- [ ] **T-013** Detección sospechosas: ≥3 firmas mismo director en 5min→sospechosa, alerta coordinador, tests
- [ ] **T-014** Horas mínimas: GET /api/admin/proyectos/{id}/horas-bitacora (suma vs mínimo), alerta, test

## Batch 4: Evaluación — Evaluaciones, Reportes (PR 4)

- [ ] **T-015** Migraciones `evaluaciones` (FK, nota, comentario) + `evaluador_proyecto` (FK, estado_invitacion); enums `EstadoInvitacion`; modelos Evaluacion, EvaluadorProyecto; config/evaluacion.php (pesos por fase)
- [ ] **T-016** CRUD Evaluaciones+asignación: EvaluacionController (CRUD, asignar evaluadores, aceptar/rechazar), StoreEvaluacionRequest, rutas admin+evaluador, tests
- [ ] **T-017** Evaluación criterio: recalcularNotaConsolidada pondera por pesos, evaluacion_completa tras todos, tests
- [ ] **T-018** Reporte: GET /api/admin/reporte-calificaciones (breakdown por proyecto, notas ponderadas), test

## Batch 5: Comunicación — Anuncios, Recursos, Notificaciones (PR 5)

- [ ] **T-019** Migraciones `anuncios` (FK, titulo, contenido, vigente) + `notificaciones` (FK usuario/emisor, tipo, leida) + `recursos_informativos` (FK, categoria, contador_accesos); enum CategoriaRecurso; modelos Anuncio, Notificacion, RecursoInformativo
- [ ] **T-020** CRUD Anuncios: AnuncioController (admin CRUD, list vigentes), StoreAnuncioRequest, rutas, tests
- [ ] **T-021** CRUD Recursos: RecursoController (admin CRUD, list filtros categoria/search, increment contador), StoreRecursoRequest, tests
- [ ] **T-022** Notificaciones: crear in-app en eventos, email ShouldQueue via Redis, MarkNotificationsReadRequest, test Queue::fake()
