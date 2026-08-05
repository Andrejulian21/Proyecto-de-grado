# Exploración: entregas-evaluacion

**Change**: `entregas-evaluacion` | **Estado**: EXPLORATION COMPLETE
**Fecha**: 2026-08-03 | **Sprint**: 5 (Integración frontend + backend)

---

## Estado Actual

El proyecto es una plataforma Laravel 11 + React/TypeScript para gestionar proyectos de grado UNAB. Sprint 1-4 completados: auth, backend completo (13 migraciones, 12 modelos, 5 enums, ~40 endpoints), tests (495 passing), y 29 wireframes porteados a React.

### Módulo Entregas — Estado Actual

| Aspecto | Estado | Detalle |
|---------|--------|---------|
| **Modelo Entrega** | ✅ Existe | `app/Models/Entrega.php` — tiene `archivos_requeridos` como JSON cast |
| **archivos_requeridos** | ✅ Existe | Columna JSON en tabla `entregas`. Estructura: `{slug, nombre, versionamiento}` |
| **Formulario coordinador** | ✅ Existe | `CoordinadorEntregas.tsx` — usa `ArchivosRequeridosBuilder` |
| **Default "documento"** | ⚠️ Solo frontend | `formArchivos` inicia con `[{id:'documento',nombre:'Documento',versionamiento:true}]` — no hay enforce backend |
| **Checkbox IA** | ❌ No existe | No hay campo `analizable_ia` en la estructura de archivos requeridos |
| **% nota por entrega** | ❌ No existe | No hay campo `grade_percentage` en entregas. Las evaluaciones usan `percentage` por criterio |
| **Regla pesos 100%** | ❌ No existe | No hay validación de que anteproyecto+presentacion_anteproyecto=100% |
| **Controller** | ⚠️ 812 líneas | `EntregaController.php` necesita splitting |
| **Endpoint store** | ✅ Existe | `POST /api/admin/entregas` — crea entrega + pivotes |

### Módulo Evaluadores — Estado Actual

| Aspecto | Estado | Detalle |
|---------|--------|---------|
| **Modelo Evaluacion** | ✅ Existe | `app/Models/Evaluacion.php` — campos: entrega_id, evaluador_id, criterio, percentage, grade, comment |
| **Modelo EvaluadorProyecto** | ✅ Existe | `app/Models/EvaluadorProyecto.php` — campos: proyecto_id, evaluador_id, fase, fecha, hora_inicio, hora_fin |
| **Asignación coordinador** | ✅ Existe | `AsignacionEvaluadores.tsx` — form completo con validaciones |
| **Cards evaluador** | ❌ No existe | No hay vista de "proyectos asignados" para evaluador externo |
| **Flujo "Evaluar"** | ⚠️ Mock | `EvaluadorCalificar.tsx` es wireframe mock — usa `setTimeout`, sin llamada API |
| **Proyectos evaluados toggle** | ❌ No existe | No hay campo `evaluado` en evaluador_proyecto |
| **Ver nota propia** | ❌ No existe | No hay endpoint para que evaluador vea sus evaluaciones previas |
| **Notas del director** | ✅ Parcial | `VersionDocumento` tiene `director_notes`, pero no se muestran en EvaluadorCalificar |
| **Endpoint store evaluación** | ⚠️ Existe pero no usado | `POST /api/evaluaciones` valida percentage sum ≤ 100, pero el frontend no lo llama |

### Fases del Proyecto (enum FaseProyecto)

| Valor | Label |
|-------|-------|
| `anteproyecto` | Anteproyecto |
| `presentacion_anteproyecto` | Presentación Anteproyecto |
| `desarrollo` | Desarrollo del proyecto |
| `presentacion_final` | Presentación Final |

---

## Áreas Afectadas

### Backend
- `app/Models/Entrega.php` — agregar campo `grade_percentage`, helper para default file
- `app/Models/Evaluacion.php` — posible refactor para soportar "nota evaluador" vs "nota criterio"
- `app/Models/EvaluadorProyecto.php` — agregar campo `evaluado` (boolean) o crear tabla `evaluaciones_evaluador`
- `app/Http/Controllers/Admin/EntregaController.php` — splitting (812 líneas), agregar validación de pesos, default archivo
- `app/Http/Controllers/Api/EvaluacionController.php` — nuevo endpoint para evaluador enviar nota
- `app/Http/Controllers/Admin/EvaluadorProyectoController.php` — posible nuevo endpoint para listar asignaciones por evaluador
- `database/migrations/` — nuevas migraciones para campos adicionales
- `routes/api.php` — nuevas rutas para evaluador

