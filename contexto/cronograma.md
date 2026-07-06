# Cronograma Crítico — Implementación Sistema de Proyectos de Grado UNAB

> **Versión:** 1.0  
> **Equipo:** 2 desarrolladores (Julian Arteaga Faria, Miguel Afanador Quintero)  
> **Stack:** Laravel 11 + FastAPI + PostgreSQL/pgvector + Redis + React/Vite/Tailwind + shadcn/ui  
> **Fecha:** Julio 2026  
> **Documentos base:** Fase 2 (arquitectura), 36 HU, 40 RF, 17 RNF

---

## ⚠️ ADVERTENCIA: Lo que el plan de 7 días ignora

Antes de ilusionarnos con sprints de 1 semana, hay verdades incómodas:

1. **Son 40 requerimientos funcionales.** Cada uno con múltiples criterios de aceptación. Eso no se hace en 7 días ni en 15.
2. **Dos personas.** No es un equipo de 5. No hay PM, no hay QA dedicado, no hay DevOps.
3. **Stack híbrido Laravel + FastAPI.** Cada módulo de IA requiere coordinar 3 servicios (Laravel → FastAPI → Azure OpenAI → pgvector → respuesta).
4. **TOTP (RFC 6238)** no es un checkbox — implica sincronización de tiempo, ventanas de 30s, prevención de replay, registro de auditoría.
5. **RBAC estricto backend** (RNF03) — cada endpoint valida rol. Esto duplica el tiempo de desarrollo de cada ruta.
6. **Los módulos de IA (HU29-HU33) son HU de ALTA prioridad** con dependencias pesadas: perfiles de docentes, criterios de fase por director, motor de embeddings, Azure OpenAI. No se "dejan para después".
7. **142 días hábiles** dijo la fase 2 — y el cronograma original asume 2 personas full-time.

---

## 📐 Estimación realista

Basado en:
- 40 RF × ~1.5 días promedio de implementación + pruebas = **60 días**
- Infraestructura + CI/CD + deploy = **10 días**
- Módulos IA (FastAPI + embeddings + Azure OpenAI) = **15 días**
- TOTP + auditoría + seguridad (RNF01-RNF06) = **10 días**
- Responsive + accesibilidad + SUS (RNF10-RNF13) = **8 días**
- Reportes + exportación (HU36, RF40) = **5 días**
- Testing + bugs + imprevistos = **15 días**

**Total estimado: ~123 días hábiles ≈ 25 semanas ≈ 6 meses**

El cronograma de abajo asume **sprints de 1 semana (5 días hábiles)** con entregas funcionales cada viernes.

---

## 🗓️ Plan de Implementación por Semanas

### Sprint 1 — Fundación (Setup + Auth + Layout)

**HU cubiertas:** HU01, HU02, HU04  
**RF cubiertos:** RF01, RF02, RF03, RF04, RNF01, RNF02, RNF03, RNF11

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| 1 | Levantar proyecto Laravel 11 + Sail + PostgreSQL + Redis. Configurar React + Vite + Tailwind + shadcn/ui. | **Alta** — sin esto no hay nada |
| 2 | Google OAuth con Socialite. Validación de dominio @unab.edu.co. Middleware de rol (RBAC). | **Alta** — base de auth |
| 3 | Migración USUARIO + SEMESTRE + PROYECTO. Seeders de prueba. | **Alta** |
| 4 | Layout base: sidebar (256px) + header (sticky) + main. Implementar tokens CSS del DESIGN.md. Componentes base: Button, Badge, Card, Table, Form. | **Alta** — sin layout todo se ve mal |
| 5 | Pantallas de login institucional y evaluador externo. Cierre de sesión. Redirección por rol. | **Alta** |

**Riesgo:** Si el setup de Sail + OAuth falla un día completo, el sprint se atrasa. Buffer: 0 días.

---

### Sprint 2 — Gestión de Proyectos (Coordinador)

