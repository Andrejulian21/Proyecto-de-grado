# Especificación: Entregas y Evaluación de Evaluadores

> Change: `entregas-evaluacion` | Sprint 5 | Modo: hybrid | 3 capacidades nuevas (sin colisión con `seguimiento-y-firma`)

## Decisiones tomadas en spec

| # | Decisión | Resolución |
|---|----------|-----------|
| D1 | ¿Más de una entrega por fase en el mismo semestre? | SÍ. La suma del par incluye TODAS las entregas de ambas fases (no se agrega constraint UNIQUE). |
| D2 | Semestre con entregas incompletas (una fase del par sin % NOT NULL) | No se dispara bloqueo. El frontend MUST mostrar advertencia visual "Par incompleto: falta asignar % en la fase X". |
| D3 | Ubicación técnica de `director_grade` | Columna en `entregas` (la nota es por entrega, no por versión de documento). |
| D4 | Edición de `%` en entrega existente | Misma lógica de validación de pesos que en Store (RF-ENT-04 aplica a Store y Update). |
| D5 | Edición de `director_grade` con la entrega cerrada | Bloqueado (ver RF-NOT-03). |
| D6 | Re-envío de evaluación del evaluador | Bloqueado con 409 (ver RF-EVA-03). |
| D7 | Rango de las NOTAS (director_grade y nota del evaluador) | **0 a 5 con 2 decimales.** `grade_percentage` (peso) sigue en 0-100 — es una magnitud distinta (peso porcentual, no calificación). |

---

## Capacidad: entregas-archivos

Default de archivo principal, `analizable_ia`, `grade_percentage` y regla de pesos 100% por par de fases a nivel de semestre.

### RF-ENT-01: Default "documento del proyecto" enforced en backend

`StoreEntregaRequest` y `UpdateEntregaRequest` MUST requerir que `archivos_requeridos` contenga al menos un objeto con `slug = "documento-proyecto"`. Si falta, MUST responder 422.

#### Escenario: Crear entrega con archivo principal válido
- GIVEN un coordinador autenticado con payload que incluye `archivos_requeridos` con `slug = "documento-proyecto"`
- WHEN envía `POST /api/admin/entregas`
- THEN el sistema MUST crear la entrega y devolver 201 con la estructura persistida

#### Escenario: Falta el archivo principal
- GIVEN un payload con `archivos_requeridos = []` o sin `slug = "documento-proyecto"`
- WHEN se envía la solicitud
- THEN el sistema MUST responder 422 con `error.message = "Debe existir al menos el archivo 'documento del proyecto' en los archivos requeridos"`

### RF-ENT-02: Campo `analizable_ia` por archivo requerido

Cada objeto de `archivos_requeridos` MAY incluir `analizable_ia: boolean`. Si un objeto con `slug ≠ "documento-proyecto"` envía `analizable_ia = true`, MUST rechazar con 422.

#### Escenario: IA habilitada en el archivo principal
- GIVEN un payload con `slug = "documento-proyecto"` y `analizable_ia = true`
- WHEN se envía la solicitud
- THEN el sistema MUST persistir la entrega con el flag `true` en el JSON

#### Escenario: IA rechazada en archivo secundario
- GIVEN un payload con `slug = "anexo"` y `analizable_ia = true`
- WHEN se envía la solicitud
- THEN el sistema MUST responder 422 con `error.message = "Solo el archivo 'documento del proyecto' puede ser analizable con IA"`

### RF-ENT-03: Columna `grade_percentage` (peso informacional)

La tabla `entregas` MUST tener `grade_percentage` (decimal 5,2, nullable). El valor MUST estar entre 0 y 100. Entregas existentes quedan NULL y no participan en la suma del par (RF-ENT-04).

#### Escenario: Persistir % al crear
- GIVEN un payload con `grade_percentage = 60`
- WHEN se crea la entrega
- THEN el sistema MUST persistir 60.00 y devolver el campo

#### Escenario: % fuera de rango
- GIVEN un payload con `grade_percentage = 150` o `-5`
- WHEN se envía la solicitud
- THEN el sistema MUST responder 422 con `error.message = "El porcentaje de nota debe estar entre 0 y 100"`

### RF-ENT-04: Regla de pesos 100% por par de fases a nivel de semestre

