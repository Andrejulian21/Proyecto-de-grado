# Propuesta: Sistema de Entregas — Rediseño a "Una Entrega por Grupo"

**Change**: sistema-entregas-rediseno
**Tipo**: Feature (backend + frontend + migración datos)
**Estado**: 📄 PROPUESTA
**Fecha**: 2026-07-24
**Riesgo**: Medio-Alto

## Resumen Ejecutivo

El sistema actual crea **N entregas duplicadas** (una por proyecto) cada vez que el coordinador
define una entrega para un grupo/semestre. Esto genera datos redundantes, gestión fragmentada
e imposibilidad de definir archivos requeridos específicos.

La propuesta consolida las entregas a **una sola fila por grupo/semestre**, vinculada a los
proyectos mediante la tabla pivote `entrega_proyecto`. Se introduce el concepto de **archivos
requeridos configurables** (ej: "Documento Anteproyecto" con versiones, "Carta de Aval" sin
versiones), transformando el flujo de subida del estudiante de "subir archivos genéricos" a
"completar una lista de entregables definida por el coordinador".

## Problema Actual

El `EntregaController@store()` actual hace:

```php
foreach ($proyectos as $proyecto) {
    Entrega::create([
        'proyecto_id' => $proyecto->id,
        // mismos datos duplicados para cada proyecto
    ]);
}
```

| Problema | Impacto |
|----------|---------|
| N filas con mismo título/descripción | Editar requiere modificar N registros |
| No hay metamodelo de "archivos requeridos" | El estudiante sube "lo que cree", sin guía |
| Proyectos nuevos en semestre activo | No se vinculan automáticamente a entregas existentes |

## Solución Propuesta

### 1. Una sola entrega por grupo

- Se crea **1 fila** en `entregas` por entrega definida
- `proyecto_id` queda `NULL` (ya fue nullable)
- `semester_id` determina el alcance del grupo
- Se usa el pivote `entrega_proyecto` para vincular a todos los proyectos del semestre
- Proyectos nuevos se auto-vinculan mediante Observer en `Proyecto`

### 2. Archivos requeridos configurables

Nuevo campo JSON `archivos_requeridos` en `entregas`:

```json
[
  {"id": "documento_anteproyecto", "nombre": "Documento Anteproyecto", "versionamiento": true},
  {"id": "carta_aval", "nombre": "Carta de Aval", "versionamiento": false}
]
```

### 3. Subida del estudiante por archivo

Cada archivo requerido se muestra como una tarjeta:
- **Con versionamiento**: historial de versiones (comportamiento actual)
- **Sin versionamiento**: subida única (reemplaza el anterior)

### 4. Revisión del director adaptada

El director ve la lista de archivos y puede dejar observaciones por cada uno.

## Archivos Afectados

### Backend
| Archivo | Cambio |
|---------|--------|
| `app/Models/Entrega.php` | `archivos_requeridos` en fillable/casts, ajustar relaciones |
| `app/Http/Controllers/Admin/EntregaController.php` | store() crea 1 entrega + attach pivots. show/update/index ajustados |
| `routes/api.php` | Endpoints para subida por archivo requerido |

### Base de datos
| Migración | Cambio |
|-----------|--------|
| `add_archivos_requeridos_to_entregas` | Columna JSON `archivos_requeridos` |
| `migrar_entregas_duplicadas` | Fusionar entregas existentes, migrar versiones al nuevo esquema |

### Frontend
| Archivo | Cambio |
|---------|--------|
| `CoordinadorEntregas.tsx` | Builder de archivos requeridos en formulario |
| `DetalleEntregaEstudiante.tsx` | Subida por archivo requerido |
| `RevisionEntregaDirector.tsx` | Revisión por archivo |
| `hooks/useEntregas.ts` | Nuevos tipos y mutaciones |
| `types/estudiante.ts` | Interfaces `ArchivoRequeridoConfig` |

## Plan de Migración

1. Backup de tablas `entregas`, `entrega_proyecto`, `archivo_versiones`
2. Agregar columna `archivos_requeridos` a `entregas`
3. Migrar entregas duplicadas: fusionar por (semester_id, título), crear pivotes
4. Migrar versiones al nuevo `entrega_proyecto_id`
5. Seed `archivos_requeridos` por defecto para entregas existentes

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Datos corruptos en migración | Backup + transacción + verificación post-migración |
| Cambio de archivos requeridos después de subidas | No permitir cambiar versionamiento si ya hay archivos |
| Breaking change en API | Mantener endpoints legacy con @deprecated 1 sprint |

## Siguiente paso

Fase de **specs**: definir contratos de API, esquema exacto de migraciones y tipos TypeScript.
