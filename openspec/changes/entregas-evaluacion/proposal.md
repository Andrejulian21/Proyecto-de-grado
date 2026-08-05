# Propuesta: Entregas y Evaluación de Evaluadores

## Intención

El sistema actual de entregas y evaluación tiene cuatro brechas críticas que impiden cerrar el ciclo de gestión de proyectos de grado:

1. **Entregas sin enforce de archivo principal**: el coordinador define archivos requeridos, pero el default "documento del proyecto" solo existe en el estado inicial del form frontend — no hay validación backend. Además, no existe la base para el futuro análisis IA de documentos.
2. **Entregas sin peso porcentual**: no hay campo `grade_percentage` en las entregas, por lo que el coordinador no puede indicar cuánto pesa cada entrega en la nota final del par de fases.
3. **Evaluadores externos sin flujo funcional**: `EvaluadorCalificar.tsx` es 100% mock (`setTimeout`). No hay endpoints para que un evaluador vea sus asignaciones, consulte el contexto de una entrega (archivos, notas del director), ni envíe su calificación.
4. **Director sin nota de entrega**: el director puede hacer observaciones, pero no existe un campo para asignar la nota de la entrega cuando la aprueba.

Este change resuelve las cuatro en un solo slice de Sprint 5.

## Alcance

### Dentro del alcance
- **C1**: Default "documento del proyecto" enforceado en `StoreEntregaRequest` (backend)
- **C1**: Campo `analizable_ia` en JSON `archivos_requeridos` — solo el archivo principal puede marcarse como analizable
- **C1**: Columna `grade_percentage` (nullable) en tabla `entregas` — informacional, sin cálculos de nota
- **C1**: Regla de pesos 100% por par de fases (anteproyecto + presentación anteproyecto / desarrollo + presentación final), validada por semestre
- **C2**: Cards de proyectos asignados para evaluador externo con toggle "ya evaluados"
- **C2**: Flujo completo de evaluación: ver entrega → nota + observaciones → envío irreversible (solo lectura)
- **C2**: Tabla `evaluaciones_evaluador` + 3 endpoints (`GET mis-asignaciones`, `GET detalle`, `POST evaluar`)
- **C2**: Conectar `EvaluadorCalificar.tsx` a API real
- **C3**: Nota del director al aprobar entrega, editable mientras la entrega esté ACTIVA
- **Refactor**: Extraer Actions del `EntregaController` (812 líneas → <500)

### Fuera de alcance
- ❌ Cálculos de nota final usando `grade_percentage` (solo se almacena y muestra como peso informacional)
- ❌ Conexión FastAPI para análisis IA de documentos (solo se prepara el campo `analizable_ia`)
- ❌ Notificaciones push al evaluador cuando se le asigna un proyecto

## Capacidades

> Contrato entre proposal y specs. El agente sdd-spec lee esto para saber qué specs crear/actualizar.

### Nuevas Capacidades
- `entregas-archivos`: Default de archivo principal enforceado en backend + campo `analizable_ia` en la estructura JSON de archivos requeridos + columna `grade_percentage` con regla de pesos 100% por par de fases.
- `evaluacion-evaluador`: Flujo completo de evaluación para evaluadores externos — cards, detalle, envío de nota, toggle evaluados, solo lectura post-envío.
- `nota-director`: Campo de nota en la entrega que el director asigna al aprobar, editable mientras la entrega esté activa.

### Capacidades Modificadas
- Ninguna. Las capacidades existentes (`seguimiento-y-firma`) no se ven afectadas.

## Enfoque

### C1 — Entregas (archivos + porcentaje)

**Archivos requeridos:**
- Extender la validación del JSON `archivos_requeridos` en `StoreEntregaRequest`: requerir al menos un archivo con slug `documento-proyecto` (o similar). Agregar campo `analizable_ia: boolean` opcional por archivo, con regla: solo el archivo con slug `documento-proyecto` puede tener `analizable_ia = true`.
- Frontend: `ArchivosRequeridosBuilder` agrega toggle "Analizable con IA" visible solo para el archivo principal.

