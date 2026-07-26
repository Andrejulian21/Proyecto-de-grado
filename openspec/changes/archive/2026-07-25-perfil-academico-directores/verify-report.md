# Verify Report: Perfil académico de Directores

**Date:** 2026-07-25  
**Change:** `2026-07-25-perfil-academico-directores`

## Checklist

| Validation | Result |
|------------|--------|
| Reuses `director_academic_profiles` (no parallel table) | PASS |
| Years of experience persisted | PASS |
| Admin GET/PUT + whitelist create sync | PASS |
| GestionUsuarios create/edit wired | PASS |
| Catalog exposes areas/años | PASS |
| No AI logic added | PASS |
| Tests | PASS — `DirectorAcademicProfileTest` + whitelist + asistente (22) |

## Commands

```text
php artisan test --filter="DirectorAcademicProfileTest|WhitelistCrudTest|AsistenteAcademicoTest"
→ 22 passed
```

## Local UI note

Requiere `php artisan migrate` y `npm run dev` / `npm run build` para ver el formulario actualizado.
