# Design: Fix carga calificaciones evaluador

## Where data diverges

| Step | Result |
|------|--------|
| POST store | Persists `criterio` + `grade` + `comment` correctly |
| GET `/api/evaluaciones?entrega_id=` | Returns stored rows correctly |
| `EvaluadorCalificar` hydrate | Matches against wrong hardcoded names → scores 0 |
| Comment hydrate | Independent of names → OK |

## Solution (minimal)

1. Shared module `resources/js/lib/evaluacionCriteria.ts`:
   - `DEFAULT_EVALUADOR_RUBRIC` — same 4 criteria used when submitting from EvaluarProyecto.
   - `SavedEvaluacionRow` type.
   - `hydrateCriteriaFromSaved(saved, fallback)`:
     - If `saved.length > 0`: build UI criteria **from API rows** (source of truth).
     - Else: return fallback with score 0.
   - `extractComment(saved)`.

2. `EvaluadorCalificar` / `EvaluarProyecto`: use helper after fetching grades; set readOnly when grades exist.

3. No backend changes (persistencia y API ya correctas).

## Why not only fix name matching?

Even exact-name match on Calificar’s 3 criteria would ignore the 4 stored rubric rows. Rebuilding from saved rows is correct for any past/future criterion set.
