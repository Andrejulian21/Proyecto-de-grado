# Roadmap — 7 Días · 1 Desarrollador

> Plan detallado día por día. Cada día es un sprint entregable.
> Sprint 1 (Auth + Layout) está **completado**. Los sprints 2-7 son los que faltan.

---

## ✅ Sprint 1 — COMPLETADO (Auth + Layout)

| Entregable | Estado |
|-----------|--------|
| Google OAuth con validación triple | ✅ |
| Login evaluador externo con bloqueo | ✅ |
| RBAC: 4 roles, middleware, políticas | ✅ |
| Gestión de Usuarios (whitelist, evaluadores, roles) | ✅ |
| Auditoría inmutable | ✅ |
| Layout: sidebar + header fijos, responsive | ✅ |
| Wireframes porteados (logins, sidebar, header, gestión usuarios) | ✅ |
| 151 tests | ✅ |

---

## Sprint 2 — Proyectos + Dashboard Coordinador + Semestre

**HU:** HU04-HU08 · **RF:** RF06-RF10

| Tarea | Detalle | Estimado |
|-------|---------|----------|
| Migración `semestres` | id, nombre, fecha_inicio, fecha_fin, activo. Seeders. | 30 min |
| Migración `proyectos` | código, título, director_id (FK), fase (enum: anteproyecto/presentación_anteproyecto/desarrollo/presentación_final), estado (enum), alertas_count, semestre_id. | 30 min |
| Migración `proyecto_estudiante` | Pivot 1-3 estudiantes por proyecto. | 15 min |
| Enums `FaseProyecto`, `EstadoProyecto` | Con helper values() para validación. | 15 min |
| Modelos `Semestre`, `Proyecto` | Con relaciones: belongsTo, belongsToMany. | 30 min |
| CRUD Proyectos (backend) | GET/POST/PUT/DELETE `/api/admin/proyectos`. Validación, tests. | 2h |
| CRUD Semestres (backend) | GET/POST/PUT/DELETE `/api/admin/semestres`. Tests. | 1h |
| KPIs endpoint | GET `/api/admin/proyectos/kpis`. 4 métricas. | 1h |
| Portear `panel-coordinador.html` | 4 KPI cards, tabla proyectos con filtros, sección alertas activas. | 2h |
| Portear `gestion-proyectos.html` | Tabla CRUD con paginación, selector de semestre, form crear/editar. | 2h |

**Total estimado:** ~10h

---

## Sprint 3 — Gestión Documental (Entregas + Versiones)

**HU:** HU09-HU14 · **RF:** RF11-RF16

| Tarea | Detalle | Estimado |
|-------|---------|----------|
| Migración `entregas` | proyecto_id, fase, titulo, descripcion, fecha_limite, estado, calificacion, feedback. | 30 min |
| Migración `versiones_entregas` | entrega_id, version, archivo_path, tamaño. | 15 min |
| Modelo `Entrega` | Con relaciones y scopes. | 30 min |
| API Entregas | Subir archivo, listar por proyecto/fase, versionado. Tests. | 3h |
| Flujo de aprobación | Estudiante solicita → director habilita → estudiante envía → director revisa. | 2h |
| Carga de archivos drag-and-drop | Validación PDF/DOCX, max 50MB, barra de progreso. | 1h |
| Portear `coordinador-entregas.html` | Tabla entregas con filtros. | 1h |
| Portear `detalle-entrega-estudiante.html` | Detalle con archivos, versiones, feedback. | 1h |
| Portear `revision-entrega-director.html` | Split-screen documento + checklist + calificación. | 2h |

**Total estimado:** ~11h

---

## Sprint 4 — Bitácoras + TOTP + Firmas

**HU:** HU15-HU19 · **RF:** RF17-RF22, RNF05

| Tarea | Detalle | Estimado |
|-------|---------|----------|
| Migración `bitacoras` | proyecto_id, tema, entregable_id, observaciones, estado, firma flags. | 30 min |
| Modelo `Bitacora` | Con relaciones. | 20 min |
| API Bitácoras | CRUD con evidencias adjuntas. Tests. | 2h |
| TOTP (RFC 6238) | Generar secreto, ventana 30s, validar timestamp, prevenir replay. Librería: `spomky-labs/otphp`. | 3h |
| Flujo de firma | Estudiante firma → director firma → estados: pendiente/firmada/completada. | 2h |
| Detección firmas sospechosas | Alerta si mismo director firma múltiples en 5 min. | 1h |
| Control horas mínimas | Configurar horas requeridas por proyecto + alerta. | 1h |
| Portear `bitacoras-estudiante.html` | Listado + filtros + botón nueva bitácora. | 1h |
| Portear `nueva-bitacora-estudiante.html` | Formulario + evidencia adjunta. | 1h |
| Portear `detalle-firma-bitacora.html` | Estado firmas + input TOTP + historial. | 1h |
| Portear `bitacoras-director.html` | Listado + acción firmar. | 1h |
| Portear `coordinador-bitacoras.html` | Vista general + filtros avanzados. | 1h |

