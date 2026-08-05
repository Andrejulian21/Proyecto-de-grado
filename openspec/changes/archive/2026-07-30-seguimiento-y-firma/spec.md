# Especificación: Seguimiento y Firma

## Propósito

Este change introduce tres capacidades nuevas en el sistema de proyectos de grado: firma de bitácoras con clave dinámica, tablero de seguimiento por semestre para el coordinador, y bitácoras semanales con ventana de edición limitada. No modifica capacidades existentes.

---

## Capacidad: logbook-signature (PR 1)

### RF-SIG-01: Generación de código al crear bitácora

Al crear una bitácora mediante `POST /api/bitacoras`, el sistema MUST generar un código numérico aleatorio de 6 dígitos (`random_int(100000, 999999)`), almacenar su hash con `Hash::make()` en `signature_code`, fijar `signature_code_expires_at = created_at + 2 minutos`, y devolver el código en texto plano en el campo `signature_code_plain` de la respuesta JSON.

#### Escenario: Código generado y devuelto al crear
- GIVEN un estudiante autenticado con un proyecto activo
- WHEN envía `POST /api/bitacoras` con datos válidos
- THEN el sistema MUST almacenar `signature_code` (hasheado) y `signature_code_expires_at`
- AND la respuesta MUST incluir `signature_code_plain` con exactamente 6 dígitos

#### Escenario: Expiración automática a los 2 minutos
- GIVEN una bitácora creada hace 2 minutos y 1 segundo
- WHEN se consulta su estado
- THEN `signature_code_expires_at` MUST ser estrictamente menor que `now()`

### RF-SIG-02: Firma del director con intentos y expiración

El endpoint `POST /api/bitacoras/{id}/firmar` MUST recibir `{ code: string }`, validar que la bitácora existe y `signature_status === Pendiente`, validar `now() <= signature_code_expires_at`, mantener un contador de intentos en memoria durante la request con máximo 5, y verificar el código con `Hash::check()`. Si verifica, MUST transicionar a `FirmadaDirector` con `director_signed_at = now()`. Si se agotan los 5 intentos o expira la ventana, MUST transicionar a `NoFirmada`.

#### Escenario: Firma exitosa con código correcto
- GIVEN una bitácora en `Pendiente` con código válido no expirado
- WHEN el director envía `POST /firmar` con `{ code: "<código correcto>" }`
- THEN el sistema MUST establecer `signature_status = FirmadaDirector`
- AND MUST registrar `director_signed_at = now()`

#### Escenario: Cinco intentos fallidos agotan la firma
- GIVEN una bitácora en `Pendiente` con código válido no expirado
- WHEN el director envía `POST /firmar` 5 veces con códigos incorrectos
- THEN el sexto intento MUST ser rechazado con estado `NoFirmada`

#### Escenario: Código expirado bloquea la firma
- GIVEN una bitácora con `signature_code_expires_at < now()`
- WHEN el director envía `POST /firmar` con cualquier código
- THEN el sistema MUST responder 422 y transicionar a `NoFirmada`

### RF-SIG-03: Re-solicitud única de código

El endpoint `POST /api/bitacoras/{id}/re-solicitar-codigo` MUST operar solo si `signature_status === NoFirmada` y `signature_retries < 1`. Si se cumplen ambas condiciones, MUST regenerar `signature_code` (nuevo hash), `signature_code_expires_at = now() + 2 minutos`, incrementar `signature_retries` en 1, y devolver el nuevo código en texto plano.

#### Escenario: Re-solicitud permitida una sola vez
- GIVEN una bitácora en `NoFirmada` con `signature_retries = 0`
- WHEN el estudiante envía `POST /re-solicitar-codigo`
- THEN el sistema MUST regenerar el código, fijar nueva expiración, incrementar `signature_retries` a 1 y devolver `signature_code_plain`

#### Escenario: Segunda re-solicitud rechazada
- GIVEN una bitácora con `signature_retries = 1`
- WHEN el estudiante envía `POST /re-solicitar-codigo`
- THEN el sistema MUST responder 422 sin regenerar código

