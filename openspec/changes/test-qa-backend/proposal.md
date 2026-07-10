# Proposal: test-qa-backend

## Intent

Add comprehensive backend test coverage in Sprint 3 to close quality gaps left after Sprint 2 backend completion. The existing suite has 373 passing tests but misses email/queue fakes, file upload fakes, 4 model unit tests, 3 enum tests, and 7 factories. Goal: reach ~450–500 tests with 1,400–1,600 assertions while maintaining existing Pest conventions.

## Scope

### In Scope
- 7 missing factories (`Proyecto`, `Entrega`, `Bitacora`, `Anuncio`, `RecursoInformativo`, `Notificacion`, `Evaluacion`)
- 4 model unit tests (`Proyecto`, `Semestre`, `Evaluacion`, `EvaluadorProyecto`)
- 3 enum unit tests (`EstadoProyecto`, `FaseProyecto`, `EstadoInvitacionEvaluador`)
- Edge-case feature tests with `Queue::fake`, `Mail::fake`, `Storage::fake`
- Split `EvaluacionTest` monolith (577 lines) into 3 focused files
- Bootstrap TestSprite MCP and use for boilerplate generation
- Add code coverage reporting (PCOV/Xdebug)

### Out of Scope
- Frontend/E2E tests (Playwright layer)
- Python/FastAPI tests
- New backend features or API changes
- Performance/load testing

## Capabilities

### New Capabilities
None

### Modified Capabilities
None

## Approach

Hybrid TestSprite + Pest workflow:
1. Bootstrap TestSprite and run `testsprite_generate_backend_test_plan` to auto-discover gaps.
2. Generate base test scaffolding with TestSprite MCP (prioritize Mail/Queue/Storage fakes).
3. Manually refine generated tests: add Spanish descriptions, edge-case assertions, RBAC negative cases, and align with existing `actingAs` + `RefreshDatabase` patterns.
4. Write missing factories first to unblock model tests.
5. Split `EvaluacionTest` into `EvaluacionCrudTest`, `EvaluadorProyectoTest`, `ReporteConsolidadoTest`.
6. Add `phpunit.xml` coverage whitelist and CI step.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `tests/Unit/Models/` | New | 4 missing model tests |
| `tests/Unit/Enums/` | New | 3 missing enum tests |
| `database/factories/` | New | 7 factories |
| `tests/Feature/` | Modified | Add fake tests + split `EvaluacionTest` |
| `.testsprite/` | New | Bootstrap config |
| `phpunit.xml` | Modified | Coverage reporting |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| TestSprite credit exhaustion (150 free) | Med | Prioritize high-impact gaps; fall back to manual writing |
| SQLite vs PostgreSQL gap (locks, pgvector) | Low | Skip untestable PG-specific paths; document in test comments |
| `EvaluacionTest` split breaks existing assertions | Low | Run full suite after each slice; keep original until split verified |

## Rollback Plan

1. Revert commit if CI red after merge.
2. Keep original `EvaluacionTest.php` until split files pass independently.
3. If TestSprite output is unusable, discard `.testsprite/` and switch to manual factory-first approach.

## Dependencies

- TestSprite bootstrap (`.testsprite/config.json`) before any generation
- Factories must exist before model unit tests
- `pcov` or `xdebug` PHP extension for coverage

## Success Criteria

- [ ] 65+ test files, 450+ tests, 1,400+ assertions, all green
- [ ] 9 factories covering all models
- [ ] `Mail::fake`, `Queue::fake`, `Storage::fake` tests present and passing
- [ ] `EvaluacionTest` split into 3 files with no regression
- [ ] Coverage report generated in CI
