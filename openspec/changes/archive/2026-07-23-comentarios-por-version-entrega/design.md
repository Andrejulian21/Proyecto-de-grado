# Design: Comentarios del Director por versión de entrega

## Overview

Corrección de persistencia y binding UI↔versión. **No hay cambio estructural de BD**: `versiones_documento.director_notes` ya es el almacenamiento canónico por versión (documentado en `docs/Backend.md`).

## Analysis summary (FASE 1)

| # | Pregunta | Hallazgo |
|---|----------|----------|
| 1 | Flujo revisión | Supervisión → `/entregas/:id/revisar` → `RevisionEntregaDirector`. También existe `DetalleEntregaDirector` (misma API, sin ruta activa en `app.tsx`). |
| 2 | Versión seleccionada | `selectedVersionIdx` sobre `versiones` ordenadas desc; deriva `selectedVersion`. |
| 3 | Dónde se almacenan | Columna `director_notes` en `versiones_documento`. |
| 4 | ¿Entrega o VersionDocumento? | **VersionDocumento** (por diseño). `Entrega` solo guarda status/nota consolidada. |
| 5 | Endpoint guardado | `PUT /api/admin/entregas/{id}/revisar` → `EntregaController@revisar`. |
| 6 | Endpoint versiones | Incluidas en `GET /api/admin/entregas/{id}`; también `GET /api/entregas/{id}/versiones`. |
| 7 | Datos al frontend | `apiFetch` → JSON `data.versiones[].director_notes`. |
| 8 | Modelos | `Entrega`, `VersionDocumento`. |
| 9 | Relaciones | `Entrega::versiones()` hasMany; `VersionDocumento::entrega()` belongsTo. |
| 10 | Consultas existentes | `VersionDocumento::where('entrega_id')->orderByDesc('version_number')`. |
| 11 | Campo por versión | **Sí**: `director_notes` (nullable text). |
| 12 | Migración | **No requerida**. |

### Root cause

```php
// EntregaController@revisar (actual)
$latestVersion = VersionDocumento::where('entrega_id', $id)
    ->orderByDesc('version_number')
    ->first();
$latestVersion->update(['director_notes' => $data['director_notes']]);
```

El frontend no envía `version_id` y el textarea no se sincroniza con la versión seleccionada.

## Chosen solution (menor impacto)

1. **Backend**: aceptar `version_id` opcional en `revisar`.
   - Si viene: validar que la versión pertenece a la entrega; actualizar sus `director_notes`.
   - Si no viene: mantener comportamiento actual (última versión) para compatibilidad.
2. **Frontend Director**: al cambiar versión, cargar `selectedVersion.director_notes` en el estado editable; al guardar, enviar `version_id: selectedVersion.id`.
3. **Sin** nuevos modelos, rutas, migraciones ni componentes.

### Alternatives rejected

| Opción | Por qué no |
|--------|------------|
| Nueva tabla `observaciones_version` | Duplica `director_notes` existente |
| Campo `director_notes` en `entregas` | Pierde historial por versión |
| Nuevo endpoint `PUT .../versiones/{id}/notes` | Más superficie; el flujo de revisión ya usa `revisar` |
| Solo fix frontend sin `version_id` | Imposible: el backend ignora la selección |

## Data flow (después)

```
Director selecciona vN
  → textarea = versiones[n].director_notes
  → PUT /revisar { status, consolidated_grade?, director_notes, version_id: vN.id }
  → VersionDocumento(id=vN).director_notes actualizado
  → otras versiones intactas
Estudiante/Coordinador
  → GET entrega → lee director_notes por versión (sin cambios)
```

## File changes

| File | Action | Why |
|------|--------|-----|
| `app/Http/Controllers/Admin/EntregaController.php` | Modify | Resolver versión por `version_id` o fallback latest |
| `resources/js/pages/director/RevisionEntregaDirector.tsx` | Modify | Sync textarea + enviar `version_id` (ruta activa) |
| `resources/js/pages/director/DetalleEntregaDirector.tsx` | Modify | Selector/binding de versión + `version_id` |
| `tests/Feature/Admin/EntregaCrudTest.php` | Modify | Caso multi-versión + `version_id` inválido |

## Compatibility

- Entregas/versiones existentes: sin migración; notas ya en filas de versión.
- Clientes sin `version_id`: misma semántica previa (última versión).
- Flujo de decisión, notificaciones y auto-avance de fase: intactos.

## Testing

- Pest: tres versiones, notas distintas vía `version_id`, assert independencia.
- Pest: `version_id` ajeno → 422.
- Frontend: build TypeScript; smoke manual selector ↔ comentario.
