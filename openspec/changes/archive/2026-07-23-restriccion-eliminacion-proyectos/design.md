# Design: Restricción de eliminación de proyectos

## Overview

Restaurar el endpoint de eliminación ausente y añadir un gate de negocio en el backend. Impacto mínimo: sin migraciones, sin modelos nuevos, sin cambios de arquitectura.

## Analysis summary (FASE 1)

| # | Pregunta | Hallazgo |
|---|----------|----------|
| 1 | Flujo actual | `GestionProyectos` → `ConfirmDialog` → `useProyectos.eliminar` → `DELETE /api/admin/proyectos/{id}` |
| 2 | Por qué falla | Ruta `destroy` **no registrada** (`only: index,store,show`) y método **inexistente** en `ProyectoController` |
| 3 | Endpoint | Pretendido: `DELETE /api/admin/proyectos/{id}` (hoy 404/Method Not Allowed) |
| 4 | Controlador | `App\Http\Controllers\Admin\ProyectoController` |
| 5 | Modelo | `Proyecto` |
| 6 | Relaciones | `entregas()` HasMany; `entregasPivot()` BelongsToMany vía `entrega_proyecto` |
| 7 | FK / pivote | **Ambas**: `entregas.proyecto_id` (nullable + cascade) y pivote `entrega_proyecto` (cascade) |
| 8 | Validación previa | Ninguna (ni siquiera existía destroy) |
| 9 | Restricciones BD | Cascade en entregas/estudiantes/bitácoras/evaluadores — por eso el gate es crítico |
| 10 | Errores FE | `eliminar` lanza `Error ${status}` sin body; `handleDelete` traga el error y cierra el diálogo |
| 11 | Datos históricos | Proyectos con entregas (demo/reales) no deben poder borrarse |

## Chosen solution

```php
public function destroy(Proyecto $proyecto): JsonResponse
{
    if ($proyecto->entregas()->exists() || $proyecto->entregasPivot()->exists()) {
        return response()->json([
            'error' => 'No se puede eliminar el proyecto porque ya posee entregas registradas.',
            'message' => 'No se puede eliminar el proyecto porque ya posee entregas registradas.',
        ], 422);
    }

    $proyecto->delete();

    return response()->json(['message' => 'Proyecto eliminado correctamente.']);
}
```

Ruta:
```php
Route::apiResource('proyectos', ProyectoController::class)
    ->only(['index', 'store', 'show', 'destroy']);
```

Frontend:
- `useProyectos.eliminar`: parsear `body.error` / `body.message`.
- `GestionProyectos`: estado `deleteError` + `ErrorBanner` existente; cerrar diálogo solo en éxito o cancelar.

## Alternatives rejected

| Opción | Por qué no |
|--------|------------|
| SoftDeletes en Proyecto | Fuera de alcance; requiere migración |
| Validación solo frontend | No garantiza integridad |
| Borrar en cascada entregas | Viola la regla de negocio |
| Nuevo endpoint `/proyectos/{id}/safe-delete` | Innecesario; reutilizar RESTful destroy |

## File changes

| File | Action |
|------|--------|
| `app/Http/Controllers/Admin/ProyectoController.php` | Add `destroy` |
| `routes/api.php` | Add `destroy` to `only` |
| `resources/js/hooks/useProyectos.ts` | Parse error body on DELETE |
| `resources/js/pages/coordinador/GestionProyectos.tsx` | Surface delete error |
| `tests/Feature/Admin/ProyectoCrudTest.php` | Cases 1–2 |

## Compatibility

- Crear/listar/mostrar proyectos: intactos.
- Proyectos con entregas históricas: protegidos.
- Proyectos sin entregas (con estudiantes/bitácoras): eliminables; pivotes/bitácoras siguen cascade DB.
