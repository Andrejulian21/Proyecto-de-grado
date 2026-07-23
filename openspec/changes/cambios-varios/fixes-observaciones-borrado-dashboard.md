# Cambios: observaciones por versión, borrado de proyectos y dashboard estudiante

**Change**: cambios-varios
**Tipo**: Fixes + features (backend + frontend)
**Estado**: ✅ IMPLEMENTADO
**Fecha**: 2026-07-23

## 1. Observaciones del director por versión

### Problema original

El endpoint `PUT /api/admin/entregas/{id}/revisar` guardaba las observaciones del director
(`director_notes`) **siempre en la última versión** de la entrega, sin importar qué versión
estuviese viendo el director en ese momento.

```php
// Código anterior — siempre la última versión
$latestVersion = VersionDocumento::where('entrega_id', $id)
    ->orderByDesc('version_number')
    ->first();
$latestVersion->update(['director_notes' => $data['director_notes']]);
```

### Solución

**Backend — `app/Http/Controllers/Admin/EntregaController.php`**

El método `revisar()` ahora recibe `version_id` como campo obligatorio y guarda las
observaciones en la versión específica:

```php
$validator = Validator::make($request->all(), [
    'version_id' => 'required|integer|exists:versiones_documento,id',
    'status' => 'required|string|in:aprobada,rechazada,revisada',
    'consolidated_grade' => 'nullable|numeric|min:0|max:5',
    'director_notes' => 'nullable|string',
]);

$version = VersionDocumento::where('entrega_id', $id)
    ->where('id', $data['version_id'])
    ->firstOrFail();

$version->update(['director_notes' => $data['director_notes'] ?? null]);
```

**Frontend — `RevisionEntregaDirector.tsx`** y **`DetalleEntregaDirector.tsx`**

Ambos componentes ahora incluyen `version_id` en el body del PUT.

### Endpoint actualizado

```
PUT /api/admin/entregas/{id}/revisar
Content-Type: application/json

{
    "version_id": 42,
    "status": "aprobada",
    "consolidated_grade": 4.5,
    "director_notes": "Bien, solo corrige la introducción"
}
```

---

## 2. Borrado de proyectos (coordinador)

### Problema original

La ruta `apiResource('proyectos', ...)` solo tenía habilitados `index`, `store` y `show`.
No existía el método `destroy` ni en rutas ni en el controlador.

### Solución

**Ruta — `routes/api.php`**

```php
// Antes
->only(['index', 'store', 'show']);

// Después
->only(['index', 'store', 'show', 'destroy']);
```

**Backend — `app/Http/Controllers/Admin/ProyectoController.php`**

Nuevo método `destroy()` con las siguientes reglas:

1. **Validación**: si el proyecto tiene entregas con al menos 1 versión subida,
   se rechaza con 422 y el mensaje: *"No se puede eliminar el proyecto porque ya
   tiene entregas con versiones subidas."*
2. **Hard delete en orden** para respetar integridad referencial:
   - Bitácoras del proyecto
   - Entregas (y sus versiones)
   - Pivot estudiantes (proyecto_estudiante)
   - Pivot evaluadores (evaluador_proyecto)
   - Evaluaciones
   - Proyecto
3. **AuditEvent** con acción `proyecto.deleted`

**Frontend — `GestionProyectos.tsx`**

- El error del backend ahora se muestra correctamente en el modal de confirmación
- El hook `useProyectos.ts` ahora lee el body del error (`body.error`) en vez de
  mostrar solo el código HTTP

### Regla de negocio

> Un proyecto solo se puede eliminar si **ninguna** de sus entregas tiene
> versiones subidas por el estudiante. Si ya hay documentos entregados,
> el proyecto no es eliminable.

---

## 3. Dashboard de estudiante — entregas con título real

### Problema original

El dashboard del estudiante mostraba las entregas con un **label genérico basado
en la fase** (ej: "Documento de Anteproyecto") en vez del **título real** de la
entrega (ej: "Planteamiento del problema").

Además el `DeliveryAccordion` tenía un diseño básico:
- No mostraba la fase como subtítulo
- No mostraba observaciones del director por versión
- Las fechas de inicio y límite no se diferenciaban bien

### Solución

**Frontend — `EstudianteDashboard.tsx`**

- El mapeo ahora prioriza `titulo` real sobre el label de fase
- Se pasa `faseLabel` como prop separada al `DeliveryAccordion`

**Frontend — `DeliveryAccordion.tsx`**

Rediseño completo del accordion:

```
┌──────────────────────────────────────────────────────────┐
│  📄 Título real de la entrega              [Aprobado]  ∨ │
│     Anteproyecto · 📅 15/07 → 30/07                      │
├──────────────────────────────────────────────────────────┤
│  Versión  Fecha       Archivo         Estado    Obs      │
│  v1       15/07       doc.pdf         Aprobado  📝       │
│  v2       20/07       v2_doc.pdf      Pendiente  —        │
└──────────────────────────────────────────────────────────┘
```

**Tipos — `types/estudiante.ts`**

- `label` → `title` (ahora el título real de la entrega)
- Nuevo campo opcional `startDate`
- Nuevo campo opcional `observaciones`

---

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/Admin/EntregaController.php` | `revisar()` ahora usa `version_id` |
| `app/Http/Controllers/Admin/ProyectoController.php` | Nuevo método `destroy()` |
| `routes/api.php` | Agregado `destroy` al apiResource |
| `resources/js/pages/director/RevisionEntregaDirector.tsx` | Envía `version_id` |
| `resources/js/pages/director/DetalleEntregaDirector.tsx` | Envía `version_id` (última versión) |
| `resources/js/pages/coordinador/GestionProyectos.tsx` | Manejo de error en eliminación |
| `resources/js/hooks/useProyectos.ts` | Lectura de error body en `eliminar` |
| `resources/js/pages/dashboard/EstudianteDashboard.tsx` | Título real + `faseLabel` |
| `resources/js/components/DeliveryAccordion.tsx` | Rediseño con fechas y observaciones |
| `resources/js/types/estudiante.ts` | `title`, `startDate`, `observaciones` |
| `tests/Feature/Admin/EntregaCrudTest.php` | Tests actualizados con `version_id` |

## Pruebas

- 3 tests de revisión de entregas actualizados y pasando ✅
- Tests de proyectos pasan (1 fallo preexistente en `EntregaCrudTest > crear entrega`) ✅
- Los 8 fallos restantes en la suite son preexistentes y no están relacionados
