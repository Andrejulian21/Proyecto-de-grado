# Design: Mejora Dashboard entregas estudiante

## Overview

Corrección de mapping frontend + ampliación mínima del payload de `/api/estudiante/entregas`. Sin migraciones ni nuevos endpoints.

## Analysis summary (FASE 1)

| # | Pregunta | Hallazgo |
|---|----------|----------|
| 1 | Construcción Dashboard | `EstudianteDashboard` → proyecto + entregas en paralelo; filtra por fase; `DeliveryAccordion` + botón "Ver detalle". |
| 2 | Endpoint entregas | `GET /api/estudiante/entregas` → `EstudianteController@entregas` |
| 3 | Payload actual | `id, fase, titulo, descripcion, fecha_limite, estado, nota, criterios, total_versiones, ultima_version` — **sin** array de versiones |
| 4 | Título en backend | Sí: `titulo` ← `$entrega->title` |
| 5 | Versiones en FE | No llegan: map descarta el eager-load; FE hace `(e.versiones \|\| [])` |
| 6 | Componente card | `DeliveryAccordion` |
| 7 | Reutilizables | `StatusBadge`; `DeliveryVersionHistory` está documentado como huérfano y **no existe en disco** → no reutilizar |
| 8 | Observaciones | `VersionDocumento.director_notes` (por versión) |
| 9 | Truncado | Sin helper global; patrón documentado `slice(0, 80)+…` |
| 10 | Rendimiento | Eager-load ya existe; solo materializar en JSON. Una query + versiones. Sin N+1. |

### Root causes

```ts
// EstudianteDashboard — título genérico gana
label: LABELS[e.fase] || e.titulo || ...
```

```php
// EstudianteController@entregas — versiones cargadas pero omitidas del map
'total_versiones' => $entrega->versiones->count(),
'ultima_version'  => $entrega->versiones->last()?->version_number,
// falta: 'versiones' => [...]
```

## Chosen solution

1. **Backend**: en el map, ordenar versiones y exponer:
   - `numero_version`, `nombre_archivo` (`original_name`), `subido_en` (`uploaded_at`/`created_at`), `observacion` (`director_notes`), `estado` derivado (sin campo status en BD).
2. **Frontend map**: `label = e.titulo \|\| e.title \|\| LABELS[fase] \|\| fallback`; mapear observación + datetime con hora.
3. **DeliveryAccordion**: añadir columna/fila de observación preview truncada; vacío sin versiones sin romper UI.
4. **Tipos**: `VersionData.observationPreview?: string | null`.

### Estado de versión (derivado)

Alineado con detalle estudiante/director:

| Condición | Estado UI |
|-----------|-----------|
| Sin `director_notes` | `pending` (Sin revisar / Pendiente) |
| Con notes + entrega `aprobada` | `approved` |
| Con notes (resto) | `rejected` (Necesita ajustes — badge existente) |

## Alternatives rejected

| Opción | Por qué no |
|--------|------------|
| Usar solo `proyecto.entregas` del otro endpoint | Requiere refactor del Dashboard; entregas endpoint ya es la fuente |
| Nuevo endpoint por entrega | N+1 / más round-trips |
| Recrear `DeliveryVersionHistory` | Huérfano; accordion ya tiene tabla |

## File changes

| File | Action |
|------|--------|
| `app/Http/Controllers/Api/EstudianteController.php` | Incluir `versiones` ordenadas en map |
| `resources/js/pages/dashboard/EstudianteDashboard.tsx` | Título real + map enriquecido |
| `resources/js/components/DeliveryAccordion.tsx` | Preview observación |
| `resources/js/types/estudiante.ts` | Extender `VersionData` |
| `tests/Feature/...` | Test payload entregas con versiones (si existe suite estudiante; si no, crear mínimo) |
