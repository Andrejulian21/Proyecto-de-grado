# Exploration: test-qa-backend

**Change**: `test-qa-backend` | **Status**: EXPLORATION COMPLETE
**Date**: 2026-07-09 | **Sprint**: 3 (Tests + QA Backend)

---

## Current State

The project is a Laravel 11 + React/TypeScript platform for managing graduate projects at UNAB. Sprint 1 (auth-access-module) and Sprint 2 (backend-completo) are archived. The backend has 13 models, 6 enums, 13 controllers, ~40 API endpoints, and 373 passing tests (1028 assertions).

### Test Infrastructure
- **Framework**: Pest PHP (strict_tdd: true)
- **Database**: SQLite in-memory for tests (`phpunit.xml`)
- **RefreshDatabase**: Applied globally to Feature tests via `Pest.php`
- **Factories**: Only 2 exist — `UserFactory` (with states: `coordinador()`, `director()`, `external()`) and `SemestreFactory`
- **TestSprite MCP**: Connected, 150 credits (Free tier), no `.testsprite/config.json` yet (bootstrap needed)

---

## Complete Test Inventory

### Feature Tests (38 files)

| Directory | File | What It Tests |
|-----------|------|---------------|
| **Api/** | `AnuncioCrudTest.php` | CRUD anuncios (list/create/update/delete + RBAC 403) |
| | `BitacoraCrudTest.php` | CRUD bitácoras + firma flow |
| | `BitacoraSospechosaTest.php` | Detección firmas sospechosas (≥3 en 5min) |
| | `DirectorHorasTest.php` | Horas mínimas por proyecto |
| | `NotificacionTest.php` | Listar, no-leídas, marcar leída |
| | `RecursoCrudTest.php` | CRUD recursos + filtros categoría/search |
| **Admin/** | `AuditLogControllerTest.php` | Audit log listing + filtros |
| | `EntregaCrudTest.php` | CRUD entregas + state machine transitions |
| | `EntregaFinalesTest.php` | Banco documentos finales |
| | `EntregaHabilitacionTest.php` | Habilitar entrega rechazada |
| | `EvaluacionTest.php` | **MONOLITHIC (577 lines)**: Evaluaciones + EvaluadorProyecto + Reporte consolidado |
| | `ProyectoCrudTest.php` | CRUD proyectos + KPIs + auto-código |
| | `SemestreCrudTest.php` | CRUD semestres + máx 2 activos |
| **Auth/** | `ChangePasswordTest.php` | Change password flow |
| | `CreateExternalEvaluatorTest.php` | Crear evaluador externo |
| | `ExternalLoginTest.php` | Login externo |
| | `GoogleOAuthCallbackTest.php` | Google OAuth callback |
| | `SingleSessionOnLoginTest.php` | Single session enforcement |
| | `WhitelistCrudTest.php` | Whitelist CRUD |
| **Middleware/** | `EnsurePasswordChangedMiddlewareTest.php` | Password change enforcement |
| | `RoleMiddlewareTest.php` | Role-based access |
| | `SessionLifecycleTest.php` | Session timeout/activity |
| **Events/** | `AuditEventTest.php` | Audit event dispatching |
| **database/** | 11 table schema tests | All migrations verified (columns, FKs, indexes, reversibility) |

### Unit Tests (17 files)

| Directory | File | What It Tests |
|-----------|------|---------------|
| **Models/** | `AnuncioTest.php` | Fillable, casts, relationships |
| | `AuditLogTest.php` | Fillable, casts, scopes |
| | `BitacoraTest.php` | Fillable, casts, firma logic, scopes |
| | `EntregaTest.php` | Fillable, casts, state machine, scopes, relationships |
| | `NotificacionTest.php` | Fillable, casts, relationships |
| | `RecursoInformativoTest.php` | Fillable, casts, scopes |
| | `UserExternalAuthTest.php` | External auth columns |
| | `UserTest.php` | Fillable, casts, relationships |
| | `VersionDocumentoTest.php` | Fillable, casts, relationships |
| **Enums/** | `EstadoEntregaTest.php` | Values, canTransitionTo |
| | `EstadoFirmaTest.php` | Values |
| | `UserRoleTest.php` | Values, labels |
| **Policies/** | `UserPolicyTest.php` | Policy authorization |

---

## Coverage Gaps — What's Missing

### 1. Unit Tests Missing (4 models)

| Model | Status | Notes |
|-------|--------|-------|
| `Proyecto` | ❌ NO unit test | Complex model: auto-código, scopes, phase transitions, relationships |
| `Semestre` | ❌ NO unit test | Scopes (activo), relationships (HasMany proyectos) |
| `Evaluacion` | ❌ NO unit test | Nota calculation, relationships |
| `EvaluadorProyecto` | ❌ NO unit test | Pivot model, invitation state |

### 2. Unit Tests Missing (3 enums)

| Enum | Status |
|------|--------|
| `EstadoProyecto` | ❌ NO unit test |
| `FaseProyecto` | ❌ NO unit test |
| `EstadoInvitacionEvaluador` | ❌ NO unit test |

### 3. Feature Test Gaps — Edge Cases Not Covered

| Gap | Spec Reference | Risk |
|-----|---------------|------|
| **No Queue::fake / Mail::fake tests** | RF29 (email dispatch) | HIGH — email notifications never verified |
| **No Storage::fake tests** | RF12 (file uploads ≤50MB, PDF/DOCX) | HIGH — file validation untested |
| **No concurrency test for auto-código** | T-005 (row-level lock) | MEDIUM — atomic counter untested under contention |
| **No test for 3-student justification** | RF06 (justificacion required) | MEDIUM — business rule unverified |
| **No test for auto phase transition** | RF07 (observer avance automático) | MEDIUM — implicit in EntregaCrudTest but not explicit |
| **No test for project status auto-transition** | RF10 (en_riesgo when ≥2 missed) | MEDIUM — business rule unverified |
| **No test for Recurso contador_accesos increment** | RF31 (atomic increment) | LOW |
| **EvaluacionTest is monolithic (577 lines)** | — | LOW — works but hard to maintain |

### 4. Factories Missing

Only `UserFactory` and `SemestreFactory` exist. All other models are created manually with `Model::create([...])` in tests. Missing factories:
- `ProyectoFactory`
- `EntregaFactory`
- `BitacoraFactory`
- `AnuncioFactory`
- `RecursoInformativoFactory`
- `NotificacionFactory`
- `EvaluacionFactory`

---

## Test Patterns & Conventions

1. **Pest PHP syntax**: `it()`, `test()`, `describe()`, `beforeEach()` — all used
2. **Auth**: `$this->actingAs($user)` with factory-created users
3. **API testing**: `getJson()`, `postJson()`, `putJson()`, `deleteJson()` with `/api/` prefix
4. **Assertions**: Mix of `$response->assertOk()` and `expect()` API
5. **RBAC pattern**: Test positive (coordinador can) + negative (estudiante gets 403)
6. **Language**: Test descriptions in Spanish
7. **Database**: `RefreshDatabase` per file (redundant with global, but consistent)
8. **No fakes**: Queue, Mail, Notification, Storage fakes are NOT used anywhere

---

## Affected Areas for Sprint 3

| Area | Files | Action Needed |
|------|-------|---------------|
| Unit Models | `tests/Unit/Models/` | Add 4 missing model tests (Proyecto, Semestre, Evaluacion, EvaluadorProyecto) |
| Unit Enums | `tests/Unit/Enums/` | Add 3 missing enum tests (EstadoProyecto, FaseProyecto, EstadoInvitacionEvaluador) |
| Feature Edge Cases | `tests/Feature/` | Add Queue::fake, Mail::fake, Storage::fake tests |
| Factories | `database/factories/` | Create 7 missing factories |
| EvaluacionTest refactor | `tests/Feature/Admin/EvaluacionTest.php` | Split into 3 focused files (Evaluacion, EvaluadorProyecto, Reporte) |
| TestSprite bootstrap | `.testsprite/config.json` | Run bootstrap for TestSprite integration |
| Code coverage | `phpunit.xml` | Add coverage reporting (PCOV/Xdebug) |

---

## Approaches

### 1. Incremental Gap-Fill (Recommended)
- Add missing factories first (enables cleaner tests)
- Fill unit test gaps (4 models + 3 enums)
- Add edge case feature tests (Queue, Mail, Storage fakes)
- Split monolithic EvaluacionTest
- **Pros**: Low risk, builds on existing patterns, fast wins
- **Cons**: Doesn't address code coverage measurement
- **Effort**: Medium (~50-80 new test cases)

### 2. TestSprite-Driven Generation
- Bootstrap TestSprite
- Use TestSprite to auto-generate tests for untested models/controllers
- Review and refine generated tests
- **Pros**: Fast coverage increase, leverages user's preferred tool
- **Cons**: Free tier (150 credits) may be limiting; generated tests may not match project conventions; needs manual review
- **Effort**: Low-Medium (but credit-constrained)

### 3. Coverage-First Approach
- Add PCOV/Xdebug + coverage reporting
- Identify exact uncovered lines
- Write tests targeting uncovered code
- **Pros**: Data-driven, no guesswork
- **Cons**: Requires Xdebug/PCOV setup; coverage ≠ quality
- **Effort**: Medium

---

## Recommendation

**Approach 1 + TestSprite hybrid**: Start with factories + unit test gaps (manual, following existing patterns), then use TestSprite for edge case discovery and boilerplate generation. Add coverage reporting as a final step.

This gives the best balance of quality (manual tests follow conventions) and speed (TestSprite for boilerplate).

---

## Risks

1. **TestSprite Free tier limit**: 150 credits may run out quickly if generating many tests. Need to prioritize what to generate.
2. **SQLite vs PostgreSQL**: Tests use SQLite in-memory but production uses PostgreSQL. Some PostgreSQL-specific features (e.g., row-level locks, pgvector) may not be testable in SQLite.
3. **No email/queue testing**: The biggest quality gap — notification emails and queued jobs are completely untested.
4. **Monolithic EvaluacionTest**: 577 lines in one file makes it hard to add new tests without conflicts.

---

## Scope Estimate

| Category | Current | Target | Delta |
|----------|---------|--------|-------|
| Test files | 51 | ~65 | +14 |
| Test cases | 373 | ~450-500 | +80-130 |
| Assertions | 1,028 | ~1,400-1,600 | +400-600 |
| Factories | 2 | 9 | +7 |
| Enum unit tests | 3/6 | 6/6 | +3 |
| Model unit tests | 9/13 | 13/13 | +4 |

---

## Ready for Proposal

**Yes** — The exploration is complete. The orchestrator can proceed to `sdd-propose` with clear scope:
1. Create 7 missing factories
2. Add 4 model unit tests + 3 enum unit tests
3. Add edge case feature tests (Queue/Mail/Storage fakes)
4. Split EvaluacionTest monolith
5. Bootstrap TestSprite and use for test generation
6. Add code coverage reporting
