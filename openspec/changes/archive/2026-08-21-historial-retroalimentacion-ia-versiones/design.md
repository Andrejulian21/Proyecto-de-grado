# Design: Historial de retroalimentación de análisis IA por versión

## Modelo actual (fuente de verdad — cambio 05)

```
entregas
  archivos_requeridos JSON  → [{ slug, nombre, versionamiento, analizable_ia }]
  description               → contexto del análisis preliminar (cambio 04)
  └── entrega_proyecto
        └── versiones_documento
              archivo_requerido_id   ← documento
              version_number, uploaded_at
              director_notes         ← observación académica (NO es IA)

ai_document_evaluations     ← ya existe (no duplicar)
  entrega_id
  version_documento_id      nullable (análisis temporal)
  type, status, result_json
  document_hash, created_at
  user_id, provider, prompt_version
```

Hoy el orquestador **ya inserta** una fila por ejecución. Huecos:

1. No hay `archivo_requerido_id` en la fila IA → el temporal solo se liga a la entrega.
2. GET director (`evaluacion-abet`) devuelve el último completed de **toda** la entrega, ignorando la versión seleccionada.
3. El estudiante no tiene GET de historial; el detalle de entrega no anida análisis por versión.
4. La UI del director no recarga el análisis al cambiar de versión (`useEffect` solo depende de `entregaId`).

## Relación conceptual

```
Entrega
└── Documento solicitado (slug en JSON; ≤1 analizable_ia)
    └── Versión (versiones_documento)
        ├── Observación director (director_notes)
        └── Análisis IA[] (ai_document_evaluations, historial)
    └── Análisis temporales (version_documento_id NULL) — solo documento IA
```

Documento B sin IA: versiones y observaciones; **cero** filas ni UI de IA.

## Decisiones de arquitectura

### D1 — Reutilizar `ai_document_evaluations`

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| Tabla nueva `analisis_ia_version` | Duplica orquestación y resultados | ❌ |
| **Columna `archivo_requerido_id` + consultas** | Misma tabla; identidad de documento para temporales e históricos | ✅ |

Tipo: `string` nullable (misma semántica que `versiones_documento.archivo_requerido_id`; no hay FK porque el documento vive en JSON).

Índice: `(entrega_id, archivo_requerido_id, version_documento_id)`.

### D2 — Análisis antes de subir versión

Flujo actual: `POST .../evaluacion-inteligente` con `file` (DOCX) **o** `version_id`. El temporal se guarda en `storage/app/tmp/ai-eval/{user}/{uuid}.docx`, se convierte, se analiza y **se borra** el archivo; la fila queda con `version_documento_id = null`.

**Persistencia del temporal:**

- `archivo_requerido_id` = `Entrega::idDocumentoAnalizableIa()` (el único documento configurado).
- `version_documento_id` = `null` (no se inventa versión).
- `document_hash` = sha256 del DOCX temporal.
- `created_at` = fecha/hora del análisis.

**Asociación posterior (cuando corresponde):**

Al `POST /api/entregas/{id}/archivos/{slug}` del documento IA, si el sha256 del archivo subido coincide con análisis completed temporales de esa entrega+documento, se actualiza **solo** `version_documento_id` (trazabilidad: mismo archivo). Si no coincide, el análisis permanece como “previo a una versión oficial”.

No se crea una versión fantasma ni se asigna la última versión “por cercanía temporal”.

### D3 — Historial

`DocumentEvaluationService::evaluate` sigue haciendo `AiDocumentEvaluation::create`. Prohibido `update` de `result_json` de filas ajenas a la ejecución en curso (solo la fila pending de esa corrida pasa a completed/failed).

GET:

- Estudiante: `GET /api/estudiante/entregas/{id}/evaluacion-inteligente?version_id=`
- Director: `GET /api/director/entregas/{id}/evaluacion-abet?version_id=` (el `version_id` pasa a ser el filtro; sin él, último del documento IA — no de otra versión mezclada de forma opaca: si hay varias versiones, el cliente debe enviar `version_id` en el panel).

Contrato de un ítem:

```json
{
  "id": 1,
  "entrega_id": 10,
  "documento_id": "marco-teorico",
  "version_id": 22,
  "temporal": false,
  "tipo": "pre_submission",
  "estado": "completed",
  "resultado": { "resumen": "...", "coherencia": "...", "...": "..." },
  "analizado_en": "2026-08-21T15:35:00-05:00"
}
```

Director GET: `data` = último de la versión (compatibilidad con tests actuales) + `historial` = lista completed de esa versión (más reciente primero).

### D4 — Datos históricos

Migración `up`:

1. Añadir `archivo_requerido_id` nullable.
2. Backfill: `JOIN versiones_documento` → copiar slug.
3. Filas sin versión: `archivo_requerido_id` = documento IA actual de la entrega, o NULL si no hay.

No se asigna `version_documento_id` a históricos temporales. No se borra `result_json`.

### D5 — Show de entrega

`GET /api/admin/entregas/{id}` (estudiante y director ya lo usan para el detalle) eager-load análisis completed por versión. Cada versión en el payload incluye `analisis_ia: [...]` además de `director_notes`.

El listado `GET /api/estudiante/entregas` incluye en cada versión `analisis_ia` resumido (id, resultado, analizado_en) para no forzar un round-trip extra.

### D6 — Frontend

Tipos:

```ts
interface AnalisisIa {
  id: number;
  documento_id: string | null;
  version_id: number | null;
  temporal: boolean;
  resultado: ResultadoPreliminar;
  analizado_en: string | null;
}

interface VersionDocumentoVista {
  id: number;
  version_number: number;
  director_notes: string | null; // observación
  analisis_ia: AnalisisIa[];     // historial de ESA versión
  ...
}
```

- Estudiante `DetalleEntregaEstudiante`: sección IA **solo** si `analizable_ia`; al cambiar de versión, leer `selectedVersion.analisis_ia`.
- Director `RevisionEntregaDirector` + `EvaluacionAbetPanel`: GET/POST con `versionId`; observación y IA en bloques distintos; disclaimer “informativo, no calificación”.
- Documentos no IA: no montar panel, no CTA, no copy genérico de IA.
- Extraer `RetroalimentacionIa` para no inflar páginas ya >500 líneas.

### D7 — Cambio 04

Sin tocar `PreliminaryAnalysisPrompt` salvo que el mapper necesite campos ya existentes. `EvaluationContext.description` sigue siendo el contexto. No se re-wirean métricas ABET.

## Data flow

```
ANÁLISIS CON VERSIÓN
  POST { version_id }
    → access resolver (estudiante/director)
    → assert documento IA + versión de ese documento
    → converter → prompt (descripción) → gateway
    → INSERT ai_document_evaluations (documento, versión, result_json, created_at)

ANÁLISIS TEMPORAL
  POST { file }
    → mismo pipeline
    → INSERT (documento IA, version_id NULL, document_hash)
    → borrar tmp
    → NO VersionDocumento

SUBIDA OFICIAL
  POST /archivos/{slug}
    → VersionDocumento
    → si slug es IA AND hash = análisis temporales → SET version_documento_id

CONSULTA
  GET ?version_id= → completed de esa versión
  Detalle entrega → versiones[].analisis_ia + director_notes
```

## Integridad

- Tests solo `RefreshDatabase` / `:memory:`.
- `nullOnDelete` en `version_documento_id` ya existe: borrar versión no borra el análisis; queda temporal otra vez (trazabilidad del resultado).
- Autorización: mismos resolvers; GET estudiante usa `StudentProjectAccessResolver`; GET director `DirectorEntregaAccessResolver`.

## Archivos

| File | Action |
|------|--------|
| `openspec/changes/historial-retroalimentacion-ia-versiones/*` | Create |
| `database/migrations/2026_08_21_140000_add_archivo_requerido_id_to_ai_document_evaluations.php` | Create |
| `app/Models/AiDocumentEvaluation.php` | Modify |
| `app/Models/VersionDocumento.php` | Modify |
| `app/Models/Entrega.php` | Modify (hasMany analisisIa) |
| `app/Services/Evaluation/DocumentEvaluationService.php` | Modify |
| `app/Services/Evaluation/AiFeedbackPresenter.php` | Create |
| `app/Services/Evaluation/AttachTemporaryAiFeedbackToVersion.php` | Create |
| Controllers IA + Entrega show + subida | Modify |
| `resources/js/types/entregas.ts` / `AnalisisIa` | Modify |
| `resources/js/components/entregas/RetroalimentacionIa.tsx` | Create |
| Detalle estudiante, revisión director, panel ABET | Modify |
| Tests Pest listados en `tasks.md` | Create/Modify |

## Permisos

Sin cambio de RBAC. Coordinador no ejecuta este análisis (sigue fuera de alcance). Estudiante: sus proyectos. Director: proyectos que dirige.

## Open questions (resueltos)

- ¿Tabla nueva? No (D1).
- ¿Inventar versión para el temporal? No (D2).
- ¿Sobrescribir el último análisis? No (D3).
- ¿Qué hacer con históricos ambiguos? Conservarlos; documento vía backfill conservador; versión solo si ya estaba.