**HU cubiertas:** HU04, HU05, HU06, HU07, HU08  
**RF cubiertos:** RF06, RF07, RF08, RF09, RF10

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| 1 | CRUD de proyectos: formulario con validación, grupos de 1-3 estudiantes, justificación para grupo de 3. | **Alta** |
| 2 | Tablero de control del coordinador: KPIs, tabla de proyectos con colores (verde/amarillo/rojo), filtros. | **Alta** |
| 3 | Sistema de fases: 4 fases, bloqueo de avance sin entregas completas, cambio manual por coordinador. | **Alta** |
| 4 | Alertas automáticas por incumplimiento: triggers por fecha vencida, notificaciones in-app, correo. | **Alta** |
| 5 | Marcar proyecto "en riesgo de pérdida" tras 2 entregas incumplidas. Contador visible. | **Media** |

**Riesgo:** La lógica de fases con dependencias entre entregas es más compleja de lo que parece. Podría tomar 1-2 días extra.

---

### Sprint 3 — Gestión Documental (Entregas + Versiones)

**HU cubiertas:** HU09, HU10, HU11, HU12, HU13, HU14  
**RF cubiertos:** RF11, RF12, RF13, RF14, RF15, RF16

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| 1 | Carga de archivos (PDF/DOCX, max 50MB) con zona de upload drag-and-drop. Validación de plazo. | **Alta** |
| 2 | Versionado automático de documentos por entrega. Historial completo con metadatos. | **Alta** |
| 3 | Panel de revisión del director: split-screen documento + feedback. Checklist de revisión. | **Alta** |
| 4 | Flujo de aprobación: estudiante solicita → director habilita/habilita → estudiante envía. Bloqueo de botón. | **Alta** |
| 5 | Calificación con justificación obligatoria (0.0-5.0). Banco de documentos finales para coordinador. | **Alta** |

**Riesgo:** La carga asíncrona de 50MB con barra de progreso (RNF08) requiere manejo de chunks o streaming. No es trivial.

---

### Sprint 4 — Bitácoras de Reunión + TOTP

**HU cubiertas:** HU15, HU16, HU17, HU18, HU19  
**RF cubiertos:** RF17, RF18, RF19, RF20, RF21, RF22, RNF05

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| 1 | CRUD de bitácoras: formulario con tema, entregable, observaciones, evidencia adjunta. | **Alta** |
| 2 | **Implementación TOTP (RFC 6238):** generar clave en servidor, ventana 30s, validar timestamp servidor, prevenir replay. | **Crítica** — es el core de integridad |
| 3 | Flujo de firma: estudiante firma → director firma → bitácora "firmada". Estados: pendiente/firmada/sospechosa. | **Alta** |
| 4 | Detección de firmas sospechosas: mismo director firma múltiples en 5 min. Alerta al coordinador. | **Alta** |
| 5 | Control de horas mínimas por proyecto + alerta automática. Historial con filtros + exportación PDF. | **Media** |

**Riesgo:** TOTP es el punto más crítico de seguridad (RNF05). Un bug aquí invalida toda la auditoría. Requiere pruebas exhaustivas. **No se puede acortar.**

---

### Sprint 5 — Asignación de Directores + Evaluadores + Agenda

**HU cubiertas:** HU20, HU21, HU22, HU23, HU24  
**RF cubiertos:** RF23, RF24, RF25, RF26, RF27

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| 1 | Perfiles de docentes: nombre, correo, áreas de especialización, cupo máximo, proyectos dirigidos. CRUD para coordinador. | **Alta** |
| 2 | Asignación de director a proyecto: selector con docentes disponibles, notificación, registro en auditoría. Reasignación con justificación. | **Alta** |
| 3 | Registro de evaluadores externos: formulario con nombre + correo. Generación automática de invitación por correo. | **Alta** |
| 4 | Agenda de sustentaciones: configurar orden, hora, lugar. Generación PDF/Excel. Envío por correo a participantes. | **Media** |
| 5 | Perfiles docentes completos visibles para el chatbot de IA. Sincronización con módulo de sugerencias. | **Alta** — dependencia de IA |

---

### Sprint 6 — Chat Interno + Notificaciones + Anuncios + Recursos

**HU cubiertas:** HU25, HU26, HU27, HU28  
**RF cubiertos:** RF28, RF29, RF30, RF31

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| 1 | Chat interno estudiante ↔ director con Laravel Reverb (WebSocket). Solo entre miembros del proyecto. Archivos adjuntos (max 10MB). | **Alta** |
| 2 | Moderación: coordinador en modo lectura. Historial de mensajes. | **Media** |
| 3 | Sistema de notificaciones in-app (contador) + correo. Configuración de preferencias por usuario. | **Alta** |
| 4 | CRUD de anuncios: solo coordinador publica/edita/elimina. Vista para todos los roles. | **Alta** |
| 5 | Gestión de recursos: categorías, subir/enlazar archivos, buscador, contador de accesos. | **Media** |

