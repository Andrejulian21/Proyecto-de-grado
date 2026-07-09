# Cronograma Crítico — Sistema de Proyectos de Grado UNAB
# 7 Días · 1 Desarrollador

> **Versión:** 2.0 (7-day sprint)  
> **Equipo:** 1 desarrollador (Julian Arteaga Faria)  
> **Stack:** Laravel 11 + React/Vite/Tailwind + shadcn/ui + FastAPI + PostgreSQL/pgvector + Redis  
> **Documentos base:** 36 HU, 40 RF, 17 RNF

---

## ✅ Sprint 1 — COMPLETADO

| Entregable | Estado |
|-----------|--------|
| Google OAuth con validación triple (hd + @unab.edu.co + whitelist) | ✅ |
| Login evaluador externo con bloqueo por 3 intentos | ✅ |
| RBAC: 4 roles, middleware, políticas | ✅ |
| Gestión de Usuarios (whitelist CRUD, crear evaluadores, cambiar roles) | ✅ |
| Auditoría inmutable con filtros | ✅ |
| Layout: sidebar + header fijos, responsive, títulos por sección | ✅ |
| Wireframes porteados de Open Design (logins, sidebar, header, gestión usuarios) | ✅ |
| 151 tests pasando | ✅ |

---

## Sprint 2 — Proyectos + Dashboard Coordinador + Semestre

**HU cubiertas:** HU04, HU05, HU06, HU07, HU08  
**RF cubiertos:** RF06, RF07, RF08, RF09, RF10

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| **Día 1** | Migración y modelo `Semestre` (id, nombre, fecha_inicio, fecha_fin, activo). CRUD para coordinador. Seeders con semestres 2025-1, 2025-2, 2026-1. | **Alta** |
| | Migración y modelo `Proyecto` con todos los campos: código, título, estudiantes (1-3), director_id, fase (anteproyecto/presentación_anteproyecto/desarrollo/presentación_final), estado (en_curso/en_riesgo/incumplimiento/completado), alertas_count, semestre_id. FK a users y semestres. | **Alta** |
| | CRUD de proyectos para coordinador: formulario con validación, selector de estudiantes, justificación para grupo de 3. API: GET/POST/PUT/DELETE /api/admin/proyectos. Tests. | **Alta** |
| | Portear wireframe `gestion-proyectos.html` a React: tabla con código, título, estudiantes, director, fase, estado, alertas, acciones. Paginación + búsqueda. | **Alta** |
| | Tablero de control del coordinador: 4 KPIs (proyectos activos, en riesgo, alertas sin revisar, tasa de cumplimiento). Portear `panel-coordinador.html` con las cards KPI + tabla de proyectos + alertas activas. | **Alta** |
| | Sistema de fases: 4 fases con bloqueo de avance. Solo coordinador puede cambiar fase manualmente. Las entregas deben completarse por fase para avanzar. | **Alta** |
| | Alertas automáticas: trigger al crear/actualizar proyecto si fecha límite vencida. Contador de alertas por proyecto. Marcar "en riesgo de pérdida" tras 2 entregas incumplidas. | **Alta** |

---

## Sprint 3 — Gestión Documental (Entregas + Versiones)

**HU cubiertas:** HU09, HU10, HU11, HU12, HU13, HU14  
**RF cubiertos:** RF11, RF12, RF13, RF14, RF15, RF16

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| **Día 2** | Migración `entregas` (id, proyecto_id, fase, titulo, descripcion, fecha_limite, fecha_entrega, estado, calificacion, feedback, archivo_path, version). FK a proyectos. | **Alta** |
| | Migración `versiones_entregas` (id, entrega_id, version, archivo_path, tamaño, created_at). Versionado automático. | **Alta** |
| | Carga de archivos con drag-and-drop (zona de upload tipo wireframe). Validación: PDF/DOCX, max 50MB. Barra de progreso. | **Alta** |
| | API entregas: subir archivo, listar por proyecto/fase, obtener historial de versiones, descargar. Tests. | **Alta** |
| | Flujo de aprobación: estudiante solicita entrega → director habilita → estudiante envía → director revisa → aprueba/rechaza con feedback. Bloqueo de botón hasta habilitación. | **Alta** |
| | Portear `coordinador-entregas.html`: tabla de entregas por proyecto con filtros (fase, estado, fecha). Vista general del coordinador. | **Alta** |
| | Portear `detalle-entrega-estudiante.html`: detalle de entrega con archivos, versiones, estado, feedback del director. | **Alta** |
| | Portear `revision-entrega-director.html`: split-screen (documento + checklist de revisión + feedback). Calificación 0.0-5.0 con justificación obligatoria. | **Alta** |
| | Banco de documentos finales para coordinador: listar entregas aprobadas, descargar, filtrar. | **Media** |

