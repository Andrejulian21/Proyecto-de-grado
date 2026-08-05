# Propuesta: Seguimiento y Firma

## Resumen

Un solo cambio con **3 capacidades nuevas** entregadas en **4 PRs encadenados**:

1. **Firma por clave dinámica** para bitácoras (código 6 dígitos, ventana 2 min, 5 intentos, 1 re-solicitud permitida).
2. **Tablero de seguimiento por semestre** para el coordinador con columnas dinámicas por entrega y grupos de fase colapsables.
3. **Bitácoras semanales** con campo `semana` (1-32) que el estudiante elige al crear, una por semana, ventana de edición de 15 min, y renombre de "descripción detallada" a "contenido".

## Motivación

- **Firma insegura**: hoy el director firma con un POST simple sin validación real de que está presente. Una clave dinámica con tiempo limitado fuerza la co-presencia sin requerir ceremonia separada.
- **Seguimiento opaco**: el coordinador no tiene una vista única de todos los proyectos del semestre. Hoy cruza alertas, páginas individuales y listas de entregas manualmente.
- **Semana ambigua**: sin campo `semana` no se sabe qué semana cubre cada bitácora, imposibilitando calcular el progreso por Grupo A (semanas 1-16) vs Grupo B (semanas 17-32).
- **Edición sin límite**: los estudiantes pueden editar bitácoras indefinidamente, lo que socava la integridad del registro.

## Alcance

### Incluido

- Nuevos campos `signature_code` (hasheado) y `signature_code_expires_at` en `bitacoras`.
- Nuevo valor `NoFirmada` en el enum `EstadoFirma`.
- Endpoint de firma con validación de 5 intentos y ventana de 2 minutos (intentos en memoria, no persistidos).
- Una re-solicitud de código por bitácora (si expiró o se agotaron intentos, el estudiante puede pedir uno nuevo, máximo 1 re-solicitud).
- Vista de seguimiento por semestre para el coordinador con selector de semestre, columnas dinámicas de entregas, grupos de fase colapsables y observaciones de texto libre por fase.
- Nueva tabla `seguimiento_observaciones` (`id`, `proyecto_id`, `semestre_id`, `fase`, `observacion`, `created_at`, `updated_at`).
- Campo `semana` (integer, 1-32) en `bitacoras`. El estudiante lo selecciona manualmente al crear. Restricción única por proyecto.
- Migración de backfill: las bitácoras existentes SIN semana reciben `semana` auto-asignada por orden de creación (1ra creada → semana 1, 2da → semana 2, etc.).
- Ventana de edición de **15 minutos** aplicada en frontend (ocultar controles) y backend (rechazar PUT).
- Renombre en UI: "descripción detallada" → "contenido" en todas las vistas de bitácora (el campo BD `notes` no cambia).

### Excluido

- Historial de observaciones del coordinador más allá del semestre actual.
- Notificaciones cuando una bitácora entra en estado `NoFirmada`.
- Cualquier cambio en los modelos de entrega o sus entidades.

## Capacidades

### Nuevas Capacidades

- `logbook-signature`: Generación de clave dinámica de 6 dígitos, expiración en 2 min, firma del director con 5 intentos, estado `NoFirmada` y re-solicitud de código (máx 1).
- `coordinator-tracking`: Tablero por semestre con columnas dinámicas de entregas, conteo de bitácoras por grupo, grupos de fase colapsables y observaciones del coordinador.
- `logbook-weekly`: Campo semana (1-32) seleccionado por el estudiante, una bitácora por semana por proyecto, backfill para existentes, ventana edición 15 min y rename a "contenido".

### Capacidades Modificadas

Ninguna. Todos los cambios introducen comportamiento nuevo o tablas nuevas.

## Enfoque

**PR 1 — Firma de Bitácora (~8 archivos)**

- Migración: agregar `signature_code` (string, nullable), `signature_code_expires_at` (timestamp, nullable) a `bitacoras`.
- Enum: agregar `NoFirmada` a `EstadoFirma`.
- Modelo: `Bitacora` con campos de firma y método `hasValidSignature()`.
- Controlador: en `store`, generar `random_int(100000, 999999)`, hashear con `Hash::make()`, persistir hash + expiración (2 min). Devolver código en texto plano al frontend solo en la respuesta de creación.
- Endpoint: `POST /bitacoras/{id}/firmar` acepta `{ code }`, valida expiración e intentos (contador en ámbito de request), transiciona a `FirmadaDirector` o `NoFirmada`.
- Endpoint: `POST /bitacoras/{id}/re-solicitar-codigo` — regenera código si la bitácora está en `NoFirmada` y `signature_retries < 1`.
- Frontend: mostrar código en modal al crear; botón "Solicitar nuevo código" si expiró; el director ve input de código en el detalle.

**PR 2 — Backend de Seguimiento (~6 archivos)**

- Migración: crear tabla `seguimiento_observaciones`.
- Modelo: `SeguimientoObservacion` con `proyecto_id`, `semestre_id`, `fase`, `observacion`.
- Endpoints:
  - `GET /seguimiento/semestre/{semestre_id}` — proyectos con estado de entregas, conteo bitácoras por grupo y observaciones.
  - `PUT /seguimiento/observaciones` — upsert de observación.