**Porcentaje de nota (`grade_percentage`):**
- Nueva columna `grade_percentage` (decimal 5.2, nullable) en tabla `entregas`. Entregas existentes quedan NULL.
- El coordinador lo asigna en el form de creación/edición de la entrega.
- Solo informacional: no se realizan cálculos de nota con este valor en el presente change.

**Regla de pesos 100% por par de fases:**

Los pares de fases son:
- Par 1: `anteproyecto` + `presentacion_anteproyecto`
- Par 2: `desarrollo` + `presentacion_final`

La validación opera a nivel de **semestre** (todas las entregas del semestre para todos los proyectos se califican de la misma forma):

1. **Bloqueo preventivo**: al crear o actualizar una entrega, BLOQUEAR si la suma de `grade_percentage` de las entregas existentes del par (en el mismo semestre) + el nuevo valor **superaría** 100%. Error 422 con mensaje claro.
2. **Validación de completitud**: cuando ya existen entregas con `grade_percentage` NOT NULL para **ambas** fases del par en el semestre, la suma MUST ser exactamente 100%. Si no lo es, BLOQUEAR la creación de la última entrega del par.
3. Entregas existentes sin `grade_percentage` (NULL) no participan en la suma — la validación solo considera valores NOT NULL.

- Frontend: mostrar suma acumulada del par en tiempo real al editar el %, con indicador visual (verde = OK, rojo = excede).

### C2 — Evaluación de evaluadores
- Migración: agregar `evaluado` (boolean, default false) a `evaluador_proyecto` + crear tabla `evaluaciones_evaluador` (id, evaluador_proyecto_id FK, nota decimal, observaciones text, evaluated_at).
- Nuevo controller `EvaluadorAsignacionesController` con 3 endpoints bajo `/api/evaluador/`.
- Frontend: nueva página "Mis Asignaciones" con cards + toggle. `EvaluadorCalificar.tsx` reemplaza mocks por llamadas reales.
- Regla de inmutabilidad: una vez creado el registro en `evaluaciones_evaluador`, no se permite PUT/DELETE.

### C3 — Nota del director
- Agregar campo `director_grade` (decimal, nullable) a la tabla `entregas`.
- Cuando el director marca observación como "aprobada", aparece campo de nota.
- Editable mientras `entrega.status === activa` y `due_date >= now()`.

### Refactor EntregaController
- Extraer `StoreEntregaAction`, `UpdateEntregaAction`, `ReviewEntregaAction` como Actions independientes.
- El controller queda como thin layer: valida request → delega a Action → retorna resource.

## Áreas Afectadas

| Área | Impacto | Descripción |
|------|---------|-------------|
| `app/Http/Requests/StoreEntregaRequest.php` | Modified | Validación default archivo + `analizable_ia` + `grade_percentage` + regla de pesos |
| `app/Http/Requests/UpdateEntregaRequest.php` | Modified | Mismas validaciones que store para `grade_percentage` |
| `app/Http/Controllers/Admin/EntregaController.php` | Modified | Refactor → extraer Actions |
| `app/Actions/Entrega/` | New | `StoreEntregaAction`, `UpdateEntregaAction`, `ReviewEntregaAction` |
| `app/Services/EntregaPesoService.php` | New | Lógica de validación de pesos por par de fases y semestre |
| `app/Http/Controllers/Api/EvaluadorAsignacionesController.php` | New | 3 endpoints para evaluador |
| `app/Models/EvaluadorProyecto.php` | Modified | Campo `evaluado` |
| `app/Models/EvaluacionEvaluador.php` | New | Modelo para tabla nueva |
| `app/Models/Entrega.php` | Modified | Campos `grade_percentage` y `director_grade` |
| `database/migrations/` | New | 3 migraciones: (1) `grade_percentage` + `director_grade` en entregas, (2) `evaluado` en evaluador_proyecto, (3) tabla evaluaciones_evaluador |
| `routes/api.php` | Modified | Nuevas rutas bajo `/api/evaluador/` |
| `resources/js/components/entregas/ArchivosRequeridosBuilder.tsx` | Modified | Toggle IA |
| `resources/js/pages/coordinador/CoordinadorEntregas.tsx` | Modified | Campo % nota + suma acumulada del par |
| `resources/js/pages/evaluador/EvaluadorCalificar.tsx` | Modified | Conectar a API real |
| `resources/js/pages/evaluador/MisAsignaciones.tsx` | New | Página de cards + toggle |
| `resources/js/types/` | Modified | Interfaces actualizadas |

