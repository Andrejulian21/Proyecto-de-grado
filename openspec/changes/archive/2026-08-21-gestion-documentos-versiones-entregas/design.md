# Design: Gestión de documentos, versiones y observaciones de entregas

## Modelo actual (fuente de verdad)

Inspección de código y migraciones:

```
entregas
  archivos_requeridos JSON  → [{ slug, nombre, versionamiento, analizable_ia }]
  └── entrega_proyecto (N proyectos del semestre)
        └── versiones_documento
              entrega_id
              entrega_proyecto_id
              archivo_requerido_id   ← identidad del documento (string, no FK)
              version_number
              file_path, original_name, file_size
              uploaded_at
              director_notes         ← observación por versión
              descontinuado
```

Hoy el código **ya** permite varios ítems en el JSON y versiones por slug, pero:

1. `Store`/`Update` **exigen** slug `documento-proyecto` y **solo** ese puede ser IA.
2. `ReviewEntregaAction` **solo** persiste observaciones si el slug es `documento-proyecto` y tiene versionamiento.
3. Unique `versiones_documento(entrega_id, version_number)` impide que dos documentos tengan `v1` en la misma entrega.
4. UI estudiante/director usa pestañas y oculta observaciones/IA en “secundarios”.
5. `DocumentEvaluationService` no comprueba `analizable_ia` (analiza cualquier versión DOCX).

No hay `uploaded_by`. `entrega_proyecto.observaciones_director` es un eco del último review (no la fuente de UI).

## Decisiones de arquitectura

### D1 — No crear tabla `documentos_solicitados`

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| Tabla relacional + FK en versiones | Integridad referencial; migración pesada; **segunda** arquitectura junto al JSON si no se elimina | ❌ |
| **Evolucionar el JSON existente** | Identidad string; unique de versión se corrige; cero duplicación de subida | ✅ |

**Racional**: el usuario prohíbe una segunda arquitectura y pide mantener la existente. El JSON ya tiene id, título, relación (columna de la entrega) y flag IA. Las versiones ya se agrupan por `archivo_requerido_id`.

Contrato persistido (sin cambio de forma):

```json
{
  "slug": "planteamiento_del_problema",
  "nombre": "Planteamiento del problema",
  "versionamiento": true,
  "analizable_ia": true
}
```

Payload de API: `id` (builder) se normaliza a `slug` como hoy.

### D2 — Unique de versión por documento y proyecto

Migración (reversible en `down` del índice nuevo; el unique viejo se recrea):

1. `dropUnique(['entrega_id', 'version_number'])`
2. `unique(['entrega_proyecto_id', 'archivo_requerido_id', 'version_number'], 'versiones_documento_ep_archivo_version_unique')`

`MAX_VERSIONS = 4` ya se cuenta por `(entrega_proyecto_id, archivo_requerido_id)`.

### D3 — Datos existentes

En el `up` de la misma migración (transacción):

- Entregas con `archivos_requeridos` null o `[]`: un documento `{ slug: 'documento', nombre: <title o "Documento">, versionamiento: true, analizable_ia: false }`.
- Versiones con `archivo_requerido_id` null: asignar el slug del primer documento de su entrega.

No se borran archivos ni filas de `ai_document_evaluations`.

### D4 — Validación IA

En `StoreEntregaRequest` / `UpdateEntregaRequest`:

- Quitar `validarArchivoPrincipal` (slug fijo).
- Reemplazar “IA solo en documento-proyecto” por: `count(analizable_ia === true) <= 1`.
- Mensaje: `Solo un documento de la entrega puede analizarse con IA.`
- Extraer la regla a un método/concern compartido para no duplicarla.

Helper en `Entrega`:

- `documentosSolicitados(): array`
- `idDocumentoAnalizableIa(): ?string`
- `getArchivoRequerido()` acepta `slug` o `id`

### D5 — Observaciones

`ReviewEntregaAction::versionAceptaObservaciones` pasa a **true** para cualquier versión de un documento de la entrega (incluido legacy sin slug). Se sigue escribiendo `director_notes` en la versión. El pivote `observaciones_director` puede actualizarse como eco del review (compatibilidad de tests de nota) pero **la UI no lo usa como observación de la entrega**.

### D6 — Análisis IA

En `DocumentEvaluationService` (un solo orquestador):

