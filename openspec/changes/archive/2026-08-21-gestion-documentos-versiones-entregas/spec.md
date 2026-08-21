# Spec: Gestión de documentos, versiones y observaciones de entregas

> Change: `gestion-documentos-versiones-entregas` | Entrega → documentos solicitados → versiones → observación

## Decisiones

| # | Decisión | Resolución |
|---|----------|-----------|
| D1 | Persistencia de documentos | Se **evoluciona** `entregas.archivos_requeridos` (JSON). No se crea una tabla paralela. Identidad = `slug` (alias de payload `id`). Título = `nombre`. |
| D2 | Pertenencia de versiones | `versiones_documento.archivo_requerido_id` identifica el documento. También se conserva `entrega_id` + `entrega_proyecto_id` (plantilla de semestre + proyecto). |
| D3 | Unique de versión | Se elimina `unique(entrega_id, version_number)`. Nueva unique `(entrega_proyecto_id, archivo_requerido_id, version_number)`. |
| D4 | Slug `documento-proyecto` | Ya no es obligatorio ni el único analizable. Las entregas existentes que lo usan siguen válidas. |
| D5 | IA | Como máximo **un** documento con `analizable_ia = true` por entrega. Cero es válido. El flag vive en el documento, no en una relación IA aparte. |
| D6 | Observaciones | Canónicas en `versiones_documento.director_notes` de **cualquier** documento. Si no hay texto, estado “Sin observación del director”. No se usa un único texto global como fuente de UI. |
| D7 | `uploaded_by` | No existe en el esquema actual; no se añade. |
| D8 | Límites vigentes | Máx. 4 versiones **por documento** (`MAX_VERSIONS`). Máx. 6 documentos por entrega (límite operativo ya existente; no es el “uno” que se elimina). Mín. 1 documento. |
| D9 | Datos existentes | Backfill: entregas sin documentos → un documento con título derivado; versiones sin `archivo_requerido_id` → ese documento (o el primero). Sin deletes destructivos. |
| D10 | Specs históricas | `openspec/specs/` no se modifica. Este change **sustituye en código** RF-ENT-01/02 (slug fijo / IA solo en principal) y RF-SUP-01/02 (observaciones solo en documento-proyecto). |

---

## Capacidad: documentos-solicitados-entrega

### RF-DOC-01: Múltiples documentos con título

WHEN un Coordinador envía `POST /api/admin/entregas` con `archivos_requeridos` de 1..N ítems (cada uno con `id`/`slug` único y `nombre`), the system SHALL persistir cada documento con su identificador y título AND SHALL NOT exigir el slug `documento-proyecto`.

#### Escenario: Tres documentos titulados

- GIVEN un coordinador autenticado
- WHEN POST con documentos “Planteamiento del problema”, “Objetivos” y “Justificación”
- THEN 201
- AND la entrega tiene 3 documentos, cada uno con su `nombre` e identidad

#### Escenario: Sin documentos

- GIVEN `archivos_requeridos` vacío
- THEN 422

### RF-DOC-02: Identidad y relación

Each documento MUST pertenecer a una entrega válida. The identidad MUST ser única dentro de la entrega.

#### Escenario: IDs duplicados

- WHEN dos documentos comparten el mismo `id`
- THEN 422

### RF-DOC-03: Update conserva y permite retitular

WHEN un Coordinador actualiza títulos o la lista de documentos, the system SHALL persistir los cambios AND SHALL NOT borrar versiones existentes de documentos que siguen en la lista.

---

## Capacidad: documento-unico-analizable-ia

### RF-IA-DOC-01: Como máximo uno

WHEN el payload marca `analizable_ia = true` en dos o más documentos de la misma entrega, the system SHALL responder 422 con un mensaje claro en español (p. ej. “Solo un documento de la entrega puede analizarse con IA.”).

#### Escenario: Un documento IA

- GIVEN dos documentos, solo “Objetivos” con `analizable_ia: true`
- THEN 201 y ese flag persiste

#### Escenario: Dos documentos IA

- THEN 422 y no se crea/actualiza la entrega

### RF-IA-DOC-02: Cualquier documento puede ser el de IA

WHEN un documento que no es `documento-proyecto` se marca como analizable AND es el único, the system SHALL aceptarlo.

### RF-IA-DOC-03: Identificación inequívoca

The entrega MUST exponer de forma inequívoca cuál documento (si hay) es analizable (`analizable_ia` en el ítem y/o `documento_analizable_ia` con su id).

### RF-IA-DOC-04: Análisis solo de ese documento

WHEN se solicita análisis IA (`evaluacion-inteligente` o `evaluacion-abet`) de una versión cuyo `archivo_requerido_id` no es el documento analizable, the system SHALL rechazar (422) AND SHALL NOT invocar el proveedor.

WHEN la entrega no tiene ningún documento analizable, the system SHALL rechazar el análisis (incluido archivo temporal).

WHEN la versión sí pertenece al documento analizable (o el temporal se usa existiendo exactamente ese documento configurado), the system SHALL reutilizar `DocumentEvaluationService` (sin duplicar orquestación).

The UI MUST NOT mostrar controles ni resultados IA en documentos no marcados.

---

## Capacidad: versiones-por-documento

### RF-VER-01: Historial independiente

WHEN el estudiante sube un archivo a `POST /api/entregas/{id}/archivos/{slug}`, the system SHALL crear una `VersionDocumento` con `archivo_requerido_id = slug`, `uploaded_at` y `version_number` incremental **de ese documento y proyecto**.

#### Escenario: Dos documentos, versión 1 cada uno

- GIVEN documentos `planteamiento` y `objetivos`
- WHEN se sube un archivo a cada uno
- THEN ambas versiones tienen `version_number = 1`
- AND cada una apunta al slug correcto
- AND no hay colisión de unicidad

### RF-VER-02: Fecha de entrega

Each versión MUST persistir `uploaded_at` (fecha y hora).

### RF-VER-03: Límite por documento

The límite de 4 versiones MUST aplicarse por documento (y proyecto), no a toda la entrega.

### RF-VER-04: Autorización

WHEN un usuario intenta subir o modificar versiones de una entrega que no le corresponde, the system SHALL denegar (403/404) con los resolvers/roles existentes.

---

## Capacidad: observaciones-por-version

### RF-OBS-01: Observación de la versión

WHEN el director revisa (`PUT .../revisar`) con `version_id` y `director_notes`, the system SHALL persistir la observación en **esa** `VersionDocumento.director_notes`, sea cual sea el documento solicitado.

#### Escenario: Observaciones independientes

- GIVEN dos versiones del mismo documento
- WHEN el director guarda textos distintos en cada `version_id`
- THEN cada versión conserva su texto
- AND ninguna reutiliza el de la otra

### RF-OBS-02: Sin observación

WHEN una versión no tiene `director_notes`, the UI SHALL mostrar un estado vacío propio (“Sin observación del director” o equivalente) AND SHALL NOT mostrar la observación de otra versión.

### RF-OBS-03: Estudiante consulta por versión

WHEN el estudiante navega las versiones de un documento, the system SHALL mostrar la observación asociada a la versión seleccionada.

### RF-OBS-04: Director sin ambigüedad

The UI del director MUST indicar el documento y el número/fecha de la versión que está revisando. The form MUST permitir registrar o modificar la observación de esa versión.
