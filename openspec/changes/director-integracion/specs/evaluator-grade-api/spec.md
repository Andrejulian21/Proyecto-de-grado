# Delta for Evaluator Grade API

## ADDED Requirements

### Requirement: Director as Evaluator — Assignment Query

El director DEBE poder ver los proyectos donde fue asignado como evaluador (excluyendo los propios). Los datos vienen de `evaluador_proyecto` (pivot table administrada por el Coordinador).

> **Coherencia con Coordinador**: El `EvaluadorProyectoController` ya expone `GET /api/admin/evaluador-proyecto` con datos agrupados por proyecto. El director DEBE poder consultar sus asignaciones filtradas por `evaluador_id`.

#### Endpoint (nuevo)

```
GET /api/director/evaluaciones
Response: { data: [{ proyecto_id, proyecto_codigo, proyecto_nombre, fase, fecha, hora_inicio, estudiantes, evaluadores_list }] }
```

#### Scenario: Director ve proyectos donde es evaluador

- GIVEN el director fue asignado como evaluador en 2 proyectos (fase Anteproyecto) por el Coordinador
- WHEN llama `GET /api/director/evaluaciones`
- THEN recibe los 2 proyectos con `fase`, `fecha`, `hora_inicio`, `estudiantes` y `evaluadores_list`

#### Scenario: Excluye proyectos propios

- GIVEN el director está asignado como evaluador de un proyecto donde también es `director_id`
- WHEN consulta sus evaluaciones
- THEN ese proyecto NO aparece (filtrado: `proyecto.director_id !== auth()->id()`)

#### Scenario: Sin asignaciones

- GIVEN el director no tiene asignaciones como evaluador
- WHEN consulta `GET /api/director/evaluaciones`
- THEN recibe `{ data: [] }` con status 200

### Requirement: Find Approved Phase Delivery for Grading

Para calificar, el director-evaluador DEBE ver la entrega aprobada de la fase correspondiente (`fase` del registro `evaluador_proyecto`: `Anteproyecto` → entregas fase `presentacion_anteproyecto`, `Final` → `presentacion_final`). Se muestra la última versión aprobada.

#### Endpoint (nuevo)

```
GET /api/director/proyectos/{proyectoId}/entrega-fase?fase=presentacion_anteproyecto
Response: { data: { id, title, status, consolidated_grade, versiones: [{ id, version_number, file_path, original_name }] } }
```

La entrega DEBE tener `status: "aprobada"` — solo entregas ya aprobadas por el director del proyecto son calificables.

#### Scenario: Entrega aprobada de la fase correcta

- GIVEN un proyecto con fase `Anteproyecto` en `evaluador_proyecto`
- AND existe una entrega con `phase: "presentacion_anteproyecto"` y `status: "aprobada"`
- WHEN el evaluador consulta `GET /api/director/proyectos/{id}/entrega-fase?fase=presentacion_anteproyecto`
- THEN recibe los datos de la entrega aprobada con sus versiones

#### Scenario: Sin entrega aprobada en la fase

- GIVEN el proyecto no tiene ninguna entrega aprobada en la fase requerida
- WHEN el evaluador consulta
- THEN recibe 404 o `{ data: null }` con mensaje "No hay entrega aprobada en esta fase"

### Requirement: Submit Grade as Evaluator

El director-evaluador DEBE poder enviar calificaciones (criterios con porcentaje y nota) para la entrega de la fase asignada. Se DEBE verificar que está asignado como evaluador en `evaluador_proyecto` con el `proyecto_id` y `fase` correctos.

> **Reuse**: El endpoint `POST /api/evaluaciones` (EvaluacionController@store) ya existe con verificación contra `evaluador_proyecto`. Para el flujo del director, se DEBE verificar adicionalmente que la `entrega.phase` coincida con la fase para la cual fue asignado.

#### Endpoint (reutilizado con verificación adicional)

```
POST /api/evaluaciones
Body: { entrega_id, criterio, percentage, grade, comment }
```

### Requirement: Escala de Nota 0.0 a 5.0

La calificación DEBE usar escala **0.0 a 5.0** con 1 decimal (ej: 4.5, 3.0). Aplica a:
- `EvaluacionController@store`: campo `grade` → `numeric|min:0|max:5`
- `EntregaController@revisar`: campo `consolidated_grade` → `numeric|min:0|max:5`
- `EvaluacionController@consolidado`: fórmula ajustada → `round($totalWeighted / $totalPercentage, 1)`

#### Scenario: Evaluador califica entrega de su fase asignada

- GIVEN el director está asignado como evaluador en `evaluador_proyecto` con `fase: "Anteproyecto"`
- AND la entrega a calificar tiene `phase: "presentacion_anteproyecto"` (fase correspondiente)
- WHEN envía `POST /api/evaluaciones` con criterios y notas en escala 0-5
- THEN se crea la evaluación y responde 201

#### Scenario: Nota inválida > 5.0

- GIVEN el evaluador ingresa `grade: 5.5`
- WHEN envía `POST /api/evaluaciones`
- THEN recibe 422 con error de validación

#### Scenario: Evaluador intenta calificar fase incorrecta

- GIVEN el director está asignado para fase `Anteproyecto`
- AND intenta calificar una entrega de fase `presentacion_final`
- THEN recibe 403 o 422 con mensaje de error

#### Scenario: No asignado como evaluador

- GIVEN el director no está en `evaluador_proyecto` para ese proyecto
- WHEN intenta calificar
- THEN recibe 403 "No estás asignado a este proyecto"

### Requirement: EvaluacionesDirector UI

La página `EvaluacionesDirector.tsx` DEBE mostrar:
1. Lista de proyectos donde el director es evaluador (de `GET /api/director/evaluaciones`)
2. Al seleccionar un proyecto: la entrega aprobada de la fase + formulario de calificación (criterios con % y nota)
3. Validación: suma de porcentajes ≤ 100%, notas entre 0-100
4. Confirmación antes de enviar

#### Scenario: Flujo completo de calificación

- GIVEN el director-evaluador selecciona un proyecto
- WHEN completa los criterios (ej: "Estructura" 30% nota 85, "Contenido" 40% nota 90, "Presentación" 30% nota 88)
- THEN la suma de porcentajes no excede 100%
- AND al enviar, cada criterio crea un registro en `evaluaciones` con `evaluador_id = auth()->id()`

#### Scenario: Error por porcentaje excedido

- GIVEN el evaluador ingresa criterios que suman 110%
- WHEN intenta enviar
- THEN el backend rechaza con 422 y mensaje "La suma de porcentajes no puede exceder 100%"
- AND el frontend muestra el error antes de enviar (validación client-side también)
