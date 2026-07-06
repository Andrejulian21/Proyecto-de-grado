# 2.5 — Propuestas de Stack Tecnológico para consulta con Director

> **Documento de trabajo — Fase 2A (Diseño)**
> Sistema centralizado para el seguimiento y control de proyectos de grado del programa de Ingeniería de Sistemas de la UNAB
> Autores: Julian Arteaga Faria · Miguel Afanador Quintero
> Director: Javier Pinzon Castellanos
> Para revisión en reunión de avance — Fase 2A
> Estado: **BORRADOR para consulta**

---

## 1. Contexto y propósito

Este documento presenta **dos propuestas alternativas de stack tecnológico** para el sistema. Ambas cumplen los requisitos del ERS (Fase 1), el marco normativo colombiano (Ley 1581/2012, Resolución 403/2013 UNAB) y los umbrales de aceptación del PGI (TRL 5, SUS ≥70, análisis IA ≥70%, chatbot ≥80%).

La decisión entre las dos propuestas se solicita al Director del proyecto, considerando el cronograma restante (≈120 días hábiles a la fecha) y la composición del equipo (2 desarrolladores).

> ⚠️ **Cambio respecto al anteproyecto**: el PGI (sección 7.5) menciona **AWS** como proveedor de hosting. Ambas propuestas migran a **Microsoft Azure** por decisión del equipo. Azure ofrece el beneficio de **Azure for Students** (crédito gratuito) y se alinea con la posible existencia de convenio institucional para **Azure OpenAI Service** mencionado en el PGI.

---

## 2. Restricciones que ambas propuestas deben cumplir (trazabilidad ICONIX)

Antes de evaluar las opciones, se enumeran las restricciones duras que **ninguna** propuesta puede saltarse. Cada propuesta demostrará cómo las cumple.

| # | Restricción | Origen | Trazabilidad |
|---|---|---|---|
| R1 | Autenticación restringida al dominio `@unab.edu.co` vía Google OAuth | PGI §5.1, HU01-HU05 | ERS |
| R2 | TOTP RFC 6238 con validez de 30s para firma de bitácoras | HU19-HU22, RNF04-RNF05 | ERS |
| R3 | Versionado de documentos entregables | HU13-HU18, RNF documental | ERS |
| R4 | Módulo IA: chatbot que sugiere directores (≥80% pertinencia) | PGI §8, HU29-HU32 | PGI §8 |
| R5 | Módulo IA: análisis semántico de entregas (≥70% coherencia) | PGI §8, HU33-HU36 | PGI §8 |
| R6 | Cumplimiento Ley 1581/2012 (cifrado en tránsito/reposo, RBAC) | PGI §6.3.1 | Marco legal |
| R7 | Cumplimiento Resolución 403/2013 UNAB (transferencia internacional de datos) | PGI §6.3.7 | Marco legal UNAB |
| R8 | Despliegue con presupuesto $80.000-$200.000 COP/mes (≈$20-$50 USD) | PGI §7.5 | Presupuesto |
| R9 | 2 desarrolladores · 142 días totales · ≥120 días restantes | PGI §7.5, cronograma | Capacidad equipo |
| R10 | Alcanzar TRL 5 al cierre (validación con usuarios reales) | PGI §7.4, §8 | Madurez tecnológica |

> **Actualización (post-consulta con profesor):** R8 queda resuelto mediante una cuenta de crédito de $500 USD provista externamente (ver sección 6, pregunta 2/5). Ambas propuestas caben dentro de ese crédito sin necesidad de las optimizaciones de costo descritas en 3.4/4.4. **El criterio de costo deja de ser diferenciador entre A y B.**

---


## 4. PROPUESTA B — Stack Laravel + Microservicio IA

### 4.1 Resumen ejecutivo

Stack **bifurcado**: Laravel (PHP) como backend principal + microservicio Python (FastAPI) dedicado a IA. React en frontend, PostgreSQL como BD, embeddings locales en el microservicio Python, Azure OpenAI para el chatbot. La decisión arquitectónica clave es **aprovechar la experiencia existente del equipo en Laravel** para acelerar el incremento 1. Trade-off: 2 servicios que desplegar, 2 logs, 2 sistemas de autenticación que coordinar (JWT compartido).

### 4.2 Stack por capas

