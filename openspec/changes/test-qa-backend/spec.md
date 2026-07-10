# Backend Testing QA — Specification

**Change**: `test-qa-backend` | **Type**: Testing-only

## Purpose

Define acceptance criteria for Sprint 3 backend test coverage additions across 6 categories:
factories, model tests, enum tests, fake-integration tests, `EvaluacionTest` split, and coverage reporting.

## Requirements

### 1. Factories (7 new)

| Factory | Default Values | Relationships |
|---------|---------------|---------------|
| `ProyectoFactory` | `title` (sentence), `code` (auto), `status=EnCurso`, `current_phase=Anteproyecto`, `requires_group_justification=false` | `belongsTo Semestre` |
| `EntregaFactory` | `title`, `phase=anteproyecto`, `due_date` (future), `status=pendiente` | `belongsTo Proyecto` |
| `BitacoraFactory` | `entry` (paragraph), `fecha` (now) | `belongsTo Proyecto` |
| `AnuncioFactory` | `title`, `content` (paragraph), `published_at` (now) | None |
| `RecursoInformativoFactory` | `title`, `category` (random), `content`, `url` | None |
| `NotificacionFactory` | `type` (random), `title`, `content`, `is_read=false`, `sent_at` (now) | `belongsTo User` |
| `EvaluacionFactory` | `criterio`, `percentage` (0–100), `grade` (0.0–5.0), `comment` | `belongsTo Entrega`, `belongsTo User (evaluador)` |

**Rules**: Each factory MUST define all fillable fields. Nullable fields (`director_id`, `comment`) MUST use nullable faker. Each factory SHALL pass `Model::factory()->create()` without external dependencies.

#### Scenario: Factory creates valid model
- GIVEN the factory definition
- WHEN `Model::factory()->create()` is called
- THEN a persisted model is returned with all required fields populated
- AND `$model->exists` is true

#### Scenario: Factory with overrides
- GIVEN `ProyectoFactory::new()`
- WHEN `->create(['title' => 'Custom Title'])` is called
- THEN the model reflects the override while other fields use defaults

### 2. Model Unit Tests (4 models)

| Model | Relationships | Scopes/Accessors | Edge Cases |
|-------|--------------|-----------------|------------|
| `Proyecto` | semestre, director, estudiantes, entregas, bitacoras | `scopeEnSemestresActivos`, auto-código on `creating` | Null director, multiple estudiantes, código format `PG-20261-001` |
| `Semestre` | proyectos (HasMany) | `scopeActivos` | Zero proyectos, múltiples activos |
| `Evaluacion` | entrega, evaluador | `percentage` decimal cast, `grade` decimal cast | Null comment, evaluated_at datetime |
| `EvaluadorProyecto` | proyecto, evaluador | `EstadoInvitacionEvaluador` enum cast, `assigned_at` datetime | All 3 invitation states |

**Rules**: Each model test SHALL verify `fillable` array, `casts` map, all relationships (return type via `instanceof`), and boot hooks. Must follow existing `tests/Unit/Models/` conventions: `test()`, Spanish descriptions, `RefreshDatabase`.

#### Scenario: Model fillable and casts are correct
- GIVEN the model class
- WHEN an instance is created with fillable attributes
- THEN all fillable fields are set correctly
- AND enum/boolean/date casts are applied

#### Scenario: Relationship returns correct type
- GIVEN a persisted parent model
- WHEN a relationship method is called
- THEN it returns the correct `BelongsTo`, `HasMany`, or `BelongsToMany` instance
- AND related records are accessible

### 3. Enum Unit Tests (3 enums)

| Enum | Cases | Tested Methods |
|------|-------|---------------|
| `EstadoProyecto` | EnCurso, EnRiesgo, Incumplimiento, Completado | `values()`, `label()`, `tryFrom()` |
| `FaseProyecto` | Anteproyecto, PresentacionAnteproyecto, Desarrollo, PresentacionFinal | `values()`, `label()`, `next()`, `tryFrom()` |
| `EstadoInvitacionEvaluador` | Pendiente, Aceptada, Rechazada | `values()`, `label()`, `tryFrom()` |