## Riesgos

| Riesgo | Probabilidad | Mitigación |
|--------|-------------|------------|
| Entregas existentes sin archivo default → queries inconsistentes | Media | Validación solo en store/update, no en lectura. Backfill opcional. |
| Evaluador envía nota y luego pide cambiar → presión de negocio | Alta | Regla clara: evaluación es inmutable. Si se necesita cambio, solo el coordinador puede resetear (fuera de este slice). |
| `EntregaController` refactor rompe tests existentes | Media | Extraer Actions sin cambiar lógica pública. Tests deben seguir pasando sin modificación. |
| Campo `director_grade` — ¿en `entregas` o en `version_documento`? | Baja | Definir en spec: la nota es por entrega, no por versión. Columna en `entregas`. |
| Regla de pesos: semestre sin entregas completas → par nunca suma 100% | Media | La validación de completitud solo se dispara cuando AMBAS fases del par tienen al menos una entrega con % NOT NULL. Si falta una fase, no hay bloqueo. |
| Regla de pesos: coordinador crea entregas en orden inesperado → bloqueo prematuro | Media | El bloqueo preventivo solo impide SUPERAR 100%. Si la suma es < 100%, se permite. El coordinador puede editar entregas existentes para ajustar. |
| Regla de pesos: múltiples entregas en la misma fase del par | Baja | Definir en spec: ¿se permite más de una entrega por fase? Si sí, la suma incluye todas las entregas de ambas fases del par. Si no, constraint UNIQUE(fase, semestre) en entregas. |
| Entregas viejas con `grade_percentage` NULL → no participan en suma | Baja | Documentado: NULL no suma. El coordinador debe backfill manualmente si quiere que participen. |

## Plan de Rollback

1. Las migraciones son aditivas (nuevas columnas nullable + tabla nueva). Rollback con `migrate:rollback` sin pérdida de datos.
2. Los endpoints nuevos no afectan rutas existentes. Se pueden deshabilitar eliminando el grupo de rutas.
3. El refactor del `EntregaController` es interno — la API pública no cambia. Si falla, revertir el commit del refactor sin impacto.

## Dependencias

- Sprint 4 completado (wireframes de `EvaluadorCalificar.tsx` y `ArchivosRequeridosBuilder.tsx` ya existen).
- Modelo `EvaluadorProyecto` y tabla `evaluador_proyecto` ya existentes.
- Enum `FaseProyecto` con los 4 valores ya definidos (anteproyecto, presentacion_anteproyecto, desarrollo, presentacion_final).

## Criterios de Éxito

- [ ] Coordinador puede crear entrega con default "documento del proyecto" enforceado en backend
- [ ] Campo `analizable_ia` persiste correctamente en JSON y solo es `true` para el archivo principal
- [ ] Columna `grade_percentage` persiste y se muestra como peso informacional en el form
- [ ] Regla de pesos bloquea creación si el par superaría 100% en el semestre
- [ ] Regla de pesos valida exactamente 100% cuando ambas fases del par tienen entregas con %
- [ ] Entregas existentes sin % (NULL) no rompen la validación
- [ ] Evaluador externo ve cards de proyectos asignados con botón "Evaluar"
- [ ] Evaluador puede ver archivos, notas del director, y enviar su propia nota + observaciones
- [ ] Evaluación enviada queda en solo lectura (sin edición)
- [ ] Toggle "ya evaluados" funciona correctamente
- [ ] Director puede asignar nota al aprobar entrega
- [ ] `EntregaController.php` queda bajo 500 líneas
- [ ] Todos los tests existentes siguen pasando (baseline: 495)
- [ ] Nuevos tests cubren los 3 endpoints de evaluador + validaciones de entrega + regla de pesos