---

## Sprint 4 — Bitácoras de Reunión + TOTP + Firmas

**HU cubiertas:** HU15, HU16, HU17, HU18, HU19  
**RF cubiertos:** RF17, RF18, RF19, RF20, RF21, RF22, RNF05

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| **Día 3** | Migración `bitacoras` (id, proyecto_id, tema, entregable_id, observaciones, evidencia_path, estado, firma_estudiante_totp, firma_director_totp, created_at). FK a proyectos y entregas. | **Alta** |
| | CRUD de bitácoras: formulario con tema, entregable asociado, observaciones, evidencia adjunta. API: GET/POST/PUT /api/bitacoras. Tests. | **Alta** |
| | **TOTP (RFC 6238):** generar secreto por usuario, ventana 30s, validar timestamp servidor, prevenir replay attack. Librería: `spomky-labs/otphp`. | **Crítica** |
| | Endpoint de firma: estudiante envía TOTP → servidor valida → marca `firma_estudiante_totp = true`. Mismo flujo para director. | **Alta** |
| | Estados de bitácora: `pendiente` → `firmada_estudiante` → `firmada_director` → `completada`. | **Alta** |
| | Detección de firmas sospechosas: si mismo director firma múltiples bitácoras en menos de 5 minutos, alerta al coordinador. | **Alta** |
| | Control de horas mínimas: configurar horas requeridas por proyecto. Alerta si no se cubren. | **Media** |
| | Portear `bitacoras-estudiante.html`: listado de bitácoras del proyecto del estudiante, filtros, botón "Nueva bitácora". | **Alta** |
| | Portear `nueva-bitacora-estudiante.html`: formulario con tema, entregable, observaciones, evidencia adjunta. | **Alta** |
| | Portear `detalle-firma-bitacora.html`: detalle con estado de firma (estudiante/director), TOTP input, historial. | **Alta** |
| | Portear `bitacoras-director.html`: listado de bitácoras de proyectos del director, filtros, acción de firmar. | **Alta** |
| | Portear `seleccion-proyectos-bitacoras.html`: selector de proyecto para filtrar bitácoras (coordinador y director). | **Alta** |
| | Portear `coordinador-bitacoras.html`: vista del coordinador con todas las bitácoras del semestre, filtros avanzados, exportación. | **Alta** |

---

## Sprint 5 — Directores + Evaluadores + Agenda + Evaluaciones + Reportes

