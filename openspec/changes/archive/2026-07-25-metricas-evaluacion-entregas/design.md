# Design: Métricas de evaluación en entregas

## Findings (analysis)

### Functional

| Aspecto | Estado actual |
|---------|---------------|
| Quién crea/edita | **Coordinador** en `CoordinadorEntregas.tsx` vía `useEntregas` → `/api/admin/entregas` |
| Director | Supervisa/revisa; **no** crea entregas |
| Datos actuales | `title`, `description`, `phase`, `due_date`, `start_date`, `start_time`, `hora_maxima`, `acceptance_criteria`, `status`, … |
| Transporte | **API JSON + SPA** (`apiFetch`). **No Inertia** |

### Existing related field

`acceptance_criteria` («Criterios de aceptación») ya existe y se usa para requisitos de aprobación. **No se reutiliza**: las métricas de evaluación son contexto futuro para IA y deben vivir en columna propia.

### Reusable UI

| Pieza | Existe | ¿Reutilizar? |
|-------|--------|--------------|
| `ConfirmDialog` | Sí | No — solo confirm/cancel con iconos de alerta |
| Tooltip component | No | Usar `title` + botón `CircleHelp` (patrón del repo) |
| Modal genérico informativo | No | Mismo patrón overlay que el edit modal de la página |
| Form create/edit | Inline en `CoordinadorEntregas` | Extender; extraer campo compartido |

## Data model

```text
entregas.evaluation_metrics  TEXT NULL
```

- Nullable → entregas existentes intactas.
- Free text → suficiente para contexto IA futuro sin cambios estructurales.

## API contract

### Create `POST /api/admin/entregas`

| Payload key | Column | Rules |
|-------------|--------|-------|
| `metricas_evaluacion` | `evaluation_metrics` | `nullable\|string` (alineado a `criterios` → `acceptance_criteria`) |

### Update `PUT /api/admin/entregas/{id}`

| Payload key | Column | Rules |
|-------------|--------|-------|
| `evaluation_metrics` | `evaluation_metrics` | `sometimes\|nullable\|string` (alineado a `acceptance_criteria`) |

Respuestas `index`/`show`/`store`/`update` ya serializan el modelo → el campo aparece automáticamente al estar en `$fillable`.

## Frontend

1. Tipos en `useEntregas`: `evaluation_metrics?` en `Entrega`; payloads create/update.
2. Componente `MetricasEvaluacionField`:
   - Label «Métricas de evaluación» + botón `(?)` (`CircleHelp`).
   - `title="Guía para redactar métricas de evaluación."` en hover.
   - Click → modal centrado con guía + botón «Cerrar».
   - Textarea controlado (`value` / `onChange`).
3. Integrar en formulario create y modal edit de `CoordinadorEntregas`.

## AI readiness (no implementation)

El campo queda en la misma fila de `entregas`. Un futuro servicio IA podrá leer `evaluation_metrics` (junto con documento/versión) sin migraciones ni cambios de forma en el modelo. Este change **no** llama a FastAPI.

## Risks

| Risk | Mitigation |
|------|------------|
| Confusión con criterios de aceptación | Labels distintos; columnas distintas |
| Archivo UI > 500 líneas | Extraer `MetricasEvaluacionField` |
| Payload naming mix ES/EN | Seguir convención existente store vs update |