### Frontend
- `resources/js/components/entregas/ArchivosRequeridosBuilder.tsx` — agregar checkbox "Analizable con IA"
- `resources/js/pages/coordinador/CoordinadorEntregas.tsx` — agregar campo % nota, validar suma 100% por par de fases
- `resources/js/types/entregas.ts` — agregar campo `analizable_ia`, `grade_percentage`
- `resources/js/hooks/useEntregas.ts` — agregar validación de pesos
- `resources/js/pages/evaluador/` — crear nueva página "Mis Asignaciones" con cards
- `resources/js/pages/evaluador/EvaluadorCalificar.tsx` — conectar a API real, mostrar archivos subidos, notas director
- `resources/js/hooks/useEvaluadorProyecto.ts` — nuevo hook para evaluador ver sus asignaciones

### Tests
- `tests/Feature/Admin/EntregaCrudTest.php` — tests para default file, IA checkbox, % nota, regla pesos
- `tests/Feature/Admin/EvaluacionTest.php` — tests para flujo evaluador
- Nuevos tests para endpoints de evaluador

---

## Enfoques

### CAPACIDAD 1 — Entregas

#### Opción A: Extender estructura JSON de archivos_requeridos (Recomendada)
- Agregar campo `analizable_ia: boolean` a cada objeto en `archivos_requeridos`
- Backend valida y persiste el nuevo campo en el JSON existente
- `ArchivosRequeridosBuilder` agrega toggle "Analizable con IA"
- Campo `grade_percentage` como nueva columna en `entregas`
- Validación de pesos en `StoreEntregaRequest` / `UpdateEntregaRequest`

| Pros | Cons | Complejidad |
|------|------|-------------|
| No requiere nueva tabla, consistente con diseño actual | JSON validation más compleja | Media |
| `grade_percentage` es columna simple | Rompe entregas existentes sin % (nullable OK) | |

#### Opción B: Tabla relacional `archivos_requeridos`
- Migrar de JSON a tabla con FK a entregas
- Cada fila: id, entrega_id, nombre, versionamiento, analizable_ia

| Pros | Cons | Complejidad |
|------|------|-------------|
| Más flexible para queries individuales | Overkill para ~2-6 archivos por entrega | Alta |
| Validación más fácil | Requiere migración de datos + refactor de todo el módulo | |

### CAPACIDAD 2 — Evaluación de Evaluadores

#### Opción A: Agregar campo `evaluado` a `evaluador_proyecto` + nueva tabla `evaluaciones_evaluador` (Recomendada)
- `evaluador_proyecto` agrega `evaluado: boolean` default false
- Nueva tabla `evaluaciones_evaluador`: id, evaluador_proyecto_id, nota, observaciones, evaluated_at
- Endpoint `GET /api/evaluador/mis-asignaciones` devuelve cards con estado
- Endpoint `POST /api/evaluador/asignaciones/{id}/evaluar` guarda nota + observaciones
- `EvaluadorCalificar.tsx` se conecta a API real

| Pros | Cons | Complejidad |
|------|------|-------------|
| Separación clara entre asignación y evaluación | Nueva tabla + migración | Media |
| Permite múltiples evaluaciones por asignación si cambia la lógica | | |
| Toggle "ya evaluados" fácil con campo booleano | | |

#### Opción B: Reutilizar tabla `evaluaciones` existente
- Agregar campo `evaluador_proyecto_id` a `evaluaciones`
- Marcar como evaluado cuando exista registro

| Pros | Cons | Complejidad |
|------|------|-------------|
| Menos tablas nuevas | `evaluaciones` actual es por-criterio, no por-evaluador | Media-Alta |
| | Confusión semántica: ¿qué es un "criterio" vs "nota del evaluador"? | |

---

## Recomendación

**Capacidad 1**: Opción A — extender JSON con `analizable_ia` + columna `grade_percentage` en entregas. Es el cambio mínimo que mantiene consistencia con el diseño existente. La validación de pesos (anteproyecto+presentacion_anteproyecto=100%) se hace en el FormRequest del backend.

**Capacidad 2**: Opción A — campo `evaluado` en `evaluador_proyecto` + tabla `evaluaciones_evaluador`. Esto separa claramente la asignación (coordinador decide quién evalúa) de la evaluación (evaluador pone nota). Permite el toggle "ya evaluados" y el flujo "Ver" para proyectos evaluados.

### Flujo de datos propuesto