**HU cubiertas:** HU20, HU21, HU22, HU23, HU24, HU34, HU35, HU36  
**RF cubiertos:** RF23, RF24, RF25, RF26, RF27, RF38, RF39, RF40

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| **Día 4** | Migración `directores` (id, user_id, especializaciones, cupo_maximo, proyectos_actuales). FK a users. | **Alta** |
| | Perfiles de docentes: CRUD para coordinador (nombre, correo, áreas de especialización, cupo máximo, proyectos dirigidos). API. Tests. | **Alta** |
| | Asignación de director a proyecto: selector con docentes disponibles (cupo no lleno), notificación al director, registro en auditoría. Reasignación con justificación obligatoria. | **Alta** |
| | Registro de evaluadores externos: formulario con nombre + correo + proyecto asignado. Generación automática de credenciales. API POST /api/admin/evaluadores (ya existe). | **Alta** |
| | Portear `asignacion-evaluadores.html`: tabla de evaluadores por proyecto, asignar/desasignar, estado de invitación. | **Alta** |
| | Migración `evaluaciones` (id, proyecto_id, evaluador_id, nota_presentacion, justificacion, created_at). FK a proyectos y users. | **Alta** |
| | CRUD evaluaciones: evaluador califica proyecto con justificación obligatoria. Promedio ponderado automático. API. Tests. | **Alta** |
| | Portear `evaluador-panel.html`: dashboard del evaluador con proyectos asignados y estado. | **Alta** |
| | Portear `evaluador-calificar.html`: formulario de calificación con rúbrica, input 0.0-5.0, justificación. | **Alta** |
| | Portear `evaluar-proyecto.html`: vista del coordinador para seguimiento de evaluaciones por proyecto. | **Alta** |
| | Portear `supervision-proyecto-director.html`: dashboard del director con KPIs (proyectos supervisando, entregas por revisar, alertas). | **Alta** |
| | Reporte consolidado de calificaciones: notas de entregas + metodología + presentación. Exportación PDF y Excel. API + frontend. | **Media** |
| | Portear `reportes-consolidados.html`: tabla de calificaciones consolidadas, filtros, botones de exportación. | **Media** |
| | Agenda de sustentaciones: configurar orden, hora, lugar. Generación PDF/Excel. | **Media** |

---

## Sprint 6 — Anuncios + Recursos + Alertas + Notificaciones + Chat

**HU cubiertas:** HU25, HU26, HU27, HU28  
**RF cubiertos:** RF28, RF29, RF30, RF31

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| **Día 5** | Migración `anuncios` (id, titulo, contenido, created_by, created_at). FK a users. | **Alta** |
| | CRUD de anuncios: solo coordinador publica/edita/elimina. Vista para todos los roles. API. Tests. | **Alta** |
| | Portear `anuncios.html`: listado de anuncios con paginación, solo coordinador ve botones de editar/eliminar. | **Alta** |
| | Portear `anuncios-publica.html`: vista pública de anuncios para estudiantes, directores y evaluadores. | **Alta** |
| | Portear `anuncio-detalle.html`: detalle de anuncio individual con contenido completo. | **Alta** |
| | Migración `recursos` (id, categoria, titulo, descripcion, archivo_path, enlace, contador_accesos, created_at). | **Alta** |
| | CRUD de recursos: categorías, subir/enlazar archivos, buscador, contador de accesos. API. Tests. | **Alta** |
| | Portear `recursos.html`: listado de recursos con categorías, buscador, contador de accesos. Vista para todos los roles. | **Alta** |
| | Portear `recursos-admin.html`: gestión de recursos para coordinador (CRUD completo). | **Alta** |
| | Portear `recurso-detalle.html`: detalle de recurso con descripción, archivo/enlace, estadísticas. | **Alta** |
| | Migración `alertas` (id, proyecto_id, tipo, mensaje, leida, created_at). FK a proyectos. | **Alta** |
| | Sistema de alertas automáticas: triggers por fecha vencida, entregas incumplidas, firmas sospechosas. API. Tests. | **Alta** |
| | Portear `gestion-alertas.html`: vista del coordinador con todas las alertas, filtros, marcar como leída. | **Alta** |
| | Sistema de notificaciones in-app (contador en header) + correo. API notificaciones no leídas. | **Media** |
| | Chat interno estudiante ↔ director con Laravel Reverb (WebSocket). Solo entre miembros del proyecto. Archivos adjuntos max 10MB. | **Baja** — primero en sacrificarse |

---

## Sprint 7 — IA + QA + Despliegue

