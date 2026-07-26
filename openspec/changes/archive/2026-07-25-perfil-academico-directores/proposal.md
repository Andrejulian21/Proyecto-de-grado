# Proposal: Perfil académico de Directores (gestión de usuarios)

## Intent

Preparar la **gestión de Directores** para que el Coordinador pueda registrar y administrar un perfil académico descriptivo en base de datos, reutilizable por el Asistente Académico Inteligente ya existente. **No implementa lógica de IA.**

## Problem

1. `GestionUsuarios` solo captura `areas` (texto) al crear un Director.
2. Ya existe `director_academic_profiles` (consumida por `DirectorCatalogBuilder`), pero **no hay UI/API de administración**.
3. Falta el campo **años de experiencia**.
4. Crear Director con `areas` no sincroniza el perfil estructurado.

## Scope

### In Scope
- Reutilizar `director_academic_profiles` (sin tabla paralela).
- Ampliar perfil con `years_of_experience`.
- API Coordinador GET/PUT perfil académico.
- Extender creación whitelist de Director para persistir perfil.
- UI en `GestionUsuarios` (crear + editar) con campos académicos.
- Exponer años/áreas en catálogo del asistente (solo lectura de datos).
- Tests Pest de persistencia y autorización.

### Out of Scope
- Lógica de recomendación / prompts IA.
- Rediseño visual de Gestión de Usuarios.
- CRUD de taxonomías normalizadas (tablas de tecnologías/líneas).
- Cambio de cupos (`max_capacity`) — sigue en gestión de proyectos/cupos.

## Approach

**Reutilizar entidad 1:1 existente** + endpoint admin + formulario Coordinador. Listas (líneas, tecnologías, metodologías) como JSON de strings (una por línea en UI). `users.areas` se mantiene como áreas de especialización (compatibilidad cupos).

## Success Criteria

- [x] Perfil académico persistido en BD (sin mocks).
- [x] Coordinador puede crear/editar la información desde Gestión de Usuarios.
- [x] Estructura consumible por `DirectorCatalogBuilder`.
- [x] Sin regresión en flujo actual de usuarios.
- [x] OpenSpec + tests + archive.