**Total estimado:** ~14h

---

## Sprint 5 — Directores + Evaluadores + Evaluaciones + Reportes

**HU:** HU20-HU24, HU34-HU36 · **RF:** RF23-RF27, RF38-RF40

| Tarea | Detalle | Estimado |
|-------|---------|----------|
| Migración `evaluaciones` | proyecto_id, evaluador_id, nota_presentacion, justificacion. | 20 min |
| Perfiles de docentes | CRUD especializaciones, cupo máximo, proyectos actuales. | 2h |
| Asignación directores | Selector con docentes disponibles, notificación, auditoría. | 1h |
| CRUD Evaluaciones | Evaluador califica 0.0-5.0 con justificación. Promedio ponderado. | 2h |
| Reporte consolidado | Notas entregas + metodología + presentación. Exportación. | 2h |
| Agenda sustentaciones | Configurar orden, hora, lugar. | 1h |
| Portear `asignacion-evaluadores.html` | Tabla evaluadores por proyecto. | 1h |
| Portear `evaluador-panel.html` | Dashboard del evaluador. | 1h |
| Portear `evaluador-calificar.html` | Rúbrica + input nota + justificación. | 2h |
| Portear `reportes-consolidados.html` | Tabla calificaciones + exportación. | 1h |

**Total estimado:** ~13h

---

## Sprint 6 — Anuncios + Recursos + Alertas + Notificaciones

**HU:** HU25-HU28 · **RF:** RF28-RF31

| Tarea | Detalle | Estimado |
|-------|---------|----------|
| Migraciones: `anuncios`, `recursos`, `alertas` | Modelos + migraciones. | 45 min |
| CRUD Anuncios | Solo coordinador publica/edita/elimina. API + tests. | 1h |
| CRUD Recursos | Categorías, subir/enlazar, buscador, contador accesos. | 1.5h |
| Alertas automáticas | Triggers por fecha vencida, entregas incumplidas. | 1.5h |
| Notificaciones in-app | Contador en header, listado no leídas. | 1h |
| Portear `anuncios.html`, `anuncios-publica.html`, `anuncio-detalle.html` | 3 pantallas. | 1.5h |
| Portear `recursos.html`, `recursos-admin.html`, `recurso-detalle.html` | 3 pantallas. | 1.5h |
| Portear `gestion-alertas.html` | Vista coordinador. | 1h |

**Total estimado:** ~10h

---

## Sprint 7 — IA + QA + Despliegue

**HU:** HU29-HU33 · **RF:** RF32-RF37, RNF07-RNF16

| Tarea | Detalle | Estimado |
|-------|---------|----------|
| Setup FastAPI + Sentence-Transformers | Microservicio HMAC, endpoint embeddings. | 2h |
| Búsqueda pgvector | Similitud contra perfiles docentes. | 1h |
| Azure OpenAI integración | Prompt con contexto, parsear respuesta. | 2h |
| Portear `asistente-orientacion.html` | UI chat + cards directores sugeridos. | 2h |
| Pipeline análisis entregas | Extraer texto → embedding → comparar → informe. | 3h |
| Portear `analisis-automatico-entregas.html` | Split-screen documento + retroalimentación. | 2h |
| Pruebas flujo completo | Login → proyecto → entrega → revisar → bitácora → evaluar → reporte. | 2h |
| Responsive + Accesibilidad | Probar 4 breakpoints, WCAG AA. | 2h |
| Docker Compose + Nginx TLS | Azure VM + GitHub Actions. | 3h |
| Documentación | README, Swagger, política datos. | 1h |

**Total estimado:** ~20h (2 días)

---

## Resumen

| Sprint | Horas estimadas |
|--------|-----------------|
| 1 — ✅ Completado | — |
| 2 — Proyectos + Dashboard | ~10h |
| 3 — Entregas | ~11h |
| 4 — Bitácoras + TOTP | ~14h |
| 5 — Evaluaciones + Reportes | ~13h |
| 6 — Anuncios + Recursos | ~10h |
| 7 — IA + QA + Deploy | ~20h |
| **Total restante** | **~78h (~7 días)** |
