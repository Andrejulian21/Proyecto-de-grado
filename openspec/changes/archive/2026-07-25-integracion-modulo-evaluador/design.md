# Design: Integración módulo Evaluador

## Analysis summary (Phases 1–3)

### Current UI (mock)
- `EvaluadorDashboard`: `MOCK_EVALUATIONS` + KPIs fijos; botón sin `onClick`/`navigate`.
- `EvaluarProyecto` / `EvaluadorCalificar`: subtítulo hardcodeado (`PG-2026-014…`), documento placeholder, rúbrica local, submit fake.
- Rutas existentes: `/dashboard/evaluador-externo`, `/evaluaciones/:id`, `/evaluaciones/:id/calificar` (React Router SPA, **no Inertia**).
- Auth: Sanctum cookie + `useAuth()`; rol `EvaluadorExterno`.

### Existing data model (reuse)
| Concept | Table / model | How linked |
|---------|---------------|------------|
| Asignación | `evaluador_proyecto` / `EvaluadorProyecto` | `(proyecto_id, evaluador_id)` + `assigned_at`, `fase`, `fecha` |
| Proyecto | `proyectos` / `Proyecto` | `director()`, `estudiantes()`, `enSemestresActivos()` |
| Entrega a evaluar | `entregas` / `Entrega` | Aprobada por fase (`entregaFase` pattern) |
| Calificación | `evaluaciones` / `Evaluacion` | Por `(entrega_id, evaluador_id, criterio)` |
| Documento | `versiones` / `VersionDocumento` | `file_path` → `/storage/{path}` |

**No existe** columna `modalidad` en `proyectos` → UI debe mostrar mensaje informativo.

### Existing APIs (reuse)
- `POST /api/evaluaciones` — calificar (ya valida asignación).
- `GET /api/evaluaciones?entrega_id=` — filas del evaluador.
- `DirectorController@evaluaciones` / `@entregaFase` — patrón a espejar (no llamar desde UI Evaluador bajo prefijo `director`).

### Identification of assigned projects
```
EvaluadorProyecto::where('evaluador_id', $authId)
  ->whereHas('proyecto', fn ($q) => $q->enSemestresActivos())
```

## Architecture decision

### A. Thin `EvaluadorController` under `/api/evaluador`
| Method | Route | Purpose |
|--------|-------|---------|
| `evaluaciones` | `GET /api/evaluador/evaluaciones` | Cards del dashboard + metadata para detalle |
| `kpis` | `GET /api/evaluador/kpis` | asignados / pendientes / completadas |
| `entregaFase` | `GET /api/evaluador/proyectos/{id}/entrega-fase` | Misma lógica que Director (check asignación) |

Payload de asignación (por ítem):
```json
{
  "id": 1,
  "code": "PG-…",
  "title": "…",
  "director": { "id": 1, "name": "…" } | null,
  "estudiantes": [{ "id", "name" }],
  "fase_asignada": "Anteproyecto",
  "fecha": "2026-07-15",
  "assigned_at": "2026-07-10T…",
  "evaluation_status": "pending" | "evaluated",
  "rating": 4.2 | null,
  "semestre": { … }
}
```

`evaluation_status` / `rating`: promedio de `Evaluacion.grade` del usuario sobre la entrega aprobada de la fase asignada (si existe); si no hay grades → `pending`.

### B. Frontend
- Hook `useEvaluadorEvaluaciones` (patrón `useReducer` de `useDirectorEvaluaciones`).
- Dashboard: KPIs desde `/kpis` o derivados del listado; cards → `navigate(/evaluaciones/${id})` o `/calificar` si ya evaluado.
- Detalle: `useParams().id` → cargar ítem del listado (o refetch) + `entrega-fase` + submit vía `POST /api/evaluaciones` (rúbrica fija del wireframe = criterios UI, no mock de proyecto).
- Helper:
  ```ts
  function datoNoEncontrado(dato: string): string {
    return `${dato} no se ha podido encontrar.`;
  }
  // datoNoEncontrado('El director') / ('La modalidad') / …
  ```

### C. Out of scope for schema
No nuevas migraciones ni relaciones Eloquent inversas en `User`/`Proyecto` (se consulta `EvaluadorProyecto` como hoy).

### D. Seed (dev only)
Asignar `miguelafanquin10.evaluador@gmail.com` al proyecto demo del `TestUsersSeeder` con `invitation_status = Aceptada`.

## Non-goals
- Extraer service compartido Director/Evaluador (posible refactor futuro; no requerido).
- Unificar `EvaluarProyecto` y `EvaluadorCalificar` en un solo componente.
- Cambiar escala de notas (ya 0–5).

## Test plan
- Pest: evaluador con/sin asignaciones; no ve asignaciones ajenas; entrega-fase 403/404; kpis coherentes.
- Manual: login Angel → dashboard → Evaluar → documento/rùbrica → enviar.
- `npm run build`.
