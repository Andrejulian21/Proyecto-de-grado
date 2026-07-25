# Tasks: Sistema de Entregas — Rediseño a "Una Entrega por Grupo"

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 1.000–1.500 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (Foundation) → PR 2 (Backend API) → PR 3 (Frontend) → PR 4 (Tests + Data Migration) |
| Delivery strategy | ask-on-risk |
| Chain strategy | Chained PRs |
| Max archivos por entrega | 6 |
| MAX_VERSIONS | 4 por archivo requerido |

Decision needed before apply: Yes
Chained PRs recommended: Yes
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Skills | Focused test command | Rollback boundary |
|------|------|-----------|--------|----------------------|-------------------|
| 1 | Migraciones + Modelos | PR 1 | `laravel-patterns`, `database-migrations` | `vendor/bin/pest --filter=EntregaStoreTest` | Revert 3 migraciones |
| 2 | Backend API (Requests, Controllers, Observer) | PR 2 | `laravel-patterns`, `backend-patterns` | `vendor/bin/pest --filter=EntregaStoreTest\|ArchivoSubidaTest` | Revert controllers + routes |
| 3 | Frontend components | PR 3 | `react-patterns`, `typescript-pro`, `shadcn-ui`, `tailwind-patterns` | `npm run build` | Revert frontend files |
| 4 | Tests + Data migration | PR 4 | `laravel-patterns`, `database-migrations` | `vendor/bin/pest --filter=MigracionEntregas\|AutoVinculacion` | Restore from DB backup |

## Phase 1: Foundation (DB + Modelos)

**Skills:** `laravel-patterns`, `database-migrations`

- [ ] 1.1 Migr. `add_archivos_requeridos_to_entregas` — columna JSON nullable after hora_maxima
- [ ] 1.2 Migr. `add_entrega_proyecto_id_to_versiones_documento` — bigIncrements id en pivot + FK + descontinuado
- [ ] 1.3 Crear `app/Models/EntregaProyecto.php` — modelo pivot con fillable + relaciones versiones/entrega/proyecto
- [ ] 1.4 Modificar `Entrega.php` — archivos_requeridos en $fillable/$casts, helper getArchivoRequerido(slug)
- [ ] 1.5 Modificar `VersionDocumento.php` — FKs entrega_proyecto_id + archivo_requerido_id + descontinuado, relación entregaProyecto()

## Phase 2: Backend (Requests + Controladores + Observer)

**Skills:** `laravel-patterns`, `backend-patterns`

- [ ] 2.1 Crear `StoreEntregaRequest` — validación: array min:1 max:6, ids únicos, slug regex /^[a-z0-9_]+$/
- [ ] 2.2 Crear `UpdateEntregaRequest` — regla custom: no cambiar versionamiento si ya hay versiones
- [ ] 2.3 Modificar `EntregaController@store()` — crear 1 entrega + attach pivote a cada proyecto activo del semestre
- [ ] 2.4 Modificar `EntregaController@show()` — incluir archivos_requeridos + proyectos + versiones agrupadas por archivo
- [ ] 2.5 Modificar `EntregaController@update()` — validar archivos_requeridos con regla versionamiento locked
- [ ] 2.6 Eliminar endpoint legacy `POST /api/entregas/{id}/versiones`
- [ ] 2.7 Crear `EntregaEstudianteController` con subirArchivoPorSlug() + estadoCompletitud()
- [ ] 2.8 Agregar rutas: POST /api/entregas/{id}/archivos/{slug} + GET /api/entregas/{id}/estado
- [ ] 2.9 Crear `ProyectoObserver` — auto-attach pivotes a entregas del semestre en created()

## Phase 3: Frontend

**Skills:** `react-patterns`, `typescript-pro`, `shadcn-ui`, `tailwind-patterns`

- [ ] 3.1 Crear `ArchivosRequeridosBuilder.tsx` — lista dinámica, inputs nombre + toggle versionamiento, botones agregar/eliminar, max 6
- [ ] 3.2 Integrar builder en `CoordinadorEntregas.tsx` — formulario crear y modal editar con archivos_requeridos en payload
- [ ] 3.3 Modificar `DetalleEntregaEstudiante.tsx` — tarjetas por archivo requerido, subida individual con reemplazo si sin versionamiento
- [ ] 3.4 Modificar `RevisionEntregaDirector.tsx` — archivos agrupados, selector versión, textarea observaciones por archivo
- [ ] 3.5 Actualizar `types/entregas.ts` (ArchivoRequeridoConfig, ArchivoRequeridoEstado, EntregaEstadoResponse)
- [ ] 3.6 Actualizar `hooks/useEntregas.ts` — subirArchivoPorSlug() y fetchEstadoCompletitud()

## Phase 4: Tests + Migración de Datos

**Skills:** `laravel-patterns`, `database-migrations`

- [ ] 4.1 `StoreEntregaTest` — creación con archivos, validación min:1 max:6, ids duplicados, slug inválido
- [ ] 4.2 `SubidaArchivoTest` — con/sin versionamiento, reemplazo, slug inexistente, MAX_VERSIONS
- [ ] 4.3 `EstadoCompletitudTest` — archivos completos/pendientes según versiones existentes
- [ ] 4.4 `AutoVinculacionTest` — proyecto nuevo en semestre con entregas → pivotes creados
- [ ] 4.5 Migr. datos: fusionar entregas duplicadas por (semester_id, titulo), preservar la primera
- [ ] 4.6 Migr. datos: crear pivotes entrega_proyecto faltantes, migrar versiones, seed archivos_requeridos default
