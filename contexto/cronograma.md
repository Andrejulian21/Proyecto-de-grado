# Cronograma — Sistema de Proyectos de Grado UNAB
# Estrategia por capas: Backend → Tests → Frontend → Integración → Docker/CI/CD → Despliegue

> **Stack:** Laravel 11 + React/Vite + FastAPI + PostgreSQL/pgvector + Redis  
> **Equipo:** 1 desarrollador  
> **Documentos base:** 36 HU, 40 RF, 17 RNF  
> Versión: 2.0 (reorganizado por capas)

---

## ✅ Sprint 1 — COMPLETADO (Auth + Layout)

| Entregable | Estado |
|-----------|--------|
| Google OAuth con validación triple (hd + @unab.edu.co + whitelist) | ✅ |
| Login evaluador externo con bloqueo por 3 intentos | ✅ |
| RBAC: 4 roles, middleware, políticas | ✅ |
| Gestión de Usuarios (whitelist CRUD, crear evaluadores, cambiar roles) | ✅ |
| Auditoría inmutable con filtros | ✅ |
| Layout: sidebar + header fijos, responsive, títulos por sección | ✅ |
| Wireframes porteados: logins, sidebar, header, gestión usuarios | ✅ |
| API endpoints: /api/admin/usuarios, /api/admin/evaluadores | ✅ |
| 151 tests backend pasando, build frontend OK | ✅ |
| Documentación: AGENTS.md, constitution.md, ADRs, Plan Maestro, Roadmap | ✅ |

---

## Sprint 2 — Backend Completo

**Objetivo:** Todas las migraciones, modelos, enums, controladores y endpoints del sistema.

### Migraciones (13)
| Migración | Columnas clave |
|-----------|---------------|
| semestres | id, name, start_date, end_date, is_active |
| proyectos | id, code, title, director_id, semester_id, current_phase, status, requires_group_justification, alert_count |
| proyecto_estudiante | proyecto_id, user_id (pivot) |
| entregas | id, proyecto_id, phase, due_date, status, consolidated_grade, evaluation_complete |
| versiones_documento | id, entrega_id, version_number, file_path, director_notes |
| bitacoras | id, proyecto_id, topic, notes, evidence_file, signature_status, student_signed_at, director_signed_at |
| evaluador_proyecto | proyecto_id, evaluador_id, invitation_status |
| notificaciones | id, user_id, sender_id, type, content, is_read |
| evaluaciones | id, entrega_id, evaluador_id, grade, comment |
| anuncios | id, author_id, title, content, is_active |
| recursos_informativos | id, author_id, title, category, file_path, content |
| analisis_ia | id, entrega_id, embedding, generated_feedback, coherence_score |
| sugerencia_director | id, estudiante_id, proposal_text, suggested_directors |

### Enums (5)
FaseProyecto · EstadoEntrega · EstadoFirma · EstadoInvitacionEvaluador · CategoriaRecurso

### Modelos (12)
Semestre · Proyecto · Entrega · VersionDocumento · Bitacora · EvaluadorProyecto · Notificacion · Evaluacion · Anuncio · RecursoInformativo · AnalisisIa · SugerenciaDirector

### API
CRUD completo para cada entidad con validación, relaciones, filtros y paginación.

---

## Sprint 3 — Tests Backend

**Objetivo:** Suite completa de tests con Pest. 200+ tests.

| Tipo | Cantidad estimada |
|------|------------------|
| Tests unitarios (enums, modelos) | ~60 |
| Tests feature (CRUD por entidad) | ~120 |
| Tests integración (flujos completos) | ~30 |
| **Total** | **~210** |

---

## Sprint 4 — Frontend Completo

**Objetivo:** Portear los 32 wireframes de Open Design a React conectados a la API real.

| Módulo | Pantallas | Estado |
|--------|-----------|--------|
| Login | LoginInstitucional, LoginExterno | ✅ Hecho |
| Layout | Sidebar, Header, AppShell | ✅ Hecho |
| Admin | GestionUsuarios, AuditLog | ✅ Hecho |
| Coordinador | Panel, Proyectos, Entregas, Bitacoras, Evaluadores, Alertas, Reportes | 🔜 |
| Director | Panel, Revision, Bitacoras | 🔜 |
| Estudiante | Panel, Entregas, Bitacoras, Asistente IA | 🔜 |
| Evaluador | Panel, Calificar | 🔜 |
| General | Anuncios, Recursos | 🔜 |

---

## Sprint 5 — Integración

**Objetivo:** Probar flujos completos, corregir bugs de integración.

- Login → semestre → proyecto → entregas → revisar → bitácora → evaluar → reporte
- Pruebas responsive (375px → 1920px)
- Ajustes de UI y UX

---

## Sprint 6 — Docker + CI/CD

**Objetivo:** Infraestructura y automatización de calidad.

- Docker Compose: Laravel, PostgreSQL, Redis, Nginx, FastAPI
- GitHub Actions: tests PHP + build frontend
- Pint (PHP), Prettier (TS), auditoría

---

## Sprint 7 — Despliegue

**Objetivo:** Sistema en producción.

- Azure VM con Docker Compose
- Nginx con TLS (Let's Encrypt)
- Documentación: README, Swagger, política de datos (Ley 1581)
- Prueba SUS con usuarios reales

---

## Orden de Sacrificio

1. ❌ Módulos IA (asistente + análisis automático)
2. ❌ TOTP para firmas → firma manual con timestamp
3. ❌ Despliegue Azure → Docker Compose local
4. ❌ Exportación PDF/Excel
5. ❌ Notificaciones push

**Nunca se sacrifica:** Auth · Proyectos · Entregas · Bitácoras · Evaluación · Tests