### RF-SIG-04: Campos de firma en el modelo Bitacora

El modelo `Bitacora` MUST incluir los campos `signature_code` (string, nullable), `signature_code_expires_at` (datetime, nullable) y `signature_retries` (integer, default 0).

#### Escenario: Campos disponibles con sus tipos
- GIVEN una nueva migración aplicada
- WHEN se consulta el esquema de `bitacoras`
- THEN los tres campos MUST existir con los tipos y nulabilidad especificados

### RF-SIG-05: Caso NoFirmada en EstadoFirma

El enum `EstadoFirma` MUST incluir el caso `NoFirmada` como valor válido para `signature_status`.

#### Escenario: Estado NoFirmada asignable
- GIVEN una bitácora con intentos agotados o código expirado
- WHEN el sistema transiciona el estado
- THEN `signature_status` MUST aceptar y persistir el valor `NoFirmada`

---

## Capacidad: coordinator-tracking (PR 2 + PR 3)

### RF-TRK-01: Tabla seguimiento_observaciones

El sistema MUST mantener una tabla `seguimiento_observaciones` con `id` (auto-increment), `proyecto_id` (FK a `proyectos`), `semestre_id` (FK a `semestres`), `fase` (string: `anteproyecto`, `presentacion_anteproyecto`, `desarrollo`, `presentacion_final`), `observacion` (text), `created_at`, `updated_at` y restricción `UNIQUE(proyecto_id, semestre_id, fase)`.

#### Escenario: Observación única por combinación proyecto+semestre+fase
- GIVEN un proyecto y semestre con observación existente en fase `desarrollo`
- WHEN se intenta insertar otra observación para la misma combinación
- THEN la base de datos MUST rechazar el duplicado

### RF-TRK-02: Endpoint de seguimiento por semestre

`GET /api/admin/seguimiento/semestre/{semestre_id}` MUST devolver, para cada proyecto del semestre, un objeto con `proyecto` (id, code, title, estudiantes, director), `entregas` (map de fase a lista con id, title, status, due_date), `bitacoras` (conteos `grupo_a`, `grupo_b`) y `observaciones` (map de fase a texto).

#### Escenario: Respuesta con datos completos de un proyecto
- GIVEN un semestre con proyectos, entregas y bitácoras
- WHEN el coordinador solicita `GET /seguimiento/semestre/{id}`
- THEN la respuesta MUST incluir un elemento por proyecto con la estructura de payload especificada

### RF-TRK-03: Cálculo de estado de entrega

Para cada entrega dentro del payload, el sistema MUST computar `status` así: `entregada` si existe al menos una `VersionDocumento` asociada; `no_entrego` si no existe versión y `due_date < now()`; `pendiente` en cualquier otro caso.

#### Escenario: Tres estados según existencia de versión y fecha
- GIVEN una entrega con versión, una sin versión con `due_date` futura, y una sin versión con `due_date` pasada
- WHEN el sistema calcula los estados para las tres
- THEN MUST retornar `entregada`, `pendiente` y `no_entrego` respectivamente

### RF-TRK-04: Conteo de bitácoras por grupo

`grupo_a` MUST ser el conteo de bitácoras del proyecto con `semana` entre 1 y 16 (inclusive). `grupo_b` MUST ser el conteo con `semana` entre 17 y 32 (inclusive).

#### Escenario: Conteo correcto por rango de semana
- GIVEN un proyecto con 5 bitácoras en semanas 1-5 y 3 en semanas 17-19
- WHEN el sistema calcula los conteos
- THEN MUST devolver `grupo_a = 5` y `grupo_b = 3`

### RF-TRK-05: Upsert de observaciones

`PUT /api/admin/seguimiento/observaciones` MUST recibir `{ proyecto_id, semestre_id, fase, observacion }` y aplicar upsert: insertar si no existe la combinación `proyecto_id + semestre_id + fase`, o actualizar `observacion` y `updated_at` si existe.

