# Design: Rediseño Dashboard Evaluador

## Separation

| Surface | Responsibility |
|---------|----------------|
| Dashboard | Home: who I am, workload summary, shortcuts |
| Evaluaciones | Full assignment list + filters + actions |

## Data sources (reuse)

- `useAuth().user` → name, email, role (+ `created_at` from sessionCheck)
- `useEvaluadorEvaluaciones().kpis` → asignados / pendientes / completadas
- Missing fields → `datoNoEncontrado(...)`

## UI blocks

1. Welcome header (“Hola, {name}”)
2. Profile card (nombre, correo, rol etiqueta, fecha ingreso)
3. Three `StatCard`s
4. Workload progress bar (% evaluados / asignados)
5. Empty hint when asignados = 0
6. Quick links: Evaluaciones, Recursos, Anuncios (nav buttons, existing routes)

No new components. Remove EvaluationCard / StarRating from dashboard.