**Rules**: Must match existing `tests/Unit/Enums/` conventions from `EstadoEntregaTest` and `UserRoleTest`. Each SHALL test: case count, case names, backed values, `values()` helper, `tryFrom()` with valid/invalid strings, and `label()` for all cases.

#### Scenario: Enum has correct case count
- GIVEN the enum class
- WHEN `Enum::cases()` is called
- THEN the count matches the expected number of cases

#### Scenario: tryFrom returns null for invalid values
- GIVEN an invalid string
- WHEN `Enum::tryFrom('invalid')` is called
- THEN it returns null

### 4. Feature Tests with Fakes

| Fake | Trigger | Verification |
|------|---------|-------------|
| `Queue::fake` | `AuditEvent::dispatch()` on auth/CRUD actions | Assert event pushed, correct payload (user_id, action, model_type) |
| `Mail::fake` | Verification gate only — no current mail dispatch | Assert `Mail::assertNothingSent()` to document current state |
| `Storage::fake` | Verification gate only — no current file upload | Assert `Storage::disk()->assertMissing()` for known paths |

**Rules**: Each fake test SHALL use `actingAs()` + API route call, then assert fake queue/mail/storage state. Queue test MUST verify `AuditEvent` is dispatched on login, logout, and CRUD actions. Mail and Storage are verification gates (no-ops documented for future features). Must follow existing Feature test conventions.

#### Scenario: Queue dispatches AuditEvent on critical action
- GIVEN `Queue::fake()` and an authenticated user
- WHEN a CRUD endpoint is hit via `postJson()`
- THEN `Queue::assertPushed(AuditEvent::class)` passes
- AND the event payload contains `user_id` and `action`

#### Scenario: Mail fake verifies no leaks
- GIVEN `Mail::fake()` 
- WHEN any API endpoint is exercised
- THEN `Mail::assertNothingSent()` passes (documents current state)

### 5. EvaluacionTest Split

The monolithic `EvaluacionTest.php` (577 lines) SHALL be split into 3 files:

| File | Responsibility | Preserved Coverage |
|------|---------------|-------------------|
| `EvaluacionCrudTest.php` | Evaluacion CRUD operations (create, list, update, delete, RBAC 403) | All `T-015` Evaluacion scenarios |
| `EvaluadorProyectoTest.php` | EvaluadorProyecto assignment, invitation status transitions, RBAC | All `T-015` EvaluadorProyecto scenarios |
| `ReporteConsolidadoTest.php` | Consolidated report generation and data aggregation | All `T-015` report scenarios |

**Rules**: Split MUST NOT lose any existing test or assertion. Original file SHALL remain until all 3 new files pass independently. Each file SHALL have its own `uses(RefreshDatabase::class)` and `beforeEach`. Test descriptions SHALL remain in Spanish.

#### Scenario: Split preserves all assertions
- GIVEN the original `EvaluacionTest.php` passes with N assertions
- WHEN the split is complete and 3 new files are run
- THEN the combined assertion count equals or exceeds the original
- AND all tests pass

### 6. Coverage Reporting

The `phpunit.xml` SHALL be extended to include:

| Config | Value |
|--------|-------|
| `source.include.directory` | `app/` (already present) |
| `coverage.report` | `text`, `html` (`.phpunit.cache/coverage`) |
| `coverage.threshold` | 60% lines (minimum) |
| `coverage.whitelist.exclude` | `app/Console/`, `app/Exceptions/`, `app/Providers/` |

**Rules**: Coverage report MUST be added to `phpunit.xml` without breaking existing test suites. CI step SHALL run `php artisan test --coverage` and fail below threshold.

#### Scenario: Coverage report generates without errors
- GIVEN `phpunit.xml` with coverage configuration and `pcov` or `xdebug` enabled
- WHEN `php artisan test --coverage` is executed
- THEN a coverage report is generated with line percentages per file
- AND the CI pipeline passes if the minimum threshold is met