- Servicio: calcular estado (✅/⏳/❌) según fecha límite y existencia de envío; contar bitácoras X/16 por grupo desde `semana`.

**PR 3 — Frontend de Seguimiento (~4 archivos)**

- Nueva vista: `SeguimientoSemestre.tsx`.
- Selector de semestre (activos primero, inactivos después).
- Tabla dinámica: columnas desde entregas del semestre.
- Grupos de fase colapsables con `<details>`.
- Observaciones editables inline con guardado automático.

**PR 4 — Semana, Edición 15 min, Renombre (~8 archivos)**

- Migración: agregar `semana` (integer, 1-32) a `bitacoras`, única por proyecto. Backfill: auto-asignar semana por orden de creación para registros existentes.
- Modelo: validación de rango 1-32 y unicidad.
- Controlador: en `store`, validar que no exista bitácora con misma semana en el proyecto. Rechazar `update` si `created_at` + 15 min < ahora.
- Frontend: selector de semana (1-32) al crear; ocultar controles de edición después de 15 min; renombrar "descripción detallada" a "contenido".

## Áreas Afectadas

| Área | Impacto | Descripción |
|------|---------|-------------|
| `database/migrations` | Nuevo | 2 migraciones: campos firma + semana, tabla observaciones |
| `app/Models/Bitacora.php` | Modificado | Campos firma, semana, validaciones |
| `app/Enums/EstadoFirma.php` | Modificado | Nuevo valor `NoFirmada` |
| `app/Http/Controllers/BitacoraController.php` | Modificado | Generar firma en store, re-solicitar código, guardia 15 min |
| `app/Http/Controllers/SeguimientoController.php` | Nuevo | Endpoints de seguimiento por semestre |
| `app/Models/SeguimientoObservacion.php` | Nuevo | Modelo de observaciones |
| `resources/js/pages/estudiante/*` | Modificado | UI firma, selector semana, límite edición, rename etiquetas |
| `resources/js/pages/coordinador/SeguimientoSemestre.tsx` | Nuevo | Tablero de seguimiento |
| `resources/js/pages/coordinador/GestionAlertas.tsx` | Modificado | Agregar pestaña de seguimiento (primera) |

## Riesgos

| Riesgo | Probabilidad | Mitigación |
|--------|-------------|------------|
| Director no alcanza ventana de 2 min | Media | Contador regresivo; estudiante puede re-solicitar código 1 vez |
| Tabla de seguimiento lenta con muchos proyectos | Baja | Paginación y eager-load |
| Ventana 15 min edición muy corta | Baja | Mostrar tiempo restante; registrar intentos para monitoreo |
| Bitácoras existentes sin `semana` | Alta | Backfill automático por orden de creación en la migración |

## Plan de Rollback

- **PR 1**: Revertir migración (eliminar columnas, quitar valor del enum). Eliminar endpoints de firma.
- **PR 2**: Eliminar tabla `seguimiento_observaciones`. Eliminar controlador y rutas.
- **PR 3**: Eliminar vista de seguimiento y enlace de navegación. Sin impacto en datos.
- **PR 4**: Revertir migración (eliminar `semana`). Eliminar guardia de edición. Revertir etiquetas UI.

Todos los PRs son independientes y pueden revertirse en orden inverso.

## Criterios de Éxito

- [ ] Estudiante crea bitácora y ve código de 6 dígitos que expira en 2 min.
- [ ] Director ingresa código correcto dentro de 2 min y 5 intentos → bitácora `FirmadaDirector` con timestamp.
- [ ] Director falla 5 intentos o expiran 2 min → bitácora `NoFirmada`.
- [ ] Estudiante puede re-solicitar un nuevo código 1 vez si expiró.
- [ ] Coordinador ve tabla de seguimiento por semestre con fila por proyecto y columnas dinámicas.
- [ ] Coordinador puede expandir/colapsar grupos de fase Anteproyecto y Desarrollo.
- [ ] Coordinador puede escribir y guardar observaciones por proyecto y fase.
- [ ] Estudiante selecciona semana (1-32) al crear bitácora; sistema rechaza semana duplicada por proyecto.
- [ ] Bitácoras existentes reciben `semana` auto-asignada por orden de creación.
- [ ] Estudiante edita bitácora dentro de 15 min; controles desaparecen y backend rechaza PUT después.
- [ ] Todas las vistas muestran "contenido" en vez de "descripción detallada".

## Archivos Estimados

| PR | Descripción | Archivos |
|----|-------------|----------|
| PR 1 | Firma por clave dinámica | ~8 |
| PR 2 | Backend de seguimiento | ~6 |
| PR 3 | Frontend de seguimiento | ~4 |
| PR 4 | Semana, edición 15 min, rename | ~8 |
| **Total** | | **~26** |

## Orden de Merge Recomendado

**PR 1 → PR 4 → PR 2 → PR 3**

(PR 4 antes que PR 2 para que el backend de seguimiento tenga datos reales de `semana`)
