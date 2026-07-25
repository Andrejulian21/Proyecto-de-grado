# Spec: Rediseño Sistema de Entregas

**Change**: sistema-entregas-rediseno
**Tipo**: Delta (ADDED)
**Estado**: 📄 ESPECIFICADO
**Fecha**: 2026-07-24

## Requerimientos Agregados

### REQ-01: Schema — archivos_requeridos en entregas

La tabla `entregas` debe incluir una columna JSON `archivos_requeridos` que almacene
la configuración de archivos esperados.

| Campo | Tipo | Regla |
|-------|------|-------|
| `archivos_requeridos` | `json` | Array de objetos `{id: string, nombre: string, versionamiento: boolean}` |

**Escenario:** Entrega creada con archivos requeridos
- GIVEN un coordinador autenticado
- WHEN crea una entrega con `archivos_requeridos: [{id: "informe", nombre: "Informe Final", versionamiento: true}]`
- THEN la columna `archivos_requeridos` persiste el array JSON íntegro

---

### REQ-02: Schema — entrega_proyecto_id y archivo_requerido_id en archivo_versiones

La tabla `archivo_versiones` debe incluir FK `entrega_proyecto_id` y columna `archivo_requerido_id`.

**Escenario:** Versión vinculada a pivote y archivo requerido
- GIVEN una versión de archivo subida por un estudiante
- WHEN se persiste en `archivo_versiones`
- THEN `entrega_proyecto_id` referencia el pivote `entrega_proyecto.id` y `archivo_requerido_id = "informe"`

---

### REQ-03: Migración — Fusión de entregas duplicadas

La migración debe fusionar entregas con mismo `(semester_id, titulo)` en una sola,
migrando pivotes y versiones.

**Escenario:** Fusión conserva pivotes existentes
- GIVEN 3 entregas con mismo `semester_id=1` y `titulo="Avance 1"`, cada una con su `entrega_proyecto`
- WHEN corre la migración
- THEN queda 1 entrega con 3 `entrega_proyecto` asociados y todas las `archivo_versiones` migradas

---

### REQ-04: Admin API — Crear entrega

`POST /api/admin/entregas` debe aceptar `archivos_requeridos` y crear una entrega
con pivotes para todos los proyectos del semestre.

**Request:**
```json
{
  "grupo_id": 1,
  "fase": "anteproyecto",
  "titulo": "Entrega de Anteproyecto",
  "descripcion": "...",
  "fecha_limite": "2026-08-15",
  "fecha_inicio": null,
  "hora_inicio": null,
  "criterios": null,
  "hora_maxima": null,
  "archivos_requeridos": [
    {"id": "documento_anteproyecto", "nombre": "Documento Anteproyecto", "versionamiento": true},
    {"id": "carta_aval", "nombre": "Carta de Aval", "versionamiento": false}
  ]
}
```

**Response 201:**
```json
{
  "data": {
    "id": 1,
    "title": "Entrega de Anteproyecto",
    "semester_id": 1,
    "phase": "anteproyecto",
    "archivos_requeridos": [...],
    "proyectos_count": 5
  }
}
```

**Escenario:** Creación con auto-vinculación
- GIVEN semestre con 5 proyectos activos
- WHEN se crea entrega con 2 archivos requeridos
- THEN se crean 5 registros `entrega_proyecto` y `archivos_requeridos` se persiste como JSON

**Escenario:** Validación — al menos un archivo requerido
- GIVEN request sin `archivos_requeridos` o array vacío
- WHEN se intenta crear
- THEN 422 con error en `archivos_requeridos`

**Escenario:** Validación — IDs duplicados
- GIVEN `archivos_requeridos` con dos objetos con mismo `id`
- WHEN se intenta crear
- THEN 422 con error de unicidad de slugs

---

### REQ-05: Admin API — Editar entrega

`PUT /api/admin/entregas/{id}` debe permitir modificar `archivos_requeridos`.

**Escenario:** No permite cambiar versionamiento si hay versiones
- GIVEN archivo con `{id: "informe", versionamiento: true}` con 3 versiones subidas
- WHEN se intenta cambiar a `versionamiento: false`
- THEN 422 con mensaje de error

**Escenario:** Eliminación de archivo marca como descontinuado
- GIVEN archivo requerido `{id: "anexos"}` con versiones existentes
- WHEN se elimina de `archivos_requeridos`
- THEN las versiones se preservan pero se marcan como descontinuadas

---

### REQ-06: Admin API — Detalle de entrega

`GET /api/admin/entregas/{id}` debe devolver `archivos_requeridos`, proyectos
vinculados y versiones agrupadas por archivo.

---

### REQ-07: Estudiante API — Subir archivo específico

`POST /api/entregas/{id}/archivos/{slug}`

Body: multipart form con `file`

| `versionamiento` | Comportamiento |
|------------------|----------------|
| `true` | Crea nueva versión (comportamiento actual) |
| `false` | Reemplaza — borra versión anterior del mismo slug + proyecto |

**Escenario:** Subida con versionamiento
- GIVEN archivo `{id: "informe", versionamiento: true}`
- WHEN estudiante sube archivo
- THEN se crea nueva versión sin borrar previas

**Escenario:** Subida sin versionamiento reemplaza
- GIVEN archivo previo subido para slug "anexos"
- WHEN se sube nuevo archivo con `versionamiento: false`
- THEN la versión anterior se borra y solo queda la nueva

---

### REQ-08: Estudiante API — Estado de completitud

`GET /api/entregas/{id}/estado`

Devuelve qué archivos están completos/pendientes para el estudiante autenticado.

**Response:**
```json
{
  "completos": 2,
  "pendientes": 1,
  "archivos": [
    {"id": "informe", "completo": true},
    {"id": "anexos", "completo": false}
  ]
}
```

---

### REQ-09: Auto-vinculación de proyectos nuevos

Al crear un proyecto en un semestre con entregas existentes, el sistema debe crear
automáticamente los pivotes `entrega_proyecto` faltantes.

**Escenario:** Proyecto nuevo vinculado
- GIVEN semestre con 3 entregas existentes
- WHEN se crea un nuevo proyecto en ese semestre
- THEN se crean 3 registros `entrega_proyecto` (uno por entrega)

Implementar mediante Observer en `Proyecto` o evento `created`.

---

## Tests Requeridos

| Test | Tipo | Cubre |
|------|------|-------|
| `EntregaStoreTest` | Unit | Creación + N pivotes, validación archivos requeridos |
| `MigracionEntregasDuplicadasTest` | Integration | Fusión de entregas, migración versiones, integridad FKs |
| `ArchivoSubidaVersionamientoTest` | Feature | Subida con/sin versionamiento, reemplazo, slug inexistente |
| `AutoVinculacionProyectoTest` | Feature | Proyecto nuevo → pivotes creados automáticamente |
