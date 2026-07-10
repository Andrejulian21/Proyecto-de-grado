# Tasks: Backend Testing QA (test-qa-backend)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1,600–2,200 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | Single PR (size:exception) |
| Delivery strategy | single-pr |
| Chain strategy | size-exception |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: size-exception
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Factories + Enum tests (pre-reqs for model tests) | PR 1 slice | Base for all other tests |
| 2 | Model tests + Feature fakes + EvaluacionTest split + Coverage | PR 1 slice | Main body; single-pr exception |

## Phase 1: Bootstrap + Infrastructure

- [x] ~~T-001~~ (TestSprite descartado) Bootstrap TestSprite — run `TestSprite_bootstrap(backend, codebase)` to create `.testsprite/config.json`

- [x] T-002 Create `ProyectoFactory.php` — belongsTo Semestre, defaults per spec, nullable director_id

- [x] T-003 Create `EntregaFactory.php` — belongsTo Proyecto, future due_date, status=pendiente

- [x] T-004 Create `BitacoraFactory.php` — belongsTo Proyecto, entry=paragraph, fecha=now

- [x] T-005 Create `AnuncioFactory.php` — no relationships, title+content+published_at

- [x] T-006 Create `RecursoInformativoFactory.php` — no relationships, random category

- [x] T-007 Create `NotificacionFactory.php` — belongsTo User, random type, is_read=false

- [x] T-008 Create `EvaluacionFactory.php` — belongsTo Entrega+User, grade 0.0–5.0, nullable comment

## Phase 2: Enum Tests

- [x] T-009 Create `EstadoProyectoTest.php` — 4 cases, label(), values(), tryFrom()

- [x] T-010 Create `FaseProyectoTest.php` — 4 cases, label(), next(), values(), tryFrom()

- [x] T-011 Create `EstadoInvitacionEvaluadorTest.php` — 3 cases, label(), values(), tryFrom()

## Phase 3: Model Tests

- [x] T-012 Create `ProyectoTest.php` — fillable, casts, relationships (semestre/director/estudiantes/entregas/bitacoras), scopeEnSemestresActivos, auto-código format, null director

- [x] T-013 Create `SemestreTest.php` — fillable, casts, scopeActivos, hasMany proyectos (0 and multiple)

- [x] T-014 Create `EvaluacionTest.php` (unit) — fillable, casts (percentage/grade decimal), relationships (entrega/evaluador), null comment edge case

- [x] T-015 Create `EvaluadorProyectoTest.php` — fillable, EstadoInvitacionEvaluador enum cast, all 3 invitation states, assigned_at datetime

## Phase 4: Feature Fakes + EvaluacionTest Split

- [x] T-016 Create `AuditQueueFakeTest.php` + `MailFakeTest.php` + `StorageFakeTest.php` — Event::fake with AuditEvent assertions, Mail/Storage as verification gates

- [x] T-017 Split `EvaluacionTest.php` into `EvaluacionCrudTest.php`, `EvaluadorProyectoTest.php`, `ReporteConsolidadoTest.php` — preserve all assertions, add uses(RefreshDatabase)

## Phase 5: Coverage + Verification

- [x] T-018 Update `phpunit.xml` — add `<coverage>` with text+html reports, 60% line threshold, exclude app/Console/, app/Exceptions/, app/Providers/

- [x] T-019 Run full suite — `php artisan test` all green (439 passed), `php artisan test --coverage` requires PCOV/Xdebug