| Capa | Tecnología | Versión | Justificación corta |
|---|---|---|---|
| **Frontend** | React + Vite + TypeScript + Tailwind CSS + shadcn/ui | 18 / 5 / 5 / 3 | Idéntico a Propuesta A |
| **Backend principal** | Laravel + Laravel Sanctum (API tokens) | 11 | Equipo ya domina Laravel; ORM Eloquent maduro |
| **Auth** | Laravel Socialite (Google provider) + listener `Authenticated` para validar dominio | 5.13 | Open source, validación de dominio en event listener |
| **TOTP** | pragmarx/google2fa-laravel + pragmarx/google2fa-qrcode | — | Estándar RFC 6238, generación QR para enrolment |
| **Base de datos** | PostgreSQL | 16 | Mismo que Propuesta A |
| **Storage documentos** | Azure Blob Storage con versionado + league/flysystem-azure-blob-storage | 3 | Versionado nativo |
| **Microservicio IA** | Python 3.12 + FastAPI + sentence-transformers + openai SDK + uvicorn | — | Servicio dedicado para inferencia, expuesto vía HTTP interno (no público) |
| **Comunicación Laravel↔IA** | HTTP REST con firma HMAC (mismo secreto en ambos servicios) | — | Autenticación service-to-service, evita exposure del microservicio a internet |
| **IA — Embeddings** | sentence-transformers `paraphrase-multilingual-MiniLM-L12-v2` en microservicio | 2.7 | Idéntico a Propuesta A |
| **IA — Chatbot** | Azure OpenAI Service (GPT-4o-mini) | — | Idéntico a Propuesta A |
| **Hosting** | Azure App Service B1 (Laravel) + Azure Container Instances (FastAPI) + Azure Database for PostgreSQL | — | ACI es ~$30 USD/mes para 1 vCPU 1 GB siempre encendido |
| **Tareas async** | Laravel Queue con database driver + Laravel Horizon | — | Procesamiento embeddings dispara job Laravel que llama al microservicio |
| **Monitoreo** | Sentry (PHP + Python SDK) + Azure Application Insights | — | Idéntico |
| **Testing** | PHPUnit + Pest (PHP) + pytest (Python) + Playwright (e2e) | — | 3 frameworks; mayor overhead de mantenimiento |
| **CI/CD** | GitHub Actions (3 jobs: backend PHP, microservicio Python, frontend) | — | Más complejo que Propuesta A |

### 4.3 Trazabilidad de restricciones

| Restricción | Cumplimiento en Propuesta B |
|---|---|
| **R1** OAuth @unab.edu.co | Laravel Socialite con `SocialiteWasCalled` listener que filtra `user->email` por regex `^.+@unab\.edu\.co$` antes de crear usuario |
| **R2** TOTP 30s | `pragmarx/google2fa-laravel` con `setWindow(1)` (±30s) |
| **R3** Versionado docs | Azure Blob con versionado habilitado + interfaz `Filesystem` de Laravel |
| **R4** Chatbot ≥80% | Mismo Azure OpenAI GPT-4o-mini, llamada desde Laravel via HTTP al microservicio (o directo con Azure SDK PHP) |
| **R5** Análisis ≥70% | Laravel dispara job → microservicio Python calcula embedding → busca en pgvector → genera feedback |
| **R6** Ley 1581/2012 | TLS 1.3, SSE at-rest, RBAC con Policies de Laravel |
| **R7** Resolución 403/2013 | Embeddings **en Colombia** dentro del microservicio Python. Chatbot envía prompt a Azure OpenAI — **idéntica consideración de consentimiento que Propuesta A** |
| **R8** Presupuesto | App Service B1 $13 + ACI embeddings $30 + Azure DB $30 + Storage $1 = **≈$74 USD/mes** (ligeramente superior a A) |
| **R9** Equipo / tiempo | **Velocidad inicial máxima** (Laravel conocido), pero a partir de incremento 5 la bifurcación penaliza |
| **R10** TRL 5 | Idéntico |

### 4.4 Costos estimados detallados (USD/mes)

| Recurso | SKU | Costo mensual | Notas |
|---|---|---|---|
| App Service (Laravel) | B1 Linux | $13.14 | |
| Azure Container Instances (FastAPI) | 1 vCPU, 1 GB, always-on | ~$30.00 | ACI always-on es más caro que VM; alternativa: VM B1s $7.59 |
| Azure Database for PostgreSQL | Basic | $30.00 | |
| Azure Blob Storage | Standard LRS | $1.00 | |
| Azure OpenAI (chatbot) | GPT-4o-mini | ~$0.45 | |
| **Total (con ACI)** | | **≈$74 USD/mes** | |
| **Total (con VM B1s en vez de ACI)** | | **≈$52 USD/mes** | |