**Riesgo:** Reverb (WebSocket) requiere configuración de broadcasting y un worker de colas. Puede complicarse con el entorno Docker.

---

### 🧠 Sprint 7 — IA: Asistente de Orientación (Chatbot)

**HU cubiertas:** HU29, HU30, HU34  
**RF cubiertos:** RF32, RF33, RF34

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| 1 | **Setup FastAPI + Sentence-Transformers:** microservicio independiente, comunicación HMAC con Laravel. | **Crítica** — dependencia de infraestructura |
| 2 | Endpoint de generación de embeddings. Búsqueda por similitud en pgvector contra perfiles docentes. | **Alta** |
| 3 | Integración con Azure OpenAI (GPT-4o-mini): construir prompt con contexto, recibir respuesta, parsear. | **Alta** |
| 4 | Chatbot en frontend: UI tipo chat, input de propuesta, cards de directores sugeridos con justificación. | **Alta** |
| 5 | Envío automático de resumen al director sugerido (correo autorizado por estudiante). Registro anonimizado de interacciones. | **Media** |

**Riesgo ALTO:** Dependemos de Azure OpenAI + Sentence-Transformers. El microservicio FastAPI debe estar operativo. Si la VM de Azure no está lista, este sprint se cae.

---

### 🧠 Sprint 8 — IA: Análisis Automático de Entregas

**HU cubiertas:** HU31, HU32, HU33, HU35  
**RF cubiertos:** RF35, RF36, RF37, RF39

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| 1 | **Criterios de evaluación por fase:** interfaz para director/coordinador crear/editar criterios con nombre, descripción, peso. | **Crítica** — sin criterios no hay análisis |
| 2 | Pipeline de análisis: al subir documento → extraer texto → generar embedding → comparar contra criterios → llamar Azure OpenAI. | **Alta** |
| 3 | Informe de retroalimentación: split-screen (documento + panel), score de coherencia, checklist de cumplimiento. | **Alta** |
| 4 | Alertas de coherencia si el documento no cumple criterios mínimos. Advertencia antes del envío. | **Media** |
| 5 | Registro de notas de metodología por corte (docente). Consolidación con notas de entregables. | **Media** |

**Riesgo ALTO:** Este es el módulo más complejo técnicamente. La extracción de texto de PDF no es 100% confiable, los embeddings requieren tuning, y Azure OpenAI puede dar respuestas inconsistentes.

---

### Sprint 9 — Evaluación + Reportes + Refinamiento

**HU cubiertas:** HU34, HU35, HU36  
**RF cubiertos:** RF38, RF39, RF40

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| 1 | Registro de notas de presentación final por evaluadores (cada uno por separado, con justificación). Promedio ponderado. | **Alta** |
| 2 | Módulo de docente de metodología: notas por corte vinculadas al proyecto. | **Media** |
| 3 | Reporte consolidado de calificaciones: notas de entregas + metodología + presentación. Exportación PDF y Excel. | **Media** |
| 4 | Auditoría (RF05): log completo visible para coordinador, no editable, filtros por usuario/fecha/acción. | **Alta** |
| 5 | RNF12: mensajes de error claros en español. RNF13: pruebas de navegación de 3 clics. | **Media** |

---

### Sprint 10 — QA + Responsive + Accesibilidad + Performance

**HU cubiertas:** N/A — calidad  
**RF cubiertos:** RNF07, RNF08, RNF09, RNF10, RNF11, RNF12, RNF13, RNF16

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| 1 | Diseño responsivo: probar 1920×1080, 1280×720, 768×1024, 375×667. Ajustar layout, tablas, formularios. | **Alta** |
| 2 | Accesibilidad: skip-link, aria-labels, focus-visible, contraste WCAG AA, prefers-reduced-motion. | **Media** |
| 3 | Pruebas de carga: 30 usuarios concurrentes, tiempo de respuesta < 3s (RNF07). Optimizar assets. | **Media** |
| 4 | Pruebas de seguridad: OWASP ZAP, CSRF tokens, sanitización de inputs, HTTPS forzado (RNF04). | **Alta** |
| 5 | Pruebas de integración de flujo completo: login → crear proyecto → subir entrega → director revisa → evaluador califica. | **Alta** |