```
COORDINADOR crea asignación
    │
    └──→ 1 fila en evaluador_proyecto (evaluado=false)

EVALUADOR ve "Mis Asignaciones"
    │
    ├──→ GET /api/evaluador/mis-asignaciones
    ├──→ Cards: proyecto, código, estudiantes, director, fase, botón "Evaluar"
    └──→ Toggle "Ver ya evaluados" → filtra evaluado=true

EVALUADOR click "Evaluar"
    │
    ├──→ GET /api/evaluador/asignaciones/{id}/detalle
    ├──→ Ve: archivos subidos, observaciones director, nota director
    ├──→ Input: nota propia + observaciones
    └──→ POST /api/evaluador/asignaciones/{id}/evaluar
         └──→ evaluador_proyecto.evaluado = true
```

---

## Riesgos

1. **Default "documento del proyecto"**: Hoy el frontend defaultea a `{id:'documento',nombre:'Documento'}`. Si el backend no enforcea un default, entregas creadas por API directa podrían no tenerlo. **Mitigación**: Agregar validación en `StoreEntregaRequest` que requiera al menos 1 archivo, y/o seed default en el controller.

2. **Checkbox IA requiere backend nuevo**: El campo `analizable_ia` no existe en la estructura JSON actual. Requiere: (a) actualizar validación en controller, (b) actualizar TypeScript interfaces, (c) posiblemente endpoint FastAPI para análisis. **Mitigación**: Agregar como campo opcional primero, conectar FastAPI después.

3. **% de nota rompe entregas existentes**: Si se agrega `grade_percentage` como NOT NULL, todas las entregas existentes fallan. **Mitigación**: Columna nullable con default NULL, migración backfill opcional.

4. **Evaluación ya tiene notas persistidas**: La tabla `evaluaciones` existe pero está diseñada para criterios con porcentajes (sum ≤ 100). No es compatible con "nota única del evaluador". **Mitigación**: Nueva tabla `evaluaciones_evaluador` separada.

5. **EvaluadorCalificar es 100% mock**: No hay integración con archivos subidos reales, notas del director, ni persistencia. Requiere trabajo frontend + backend completo. **Mitigación**: Crear endpoint `GET /api/evaluador/asignaciones/{id}/detalle` que devuelva todo el contexto necesario.

6. **Regla de pesos compleja**: Validar que anteproyecto+presentacion_anteproyecto=100% requiere conocer las entregas del mismo semestre/fase par. No es una validación simple por entrega individual. **Mitigación**: Validación en el controller que consulta todas las entregas del semestre para el par de fases.

7. **EntregaController 812 líneas**: Ya excede el límite de 500 líneas del proyecto. Cualquier cambio adicional empeora el problema. **Mitigación**: Extraer `EntregaReviewAction` y `EntregaFileUploadAction` como parte de este change.

---

## Tests Existentes (Baseline)

| Test | Cubre |
|------|-------|
| `EntregaCrudTest.php` | CRUD entregas + state machine transitions |
| `EvaluacionTest.php` | Evaluaciones + EvaluadorProyecto + Reporte consolidado (577 líneas, monolítico) |
| `EntregaTest.php` (Unit) | Fillable, casts, state machine, scopes, relationships |

Tests que necesitarán agregarse:
- Validación de `analizable_ia` en archivos_requeridos
- Validación de `grade_percentage` (nullable, 0-100)
- Regla de pesos 100% por par de fases
- Endpoint `GET /api/evaluador/mis-asignaciones`
- Endpoint `POST /api/evaluador/asignaciones/{id}/evaluar`
- Toggle evaluado/no evaluado

---

## Listo para Propuesta

**Sí** — La exploración está completa. El orquestador puede proceder a `sdd-propose` con alcance claro:

### Capacidad 1 — Entregas
1. Agregar `analizable_ia` a estructura JSON de archivos_requeridos
2. Agregar columna `grade_percentage` nullable a tabla `entregas`
3. Validación de pesos 100% por par de fases en StoreEntregaRequest
4. Default "documento del proyecto" en backend (no solo frontend)
5. Actualizar ArchivosRequeridosBuilder con checkbox IA
6. Actualizar CoordinadorEntregas con campo % nota

### Capacidad 2 — Evaluación Evaluadores
1. Agregar campo `evaluado` boolean a `evaluador_proyecto`
2. Crear tabla `evaluaciones_evaluador` (nota + observaciones)
3. Endpoint `GET /api/evaluador/mis-asignaciones` (cards con estado)
4. Endpoint `GET /api/evaluador/asignaciones/{id}/detalle` (contexto completo)
5. Endpoint `POST /api/evaluador/asignaciones/{id}/evaluar` (guardar nota)
6. Nueva página React "Mis Asignaciones" con toggle evaluados
7. Conectar EvaluadorCalificar a API real