Pares: `anteproyecto` + `presentacion_anteproyecto` (Par 1), `desarrollo` + `presentacion_final` (Par 2). La validación opera sobre TODAS las entregas del semestre (no por proyecto) y aplica en Store y Update. Entregas con `grade_percentage = NULL` no cuentan.

**Bloqueo preventivo**: si (suma existente del par NOT NULL en el semestre) + (nuevo valor propuesto) > 100, MUST rechazar con 422.

**Validación de completitud**: si al guardar la entrega se cumple que ambas fases del par tienen al menos una entrega con % NOT NULL en el semestre, la suma total MUST ser exactamente 100; si no, MUST rechazar con 422.

#### Escenario: Crear entrega que completa el par en 100%
- GIVEN el semestre tiene solo una entrega en `anteproyecto` con `grade_percentage = 40` y ninguna en `presentacion_anteproyecto`
- WHEN el coordinador crea una entrega en `presentacion_anteproyecto` con `grade_percentage = 60`
- THEN el sistema MUST persistir la entrega (Par 1 = 100%)

#### Escenario: Bloqueo cuando la suma superaría 100%
- GIVEN el Par 1 del semestre ya suma 70% NOT NULL
- WHEN se intenta crear/actualizar una entrega del Par 1 con `grade_percentage = 40`
- THEN el sistema MUST responder 422 con `error.message = "La suma de porcentajes del par de fases superaría el 100% (actual 70% + nuevo 40% = 110%)"`

#### Escenario: Completitud = 100% cuando se cierra el par
- GIVEN el Par 1 ya tiene entregas NOT NULL en `anteproyecto` (50%) y `presentacion_anteproyecto` (40%), y no existen más entregas en la otra fase
- WHEN se crea otra entrega del Par 1 con `grade_percentage = 20`
- THEN el sistema MUST responder 422 con `error.message = "La suma de porcentajes del par de fases debe ser exactamente 100% (actual 90% + nuevo 20% = 110%)"`

#### Escenario: % NULL no participa en la suma
- GIVEN el semestre tiene 3 entregas en `anteproyecto` (dos NULL, una con 50%) y ninguna en `presentacion_anteproyecto`
- WHEN se crea una entrega en `presentacion_anteproyecto` con `grade_percentage = 50`
- THEN el sistema MUST persistir la entrega (suma efectiva = 100%)

#### Escenario: Crear entrega con % NULL nunca bloquea
- GIVEN el Par 1 del semestre no tiene ninguna entrega con % NOT NULL
- WHEN se crea una entrega con `grade_percentage = NULL`
- THEN el sistema MUST persistir sin disparar validación de suma

#### Escenario: Update con cambio a NULL desbalancea pero no bloquea
- GIVEN el Par 1 ya está completo en 100% (50 + 50)
- WHEN el coordinador actualiza la entrega de 50% a `grade_percentage = NULL`
- THEN el sistema MUST persistir el cambio (el par pasa a estado "incompleto" sin disparar bloqueo)

### RF-ENT-05: Indicador visual de suma acumulada del par

El form del coordinador MUST mostrar en tiempo real la suma acumulada de `grade_percentage` del par (mismo semestre, ambas fases) considerando solo entregas con % NOT NULL. La suma MUST pintarse verde cuando ≤ 100 y roja cuando > 100. Si una de las fases no tiene entregas con %, MUST mostrar advertencia "Par incompleto: falta asignar % en la(s) fase(s) X".

#### Escenario: Suma en verde
- GIVEN el coordinador edita una entrega con `grade_percentage = 40` y la suma del par en el semestre es 50%
- WHEN el campo de % muestra el valor
- THEN el indicador MUST estar en verde con texto "Suma del par: 50% / 100%"

#### Escenario: Suma en rojo
- GIVEN el coordinador edita una entrega con `grade_percentage = 70` y la suma del par es 50%
- WHEN el campo muestra el valor
- THEN el indicador MUST estar en rojo con texto "Suma del par: 120% (excede 100%)"

#### Escenario: Advertencia de par incompleto
- GIVEN el semestre no tiene entregas con % NOT NULL en `desarrollo`
- WHEN el coordinador edita una entrega de `presentacion_final` con cualquier %
- THEN la vista MUST mostrar "Par incompleto: falta asignar % en la fase 'desarrollo'"

---

## Capacidad: evaluacion-evaluador

Flujo completo de evaluación para evaluadores externos con cards, detalle, envío de nota, toggle e inmutabilidad.