---

### Sprint 11 — CI/CD + Despliegue + Documentación

**HU cubiertas:** N/A  
**RF cubiertos:** RNF06, RNF14, RNF15, RNF09

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| 1 | Configurar GitHub Actions: 3 jobs (PHP backend, Python IA, frontend). Tests automáticos. | **Alta** |
| 2 | Docker Compose para todos los servicios. Nginx con TLS. Despliegue en VM de Azure. | **Crítica** |
| 3 | Configurar Sentry + Azure Application Insights. Backup automático de BD y archivos. | **Alta** |
| 4 | Documentación: README con instrucciones de instalación/despliegue (RNF14). Swagger/OpenAPI de la API. | **Media** |
| 5 | Política de tratamiento de datos (Ley 1581). Aceptación al primer login. Proceso de solicitud de eliminación. | **Media** |

**Riesgo:** Si la VM de Azure no tiene los $500 USD de crédito confirmados o hay restricciones de región, el despliegue se cae.

---

### Sprint 12 — Buffer + Correcciones + Prueba SUS

| Día | Actividad | Criticidad |
|-----|-----------|------------|
| 1-5 | Buffer para bugs imprevistos, correcciones post-despliegue, ajustes de UI, prueba SUS con usuarios reales. | **Alta** |

---

## 📊 Resumen de Esfuerzo

| Sprint | Área | Días |
|--------|------|------|
| 1 | Setup + Auth + Layout | 5 |
| 2 | Gestión de Proyectos (Coordinador) | 5 |
| 3 | Gestión Documental | 5 |
| 4 | Bitácoras + TOTP | 5 |
| 5 | Asignación Directores + Evaluadores | 5 |
| 6 | Chat + Notificaciones + Anuncios | 5 |
| 7 | IA: Asistente de Orientación | 5 |
| 8 | IA: Análisis Automático | 5 |
| 9 | Evaluación + Reportes | 5 |
| 10 | QA + Responsive + Accesibilidad | 5 |
| 11 | CI/CD + Despliegue + Docs | 5 |
| 12 | Buffer + Correcciones + SUS | 5 |
| **Total** | | **60 días hábiles ≈ 12 semanas ≈ 3 meses** |

> **Nota:** 60 días hábiles es lo que toma con 2 personas full-time sin interrupciones. Si hay días perdidos por otros compromisos académicos, ajustar a la realidad: **3 meses calendario es lo mínimo realista.**

---

## 🚨 Riesgos Clave (lo que puede matar el cronograma)

1. **Azure OpenAI sin aprobar** — Si el crédito de Azure no cubre GPT-4o-mini o hay restricciones de región, los módulos de IA no funcionan. **Mitigación:** tener plan B con API local (modelo open-source tipo Llama 3).

2. **TOTP mal implementado** — Si la ventana de 30s no es exacta o hay drift de reloj, las firmas fallan en producción. **Mitigación:** probar con skew de ±1 ventana.

3. **Rendimiento de embeddings** — Sentence-Transformers en CPU es lento. Si el microservicio FastAPI corre en una VM sin GPU, generar embeddings para documentos grandes (>50 páginas) puede tomar minutos. **Mitigación:** cola asíncrona con Laravel Queue + Redis.

4. **2 personas no alcanzan** — Cuando uno está bloqueado, el otro también porque no hay quién tome la tarea. **Mitigación:** priorizar implacablemente. Lo que no esté en el sprint actual, no existe.

5. **Subestimación de QA** — 40 RF × múltiples criterios = cientos de casos de prueba. Con 2 personas, el testing es lo primero que se sacrifica. **Mitigación:** automatizar pruebas desde el sprint 1 (Pest + Playwright).

---

## ✅ Verificación Semanal

Cada viernes antes de cerrar sprint, verificar:

- [ ] ¿Todos los RF del sprint tienen pruebas que pasan?
- [ ] ¿Las pantallas nuevas son responsive?
- [ ] ¿Los mensajes de error están en español y son claros?
- [ ] ¿Se registraron los cambios en el log de auditoría?
- [ ] ¿El coordinador puede hacer las tareas del sprint en ≤3 clics?

Si alguna respuesta es NO → el sprint no está completo.
