# Design: Sistema general de consulta de notas por proyecto y entrega

## Modelo actual (fuente de verdad)

Inspección:

| Dato | Dónde | Uso en este change |
|------|--------|-------------------|
| Nota del director por entrega **y** proyecto | `entrega_proyecto.director_grade` (nullable decimal 0–5) | **Canónica** |
| Nota en el template de entrega | `entregas.consolidated_grade`, `entregas.director_grade` | No usar (compartida entre proyectos) |
| Nota del evaluador externo | `evaluaciones_evaluador.nota` vía `evaluador_proyecto` | Extra para rol EvaluadorExterno |
| Rúbrica antigua | `evaluaciones.grade` | No usar (reporte consolidado legado; no es el flujo actual) |

Relaciones:

```
Proyecto
  director_id → User
  estudiantes ↔ proyecto_estudiante
  entrega_proyecto (pivot) → Entrega + director_grade
  evaluador_proyecto → EvaluacionEvaluador.nota
```

Quién registra hoy (no se cambia):

- Director: `PUT /api/admin/entregas/{id}/revisar` → `ReviewEntregaAction` escribe `director_grade` en el pivot al aprobar.
- Evaluador: `POST /api/evaluador/asignaciones/{id}/evaluar` → `evaluaciones_evaluador`.

No hay pantalla general de consulta; hay notas sueltas en dashboards, revisión, cards de asignaciones y seguimiento (estados de entrega, **sin** notas).

## Decisiones

### D1 — No nueva tabla

Reutilizar pivot + `evaluaciones_evaluador`.

### D2 — Una API, cuatro scopes

`GET /api/notas` autenticado con `role:Coordinador,Director,Estudiante,EvaluadorExterno`.

El service aplica el scope. `proyecto_id` fuera de ámbito → 403 (no lista vacía silenciosa, para no ocultar IDOR).

### D3 — Filtros en query

| Param | SQL |
|-------|-----|
| `semestre_id` | `proyectos.semester_id` |
| `q` | `code LIKE` OR `title LIKE` |
| `proyecto_id` | `proyectos.id` + authorize |
| `entrega_id` | filtra entregas del set |
| `estado_nota` | `calificada` = pivot `director_grade IS NOT NULL`; `sin_calificar` = null o sin pivot |

Sin `semestre_id`: proyectos en semestres `is_active` (coordinador) o el scope de rol (resto).

### D4 — Payload

```json
{
  "data": {
    "semestres": [{ "id": 1, "nombre": "2026-2", "is_active": true }],
    "proyectos": [
      {
        "id": 1,
        "codigo": "PG-…",
        "titulo": "…",
        "director": "…",
        "estudiantes": "…",
        "semestre_id": 1,
        "entregas": [
          {
            "id": 10,
            "titulo": "Anteproyecto",
            "fase": "anteproyecto",
            "nota": 4.5,
            "estado_nota": "calificada"
          }
        ],
        "nota_evaluador": null
      }
    ]
  }
}
```

`nota` JSON number or `null`. `nota_evaluador` solo tiene sentido para evaluador (null para otros).

### D5 — UI

Una página `ConsultaNotas` (patrón seguimiento: PageHeader, selector de semestre desde `data.semestres`, búsqueda, filtro estado, tabla con fila por proyecto y detalle de entregas). Sin SemestreSelector admin (`/api/admin/semestres` es solo Coordinador).

### D6 — Eficiencia

`where` + `whereHas` en proyectos; un `EntregaProyecto::whereIn(proyecto_id)` y `Entrega::paraProyecto` por lote (groupBy), no N+1 de grades.

## Archivos

| File | Action |
|------|--------|
| `openspec/changes/consulta-notas-proyecto-entrega/*` | Create |
| `app/Services/ConsultaNotasService.php` | Create |
| `app/Http/Controllers/Api/ConsultaNotasController.php` | Create |
| `app/Models/Proyecto.php` | Modify (`entregaProyectos`) |
| `app/Models/EvaluadorProyecto.php` | Modify (`evaluacion` hasOne) |
| `routes/api.php` | Modify |
| `resources/js/pages/shared/ConsultaNotas.tsx` | Create |
| Sidebar, app.tsx, AppShell | Modify |
| `tests/Feature/Api/ConsultaNotasTest.php` | Create |

## Permisos

| Rol | Scope |
|-----|--------|
| Coordinador | Proyectos del semestre |
| Director | `director_id` |
| Estudiante | `proyecto_estudiante` |
| EvaluadorExterno | `evaluador_proyecto` |

Frontend no es la frontera: la API niega.

## Open questions (resueltos)

- ¿Usar consolidated_grade? No.
- ¿Migración? No.
- ¿Export? No en este change.