> Si se usa Azure for Students, baja a ~$20-30 USD/mes en ambos casos.

### 4.5 Plan de implementación por incremento (Fase 3)

| Incremento | Duración estimada | Componentes principales |
|---|---|---|
| 1. Auth + roles | 1.5 semanas | Laravel + Socialite, RBAC con Policies, TOTP, modelos User/Role |
| 2. Gestión documental | 2 semanas | Modelos, Azure Blob integration, versionado, API REST |
| 3. Seguimiento por fases + asignación | 2.5 semanas | Máquina de estados, asignación, dashboard |
| 4. Comunicación entre actores | 1.5 semanas | Chat interno (Laravel Reverb/Pusher), notificaciones, email |
| 5. Funcionalidades IA | 5 semanas | **Desarrollo del microservicio FastAPI**, embeddings, pgvector, integración Azure OpenAI, UI chatbot, **coordinación Laravel↔Python** |

**Total estimado Fase 3**: 12.5 semanas (≈63 días hábiles) — **ligeramente inferior a A**, pero el incremento 5 es más riesgoso por la integración entre dos stacks.

### 4.6 Pros y contras

| ✅ Pros | ❌ Contras |
|---|---|
| **Velocidad inicial máxima**: equipo ya domina Laravel | **2 servicios** que desplegar, monitorear, versionar |
| Eloquent ORM es muy productivo | Debug cross-stack (PHP ↔ Python) requiere herramientas adicionales |
| Laravel Horizon para colas es muy visual | Tests en 3 frameworks (PHPUnit/Pest + pytest + Playwright) |
| Comunidad Laravel enorme en español | Complejidad operacional: HMAC service-to-service, latencia de red entre servicios |
| — | Reutilización de modelos: User existe en Laravel, no en FastAPI → modelo duplicado o referencia por ID |
| — | Si el equipo aprende Django durante el proyecto, **no se capitaliza para mantenimiento futuro** |

### 4.7 Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Latencia Laravel → FastAPI degrada UX | Media | Medio | Pre-cálculo de embeddings; respuesta asíncrona con polling |
| Microservicio Python se cae y rompe análisis | Media | Alto | Health check + retry exponencial en Laravel; circuit breaker |
| Costos ACI se disparan | Media | Medio | Migrar a VM B1s ($7.59 vs $30) si budget aprieta |
| Duplicación de modelos (User en Laravel y FastAPI) | Alta | Bajo | FastAPI solo lee metadata de usuario vía API Laravel; no duplica |
| Complejidad operacional con 2 deploys | Alta | Medio | Docker Compose local; IaC con Terraform para Azure |

---

## 5. Comparación lado a lado

| Criterio | Propuesta A (Django) | Propuesta B (Laravel+FastAPI) | Ganador |
|---|---|---|---|
| **Velocidad inicial** (primeros 30 días) | ⚠️ Curva Django 2-3 sem | ✅ Equipo ya conoce Laravel | **B** |
| **Velocidad sostenida** (después de incremento 3) | ✅ Todo en Python, sin coordinación cross-stack | ⚠️ Debug PHP↔Python, 2 deploys | **A** |
| **Complejidad operacional** | ✅ 1 backend, 1 deploy | ⚠️ 2 servicios, 2 deploys, HMAC | **A** |
| **Ecosistema IA** (LangChain, pgvector, sentence-transformers) | ✅ **Nativo Python** | ⚠️ A través de microservicio | **A** |
| **Costo mensual hosting** | $69 USD (optimizable a $51) | $74 USD (optimizable a $52) | Empate |
| **Cobertura de tests** | pytest + Playwright | PHPUnit + Pest + pytest + Playwright | **A** (menos overhead) |
| **Comunidad y documentación en español** | ✅ Buena | ✅ Excelente (Laravel es muy popular) | **B** |
| **Contratación futura / mantenimiento** | Django tiene demanda media-alta | Laravel tiene demanda alta en Colombia | **B** |
| **Cumplimiento R7 (datos en Colombia)** | ✅ Idéntico (embeddings locales) | ✅ Idéntico | Empate |
| **Riesgo de retraso en incremento 5 (IA)** | Bajo (todo en Python) | Medio (integración PHP-Python) | **A** |
| **Reutilización de aprendizaje para versión 2** | El equipo aprende Django y Python AI — útil a futuro | El equipo confirma Laravel, aprende FastAPI — útil también | Empate |
| **Documentación ERS / RNF traceability** | ✅ Completa | ✅ Completa | Empate |

