# Roadmap — Sistema de Proyectos de Grado UNAB
> **Estrategia:** Backend → Tests → Frontend → Integración → Docker/CI/CD → Despliegue  
> **Equipo:** 1 desarrollador · **Sprint 1:** ✅ Completado

---

## ✅ Sprint 1 — COMPLETADO (Auth + Layout)

Google OAuth · Login externo · RBAC · Gestión Usuarios · Auditoría · Sidebar/Header · 151 tests

---

## Sprint 2 — Backend Completo (13 migraciones + modelos + API)

**Objetivo:** Toda la lógica de negocio del sistema en la API. Sin frontend nuevo.

| Tarea | Detalle |
|-------|---------|
| **Migraciones** | semestres, proyectos, proyecto_estudiante, entregas, versiones_documento, bitacoras, evaluador_proyecto, notificaciones, evaluaciones, anuncios, recursos_informativos, analisis_ia, sugerencia_director |
| **Enums** | FaseProyecto, EstadoEntrega, EstadoFirma, EstadoInvitacion, CategoriaRecurso |
| **Modelos** | Semestre, Proyecto, Entrega, VersionDocumento, Bitacora, EvaluadorProyecto, Notificacion, Evaluacion, Anuncio, RecursoInformativo, AnalisisIa, SugerenciaDirector |
| **Controllers** | ProyectoController, EntregaController, BitacoraController, EvaluacionController, AnuncioController, RecursoController, NotificacionController |
| **Endpoints** | CRUDs completos con validación, relaciones, filtros, paginación |

---

## Sprint 3 — Tests + QA Backend

**Objetivo:** Suite completa de tests unitarios y de feature para toda la API.

| Tarea | Detalle |
|-------|---------|
| Tests unitarios | Enums, Models, Scopes, Accessors |
| Tests feature | CRUD de cada entidad, validaciones, autorización RBAC |
| Tests de integración | Flujos multi-entidad, auditoría, roles |
| Cobertura | Baseline 200+ tests |

---

## Sprint 4 — Frontend Completo

**Objetivo:** Portear los 32 wireframes de Open Design a React.

| Tarea | Detalle |
|-------|---------|
| Login | YA HECHO ✅ |
| Sidebar + Header | YA HECHO ✅ |
| Gestión Usuarios | YA HECHO ✅ |
| Dashboard Coordinador | KPIs + tabla proyectos + alertas |
| Gestión Proyectos | CRUD completo |
| Dashboard Director | Proyectos supervisando, entregas por revisar |
| Dashboard Estudiante | Mi proyecto, entregas, bitácoras |
| Dashboard Evaluador | Proyectos asignados, calificar |
| Entregas | Subir, versionado, revisiones |
| Bitácoras | CRUD + firma TOTP |
| Evaluaciones | Calificar con rúbrica |
| Anuncios | CRUD coordinador, vista pública |
| Recursos | Categorías, subir, buscar |
| Alertas | Gestión de alertas |
| Reportes | Consolidado + exportación |
| Asistente IA | Chatbot sugerencia directores |
| Análisis IA | Retroalimentación automática |

---

## Sprint 5 — Integración

**Objetivo:** Conectar frontend con backend, probar flujos completos, corregir bugs.

| Tarea | Detalle |
|-------|---------|
| Conectar todas las pantallas | Fetch a APIs reales |
| Flujo coordinador | Login → semestre → proyecto → entregas → evaluar |
| Flujo estudiante | Login → ver proyecto → subir entrega → ver bitácoras |
| Flujo director | Login → revisar entregas → firmar bitácoras |
| Flujo evaluador | Login → calificar proyectos |
| Bugs y ajustes | Correcciones de integración |

---

## Sprint 6 — Docker + CI/CD

**Objetivo:** Infraestructura y automatización.

| Tarea | Detalle |
|-------|---------|
| Docker Compose | Laravel, PostgreSQL, Redis, Nginx, FastAPI |
| GitHub Actions | Tests PHP + build frontend + lint |
| Calidad | Pint (PHP), Prettier (TS), auditoría |

---

## Sprint 7 — Despliegue

**Objetivo:** Sistema en producción.

| Tarea | Detalle |
|-------|---------|
| Azure VM | Docker Compose + Nginx TLS |
| Dominio | HTTPS con Let's Encrypt |
| Documentación | README, Swagger, política de datos |
| Prueba SUS | Con usuarios reales |