### RF-EVA-01: Cards de proyectos asignados

`GET /api/evaluador/mis-asignaciones` MUST devolver, para el evaluador autenticado en el semestre activo, items con `id`, `proyecto: { id, codigo, titulo, estudiantes, director }`, `fase`, `evaluado: boolean`, `created_at`.

#### Escenario: Listar asignaciones del evaluador autenticado
- GIVEN un evaluador con 3 asignaciones (2 pendientes, 1 evaluada)
- WHEN solicita el endpoint
- THEN el sistema MUST devolver 200 con los 3 items y su flag `evaluado` correspondiente

#### Escenario: Aislamiento por evaluador
- GIVEN dos evaluadores con asignaciones distintas
- WHEN el evaluador A solicita el endpoint
- THEN el sistema MUST devolver exclusivamente las asignaciones del evaluador A

### RF-EVA-02: Detalle de asignación con contexto completo

`GET /api/evaluador/asignaciones/{id}/detalle` MUST devolver `proyecto` (id, codigo, titulo, director, estudiantes), `fase`, `entrega` (con `archivos_requeridos`, `due_date`, `director_grade` en escala 0-5 y `versiones_documento` con archivos y `director_notes`), `evaluacion` (null si pendiente, o `{nota, observaciones, evaluated_at}` si enviada, con `nota` en escala 0-5). La asignación MUST pertenecer al evaluador autenticado; si no, 403.

#### Escenario: Detalle de asignación pendiente
- GIVEN una asignación pendiente del evaluador autenticado
- WHEN solicita el detalle
- THEN el sistema MUST devolver 200 con `evaluacion = null` y `entrega` con archivos del director

#### Escenario: Detalle de asignación de otro evaluador
- GIVEN una asignación que pertenece a otro evaluador
- WHEN el evaluador autenticado solicita su detalle
- THEN el sistema MUST responder 403 con `error.message = "No tiene acceso a esta asignación"`

### RF-EVA-03: Envío de nota + observaciones (inmutable)

`POST /api/evaluador/asignaciones/{id}/evaluar` MUST recibir `{ nota: decimal(0-5, 2 decimales), observaciones: string(1-2000) }`, validar pertenencia al evaluador autenticado y `evaluado = false`, crear el registro en `evaluaciones_evaluador` con `evaluated_at = now()`, marcar `evaluador_proyecto.evaluado = true` y devolver 201. La evaluación NO MUST ser editable: no existen `PUT/DELETE` sobre este recurso.

#### Escenario: Enviar evaluación exitosamente
- GIVEN una asignación pendiente del evaluador autenticado
- WHEN envía `POST .../evaluar` con `nota = 4.5` y `observaciones = "Documento bien estructurado"`
- THEN el sistema MUST crear el registro, marcar `evaluado = true` y devolver 201

#### Escenario: Nota fuera de rango
- GIVEN un payload con `nota = 5.01` o `6` o `-0.5`
- WHEN se envía la solicitud
- THEN el sistema MUST responder 422 con `error.message = "La nota debe estar entre 0 y 5"`

#### Escenario: Re-envío sobre evaluación ya creada
- GIVEN una asignación con `evaluado = true`
- WHEN el evaluador intenta `POST .../evaluar` nuevamente
- THEN el sistema MUST responder 409 con `error.message = "La evaluación ya fue enviada y no puede modificarse"`

### RF-EVA-04: Toggle de proyectos ya evaluados

La página "Mis Asignaciones" MUST ocultar por defecto las asignaciones con `evaluado = true` y MUST mostrar un control para alternar la visibilidad. Cuando se activa, las tarjetas evaluadas MUST mostrar botón "Ver" en lugar de "Evaluar" y, al hacer click, MUST abrir el detalle en modo solo lectura con nota y observaciones propias.

#### Escenario: Toggle activa vista de evaluados
- GIVEN el evaluador tiene 2 asignaciones evaluadas
- WHEN activa el toggle "Ver ya evaluados"
- THEN la página MUST mostrar las 2 tarjetas evaluadas con botón "Ver"

#### Escenario: Ver detalle de evaluación enviada
- GIVEN una asignación evaluada
- WHEN el evaluador hace click en "Ver"
- THEN el sistema MUST abrir la vista de detalle con los inputs de nota y observaciones deshabilitados (solo lectura)