### Análisis comparativo — sin recomendación cerrada del equipo

Ambas propuestas cumplen las restricciones R1-R10. La diferencia real entre ellas no es de "cuál es mejor en abstracto", sino de **qué riesgo está dispuesto a asumir el proyecto** dado el tiempo restante (~120 días) y el equipo (2 desarrolladores sin experiencia previa en Django ni en FastAPI):

- **Propuesta A (Django)** reduce el riesgo de integración en el incremento 5 (todo el código de IA vive en el mismo lenguaje y proceso lógico), a cambio de absorber una curva de aprendizaje completa de Django + DRF al inicio del proyecto, en un framework que el equipo no ha usado antes. Si esa curva se extiende más allá de las 2-3 semanas estimadas, el colchón de tiempo se reduce directamente.

- **Propuesta B (Laravel + microservicio FastAPI)** elimina la curva de aprendizaje inicial porque el equipo ya domina Laravel, lo que da más margen en los primeros 3 incrementos. El riesgo se traslada al incremento 5, donde dos servicios en lenguajes distintos deben coordinarse (autenticación service-to-service, dos suites de pruebas, dos pipelines de despliegue).

No hay una opción objetivamente superior: es una decisión de **en qué fase del proyecto se prefiere absorber el riesgo**, al inicio (A) o al final (B). Se solicita al Director una posición explícita sobre esto antes de avanzar a la actividad 2.1.

---

## 6. Preguntas para el Director

1. **¿La decisión de cambiar AWS → Azure es definitiva?** Si sí, se actualiza el PGI §7.5. Si no, ajustamos ambas propuestas a AWS.

2. ~~¿Existe convenio institucional activo con Azure OpenAI Service?~~ **CONFIRMADO**: el profesor [pendiente nombre/rol] proveerá una cuenta con crédito de **$500 USD** para uso de Azure OpenAI Service. Esto cubre ampliamente el costo estimado del chatbot (~$0.45 USD/mes) durante toda la Fase 3 y el piloto. Queda pendiente confirmar: (a) si esta cuenta tiene alguna restricción de región/data residency que afecte el cumplimiento de R7, y (b) si el crédito es de uso único o recurrente mes a mes.

3. **¿Cuál es la prioridad relativa entre estos dos factores?**
   - (a) Velocidad de entrega de los primeros 3 incrementos (favorece B)
   - (b) Menor riesgo de integración en el incremento 5 / IA (favorece A)

4. **¿Hay preferencia institucional** (decano, oficina de TI) por algún stack en particular? Algunas universidades tienen política de uso de PHP o de Python.

5. ~~¿El equipo tiene acceso a créditos Azure?~~ **CONFIRMADO**: sí existe convenio/acceso a créditos. Con esto, el costo mensual de hosting de **ambas propuestas queda cubierto** (Propuesta A ≈$69 USD/mes, Propuesta B ≈$74 USD/mes), sin necesidad de las optimizaciones de reducción de costos descritas en 3.4 y 4.4. Esto **elimina la restricción R8 como factor de decisión** entre A y B — ya no es un criterio diferenciador.

6. **¿Aprueba el Director que los embeddings se ejecuten localmente** (datos académicos no salen de Colombia) en vez de usar Azure OpenAI Embeddings? Esta decisión es la más sensible legalmente y debe quedar documentada en el acta de validación de Fase 2.

---

## 7. Cierre

Una vez aprobada una de las dos propuestas, este documento se actualiza a **versión "Aprobada — Director"** y se incorpora al **Documento de Diseño del Sistema** (entregable OE2) junto con los artefactos de las actividades 2.1, 2.2 y 2.3.

| | |
|---|---|
| **Próxima actividad (2.1)** | Diseño de arquitectura en capas, basada en la propuesta aprobada |
| **Próxima actividad (2.4)** | Diseño detallado del módulo IA con la API seleccionada |
| **Documentos relacionados** | `pgi-documento.md` §7, `historias-usuario.md` HU29-HU36, `gantt.md` Fase 2 |

---

*Documento preparado por Julian Arteaga Faria & Miguel Afanador Quintero — 02/06/2026*
*Revisión v2 — 19/06/2026: corrección de sesgo en veredicto (ahora presentado como trade-off neutral, no como recomendación cerrada del equipo), actualización de R8/presupuesto con crédito confirmado de $500 USD, y confirmación de convenio Azure OpenAI.*
