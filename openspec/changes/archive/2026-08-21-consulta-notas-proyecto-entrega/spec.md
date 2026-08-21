# Spec: Sistema general de consulta de notas por proyecto y entrega

> Change: `consulta-notas-proyecto-entrega` | Lectura de notas existentes por rol

## Decisiones

| # | Decisión | Resolución |
|---|----------|-----------|
| D1 | Fuente de nota de entrega | `entrega_proyecto.director_grade` (por proyecto). No `entregas.consolidated_grade`. |
| D2 | Ausencia de nota | `null` → estado `sin_calificar` / texto “Sin calificar”. Nunca coercer a 0. |
| D3 | Nota 0 | `director_grade = 0` es una nota válida (`calificada`). |
| D4 | Evaluador | Ámbito = proyectos en `evaluador_proyecto`. Además `nota_evaluador` desde `evaluaciones_evaluador` si existe. |
| D5 | Coordinador | Proyectos del semestre solicitado (o semestres activos si no hay filtro). |
| D6 | Director | `proyectos.director_id = user`. |
| D7 | Estudiante | Proyectos del pivot `proyecto_estudiante`. |
| D8 | Filtros en BD | `semestre_id`, `proyecto_id`, `entrega_id`, `estado_nota`, `q` (código/título). |
| D9 | Specs históricas | `openspec/specs/` no se modifica. |
| D10 | Escritura | Este change no registra ni recalcula notas. |

---

## Capacidad: consulta-notas-por-rol

### RF-NOTAS-01: Coordinador consulta su ámbito

WHEN un Coordinador autenticado llama `GET /api/notas`, the system SHALL devolver proyectos del semestre (filtro o activos) con sus entregas y `nota` tomada de `entrega_proyecto.director_grade`.

#### Escenario: Ve notas de los proyectos del semestre

- GIVEN dos proyectos en el semestre, uno con nota 4.5 y otro sin nota
- WHEN GET sin `proyecto_id`
- THEN ambos aparecen
- AND el primero tiene `nota` 4.5 y `estado_nota` calificada
- AND el segundo tiene `nota` null y `estado_nota` sin_calificar

### RF-NOTAS-02: Estudiante solo sus proyectos

WHEN un Estudiante llama `GET /api/notas`, the system SHALL incluir únicamente proyectos donde está en `proyecto_estudiante`.

WHEN envía `proyecto_id` de un proyecto ajeno, the system SHALL responder 403.

### RF-NOTAS-03: Director solo proyectos asignados

WHEN un Director llama `GET /api/notas`, the system SHALL incluir únicamente proyectos con `director_id` igual al usuario.

WHEN pide `proyecto_id` de otro director, the system SHALL responder 403.

### RF-NOTAS-04: Evaluador solo asignaciones

WHEN un EvaluadorExterno llama `GET /api/notas`, the system SHALL incluir únicamente proyectos presentes en `evaluador_proyecto` para ese usuario.

WHEN pide un proyecto no asignado, the system SHALL responder 403.

### RF-NOTAS-05: Entregas del proyecto correcto

Each entrega in the payload MUST pertenecer al proyecto (pivot `entrega_proyecto` y/o `entregas.proyecto_id` vía `Entrega::paraProyecto`).

### RF-NOTAS-06: Nota de la entrega correcta

The `nota` of an entrega MUST ser el `director_grade` del pivot de **ese** `proyecto_id` + `entrega_id`, no el de otro proyecto ni el del template.

### RF-NOTAS-07: Sin nota no es cero

WHEN `director_grade` is NULL, the system SHALL devolver `nota: null` AND `estado_nota: "sin_calificar"`. The UI MUST mostrar “Sin calificar” AND MUST NOT mostrar `0` / `0.00`.

WHEN `director_grade` is 0, the system SHALL devolver `nota: 0` AND `estado_nota: "calificada"`.

### RF-NOTAS-08: Filtros

WHEN se envía `estado_nota=sin_calificar`, the system SHALL omitir entregas calificadas (y proyectos que queden sin entregas visibles).

WHEN se envía `q` con un código de proyecto, the system SHALL filtrar en base de datos (código o título).

WHEN se envía `entrega_id`, the system SHALL mostrar solo esa entrega en los proyectos que la tengan.

### RF-NOTAS-09: No autorizado

WHEN un usuario autenticado de un rol válido intenta leer notas fuera de su ámbito (`proyecto_id` ajeno), the system SHALL 403.

Una petición sin autenticación MUST ser 401.

### RF-NOTAS-10: Datos de BD

The response MUST construirse desde modelos Eloquent (Proyecto, Entrega, EntregaProyecto, EvaluacionEvaluador). The system SHALL NOT rellenar notas con constantes de UI.

### RF-NOTAS-11: UI por rol

The sección `/notas` MUST estar en el sidebar de Coordinador, Director, Estudiante y EvaluadorExterno.

The UI MUST agrupar por proyecto y listar entregas con nota o “Sin calificar”.

Documentos no aplican. Evaluación del evaluador (si hay) MUST mostrarse como dato adicional `nota_evaluador`, no como si fuera la nota del director.