**HU cubiertas:** HU29, HU30, HU31, HU32, HU33, HU34  
**RF cubiertos:** RF32, RF33, RF34, RF35, RF36, RF37, RNF07-RNF16

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| **Día 6** | **Setup FastAPI + Sentence-Transformers:** microservicio independiente, comunicación HMAC con Laravel. Endpoint POST /api/embeddings (texto → vector). | **Crítica** |
| | Endpoint de búsqueda por similitud en pgvector contra perfiles de docentes. | **Alta** |
| | Integración con Azure OpenAI (GPT-4o-mini): construir prompt con contexto de perfiles docentes, recibir sugerencia, parsear respuesta. | **Alta** |
| | Portear `asistente-orientacion.html`: UI tipo chat con input de propuesta, cards de directores sugeridos con justificación. | **Alta** |
| | Pipeline de análisis automático de entregas: al subir documento → extraer texto → generar embedding → comparar contra criterios → llamar Azure OpenAI → generar informe. | **Alta** |
| | Portear `analisis-automatico-entregas.html`: split-screen (documento + panel de retroalimentación), score de coherencia, checklist de cumplimiento. | **Alta** |
| | Criterios de evaluación por fase: interfaz para director/coordinador crear/editar criterios con nombre, descripción, peso. API. | **Alta** |
| **Día 7** | Pruebas de flujo completo: login → crear proyecto → asignar director → subir entrega → revisar → aprobar → firmar bitácora → evaluar → reporte. | **Alta** |
| | Responsive: probar 1920×1080, 1280×720, 768×1024, 375×667. Ajustes de layout. | **Media** |
| | Accesibilidad: skip-link, aria-labels, focus-visible, contraste WCAG AA. | **Media** |
| | Seguridad: OWASP ZAP, sanitización de inputs, HTTPS. | **Alta** |
| | Docker Compose para todos los servicios. Nginx con TLS. | **Crítica** |
| | Despliegue en VM de Azure. GitHub Actions con tests automáticos. | **Crítica** |
| | Documentación: README con instrucciones de instalación/despliegue. Swagger/OpenAPI. | **Media** |
| | Política de datos (Ley 1581). Aceptación al primer login. Proceso de solicitud de eliminación. | **Media** |
| | Prueba SUS con usuarios reales. Correcciones finales. | **Media** |

---

## 📊 Resumen de Esfuerzo

| Sprint | Área | Días |
|--------|------|------|
| 1 | **✅ COMPLETADO** — Auth + Layout | — |
| 2 | Proyectos + Dashboard + Semestre | 1 |
| 3 | Entregas + Versiones + Revisiones | 1 |
| 4 | Bitácoras + TOTP + Firmas | 1 |
| 5 | Directores + Evaluadores + Agenda + Evaluaciones + Reportes | 1 |
| 6 | Anuncios + Recursos + Alertas + Notificaciones + Chat | 1 |
| 7 | IA + QA + Despliegue | 2 |
| **Total** | **7 días hábiles** | |

---

## 🚨 Orden de Sacrificio

Si algo no alcanza, esto es lo que se corta primero:

| Prioridad | Se sacrifica | Reemplazo |
|-----------|-------------|-----------|
| 1 | Chat interno (Reverb WebSocket) | Comunicación por email |
| 2 | Notificaciones push/correo | Solo notificaciones in-app (contador) |
| 3 | TOTP para firmas | Firma manual con checkbox + timestamp + auditoría |
| 4 | Agenda de sustentaciones PDF/Excel | Agenda manual |
| 5 | Análisis automático IA de entregas | Solo asistente de orientación |
| 6 | Asistente de orientación (chatbot) | Se entrega sin módulo IA |
| 7 | Exportación PDF/Excel | Solo vista en pantalla |

**Nunca se sacrifica:** Proyectos CRUD · Entregas con versionado · Bitácoras · Evaluación con calificación · Reportes consolidados · Auditoría

---

## ✅ Verificación Diaria

Cada día antes de cerrar, verificar:

- [ ] ¿Los endpoints nuevos tienen tests pasando?
- [ ] ¿Las pantallas nuevas son responsive?
- [ ] ¿Los mensajes de error están en español?
- [ ] ¿Se registraron los cambios en auditoría?
- [ ] ¿El coordinador puede hacer la tarea del día en ≤3 clics?