1. Resolver el id del documento IA de la entrega.
2. Si no hay → 422, no llamar al gateway.
3. Si hay `version_id` → debe coincidir `archivo_requerido_id` (legacy null solo si el primer documento es el IA).
4. Si no hay versión (última) → última DOCX **de ese documento**, no de toda la entrega.
5. Archivo temporal: permitido solo si existe documento IA (borrador de ese documento).

UI: `EvaluacionAbetPanel` y CTA “Analizar con IA” solo si `analizable_ia`. Estudiante: secciones apiladas por documento.

### D7 — Frontend / tipos

Sustituir el concepto “un solo documento de la entrega” en tipos:

```ts
interface DocumentoSolicitado { id: string; slug?: string; nombre: string; versionamiento: boolean; analizable_ia?: boolean; }
interface VersionDocumentoVista { id; version_number; uploaded_at; director_notes; archivo_requerido_id; ... }
// Entrega.archivos_requeridos → documentos; versiones anidadas vía agruparVersionesPorArchivo
```

`ArchivoRequeridoConfig` se aliasa o renombra a `DocumentoSolicitado` (un solo tipo). Builder: checkbox IA en todos; al marcar un segundo, no aplicar el cambio y mostrar validación. Default de creación: un documento con título vacío (el coordinador escribe el título), sin slug bloqueado.

Estudiante: una card por documento (no un único switcher que esconda los demás). Director: lista de documentos + versiones; el panel de revisión nombra documento y versión.

## Data flow

```
CREAR ENTREGA
  POST { archivos_requeridos: [{ id, nombre, versionamiento, analizable_ia? }, ...] }
    → ≤1 analizable_ia, ids únicos, min 1, max 6
    → JSON persistido con slug = id

ESTUDIANTE
  GET entrega → documentos + versiones filtradas por archivo_requerido_id
  POST /archivos/{slug} → VersionDocumento de ESE documento (v+1, uploaded_at)

DIRECTOR
  PUT /revisar { version_id, director_notes }
    → director_notes en ESA versión

IA
  POST evaluacion-* { version_id | file }
    → DocumentEvaluationService exige documento analizable
```

## Integridad

- Tests solo `RefreshDatabase` / `:memory:`.
- Autorización: mismos FormRequest `authorize`, pivote estudiante/director, resolvers IA.
- Unique nuevo: dos `v1` de documentos distintos en el mismo proyecto **permitido**.

## Archivos

| File | Action |
|------|--------|
| `openspec/changes/gestion-documentos-versiones-entregas/*` | Create |
| `database/migrations/2026_08_21_120000_versiones_por_documento_solicitado.php` | Create |
| `app/Models/Entrega.php` | Modify |
| `app/Http/Requests/StoreEntregaRequest.php` | Modify |
| `app/Http/Requests/UpdateEntregaRequest.php` | Modify |
| `app/Actions/Entrega/ReviewEntregaAction.php` | Modify |
| `app/Services/Evaluation/DocumentEvaluationService.php` | Modify |
| `app/Exceptions/DocumentEvaluationException.php` | Modify |
| `app/Http/Controllers/Api/EstudianteController.php` | Modify |
| `resources/js/types/entregas.ts` | Modify |
| `resources/js/lib/entregas.ts` | Modify |
| `resources/js/components/entregas/ArchivosRequeridosBuilder.tsx` | Modify |
| `resources/js/pages/coordinador/CoordinadorEntregas.tsx` | Modify |
| `resources/js/pages/estudiante/DetalleEntregaEstudiante.tsx` | Modify |
| `resources/js/pages/director/RevisionEntregaDirector.tsx` | Modify |
| `resources/js/pages/estudiante/SeleccionEntregaAnalisisIA.tsx` | Modify |
| `resources/js/pages/estudiante/AnalisisAutomaticoEntregas.tsx` | Modify |
| Tests Pest listados en `tasks.md` | Create/Modify |

## Permisos

Sin cambio de RBAC: coordinador configura documentos; estudiante sube los de su proyecto; director observa versiones de sus proyectos; IA con los mismos access resolvers.

## Open questions (resueltos)

- ¿Tabla nueva? No (D1).
- ¿Obligar un documento IA? No; cero es válido; el análisis se rechaza hasta que se configure uno.
- ¿Observaciones solo con versionamiento? No: cualquier versión persistida puede tener observación.
