# Design: Rediseño del área y dashboard de evaluadores

## Estado actual

| Pieza | Qué hay |
|-------|---------|
| Dashboard | `MOCK_EVALUATIONS` + StatCard 6/4/2 |
| Sidebar evaluador | `/`, Mis Asignaciones, Notas, Anuncios, Recursos |
| Asignaciones reales | `GET /api/evaluador/mis-asignaciones` → `EvaluadorProyecto` |
| Calificar | `EvaluadorCalificar` + POST evaluar (inmutable) |
| Mock rúbrica | `EvaluarProyecto.tsx` en `/evaluaciones/:id` |
| Fechas | `evaluador_proyecto.fecha`, `hora_inicio`, `hora_fin` |
| Completado | `evaluado` + fila `evaluaciones_evaluador` |
| Calendario UI | `CalendarGrid` (coordinador) |
| Notas | `/notas` + `GET /api/notas` |

No hay “en proceso”. No hay perfil académico de evaluador.

## Decisiones

### D1 — Extender el index, no clonar listados

`GET /api/evaluador/mis-asignaciones?estado=pendiente|evaluada&q=`

### D2 — Endpoints de lectura

- `GET /api/evaluador/dashboard`
- `GET /api/evaluador/calendario` (solo `fecha` not null)

Autorización: `evaluador_id = auth()->id()` (igual que hoy).

### D3 — Service

`EvaluadorAreaService` para queries y mapeo; el controller de calificación (`evaluar`, `detalle`) permanece.

### D4 — UI

Cinco rutas. Extraer card compartida. Dashboard sin sección “Mis evaluaciones” mock. Pendientes/historial con buscador. Calendario = `CalendarGrid` + lista de eventos. Notas = página existente.

### D5 — EvaluarProyecto

Ruta `/evaluaciones/:id` solo Director. El evaluador usa `/evaluador/asignaciones/:id`.

## Payload dashboard

```json
{
  "data": {
    "evaluador": { "id": 1, "name": "…", "email": "…" },
    "resumen": { "asignadas": 3, "pendientes": 2, "realizadas": 1, "sin_fecha": 1 },
    "proximas": [{ "id", "proyecto", "fase", "fecha", "hora_inicio", "hora_fin", "estado": "pendiente" }]
  }
}
```

## Payload calendario

```json
{
  "data": [
    { "id", "fecha", "hora_inicio", "hora_fin", "fase", "estado", "proyecto": { "id", "codigo", "titulo" } }
  ]
}
```

## Archivos

| File | Action |
|------|--------|
| OpenSpec change | Create |
| `app/Services/EvaluadorAreaService.php` | Create |
| `EvaluadorAsignacionesController` | Modify |
| `routes/api.php` | Modify |
| Dashboard + 3 páginas + card | Create/rewrite |
| Sidebar, App.tsx, AppShell | Modify |
| `tests/Feature/Evaluador/EvaluadorAreaTest.php` | Create |

## Riesgos

- CalendarGrid oculta la grilla si `assignments.length === 0`: EmptyState existente, aceptable.
- `mapCard` gana campos; tests de estructura actuales siguen pasando (subset).