### RF-EVA-05: Persistencia en `evaluador_proyecto` y `evaluaciones_evaluador`

`evaluador_proyecto` MUST agregar `evaluado: boolean` (default `false`). La tabla `evaluaciones_evaluador` MUST tener `id`, `evaluador_proyecto_id` (FK a `evaluador_proyecto`, UNIQUE), `nota` (decimal 4,2, rango 0-5 — ver RF-EVA-03), `observaciones` (text), `created_at`, `updated_at`. Se agrega `evaluated_at` (timestamp nullable) para distinguir el momento de envío de la nota del `created_at` del registro.

#### Escenario: Unicidad por asignación
- GIVEN una asignación ya evaluada
- WHEN se intenta crear un segundo registro en `evaluaciones_evaluador` con el mismo `evaluador_proyecto_id`
- THEN la base de datos MUST rechazar el duplicado

#### Escenario: `evaluated_at` se persiste al enviar
- GIVEN una asignación pendiente
- WHEN el evaluador envía `POST .../evaluar` con una nota válida
- THEN el registro creado en `evaluaciones_evaluador` MUST tener `evaluated_at` distinto de NULL y cercano a `now()`

---

## Capacidad: nota-director

Nota de la entrega que el director asigna al aprobar, editable mientras la entrega esté activa.

### RF-NOT-01: Columna `director_grade` en `entregas`

La tabla `entregas` MUST tener `director_grade` (decimal 4,2, nullable). El valor MUST estar entre 0 y 5 con 2 decimales cuando se asigne.

#### Escenario: Columna disponible tras migración
- GIVEN una migración aplicada
- WHEN se consulta el esquema de `entregas`
- THEN la columna `director_grade` MUST existir como `decimal(4,2) NULL`

#### Escenario: Nota fuera de rango
- GIVEN un payload con `director_grade = 5.01` o `6` o `-0.5`
- WHEN se envía la solicitud
- THEN el sistema MUST responder 422 con `error.message = "La nota del director debe estar entre 0 y 5"`

### RF-NOT-02: Nota aparece al aprobar observación del director

Cuando el director marca una observación de la entrega como "aprobada", la vista MUST mostrar un campo para ingresar `director_grade`. Si no hay observación aprobada, el campo MUST permanecer oculto.

#### Escenario: Campo de nota visible tras aprobación
- GIVEN el director marca una observación como aprobada
- WHEN se renderiza la vista de revisión
- THEN el sistema MUST mostrar el campo `director_grade` habilitado

#### Escenario: Campo de nota oculto sin aprobación
- GIVEN el director solo tiene observaciones pendientes (ninguna aprobada)
- WHEN se renderiza la vista
- THEN el sistema MUST ocultar el campo de nota

### RF-NOT-03: Nota y observación editables mientras la entrega esté activa

El director MAY editar `director_grade` y sus observaciones mientras `entrega.status === activa` y `due_date >= now()`. En caso contrario, los inputs MUST estar deshabilitados y la API MUST responder 422.

#### Escenario: Edición permitida antes del cierre
- GIVEN una entrega activa con `due_date` en el futuro
- WHEN el director actualiza `director_grade`
- THEN el sistema MUST persistir el cambio y devolver 200

#### Escenario: Edición rechazada tras el cierre
- GIVEN una entrega con `status ≠ activa` o `due_date < now()`
- WHEN el director intenta modificar la nota o la observación
- THEN el sistema MUST responder 422 con `error.message = "La entrega está cerrada; la nota y las observaciones no pueden modificarse"`

### RF-NOT-04: Nota del director visible al evaluador

El endpoint `GET /api/evaluador/asignaciones/{id}/detalle` MUST incluir `director_grade` (puede ser null) en la sección `entrega`.

#### Escenario: Nota del director presente
- GIVEN una entrega con `director_grade = 4.0` ya asignada
- WHEN el evaluador solicita el detalle
- THEN la respuesta MUST incluir `entrega.director_grade = 4.0`

> Nota: la nota se almacena en escala 0-5 con 2 decimales (ver RF-NOT-01). El ejemplo 4.0 permanece válido dentro del rango.

#### Escenario: Nota del director ausente
- GIVEN una entrega con `director_grade = NULL`
- WHEN el evaluador solicita el detalle
- THEN la respuesta MUST incluir `entrega.director_grade = null`
