# Design: Perfil académico de Directores

## Phase 1–3 findings

| Area | Finding |
|------|---------|
| UI | `GestionUsuarios.tsx` — create Director via whitelist with `areas` only; edit modal has no director academic fields |
| Create API | `POST /api/admin/whitelist` → `UserController::store` writes `users.areas`, no profile upsert |
| Cupos | `users.max_capacity` + `users.areas` via `DirectorCupoController` — keep as-is |
| Existing profile | `director_academic_profiles`: `research_lines`, `technologies`, `methodologies`, `academic_experience` |
| Consumer | `DirectorCatalogBuilder` already reads profile (+ areas fallback) |
| Gap | No admin CRUD; missing years of experience; create does not sync profile |

## Phase 4 — Storage strategy

### Alternatives

| Option | Verdict |
|--------|---------|
| A. Nuevas columnas en `users` | Rejected — mezcla identidad/ops con catálogo académico; acopla crecimiento del perfil |
| B. Nueva tabla paralela | Rejected — duplicaría `director_academic_profiles` |
| C. Tablas normalizadas (tecnologías, líneas…) | Rejected for v1 — sin catálogo institucional; alto costo; listas JSON bastan para IA |
| D. **Reutilizar `director_academic_profiles` + `years_of_experience`** | **Adopted** |

### Field mapping

| Product field | Storage | Notes |
|---------------|---------|-------|
| Áreas de especialización | `users.areas` (text, líneas) | Compatibilidad cupos / DirectoresPage |
| Líneas de investigación | `research_lines` (json[]) | Fuente IA |
| Tecnologías | `technologies` (json[]) | |
| Metodologías | `methodologies` (json[]) | |
| Descripción profesional | `academic_experience` (text) | Reutilizar columna existente |
| Años de experiencia | **`years_of_experience`** (unsignedTinyInteger, nullable) | Nueva columna |
| Cupo | `users.max_capacity` | Fuera de este change |

Listas en API: `string[]`. En UI: textarea “una por línea” (mismo patrón que áreas actuales).

### Why this prepares the Assistant

`DirectorCatalogBuilder` already builds context from these fields. After this change it will also expose `años_experiencia` and treat `users.areas` as `areas_especializacion` explicitly. No AI logic added.

## Architecture

```text
GestionUsuarios (Coordinador)
  → POST /api/admin/whitelist  (Director + perfil opcional)
       → UserController::store
            → users (+ areas)
            → DirectorAcademicProfileWriter::upsert
  → GET/PUT /api/admin/directores/{id}/perfil-academico
       → DirectorAcademicProfileController
            → validate Director role
            → upsert profile + sync areas
  → DirectorCatalogBuilder (read-only, already wired)
```

### Writer service

`DirectorAcademicProfileWriter` centralizes split-lines / upsert to avoid duplicating logic in whitelist store and profile controller.

### Frontend

- Expand create card “Directores” with academic fields (component extract if needed to limit file growth).
- Edit modal: if user role is Director (and has user id), load GET perfil and save via PUT.
- Keep visual language (labels, borders, burnt orange / indigo buttons).

## Non-goals

- No mock data.
- No changes to assistant prompts/strategies beyond catalog field exposure.
- No new AI endpoints.