#### Escenario: Crear y luego actualizar la misma observación
- GIVEN una combinación proyecto+semestre+fase sin observación previa
- WHEN el coordinador envía `PUT` con `observacion = "texto A"` y luego con `observacion = "texto B"`
- THEN la segunda llamada MUST persistir `texto B` sin crear un segundo registro

### RF-TRK-06: Vista de seguimiento en frontend

La pestaña "Seguimiento" en `GestionAlertas.tsx` MUST estar activa por defecto. La vista MUST incluir un selector de semestre (activos primero), una tabla generada dinámicamente desde el endpoint, columnas agrupadas por fase en `<details>` colapsables, indicadores visuales para cada estado de entrega y un campo de observación editable inline con guardado automático.

#### Escenario: Pestaña y selector funcionales
- GIVEN un coordinador autenticado
- WHEN abre la vista de alertas
- THEN la pestaña "Seguimiento" MUST estar activa y el selector MUST mostrar semestres activos antes que inactivos

#### Escenario: Observaciones se persisten tras recarga
- GIVEN una observación editada e inline-saved
- WHEN el coordinador recarga la página
- THEN el campo MUST mostrar el valor persistido

---

## Capacidad: logbook-weekly (PR 4)

### RF-WK-01: Campo semana en bitacoras

El sistema MUST agregar el campo `semana` (integer, nullable) a la tabla `bitacoras` con restricción `UNIQUE(proyecto_id, semana)`. El rango válido MUST ser 1 a 32 inclusive.

#### Escenario: Semana única por proyecto
- GIVEN un proyecto con una bitácora en semana 5
- WHEN se intenta crear otra bitácora en el mismo proyecto con `semana = 5`
- THEN el sistema MUST responder 422

### RF-WK-02: Backfill automático de semana

La migración MUST asignar `semana` a toda bitácora con `semana IS NULL`, ordenada por `created_at` ascendente dentro de cada proyecto, asignando 1, 2, 3, ... secuencialmente.

#### Escenario: Bitácoras existentes reciben semana por orden de creación
- GIVEN un proyecto con 3 bitácoras sin `semana`, creadas en orden A, B, C
- WHEN se ejecuta la migración de backfill
- THEN A MUST tener `semana = 1`, B `semana = 2`, C `semana = 3`

### RF-WK-03: Validación de semana al crear

Al crear una bitácora, el backend MUST validar que `semana` esté entre 1 y 32 inclusive y que no exista ya una bitácora con el mismo `proyecto_id` y `semana`. El frontend MUST proveer un selector con valores 1-32.

#### Escenario: Semana fuera de rango rechazada
- GIVEN una solicitud de creación con `semana = 33`
- WHEN el backend valida la entrada
- THEN MUST responder 422 con error de rango

#### Escenario: Semana duplicada rechazada
- GIVEN un proyecto con bitácora en semana 10
- WHEN el estudiante intenta crear otra con `semana = 10`
- THEN el backend MUST responder 422 y el frontend MUST mostrar mensaje de error

### RF-WK-04: Ventana de edición de 15 minutos

`PUT /api/bitacoras/{id}` MUST ser rechazado con 422 si `created_at + 15 minutos < now()`. El frontend MUST ocultar los controles de edición después de esa ventana y, dentro de ella, MUST mostrar el tiempo restante.

#### Escenario: Edición permitida dentro de la ventana
- GIVEN una bitácora creada hace 10 minutos
- WHEN el estudiante envía `PUT` con cambios
- THEN el sistema MUST aceptar la actualización

#### Escenario: Edición rechazada fuera de la ventana
- GIVEN una bitácora creada hace 20 minutos
- WHEN el estudiante envía `PUT` con cambios
- THEN el backend MUST responder 422
- AND el frontend MUST haber ocultado los controles de edición

### RF-WK-05: Renombre de etiqueta en UI

Todas las vistas de bitácora MUST mostrar la etiqueta "contenido" en lugar de "descripción detallada". El campo de base de datos `notes` no se renombra.

#### Escenario: Etiqueta actualizada en todas las vistas
- GIVEN cualquier vista que muestra una bitácora
- WHEN se renderiza el campo de descripción
- THEN la etiqueta visible MUST ser "contenido"
